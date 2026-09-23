<?php

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class AddonPackCacheTest extends TestCase{

	private string $tempDir;
	private AddonManager $manager;
	private \ReflectionMethod $zipPackMethod;

	protected function setUp() : void{
		$this->tempDir = Path::join(sys_get_temp_dir(), "amber_pack_cache_test_" . uniqid());
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

	public function testPackCacheInvalidatesOnContentChange() : void{
		$packDir = Path::join($this->tempDir, "sample_pack");
		@mkdir(Path::join($packDir, "textures"), 0777, true);

		file_put_contents(Path::join($packDir, "manifest.json"), '{"format_version":2,"header":{"name":"Test","uuid":"11111111-1111-1111-1111-111111111111","version":[1,0,0]}}');
		file_put_contents(Path::join($packDir, "textures", "terrain.png"), "initial content");

		$pack = new AddonPack(
			"11111111-1111-1111-1111-111111111111",
			"Test Pack",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$packDir,
			"sample_pack"
		);

		// same uuid/version + same contents => same/reused cache
		/** @var string $zipPath1 */
		$zipPath1 = $this->zipPackMethod->invoke($this->manager, $pack);
		self::assertFileExists($zipPath1);
		$initialMtime = filemtime($zipPath1);

		/** @var string $zipPath2 */
		$zipPath2 = $this->zipPackMethod->invoke($this->manager, $pack);
		self::assertSame($zipPath1, $zipPath2);
		self::assertSame($initialMtime, filemtime($zipPath2));

		// same uuid/version + changed contents => different/refreshed cached pack
		file_put_contents(Path::join($packDir, "textures", "terrain.png"), "modified texture bytes");

		/** @var string $zipPath3 */
		$zipPath3 = $this->zipPackMethod->invoke($this->manager, $pack);
		self::assertNotSame($zipPath1, $zipPath3);
		self::assertFileExists($zipPath3);

		// generated archive contains the new content
		$zip = new \ZipArchive();
		self::assertTrue($zip->open($zipPath3));
		$archivedContent = $zip->getFromName("textures/terrain.png");
		$zip->close();
		self::assertSame("modified texture bytes", $archivedContent);
	}

	public function testStructuralLayoutCollisionProducesDifferentFingerprint() : void{
		$packDirA = Path::join($this->tempDir, "pack_a");
		$packDirB = Path::join($this->tempDir, "pack_b");
		@mkdir($packDirA, 0777, true);
		@mkdir($packDirB, 0777, true);

		$manifest = '{"format_version":2,"header":{"name":"CollisionTest","uuid":"22222222-2222-2222-2222-222222222222","version":[1,0,0]}}';
		file_put_contents(Path::join($packDirA, "manifest.json"), $manifest);
		file_put_contents(Path::join($packDirB, "manifest.json"), $manifest);

		file_put_contents(Path::join($packDirA, "a"), "Xb\0Y");

		file_put_contents(Path::join($packDirB, "a"), "X");
		file_put_contents(Path::join($packDirB, "b"), "Y");

		$packA = new AddonPack(
			"22222222-2222-2222-2222-222222222222",
			"Collision Test A",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$packDirA,
			"pack_a"
		);

		$packB = new AddonPack(
			"22222222-2222-2222-2222-222222222222",
			"Collision Test B",
			[1, 0, 0],
			AddonPack::TYPE_RESOURCES,
			$packDirB,
			"pack_b"
		);

		/** @var string $zipPathA */
		$zipPathA = $this->zipPackMethod->invoke($this->manager, $packA);
		/** @var string $zipPathB */
		$zipPathB = $this->zipPackMethod->invoke($this->manager, $packB);

		self::assertNotSame($zipPathA, $zipPathB);
	}
}
