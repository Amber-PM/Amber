<?php

declare(strict_types=1);

namespace pocketmine\world\backup;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function mkdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
if(!class_exists(\pmmp\thread\ThreadSafe::class)){
	class ThreadSafeMock{}
	class_alias(ThreadSafeMock::class, \pmmp\thread\ThreadSafe::class);
}
if(!class_exists(\pmmp\thread\Runnable::class)){
	abstract class RunnableMock extends \pmmp\thread\ThreadSafe{
		abstract public function run() : void;
	}
	class_alias(RunnableMock::class, \pmmp\thread\Runnable::class);
}
if(!class_exists(\pmmp\thread\ThreadSafeArray::class)){
	class ThreadSafeArrayMock extends \ArrayObject{}
	class_alias(ThreadSafeArrayMock::class, \pmmp\thread\ThreadSafeArray::class);
}
if(!function_exists('igbinary_serialize')){
	function igbinary_serialize(mixed $val) : string{ return serialize($val); }
}
if(!function_exists('igbinary_unserialize')){
	function igbinary_unserialize(string $val) : mixed{ return unserialize($val); }
}

final class DummyBackupLogger extends \pocketmine\thread\log\AttachableThreadSafeLogger{
	public function emergency($message) : void{}
	public function alert($message) : void{}
	public function critical($message) : void{}
	public function error($message) : void{}
	public function warning($message) : void{}
	public function notice($message) : void{}
	public function info($message) : void{}
	public function debug($message) : void{}
	public function log($level, $message) : void{}
	public function logException(\Throwable $e, $trace = null) : void{}
}

final class WorldBackupTest extends TestCase{
	private string $tempDir;

	private function provider(string $path) : \pocketmine\world\format\io\leveldb\LevelDB{
		$provider = (new \ReflectionClass(\pocketmine\world\format\io\leveldb\LevelDB::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty($provider, "path"))->setValue($provider, $path);
		(new \ReflectionProperty($provider, "db"))->setValue($provider, new \LevelDB(Path::join($path, "db"), ["create_if_missing" => true, "compression" => LEVELDB_ZLIB_RAW_COMPRESSION]));
		return $provider;
	}

	public function testSnapshotPreservesWritesStillInWalAndReopensProvider() : void{
		$source = Path::join($this->tempDir, "wal_world");
		mkdir($source);
		file_put_contents(Path::join($source, "level.dat"), "world metadata");
		$provider = $this->provider($source);
		$dbProperty = new \ReflectionProperty($provider, "db");
		$dbProperty->getValue($provider)->put("saved_key", "saved_value");
		self::assertSame([], glob(Path::join($source, "db", "*.ldb")));
		$staging = Path::join($this->tempDir, "wal_staging");
		$manager = (new \ReflectionClass(WorldBackupManager::class))->newInstanceWithoutConstructor();
		try{
			$provider->withClosedDatabase(function() use ($manager, $source, $staging) : void{
				(new \ReflectionMethod(WorldBackupManager::class, "snapshot"))->invoke($manager, $source, $staging);
			});
			self::assertSame("saved_value", $dbProperty->getValue($provider)->get("saved_key"));
			$dbProperty->getValue($provider)->put("later_key", "later_value");
			$dbProperty->getValue($provider)->compactRange("", "\xff\xff\xff\xff");
			$target = Path::join($this->tempDir, "wal.zip");
			$task = new WorldBackupTask($staging, $target, true, static function(?string $error) : void{});
			$task->onRun();
			self::assertNull($task->getResult());
			$zip = new \ZipArchive();
			self::assertTrue($zip->open($target));
			$restore = Path::join($this->tempDir, "wal_restore");
			self::assertTrue($zip->extractTo($restore));
			$zip->close();
			$restored = new \LevelDB(Path::join($restore, "db"), ["create_if_missing" => false, "compression" => LEVELDB_ZLIB_RAW_COMPRESSION], ["verify_check_sum" => true]);
			self::assertSame("saved_value", $restored->get("saved_key"));
			self::assertFalse($restored->get("later_key"));
			unset($restored);
		}finally{
			$provider->close();
		}
	}

	public function testProviderReopensAfterCaptureThrows() : void{
		$source = Path::join($this->tempDir, "failed_capture");
		mkdir($source);
		$provider = $this->provider($source);
		$dbProperty = new \ReflectionProperty($provider, "db");
		$dbProperty->getValue($provider)->put("saved", "value");
		try{
			try{
				$provider->withClosedDatabase(static function() : void{ throw new \RuntimeException("capture failed"); });
				self::fail("Capture exception should propagate");
			}catch(\RuntimeException $e){
				self::assertSame("capture failed", $e->getMessage());
			}
			self::assertSame("value", $dbProperty->getValue($provider)->get("saved"));
			$dbProperty->getValue($provider)->put("next", "write");
			self::assertSame("write", $dbProperty->getValue($provider)->get("next"));
		}finally{
			$provider->close();
		}
	}

	public function testActiveReaderPreventsCaptureWithoutClosingItsDatabase() : void{
		$source = Path::join($this->tempDir, "active_reader");
		mkdir($source);
		$provider = $this->provider($source);
		$database = (new \ReflectionProperty($provider, "db"))->getValue($provider);
		$database->put("saved", "value");
		$iterator = $database->getIterator();
		unset($database);
		try{
			try{
				$provider->withClosedDatabase(static function() : void{ self::fail("Capture must not run with outstanding readers"); });
				self::fail("Expected active reader rejection");
			}catch(\RuntimeException){
				$iterator->rewind();
				self::assertTrue($iterator->valid());
				self::assertSame("value", $iterator->current());
			}
		}finally{
			unset($iterator);
			$provider->close();
		}
	}

	public function testSnapshotRejectsFailedWalCopy() : void{
		$source = Path::join($this->tempDir, "failed_wal_source");
		$target = Path::join($this->tempDir, "failed_wal_target");
		mkdir(Path::join($source, "db"), 0777, true);
		file_put_contents(Path::join($source, "db", "000001.log"), "saved writes");
		file_put_contents(Path::join($source, "db", "CURRENT"), "MANIFEST-000002\n");
		mkdir(Path::join($target, "db", "000001.log"), 0777, true);
		$manager = (new \ReflectionClass(WorldBackupManager::class))->newInstanceWithoutConstructor();
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Cannot copy metadata file 000001.log");
		(new \ReflectionMethod(WorldBackupManager::class, "snapshot"))->invoke($manager, $source, $target);
	}

	protected function setUp() : void{
		$this->tempDir = Path::join(sys_get_temp_dir(), "amber_backup_test_" . uniqid());
		@mkdir($this->tempDir, 0777, true);
	}

	protected function tearDown() : void{
		if(is_dir($this->tempDir)){
			try{
				Filesystem::recursiveUnlink($this->tempDir);
			}catch(\Throwable){
				// best-effort cleanup on platforms with lingering file locks
			}
		}
	}

	public function testZipCreatesValidArchiveAndSkipsLock() : void{
		$staging = Path::join($this->tempDir, "staging");
		@mkdir(Path::join($staging, "db"), 0777, true);
		file_put_contents(Path::join($staging, "level.dat"), "test data");
		file_put_contents(Path::join($staging, "LOCK"), "locked");
		file_put_contents(Path::join($staging, "db", "000001.ldb"), "table data");

		$target = Path::join($this->tempDir, "backups", "world.zip");
		$task = new WorldBackupTask($staging, $target, false, function(?string $error) : void{});

		$method = new \ReflectionMethod(WorldBackupTask::class, "zip");
		$method->invoke($task);

		self::assertTrue(is_file($target));

		$zip = new \ZipArchive();
		self::assertTrue($zip->open($target));
		self::assertNotFalse($zip->locateName("level.dat"));
		self::assertNotFalse($zip->locateName("db/000001.ldb"));
		self::assertFalse($zip->locateName("LOCK")); //LOCK must be skipped
		self::assertSame("test data", $zip->getFromName("level.dat"));
		$zip->close();
	}

	public function testZipOverwritesExistingTargetSafely() : void{
		$staging = Path::join($this->tempDir, "staging");
		@mkdir($staging, 0777, true);
		file_put_contents(Path::join($staging, "level.dat"), "newer version");

		$target = Path::join($this->tempDir, "backups", "world.zip");
		@mkdir(Path::join($this->tempDir, "backups"), 0777, true);
		file_put_contents($target, "pre-existing content");

		$task = new WorldBackupTask($staging, $target, false, function(?string $error) : void{});
		$method = new \ReflectionMethod(WorldBackupTask::class, "zip");
		$method->invoke($task);

		self::assertTrue(is_file($target));
		$zip = new \ZipArchive();
		self::assertTrue($zip->open($target));
		self::assertSame("newer version", $zip->getFromName("level.dat"));
		$zip->close();
	}

	public function testRotateDeletesOldestArchives() : void{
		$backupPath = Path::join($this->tempDir, "backups");
		$worldDir = Path::join($backupPath, "world");
		@mkdir($worldDir, 0777, true);

		// Create 5 dummy backups with sortable timestamps
		$files = [
			"world-2026-10-01_10-00-00.zip",
			"world-2026-10-02_10-00-00.zip",
			"world-2026-10-03_10-00-00.zip",
			"world-2026-10-04_10-00-00.zip",
			"world-2026-10-05_10-00-00.zip",
		];
		foreach($files as $file){
			touch(Path::join($worldDir, $file));
		}

		// Mock server for logging inside rotate
		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);

		$manager = new WorldBackupManager(
			$server,
			$backupPath,
			false,
			3600,
			3, // keep 3
			[]
		);

		$rotateMethod = new \ReflectionMethod(WorldBackupManager::class, "rotate");
		$rotateMethod->invoke($manager, "world");

		self::assertFalse(is_file(Path::join($worldDir, "world-2026-10-01_10-00-00.zip")));
		self::assertFalse(is_file(Path::join($worldDir, "world-2026-10-02_10-00-00.zip")));
		self::assertTrue(is_file(Path::join($worldDir, "world-2026-10-03_10-00-00.zip")));
		self::assertTrue(is_file(Path::join($worldDir, "world-2026-10-04_10-00-00.zip")));
		self::assertTrue(is_file(Path::join($worldDir, "world-2026-10-05_10-00-00.zip")));
	}

	public function testSnapshotLevelDBOrderingAndFileExclusion() : void{
		$source = Path::join($this->tempDir, "source_world");
		$sourceDb = Path::join($source, "db");
		@mkdir($sourceDb, 0777, true);

		file_put_contents(Path::join($source, "level.dat"), "level nbt data");
		file_put_contents(Path::join($source, "world_icon.jpeg"), "image data");
		file_put_contents(Path::join($source, "LOCK"), "root locked");

		file_put_contents(Path::join($sourceDb, "000001.ldb"), "table 1");
		file_put_contents(Path::join($sourceDb, "000002.sst"), "table 2");
		file_put_contents(Path::join($sourceDb, "MANIFEST-000001"), "manifest data");
		file_put_contents(Path::join($sourceDb, "LOG"), "log data");
		file_put_contents(Path::join($sourceDb, "LOCK"), "db locked");
		file_put_contents(Path::join($sourceDb, "CURRENT"), "MANIFEST-000001\n");

		$target = Path::join($this->tempDir, "snapshot_target");

		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);

		$manager = new WorldBackupManager(
			$server,
			Path::join($this->tempDir, "backups"),
			false,
			3600,
			3,
			[]
		);

		$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
		$snapshotMethod->invoke($manager, $source, $target);

		self::assertTrue(is_file(Path::join($target, "level.dat")));
		self::assertTrue(is_file(Path::join($target, "world_icon.jpeg")));
		self::assertFalse(is_file(Path::join($target, "LOCK")));

		$targetDb = Path::join($target, "db");
		self::assertTrue(is_file(Path::join($targetDb, "000001.ldb")));
		self::assertTrue(is_file(Path::join($targetDb, "000002.sst")));
		self::assertTrue(is_file(Path::join($targetDb, "MANIFEST-000001")));
		self::assertFalse(is_file(Path::join($targetDb, "LOG")));
		self::assertFalse(is_file(Path::join($targetDb, "LOCK")));
		self::assertTrue(is_file(Path::join($targetDb, "CURRENT")));
		self::assertSame("MANIFEST-000001\n", file_get_contents(Path::join($targetDb, "CURRENT")));
	}

	public function testSnapshotMissingCurrentThrows() : void{
		$source = Path::join($this->tempDir, "source_no_current");
		$sourceDb = Path::join($source, "db");
		@mkdir($sourceDb, 0777, true);
		file_put_contents(Path::join($sourceDb, "000001.ldb"), "table");

		$target = Path::join($this->tempDir, "snapshot_no_current_target");

		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);

		$manager = new WorldBackupManager(
			$server,
			Path::join($this->tempDir, "backups"),
			false,
			3600,
			3,
			[]
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Missing CURRENT file");

		$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
		$snapshotMethod->invoke($manager, $source, $target);
	}

	public function testSnapshotMissingDbDirThrows() : void{
		$source = Path::join($this->tempDir, "source_no_db");
		@mkdir($source, 0777, true);
		$target = Path::join($this->tempDir, "snapshot_no_db_target");

		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);

		$manager = new WorldBackupManager(
			$server,
			Path::join($this->tempDir, "backups"),
			false,
			3600,
			3,
			[]
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("LevelDB database folder not found");

		$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
		$snapshotMethod->invoke($manager, $source, $target);
	}

	public function testNonLevelDBWorldIsRejected() : void{
		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);

		$manager = new WorldBackupManager(
			$server,
			Path::join($this->tempDir, "backups"),
			false,
			3600,
			3,
			[]
		);

		$mockProvider = $this->createMock(\pocketmine\world\format\io\WritableWorldProvider::class);
		$mockWorld = (new \ReflectionClass(\pocketmine\world\World::class))->newInstanceWithoutConstructor();

		$folderNameProp = new \ReflectionProperty(\pocketmine\world\World::class, "folderName");
		$folderNameProp->setValue($mockWorld, "non_leveldb_world");

		$providerProp = new \ReflectionProperty(\pocketmine\world\World::class, "provider");
		$providerProp->setValue($mockWorld, $mockProvider);

		$errorResult = null;
		$started = $manager->backup($mockWorld, function(?string $path, ?string $error) use (&$errorResult) : void{
			$errorResult = $error;
		});

		self::assertFalse($started);
		self::assertSame("Unsupported world provider: only LevelDB worlds are supported", $errorResult);
	}

	public function testTaskOnRunGuardsCleanupException() : void{
		$staging = Path::join($this->tempDir, "staging_locked");
		@mkdir($staging, 0777, true);
		$lockedFile = Path::join($staging, "locked.txt");
		file_put_contents($lockedFile, "locked content");

		$target = Path::join($this->tempDir, "backups", "world.zip");
		$task = new WorldBackupTask($staging, $target, false, function(?string $error) : void{});

		$fp = @fopen($lockedFile, "r");
		set_error_handler(static function(int $severity, string $message, string $file, int $line) : bool{
			if((error_reporting() & $severity) === 0){
				return false;
			}
			throw new \ErrorException($message, 0, $severity, $file, $line);
		});
		try{
			// onRun must catch any cleanup error and safely record result without throwing an uncaught exception
			$task->onRun();
			$result = $task->getResult();
			self::assertTrue($result === null || str_contains((string) $result, "Cleanup failed"));
		}finally{
			restore_error_handler();
			if(is_resource($fp)){
				fclose($fp);
			}
		}
	}

	public function testTaskOnRunHandlesZipFailureGracefully() : void{
		$staging = Path::join($this->tempDir, "non_existent_staging");
		$target = Path::join($this->tempDir, "backups", "world.zip");
		$task = new WorldBackupTask($staging, $target, false, function(?string $error) : void{});

		$task->onRun();
		self::assertTrue($task->hasResult());
		self::assertIsString($task->getResult());
	}

	public function testSnapshotFailsWhenHardlinkFails() : void{
		$source = Path::join($this->tempDir, "source_hardlink_fail");
		$sourceDb = Path::join($source, "db");
		@mkdir($sourceDb, 0777, true);
		file_put_contents(Path::join($source, "level.dat"), "world data");
		file_put_contents(Path::join($sourceDb, "000001.ldb"), "table data");
		file_put_contents(Path::join($sourceDb, "CURRENT"), "MANIFEST-000001");

		$target = Path::join($this->tempDir, "target_hardlink_fail");
		$targetDb = Path::join($target, "db");
		@mkdir($targetDb, 0777, true);
		// Pre-create the destination table so link() fails (EEXIST)
		file_put_contents(Path::join($targetDb, "000001.ldb"), "pre-existing");

		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);
		$manager = new WorldBackupManager($server, Path::join($this->tempDir, "backups"), false, 3600, 3, []);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Cannot hardlink table file 000001.ldb");

		$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
		$snapshotMethod->invoke($manager, $source, $target);
	}

	public function testSnapshotFailsWhenAggregateMetadataBudgetExceeded() : void{
		$source = Path::join($this->tempDir, "source_budget_fail");
		$sourceDb = Path::join($source, "db");
		@mkdir($sourceDb, 0777, true);
		file_put_contents(Path::join($source, "level.dat"), "world data");
		file_put_contents(Path::join($sourceDb, "000001.ldb"), "table data");
		file_put_contents(Path::join($sourceDb, "CURRENT"), "MANIFEST-000001");

		// Exceed file count budget (> 100 metadata files)
		for($i = 0; $i < 105; ++$i){
			file_put_contents(Path::join($sourceDb, "meta_" . $i . ".dat"), "meta data");
		}

		$target = Path::join($this->tempDir, "target_budget_fail");
		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);
		$manager = new WorldBackupManager($server, Path::join($this->tempDir, "backups"), false, 3600, 3, []);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Snapshot exceeded maximum metadata file count limit");

		$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
		$snapshotMethod->invoke($manager, $source, $target);
	}

	public function testCorruptedLevelDBTableFailsVerificationAndAbortsArchive() : void{
		if(!class_exists(\LevelDB::class)){
			self::markTestSkipped("ext-leveldb is required for this test");
		}

		$source = Path::join($this->tempDir, "corrupt_source");
		$sourceDb = Path::join($source, "db");
		@mkdir($sourceDb, 0777, true);
		file_put_contents(Path::join($source, "level.dat"), "world data");

		// Populate data with small write buffer and compact to force LevelDB to flush to .ldb/.sst table files
		$db = new \LevelDB($sourceDb, [
			"create_if_missing" => true,
			"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
			"block_size" => 4096,
			"write_buffer_size" => 4096,
		]);
		$payload = str_repeat("abcdefghijklmnop", 64); // 1024 bytes per record
		for($batchIndex = 0; $batchIndex < 10; ++$batchIndex){
			$batch = new \LevelDBWriteBatch();
			for($i = 0; $i < 200; ++$i){
				$batch->put("key_" . $batchIndex . "_" . $i, $payload);
			}
			$db->write($batch);
		}
		$db->compactRange("", "\xff\xff\xff\xff");
		unset($db); // close to flush tables and write CURRENT

		// Find a .ldb or .sst file and corrupt one byte in the data blocks
		$tableFiles = array_merge(
			glob(Path::join($sourceDb, "*.ldb")) ?: [],
			glob(Path::join($sourceDb, "*.sst")) ?: []
		);
		self::assertNotEmpty($tableFiles, "Expected at least one LevelDB table file in $sourceDb, found: " . implode(", ", scandir($sourceDb) ?: []));
		$tableFile = $tableFiles[0];

		$contents = file_get_contents($tableFile);
		self::assertIsString($contents);
		self::assertGreaterThan(50, strlen($contents));
		// Flip bits in the middle of a data block
		$corruptPos = (int) (strlen($contents) / 2);
		$contents[$corruptPos] = chr(ord($contents[$corruptPos]) ^ 0xff);
		file_put_contents($tableFile, $contents);

		$target = Path::join($this->tempDir, "backups", "corrupted.zip");
		$task = new WorldBackupTask($source, $target, true, function(?string $error) : void{});

		$task->onRun();

		// Check that onRun recorded a corruption error and did NOT publish the archive
		$result = $task->getResult();
		self::assertNotNull($result);
		self::assertTrue(
			stripos($result, "corrupt") !== false ||
			stripos($result, "checksum") !== false,
			"Expected corruption/checksum error message, got: " . $result
		);
		self::assertFileDoesNotExist($target);
		self::assertFileDoesNotExist($target . ".tmp");
	}

	public function testClosedProviderSnapshotSurvivesSubsequentSourceCompaction() : void{
		if(!class_exists(\LevelDB::class)){
			self::markTestSkipped("ext-leveldb is required for this test");
		}

		$source = Path::join($this->tempDir, "compaction_world");
		$sourceDbPath = Path::join($source, "db");
		@mkdir($sourceDbPath, 0777, true);
		file_put_contents(Path::join($source, "level.dat"), "world nbt data");

		// 1. Populate initial state of 1,000 keys and flush to SSTable files
		$db = new \LevelDB($sourceDbPath, [
			"create_if_missing" => true,
			"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
			"block_size" => 4096,
		]);
		$initialState = [];
		try{
			$batch = new \LevelDBWriteBatch();
			for($i = 0; $i < 1000; ++$i){
				$key = "player_pos_" . $i;
				$val = "coord_data_chunk_" . $i;
				$batch->put($key, $val);
				$initialState[$key] = $val;
			}
			$db->write($batch);
			$db->compactRange("", "\xff\xff\xff\xff"); // flush initial records to SSTable files
			unset($db);
			$provider = $this->provider($source);

			// 2. Take consistent snapshot via WorldBackupManager::snapshot()
			$staging = Path::join($this->tempDir, "staging_snapshot");
			$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
			$logger = new DummyBackupLogger();
			(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);
			$manager = new WorldBackupManager($server, Path::join($this->tempDir, "backups"), false, 3600, 3, []);

			$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
			$provider->withClosedDatabase(static function() use ($snapshotMethod, $manager, $source, $staging) : void{
				$snapshotMethod->invoke($manager, $source, $staging);
			});
			$db = (new \ReflectionProperty($provider, "db"))->getValue($provider);

			// 3. Simulate active concurrent operations and compaction on the source database:
			// Delete old keys, write new keys, and compact the source database while the snapshot exists
			$mutateBatch = new \LevelDBWriteBatch();
			for($i = 0; $i < 1000; ++$i){
				$mutateBatch->delete("player_pos_" . $i);
				$mutateBatch->put("new_state_" . $i, "new_value_" . $i);
			}
			$db->write($mutateBatch);
			$db->compactRange("", "\xff\xff\xff\xff"); // force compaction of source database
		}finally{
			unset($db); // ensure source database is always closed
			if(isset($provider)){
				$provider->close();
			}
		}

		// 4. Verify snapshot with WorldBackupTask on the staging snapshot
		$targetZip = Path::join($this->tempDir, "backups", "compaction_verified.zip");
		$task = new WorldBackupTask($staging, $targetZip, true, function(?string $error) : void{});
		$task->onRun();
		self::assertNull($task->getResult(), "Snapshot verification failed: " . $task->getResult());
		self::assertFileExists($targetZip);

		// 5. Restore/extract the archive and verify that the recovered LevelDB database matches
		// the complete initial saved state and is completely unaffected by the source mutations/compaction
		$restoreDir = Path::join($this->tempDir, "restored_world");
		@mkdir($restoreDir, 0777, true);
		$zip = new \ZipArchive();
		self::assertTrue($zip->open($targetZip));
		$zip->extractTo($restoreDir);
		$zip->close();

		$restoredDb = new \LevelDB(Path::join($restoreDir, "db"), [
			"create_if_missing" => false,
			"paranoid_checks" => true,
		], ["verify_check_sum" => true]);

		try{
			// Verify every initial key was recovered intact
			foreach($initialState as $k => $expectedV){
				self::assertSame($expectedV, $restoredDb->get($k), "Missing or mismatched key $k in restored database");
			}
			// Verify none of the post-snapshot mutated keys leaked into the restored snapshot
			self::assertFalse($restoredDb->get("new_state_0"));
		}finally{
			unset($restoredDb);
		}
	}

	public function testMissingCapturedTableAbortsArchivePublication() : void{
		if(!class_exists(\LevelDB::class)){
			self::markTestSkipped("ext-leveldb is required for this test");
		}

		$source = Path::join($this->tempDir, "compaction_race_world");
		$sourceDbPath = Path::join($source, "db");
		@mkdir($sourceDbPath, 0777, true);
		file_put_contents(Path::join($source, "level.dat"), "world nbt data");

		$db = new \LevelDB($sourceDbPath, [
			"create_if_missing" => true,
			"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
			"block_size" => 4096,
		]);
		try{
			$batch = new \LevelDBWriteBatch();
			for($i = 0; $i < 500; ++$i){
				$batch->put("key_" . $i, "value_" . $i);
			}
			$db->write($batch);
			$db->compactRange("", "\xff\xff\xff\xff");
		}finally{
			unset($db);
		}

		$staging = Path::join($this->tempDir, "staging_compaction_race");
		$server = (new \ReflectionClass(\pocketmine\Server::class))->newInstanceWithoutConstructor();
		$logger = new DummyBackupLogger();
		(new \ReflectionProperty(\pocketmine\Server::class, "logger"))->setValue($server, $logger);
		$manager = new WorldBackupManager($server, Path::join($this->tempDir, "backups"), false, 3600, 3, []);

		$snapshotMethod = new \ReflectionMethod(WorldBackupManager::class, "snapshot");
		$snapshotMethod->invoke($manager, $source, $staging);

		// Simulate a concurrent compaction race where a table referenced by MANIFEST was removed/compacted away
		$tableFiles = array_merge(
			glob(Path::join($staging, "db", "*.ldb")) ?: [],
			glob(Path::join($staging, "db", "*.sst")) ?: []
		);
		self::assertNotEmpty($tableFiles, "Expected table files in staging snapshot");
		@unlink($tableFiles[0]); // simulate compaction deletion of table referenced in manifest

		$targetZip = Path::join($this->tempDir, "backups", "race_aborted.zip");
		$task = new WorldBackupTask($staging, $targetZip, true, function(?string $error) : void{});
		$task->onRun();

		$result = $task->getResult();
		self::assertNotNull($result, "Expected verification task to detect missing table file");
		self::assertTrue(
			stripos($result, "missing") !== false ||
			stripos($result, "corrupt") !== false ||
			stripos($result, "fail") !== false,
			"Expected missing table file / corruption error, got: " . $result
		);
		self::assertFileDoesNotExist($targetZip);
	}
}
