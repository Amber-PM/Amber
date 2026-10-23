<?php

declare(strict_types=1);

namespace pocketmine\tests\support\entity;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use pocketmine\world\WorldTimings;
use ReflectionProperty;
use SplQueue;

final class NativeEntityTestEnvironment{

	private World $world;
	private int $tick = 0;

	public static function create(TestCase $test) : self{
		$self = new self();
		$server = $test->getMockBuilder(Server::class)->disableOriginalConstructor()->getMock();
		$server->method("getTick")->willReturnCallback(fn() => $self->tick);
		$logger = $test->getMockBuilder(Logger::class)->getMock();
		$world = $test->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"updateAllLight", "scheduleDelayedBlockUpdate", "addSound", "getFolderName", "getDisplayName", "addParticle", "dropItem", "isSpawnChunk", "getRealBlockSkyLightAt", "getSunAnglePercentage"
		])->getMock();
		$world->method("getFolderName")->willReturn("entity-regression");
		$world->method("getDisplayName")->willReturn("entity-regression");
		$world->method("isSpawnChunk")->willReturn(false);
		$world->method("getRealBlockSkyLightAt")->willReturn(15);
		$world->method("getSunAnglePercentage")->willReturn(0.0);
		$chunks = [];
		for($x = -1; $x <= 2; ++$x){
			for($z = -1; $z <= 2; ++$z){
				$chunks[World::chunkHash($x, $z)] = new Chunk([], true);
			}
		}
		foreach(["minY" => -64, "maxY" => 320, "damageY" => -80, "server" => $server, "logger" => $logger, "blockStateRegistry" => RuntimeBlockStateRegistry::getInstance(), "chunks" => $chunks, "neighbourBlockUpdateQueue" => new SplQueue()] as $name => $value){
			(new ReflectionProperty(World::class, $name))->setValue($world, $value);
		}
		$world->timings = new WorldTimings($world);
		$self->world = $world;
		return $self;
	}

	public function getWorld() : World{
		return $this->world;
	}

	public function advanceTicks(int $ticks) : void{
		for($i = 0; $i < $ticks; ++$i){
			++$this->tick;
			foreach($this->world->getEntities() as $entity){
				$entity->onUpdate($this->tick);
			}
		}
	}

	public function close() : void{
		foreach($this->world->getEntities() as $entity){
			$entity->close();
		}
	}
}
