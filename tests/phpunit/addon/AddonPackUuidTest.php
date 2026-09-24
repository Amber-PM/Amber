<?php

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class AddonPackUuidTest extends TestCase{

	private string $tempDir;
	private AddonManager $manager;
	private \ReflectionMethod $zipPackMethod;

	protected function setUp() : void{
		$this->tempDir = Path::join(sys_get_temp_dir(), "amber_pack_uuid_test_" . uniqid());
		@mkdir(Path::join($this->tempDir, "addons"), 0777, true);
		$server = $this->createMock(\pocketmine\Server::class);
		$logger = $this->createMock(\Logger::class);
		$this->manager = new AddonManager($server, Path::join($this->tempDir, "addons"), $logger);

		$this->zipPackMethod = new \ReflectionMethod(AddonManager::class, "zipPack");
		$this->zipPackMethod->setAccessible(true);
	}

	protected function tearDown() : void{
		Filesystem::recursiveUnlink($this->tempDir);
	}

	private function createPackDir(string $uuid) : string{
		$packDir = Path::join($this->tempDir, "pack_" . uniqid());
		@mkdir($packDir, 0777, true);
		$manifest = [
			"format_version" => 2,
			"header" => [
				"name" => "Test Pack",
				"uuid" => $uuid,
				"version" => [1, 0, 0]
			],
			"modules" => [
				[
					"type" => "resources",
					"uuid" => "22222222-2222-2222-2222-222222222222",
					"version" => [1, 0, 0]
				]
			]
		];
		file_put_contents(Path::join($packDir, "manifest.json"), json_encode($manifest, JSON_THROW_ON_ERROR));
		return $packDir;
	}

	public function testValidUuidAccepted() : void{
		$validUuid = "c18d1844-3c66-419b-b9f0-280fb5c5f492";
		$packDir = $this->createPackDir($validUuid);

		$packFromDir = AddonPack::fromDirectory($packDir, "test_source");
		self::assertSame($validUuid, $packFromDir->getUuid());

		$directPack = new AddonPack(
			$validUuid,
			"Test Pack",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$packDir,
			"test_source"
		);
		self::assertSame($validUuid, $directPack->getUuid());
	}

	public function testMalformedUuidRejectedFromDirectory() : void{
		$packDir = $this->createPackDir("not-a-uuid");

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("manifest.json header uuid is not a valid UUID");
		AddonPack::fromDirectory($packDir, "test_source");
	}

	public function testMalformedUuidRejectedInConstructor() : void{
		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("pack UUID 'not-a-uuid' is not a valid UUID");
		new AddonPack(
			"not-a-uuid",
			"Test Pack",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$this->tempDir,
			"test_source"
		);
	}

	public function testTraversalUuidRejectedFromDirectory() : void{
		$packDir = $this->createPackDir("../../../outside");

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("manifest.json header uuid is not a valid UUID");
		AddonPack::fromDirectory($packDir, "test_source");
	}

	public function testTraversalUuidRejectedInConstructor() : void{
		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("pack UUID '../../../outside' is not a valid UUID");
		new AddonPack(
			"../../../outside",
			"Test Pack",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$this->tempDir,
			"test_source"
		);
	}

	public function testUuidWithPathSeparatorRejectedFromDirectory() : void{
		$packDir = $this->createPackDir("c18d1844/3c66/419b/b9f0/280fb5c5f492");

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("manifest.json header uuid is not a valid UUID");
		AddonPack::fromDirectory($packDir, "test_source");
	}

	public function testUuidWithPathSeparatorRejectedInConstructor() : void{
		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("pack UUID 'c18d1844/3c66/419b/b9f0/280fb5c5f492' is not a valid UUID");
		new AddonPack(
			"c18d1844/3c66/419b/b9f0/280fb5c5f492",
			"Test Pack",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$this->tempDir,
			"test_source"
		);
	}

	public function testZipPackPathCannotEscapeCacheDir() : void{
		$validUuid = "c18d1844-3c66-419b-b9f0-280fb5c5f492";
		$packDir = $this->createPackDir($validUuid);
		$pack = AddonPack::fromDirectory($packDir, "test_source");

		/** @var string $zipPath */
		$zipPath = $this->zipPackMethod->invoke($this->manager, $pack);
		$cacheDir = Path::join($this->tempDir, "addons", ".cache", "packs");
		self::assertTrue(Path::isBasePath($cacheDir, $zipPath));
		self::assertFileExists($zipPath);

		$uuidProp = new \ReflectionProperty(AddonPack::class, "uuid");
		$uuidProp->setValue($pack, "../../../escaped_pack");

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("pack cache path escapes cache directory");
		$this->zipPackMethod->invoke($this->manager, $pack);
	}
}
