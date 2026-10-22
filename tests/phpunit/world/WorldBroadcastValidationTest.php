<?php

declare(strict_types=1);

namespace pocketmine\world;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use ReflectionClass;
use TypeError;

final class WorldBroadcastValidationTest extends TestCase{

	private World $world;

	protected function setUp() : void{
		$this->world = (new ReflectionClass(World::class))->newInstanceWithoutConstructor();
	}

	public static function invalidClosureProvider() : array{
		return [
			'wrong return type' => [fn(TypeConverter $typeConverter) : int => 0],
			'wrong parameter type' => [fn(string $typeConverter) : array => []],
		];
	}

	private function getQueuedBuffers() : array{
		return (new ReflectionClass(World::class))->getProperty('packetBuffersByChunkTypeConverter')->getValue($this->world);
	}

	#[DataProvider('invalidClosureProvider')]
	public function testPublicBroadcastRejectsInvalidClosure(\Closure $closure) : void{
		try{
			$this->world->broadcastPacketToViewersByTypeConverter(new Vector3(0, 0, 0), $closure);
			self::fail('Expected a TypeError for an invalid closure signature');
		}catch(TypeError){
			self::assertSame([], $this->getQueuedBuffers());
		}
	}

	public function testPublicBroadcastQueuesValidClosures() : void{
		$position = new Vector3(0, 0, 0);
		$this->world->broadcastPacketToViewersByTypeConverter($position, fn(TypeConverter $typeConverter) : array => []);
		$this->world->broadcastPacketToViewersByTypeConverter($position, fn($typeConverter) : array => []);

		$buffers = $this->getQueuedBuffers();
		self::assertCount(1, $buffers);
		self::assertCount(2, $buffers[World::chunkHash(0, 0)]);
	}
}
