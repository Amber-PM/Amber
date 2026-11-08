<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\redstone;

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use pocketmine\world\WorldTimings;

/**
 * a world with real block storage and neighbour updates run like world doTick for redstone tests
 */
trait NativeRedstoneWorldTrait{

	/** @var World[] */
	private array $nativeWorlds = [];

	private int $nativeTick = 0;

	protected function closeNativeWorlds() : void{
		foreach($this->nativeWorlds as $world){
			foreach((new \ReflectionProperty(World::class, "chunks"))->getValue($world) as $chunk){
				foreach($chunk->getTiles() as $tile){
					$tile->close();
				}
			}
		}
		$this->nativeWorlds = [];
		$this->nativeTick = 0;
	}

	protected function nativeWorld(int $chunkRadius = 1) : World{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()
			->onlyMethods(["updateAllLight", "scheduleDelayedBlockUpdate", "addSound", "getNearbyEntities", "getFolderName", "getDisplayName", "addParticle", "dropItem", "isSpawnChunk", "getRealBlockSkyLightAt", "getSunAnglePercentage"])->getMock();
		$world->method("getFolderName")->willReturn("redstone");
		$world->method("getDisplayName")->willReturn("redstone");
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("isSpawnChunk")->willReturn(false);
		$world->method("getRealBlockSkyLightAt")->willReturn(15);
		$world->method("getSunAnglePercentage")->willReturn(0.0);
		$chunks = [];
		for($x = 0; $x <= $chunkRadius; ++$x){
			for($z = 0; $z <= $chunkRadius; ++$z){
				$chunks[World::chunkHash($x, $z)] = new Chunk([], true);
			}
		}
		foreach([
			"minY" => -64, "maxY" => 320, "server" => $this->createMock(\pocketmine\Server::class), "logger" => $this->createMock(\Logger::class),
			"blockStateRegistry" => RuntimeBlockStateRegistry::getInstance(),
			"chunks" => $chunks,
			"neighbourBlockUpdateQueue" => new \SplQueue()
		] as $name => $value){
			(new \ReflectionProperty(World::class, $name))->setValue($world, $value);
		}
		$world->timings = new WorldTimings($world);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, new RedstoneEngine($world, 2000));
		$this->nativeWorlds[] = $world;
		return $world;
	}

	/** @var array<int, Chunk> */
	private array $unloadedNativeChunks = [];

	protected function unloadNativeChunk(World $world, int $chunkX, int $chunkZ) : void{
		$property = new \ReflectionProperty(World::class, "chunks");
		$chunks = $property->getValue($world);
		$hash = World::chunkHash($chunkX, $chunkZ);
		$this->unloadedNativeChunks[$hash] = $chunks[$hash];
		unset($chunks[$hash]);
		$property->setValue($world, $chunks);
		$world->clearCache(true);
	}

	protected function reloadNativeChunk(World $world, int $chunkX, int $chunkZ) : void{
		$property = new \ReflectionProperty(World::class, "chunks");
		$chunks = $property->getValue($world);
		$hash = World::chunkHash($chunkX, $chunkZ);
		$chunks[$hash] = $this->unloadedNativeChunks[$hash];
		unset($this->unloadedNativeChunks[$hash]);
		$property->setValue($world, $chunks);
		$world->clearCache(true);
	}

	protected function engineOf(World $world) : RedstoneEngine{
		$engine = $world->getRedstoneEngine();
		self::assertNotNull($engine);
		return $engine;
	}

	//same order as world doTick
	protected function nativeTicks(World $world, int $count = 1) : void{
		$queue = (new \ReflectionProperty(World::class, "neighbourBlockUpdateQueue"))->getValue($world);
		$index = new \ReflectionProperty(World::class, "neighbourBlockUpdateQueueIndex");
		for($i = 0; $i < $count; ++$i){
			++$this->nativeTick;
			while($queue->count() > 0){
				$hash = $queue->dequeue();
				$pending = $index->getValue($world);
				unset($pending[$hash]);
				$index->setValue($world, $pending);
				World::getBlockXYZ($hash, $x, $y, $z);
				if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
					continue;
				}
				$block = $world->getBlockAt($x, $y, $z);
				$block->onNearbyBlockChange();
				$this->engineOf($world)->onNeighbourUpdate($block);
			}
			$this->engineOf($world)->tick($this->nativeTick);
		}
	}
}
