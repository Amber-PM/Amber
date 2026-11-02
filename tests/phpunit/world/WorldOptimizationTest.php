<?php

declare(strict_types=1);

namespace pocketmine\world;

use PHPUnit\Framework\TestCase;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\format\Chunk;

final class WorldOptimizationTest extends TestCase{

	private function world(int $chunks = 1) : World{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()
			->onlyMethods(["isSpawnChunk", "getAutoSave", "getDisplayName"])->getMock();
		$world->method("isSpawnChunk")->willReturn(false);
		$world->method("getAutoSave")->willReturn(false);
		$world->method("getDisplayName")->willReturn("optimization-regression");
		$loaded = [];
		for($x = 0; $x < $chunks; ++$x){
			$loaded[World::chunkHash($x, 0)] = new Chunk([], true);
		}
		foreach(["minY" => -64, "maxY" => 320, "folderName" => "optimization-regression", "blockStateRegistry" => RuntimeBlockStateRegistry::getInstance(), "chunks" => $loaded] as $name => $value){
			(new \ReflectionProperty(World::class, $name))->setValue($world, $value);
		}
		$world->timings = new WorldTimings($world);
		return $world;
	}

	private function property(World $world, string $name) : mixed{
		return (new \ReflectionProperty(World::class, $name))->getValue($world);
	}

	private function age(World $world, int $x, float $seconds) : void{
		$queue = $this->property($world, "unloadQueue");
		$queue[World::chunkHash($x, 0)] -= $seconds;
		(new \ReflectionProperty(World::class, "unloadQueue"))->setValue($world, $queue);
	}

	public function testUncachedWritesDoNotReduceCacheSize() : void{
		$world = $this->world();
		for($i = 0; $i < 100; ++$i){
			$world->setBlockAt($i % 16, 64 + intdiv($i, 16), 0, VanillaBlocks::STONE(), false);
		}
		self::assertSame(0, $this->property($world, "blockCacheSize"));
		self::assertSame([], $this->property($world, "blockCache"));
	}

	public function testCacheTrimsAtCapAfterUncachedWrites() : void{
		$world = $this->world();
		for($i = 0; $i < 100; ++$i){
			$world->setBlockAt($i % 16, 64 + intdiv($i, 16), 0, VanillaBlocks::STONE(), false);
		}
		$cap = (new \ReflectionClass(World::class))->getConstant("BLOCK_CACHE_SIZE_CAP");
		for($i = 0; $i < $cap - 1; ++$i){
			$world->getBlockAt($i % 16, intdiv($i, 256), intdiv($i % 256, 16));
		}
		self::assertSame($cap - 1, $this->property($world, "blockCacheSize"));
		self::assertSame($cap - 1, array_sum(array_map("count", $this->property($world, "blockCache"))));
		$world->getBlockAt(15, 7, 15);
		self::assertSame(0, $this->property($world, "blockCacheSize"));
		self::assertSame([], $this->property($world, "blockCache"));
		$world->getBlockAt(0, 64, 0);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::DIRT(), false);
		$world->setBlockAt(0, 64, 0, VanillaBlocks::STONE(), false);
		self::assertSame(0, $this->property($world, "blockCacheSize"));
		self::assertSame(VanillaBlocks::STONE()->getStateId(), $world->getBlockAt(0, 64, 0)->getStateId());
	}

	public function testGraceAndReschedulingOrder() : void{
		$world = $this->world(2);
		$world->unloadChunkRequest(0, 0);
		$this->age($world, 0, 31);
		$world->unloadChunkRequest(1, 0);
		$this->age($world, 1, 31);
		$world->unloadChunkRequest(0, 0);
		self::assertSame([World::chunkHash(1, 0), World::chunkHash(0, 0)], array_keys($this->property($world, "unloadQueue")));
		$world->unloadChunks();
		self::assertTrue($world->isChunkLoaded(0, 0));
		self::assertFalse($world->isChunkLoaded(1, 0));
		$this->age($world, 0, 29);
		$world->unloadChunks();
		self::assertTrue($world->isChunkLoaded(0, 0));
		$this->age($world, 0, 2);
		$world->unloadChunks();
		self::assertFalse($world->isChunkLoaded(0, 0));
	}

	public function testCancelRequestKeepsChunkLoaded() : void{
		$world = $this->world();
		$world->unloadChunkRequest(0, 0);
		$this->age($world, 0, 31);
		$world->cancelUnloadChunkRequest(0, 0);
		$world->unloadChunks(true);
		self::assertTrue($world->isChunkLoaded(0, 0));
		self::assertSame([], $this->property($world, "unloadQueue"));
	}

	public function testCancelledEventIsRetriedWithoutBlockingLaterExpiredChunks() : void{
		$world = $this->world(2);
		$calls = 0;
		$listener = new RegisteredListener(static function(ChunkUnloadEvent $event) use (&$calls) : void{
			if($event->getChunkX() === 0){
				++$calls;
				$event->cancel();
			}
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler("unload regression"));
		$handlers = HandlerListManager::global()->getListFor(ChunkUnloadEvent::class);
		$handlers->register($listener);
		try{
			foreach([0, 1] as $x){
				$world->unloadChunkRequest($x, 0);
				$this->age($world, $x, 31);
			}
			$world->unloadChunks();
			self::assertTrue($world->isChunkLoaded(0, 0));
			self::assertFalse($world->isChunkLoaded(1, 0));
			self::assertCount(1, $this->property($world, "unloadQueue"));
			$world->unloadChunks();
			self::assertSame(2, $calls);
		}finally{
			$handlers->unregister($listener);
		}
		$world->unloadChunks();
		self::assertFalse($world->isChunkLoaded(0, 0));
	}

	public function testSuccessCapAndForceBypass() : void{
		$world = $this->world(100);
		for($x = 0; $x < 100; ++$x){
			$world->unloadChunkRequest($x, 0);
			$this->age($world, $x, 31);
		}
		$world->unloadChunks();
		self::assertCount(4, $world->getLoadedChunks());
		$world = $this->world(100);
		for($x = 0; $x < 100; ++$x){
			$world->unloadChunkRequest($x, 0);
		}
		$world->unloadChunks(true);
		self::assertSame([], $world->getLoadedChunks());
		self::assertSame([], $this->property($world, "unloadQueue"));
	}
}
