<?php

declare(strict_types=1);

namespace pocketmine\world\format\io\leveldb;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function defined;
use function extension_loaded;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class LevelDBBlockCacheTest extends TestCase{
	private string $tempDir;

	protected function setUp() : void{
		if(!extension_loaded('leveldb') || !defined('LEVELDB_ZLIB_RAW_COMPRESSION')){
			self::markTestSkipped("The leveldb extension with zlib raw compression is required");
		}
		$this->tempDir = Path::join(sys_get_temp_dir(), "amber_leveldb_cache_" . uniqid());
		mkdir($this->tempDir, 0777, true);
	}

	protected function tearDown() : void{
		if(isset($this->tempDir)){
			Filesystem::recursiveUnlink($this->tempDir);
		}
	}

	public function testDefaultOptionsKeepTheLevelDbCache() : void{
		$options = LevelDB::getDatabaseOptions();

		self::assertSame(["compression", "block_size"], array_keys($options));
		self::assertSame(LEVELDB_ZLIB_RAW_COMPRESSION, $options["compression"]);
		self::assertSame(64 * 1024, $options["block_size"]);
		self::assertSame($options, LevelDB::getDatabaseOptions(0));
	}

	public function testBlockCacheSizeIsPassedUnderTheExtensionOptionName() : void{
		//the extension ignores unknown option names, so the exact key matters
		$options = LevelDB::getDatabaseOptions(64 * 1024 * 1024);

		self::assertArrayHasKey("block_cache_size", $options);
		self::assertSame(64 * 1024 * 1024, $options["block_cache_size"]);
		self::assertSame(LEVELDB_ZLIB_RAW_COMPRESSION, $options["compression"]);
		self::assertSame(64 * 1024, $options["block_size"]);
	}

	public function testNegativeBlockCacheSizeIsRejected() : void{
		$this->expectException(\InvalidArgumentException::class);
		LevelDB::getDatabaseOptions(-1);
	}

	public function testConstructorRejectsNegativeBlockCacheSize() : void{
		$this->expectException(\InvalidArgumentException::class);
		new LevelDB($this->tempDir, $this->createMock(\Logger::class), -1);
	}

	public function testDatabaseOpensAndPersistsWithAnExplicitCache() : void{
		$path = Path::join($this->tempDir, "db");
		$options = LevelDB::getDatabaseOptions(16 * 1024 * 1024) + ["create_if_missing" => true];

		$db = new \LevelDB($path, $options);
		$db->put("key", "value");
		unset($db);

		$db = new \LevelDB($path, $options);
		self::assertSame("value", $db->get("key"));
	}

	public function testCacheSizeSurvivesReopeningTheDatabase() : void{
		$world = Path::join($this->tempDir, "world");
		mkdir(Path::join($world, "db"), 0777, true);

		$provider = (new \ReflectionClass(LevelDB::class))->newInstanceWithoutConstructor();
		$blockCache = new \ReflectionProperty(LevelDB::class, "blockCacheBytes");
		$dbProperty = new \ReflectionProperty(LevelDB::class, "db");
		$pathProperty = new \ReflectionProperty($provider, "path");

		self::assertSame(0, $blockCache->getValue($provider), "A provider built without a constructor must default to the LevelDB cache");

		$blockCache->setValue($provider, 32 * 1024 * 1024);
		$pathProperty->setValue($provider, $world);
		$dbProperty->setValue($provider, new \LevelDB(Path::join($world, "db"), LevelDB::getDatabaseOptions(32 * 1024 * 1024) + ["create_if_missing" => true]));
		$dbProperty->getValue($provider)->put("saved", "yes");

		try{
			$captured = false;
			$provider->withClosedDatabase(function() use (&$captured) : void{ $captured = true; });

			self::assertTrue($captured);
			self::assertSame(32 * 1024 * 1024, $blockCache->getValue($provider));
			self::assertSame("yes", $dbProperty->getValue($provider)->get("saved"));
		}finally{
			//release the database files before tearDown() removes the directory
			$provider->close();
		}
	}
}
