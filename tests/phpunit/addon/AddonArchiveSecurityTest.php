<?php

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function count;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function pack;
use function sha1_file;
use function str_repeat;
use function strpos;
use function substr_replace;
use function sys_get_temp_dir;
use function uniqid;
use const JSON_THROW_ON_ERROR;

final class AddonArchiveSecurityTest extends TestCase{

	private string $tempDir;
	private string $addonsDir;
	private AddonManager $manager;
	private \ReflectionProperty $packsProp;
	private \ReflectionMethod $extractZipMethod;

	protected function setUp() : void{
		$this->tempDir = Path::join(sys_get_temp_dir(), "amber_archive_sec_test_" . uniqid());
		$this->addonsDir = Path::join($this->tempDir, "addons");
		@mkdir($this->addonsDir, 0777, true);

		$server = $this->createMock(\pocketmine\Server::class);
		$logger = $this->createMock(\Logger::class);
		$this->manager = new AddonManager($server, $this->addonsDir, $logger);

		$this->packsProp = new \ReflectionProperty(AddonManager::class, "packs");
		$this->packsProp->setAccessible(true);

		$this->extractZipMethod = new \ReflectionMethod(AddonManager::class, "extractZip");
		$this->extractZipMethod->setAccessible(true);
	}

	protected function tearDown() : void{
		Filesystem::recursiveUnlink($this->tempDir);
	}

	private static function createManifestJson(string $uuid, string $name = "Test Pack") : string{
		return json_encode([
			"format_version" => 2,
			"header" => [
				"name" => $name,
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
		], JSON_THROW_ON_ERROR);
	}

	private static function patchZipCentralDirectory(string $zipPath, int $uncompressedSize, ?int $compressedSize = null) : void{
		$data = file_get_contents($zipPath);
		if($data === false){
			throw new \RuntimeException("Cannot read zip file");
		}
		$offset = 0;
		while(($pos = strpos($data, "PK\x01\x02", $offset)) !== false){
			if($compressedSize !== null){
				$data = substr_replace($data, pack("V", $compressedSize), $pos + 20, 4);
			}
			$data = substr_replace($data, pack("V", $uncompressedSize), $pos + 24, 4);
			$offset = $pos + 4;
		}
		file_put_contents($zipPath, $data);
	}

	/** @return AddonPack[] */
	private function getLoadedPacks() : array{
		/** @var AddonPack[] $packs */
		$packs = $this->packsProp->getValue($this->manager);
		return $packs;
	}

	public function testNormalArchiveLoads() : void{
		$uuid = "11111111-1111-1111-1111-111111111111";
		$zipPath = Path::join($this->addonsDir, "normal.mcpack");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFromString("manifest.json", self::createManifestJson($uuid));
		$zip->close();

		$this->manager->load();
		$packs = $this->getLoadedPacks();
		self::assertCount(1, $packs);
		self::assertSame($uuid, $packs[0]->getUuid());
	}

	public function testNestedArchiveWithinAllowedDepthIsProcessed() : void{
		$uuid = "22222222-2222-2222-2222-222222222222";
		$nestedPath = Path::join($this->tempDir, "child.mcpack");

		$childZip = new \ZipArchive();
		$childZip->open($nestedPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$childZip->addFromString("manifest.json", self::createManifestJson($uuid));
		$childZip->close();

		$rootZipPath = Path::join($this->addonsDir, "root.mcaddon");
		$rootZip = new \ZipArchive();
		$rootZip->open($rootZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$rootZip->addFile($nestedPath, "child.mcpack");
		$rootZip->close();

		$this->manager->load();
		$packs = $this->getLoadedPacks();
		self::assertCount(1, $packs);
		self::assertSame($uuid, $packs[0]->getUuid());
	}

	public function testArchiveExactlyAtDocumentedBoundaryBehavesAsIntended() : void{
		$uuid = "33333333-3333-3333-3333-333333333333";
		$z3Path = Path::join($this->tempDir, "level3.mcpack");
		$z3 = new \ZipArchive();
		$z3->open($z3Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z3->addFromString("manifest.json", self::createManifestJson($uuid));
		$z3->close();

		$z2Path = Path::join($this->tempDir, "level2.zip");
		$z2 = new \ZipArchive();
		$z2->open($z2Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z2->addFile($z3Path, "level3.mcpack");
		$z2->close();

		$z1Path = Path::join($this->tempDir, "level1.zip");
		$z1 = new \ZipArchive();
		$z1->open($z1Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z1->addFile($z2Path, "level2.zip");
		$z1->close();

		$z0Path = Path::join($this->addonsDir, "root.zip");
		$z0 = new \ZipArchive();
		$z0->open($z0Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z0->addFile($z1Path, "level1.zip");
		$z0->close();

		$this->manager->load();
		$packs = $this->getLoadedPacks();
		self::assertCount(1, $packs);
		self::assertSame($uuid, $packs[0]->getUuid());
	}

	public function testOneLevelBeyondBoundaryIsRejectedOrSkipped() : void{
		$uuid = "44444444-4444-4444-4444-444444444444";
		$z4Path = Path::join($this->tempDir, "level4.mcpack");
		$z4 = new \ZipArchive();
		$z4->open($z4Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z4->addFromString("manifest.json", self::createManifestJson($uuid));
		$z4->close();

		$z3Path = Path::join($this->tempDir, "level3.zip");
		$z3 = new \ZipArchive();
		$z3->open($z3Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z3->addFile($z4Path, "level4.mcpack");
		$z3->close();

		$z2Path = Path::join($this->tempDir, "level2.zip");
		$z2 = new \ZipArchive();
		$z2->open($z2Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z2->addFile($z3Path, "level3.zip");
		$z2->close();

		$z1Path = Path::join($this->tempDir, "level1.zip");
		$z1 = new \ZipArchive();
		$z1->open($z1Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z1->addFile($z2Path, "level2.zip");
		$z1->close();

		$z0Path = Path::join($this->addonsDir, "root.zip");
		$z0 = new \ZipArchive();
		$z0->open($z0Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z0->addFile($z1Path, "level1.zip");
		$z0->close();

		$this->manager->load();
		$packs = $this->getLoadedPacks();
		self::assertCount(0, $packs);
	}

	public function testRepeatedArchiveNestingCannotResetCounterAndBypassLimit() : void{
		$uuidBoundary = "55555555-5555-5555-5555-555555555555";
		$uuidBeyond = "66666666-6666-6666-6666-666666666666";

		$z4Path = Path::join($this->tempDir, "beyond4.mcpack");
		$z4 = new \ZipArchive();
		$z4->open($z4Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z4->addFromString("manifest.json", self::createManifestJson($uuidBeyond, "Beyond Limit Pack"));
		$z4->close();

		$z3Path = Path::join($this->tempDir, "boundary3.mcpack");
		$z3 = new \ZipArchive();
		$z3->open($z3Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z3->addFromString("manifest.json", self::createManifestJson($uuidBoundary, "Boundary Pack"));
		$z3->addFile($z4Path, "nested_beyond.mcpack");
		$z3->close();

		$z2Path = Path::join($this->tempDir, "level2.zip");
		$z2 = new \ZipArchive();
		$z2->open($z2Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z2->addFile($z3Path, "boundary3.mcpack");
		$z2->close();

		$z1Path = Path::join($this->tempDir, "level1.zip");
		$z1 = new \ZipArchive();
		$z1->open($z1Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z1->addFile($z2Path, "level2.zip");
		$z1->close();

		$z0Path = Path::join($this->addonsDir, "root.zip");
		$z0 = new \ZipArchive();
		$z0->open($z0Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z0->addFile($z1Path, "level1.zip");
		$z0->close();

		$this->manager->load();
		$packs = $this->getLoadedPacks();
		self::assertCount(1, $packs);
		self::assertSame($uuidBoundary, $packs[0]->getUuid());
	}

	public function testNormalRealisticArchiveAccepted() : void{
		$zipPath = Path::join($this->tempDir, "realistic.zip");
		$target = Path::join($this->tempDir, "extracted_realistic");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFromString("manifest.json", self::createManifestJson("77777777-7777-7777-7777-777777777777"));
		$zip->addEmptyDir("textures");
		$zip->addFromString("textures/terrain.png", "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 64));
		$zip->addFromString("item.json", '{"format_version":"1.20.0","minecraft:item":{"description":{"identifier":"custom:sample"}}}');
		$zip->close();

		$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
		self::assertFileExists(Path::join($target, "manifest.json"));
		self::assertFileExists(Path::join($target, "textures", "terrain.png"));
		self::assertFileExists(Path::join($target, "item.json"));
	}

	public function testTooManyEntriesRejected() : void{
		$zipPath = Path::join($this->tempDir, "too_many_entries.zip");
		$target = Path::join($this->tempDir, "extracted_entries");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		for($i = 0; $i <= 10000; ++$i){
			$zip->addFromString("file_$i.txt", "");
		}
		$zip->close();

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("contains too many entries");
		$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
	}

	public function testSingleOversizedEntryRejected() : void{
		$zipPath = Path::join($this->tempDir, "single_oversized.zip");
		$target = Path::join($this->tempDir, "extracted_oversized");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFromString("large.dat", "dummy");
		$zip->close();

		self::patchZipCentralDirectory($zipPath, 105 * 1024 * 1024);

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("exceeding maximum size limit");
		$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
	}

	public function testTotalUncompressedSizeAboveLimitRejected() : void{
		$zipPath = Path::join($this->tempDir, "total_oversized.zip");
		$target = Path::join($this->tempDir, "extracted_total");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		for($i = 0; $i < 6; ++$i){
			$zip->addFromString("entry_$i.dat", "content_$i");
		}
		$zip->close();

		self::patchZipCentralDirectory($zipPath, 90 * 1024 * 1024, 80 * 1024 * 1024);

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("exceeds maximum total uncompressed size limit");
		$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
	}

	public function testRejectedArchiveDoesNotLeavePartiallyExtractedFiles() : void{
		$zipPath = Path::join($this->tempDir, "partial_check.zip");
		$target = Path::join($this->tempDir, "target_never_created");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFromString("valid.txt", "allowed content");
		$zip->addFromString("bad.dat", "trigger");
		$zip->close();

		self::patchZipCentralDirectory($zipPath, 105 * 1024 * 1024);

		try{
			$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
			self::fail("Expected AddonException was not thrown");
		}catch(AddonException $e){
			self::assertStringContainsString("exceeding maximum size limit", $e->getMessage());
		}

		self::assertFalse(is_dir($target));
	}

	public function testPathologicalCompressionRatioRejected() : void{
		$zipPath = Path::join($this->tempDir, "ratio_bomb.zip");
		$target = Path::join($this->tempDir, "extracted_bomb");

		$zeroes = str_repeat("0", 1024 * 1024);
		$tmpRaw = Path::join($this->tempDir, "raw_10mb.dat");
		$fp = fopen($tmpRaw, "wb");
		if($fp === false){
			self::fail("Failed to open temporary file for test");
		}
		for($i = 0; $i < 10; ++$i){
			fwrite($fp, $zeroes);
		}
		fclose($fp);

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFile($tmpRaw, "bomb.dat");
		$zip->close();
		unlink($tmpRaw);

		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("exceeds maximum compression ratio");
		$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
	}

	public function testOrdinaryCompressedContentAccepted() : void{
		$zipPath = Path::join($this->tempDir, "ordinary.zip");
		$target = Path::join($this->tempDir, "extracted_ordinary");

		$sampleJson = '{"data":[' . str_repeat('{"id":1,"type":"block"},', 2000) . '{"id":2,"type":"item"}]}';

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFromString("manifest.json", self::createManifestJson("88888888-8888-8888-8888-888888888888"));
		$zip->addFromString("data.json", $sampleJson);
		$zip->close();

		$this->extractZipMethod->invoke($this->manager, $zipPath, $target);
		self::assertFileExists(Path::join($target, "manifest.json"));
		self::assertFileExists(Path::join($target, "data.json"));
	}

	public function testMultipleNestedZipsIndividuallyValidExceedGlobalBudget() : void{
		$budget = new AddonExtractionBudget();

		$z1Path = Path::join($this->tempDir, "child1.mcpack");
		$z1 = new \ZipArchive();
		$z1->open($z1Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		for($i = 0; $i < 4; ++$i){
			$z1->addFromString("entry_$i.dat", "content1_$i");
		}
		$z1->close();
		self::patchZipCentralDirectory($z1Path, 75 * 1024 * 1024, 70 * 1024 * 1024);

		$z2Path = Path::join($this->tempDir, "child2.mcpack");
		$z2 = new \ZipArchive();
		$z2->open($z2Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		for($i = 0; $i < 4; ++$i){
			$z2->addFromString("entry_$i.dat", "content2_$i");
		}
		$z2->close();
		self::patchZipCentralDirectory($z2Path, 75 * 1024 * 1024, 70 * 1024 * 1024);

		$target1 = Path::join($this->tempDir, "ext1");
		$target2 = Path::join($this->tempDir, "ext2");
		@mkdir($target1, 0777, true);

		// First archive is within limits (4 * 75 MiB = 300 MiB <= 500 MiB, each file 75 MiB <= 100 MiB)
		$this->extractZipMethod->invoke($this->manager, $z1Path, $target1, $budget);
		self::assertSame(300 * 1024 * 1024, $budget->totalBytes);

		// Second archive is individually within 500 MiB, but cumulative (600 MiB) exceeds 500 MiB limit
		$this->expectException(AddonException::class);
		$this->expectExceptionMessage("exceeds cumulative add-on uncompressed size limit");
		$this->extractZipMethod->invoke($this->manager, $z2Path, $target2, $budget);
	}

	public function testMultipleNestedZipsExceedCumulativeEntryBudget() : void{
		$uuid1 = "12121212-1212-1212-1212-121212121212";
		$uuid2 = "34343434-3434-3434-3434-343434343434";

		$z1Path = Path::join($this->tempDir, "child_entries1.mcpack");
		$z1 = new \ZipArchive();
		$z1->open($z1Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z1->addFromString("manifest.json", self::createManifestJson($uuid1, "Child 1"));
		for($i = 0; $i < 6000; ++$i){
			$z1->addFromString("f_$i.txt", "");
		}
		$z1->close();

		$z2Path = Path::join($this->tempDir, "child_entries2.mcpack");
		$z2 = new \ZipArchive();
		$z2->open($z2Path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$z2->addFromString("manifest.json", self::createManifestJson($uuid2, "Child 2"));
		for($i = 0; $i < 6000; ++$i){
			$z2->addFromString("f_$i.txt", "");
		}
		$z2->close();

		$rootZipPath = Path::join($this->addonsDir, "root_entries.mcaddon");
		$rootZip = new \ZipArchive();
		$rootZip->open($rootZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$rootZip->addFile($z1Path, "a_child.mcpack");
		$rootZip->addFile($z2Path, "b_child.mcpack");
		$rootZip->close();

		$this->manager->load();
		$packs = $this->getLoadedPacks();
		self::assertCount(1, $packs);
		self::assertSame($uuid1, $packs[0]->getUuid());
	}

	public function testPreExistingUnvalidatedCacheCannotBypassValidation() : void{
		$uuid = "99999999-9999-9999-9999-999999999999";
		$zipPath = Path::join($this->addonsDir, "malicious.mcpack");

		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFromString("manifest.json", self::createManifestJson($uuid));
		$zip->addFromString("bad.dat", "trigger");
		$zip->close();

		self::patchZipCentralDirectory($zipPath, 105 * 1024 * 1024);

		$hash = sha1_file($zipPath);
		self::assertIsString($hash);
		$cacheTarget = Path::join($this->addonsDir, ".cache", "unpacked", $hash);
		@mkdir($cacheTarget, 0777, true);
		file_put_contents(Path::join($cacheTarget, "manifest.json"), self::createManifestJson($uuid));
		self::assertTrue(is_dir($cacheTarget));

		$this->manager->load();

		self::assertCount(0, $this->getLoadedPacks());
		self::assertFalse(is_dir($cacheTarget));
	}
}
