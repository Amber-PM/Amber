<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\compression;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use function chr;
use function extension_loaded;
use function random_bytes;
use function str_repeat;

final class SnappyCompressorTest extends TestCase{

	protected function setUp() : void{
		if(!extension_loaded("snappy")){
			self::markTestSkipped("The snappy extension is required");
		}
	}

	public function testNetworkId() : void{
		self::assertSame(CompressionAlgorithm::SNAPPY, (new SnappyCompressor())->getNetworkId());
	}

	public function testCompressionThresholdFollowsConstructor() : void{
		self::assertSame(256, (new SnappyCompressor())->getCompressionThreshold());
		self::assertSame(0, (new SnappyCompressor(0))->getCompressionThreshold());
		self::assertNull((new SnappyCompressor(null))->getCompressionThreshold());
	}

	public function testRoundTrip() : void{
		$compressor = new SnappyCompressor();
		foreach(["", "a", str_repeat("amber", 5000), random_bytes(4096)] as $data){
			self::assertSame($data, $compressor->decompress($compressor->compress($data)));
		}
	}

	public function testRejectsOversizedPayloadBeforeAllocating() : void{
		$compressor = new SnappyCompressor(maxDecompressionSize: 1024);
		$payload = $compressor->compress(str_repeat("a", 2048));

		$this->expectException(DecompressionException::class);
		$compressor->decompress($payload);
	}

	public function testRejectsDeclaredLengthAboveLimit() : void{
		//varint 0xffffffff followed by garbage: must be rejected from the header alone
		$compressor = new SnappyCompressor(maxDecompressionSize: 1024);
		$this->expectException(DecompressionException::class);
		$compressor->decompress(chr(0xff) . chr(0xff) . chr(0xff) . chr(0xff) . chr(0x0f) . "garbage");
	}

	public function testRejectsGarbageAndTruncatedInput() : void{
		$compressor = new SnappyCompressor();
		foreach(["", chr(0x80), chr(0x05) . "xx"] as $bad){
			try{
				$compressor->decompress($bad);
				self::fail("Expected DecompressionException");
			}catch(DecompressionException){
				self::assertTrue(true);
			}
		}
	}
}
