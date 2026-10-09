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

	protected function setUp() : void{
		$this->tempDir = Path::join(sys_get_temp_dir(), "amber_backup_test_" . uniqid());
		@mkdir($this->tempDir, 0777, true);
	}

	protected function tearDown() : void{
		if(is_dir($this->tempDir)){
			Filesystem::recursiveUnlink($this->tempDir);
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
		self::assertTrue(is_file(Path::join($targetDb, "LOG")));
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
		try{
			// onRun must catch any cleanup error and safely record result without throwing an uncaught exception
			$task->onRun();
			self::assertTrue($task->hasResult());
		}finally{
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
}

