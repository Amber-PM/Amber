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

require_once __DIR__ . '/../../../../src/world/backup/WorldBackupManager.php';
require_once __DIR__ . '/../../../../src/world/backup/WorldBackupTask.php';

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
		$logger = $this->createMock(\pocketmine\thread\log\AttachableThreadSafeLogger::class);
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
}
