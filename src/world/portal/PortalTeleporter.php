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

namespace pocketmine\world\portal;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\sound\PortalTravelSound;
use pocketmine\world\World;
use function array_values;
use function floor;
use function in_array;
use function max;
use function min;
use function strtolower;

final class PortalTeleporter{

	public const SURVIVAL_PORTAL_TICKS = 80;
	public const CREATIVE_PORTAL_TICKS = 1;
	public const PORTAL_COOLDOWN_TICKS = 300;

	/** @var \WeakMap<Player, int>|null */
	private static ?\WeakMap $cooldowns = null;

	/** @var \WeakMap<Player, int>|null */
	private static ?\WeakMap $portalWaitTicks = null;

	/** @var \WeakMap<Player, bool>|null */
	private static ?\WeakMap $inPortalThisTick = null;

	private static ?\WeakMap $lastPortalTicks = null;

	/** @var (\Closure(Player, int) : ?World)|null */
	private static ?\Closure $destinationResolver = null;

	/** @var array<string, list<Vector3>> */
	private static array $registeredPortals = [];

	private function __construct(){
		// NOOP
	}

	public static function init() : void{
		/** @var \WeakMap<Player, int> $cooldowns */
		$cooldowns = new \WeakMap();
		self::$cooldowns ??= $cooldowns;

		/** @var \WeakMap<Player, int> $portalWaitTicks */
		$portalWaitTicks = new \WeakMap();
		self::$portalWaitTicks ??= $portalWaitTicks;

		/** @var \WeakMap<Player, bool> $inPortalThisTick */
		$inPortalThisTick = new \WeakMap();
		self::$inPortalThisTick ??= $inPortalThisTick;
		self::$lastPortalTicks ??= new \WeakMap();
	}

	public static function scaleCoordinates(Vector3 $pos, int $sourceDimension, int $targetDimension) : Vector3{
		if($sourceDimension === DimensionIds::OVERWORLD && $targetDimension === DimensionIds::NETHER){
			$x = floor($pos->x / 8.0);
			$z = floor($pos->z / 8.0);
			$y = max(32.0, min(120.0, $pos->y));
			return new Vector3($x, $y, $z);
		}

		if($sourceDimension === DimensionIds::NETHER && $targetDimension === DimensionIds::OVERWORLD){
			$x = floor($pos->x * 8.0);
			$z = floor($pos->z * 8.0);
			$y = max(64.0, min(250.0, $pos->y));
			return new Vector3($x, $y, $z);
		}

		if($targetDimension === DimensionIds::THE_END){
			return new Vector3(100.5, 49.0, 0.5);
		}

		return $pos;
	}

	public static function registerPortal(string $worldName, Vector3 $center) : void{
		foreach(self::$registeredPortals[$worldName] ?? [] as $registered){
			if($registered->equals($center)){
				return;
			}
		}
		self::$registeredPortals[$worldName][] = $center;
	}

	public static function unregisterPortalAt(string $worldName, Vector3 $pos) : void{
		if(!isset(self::$registeredPortals[$worldName])){
			return;
		}
		$remaining = [];
		foreach(self::$registeredPortals[$worldName] as $center){
			if($center->distanceSquared($pos) > 4.0){
				$remaining[] = $center;
			}
		}
		self::$registeredPortals[$worldName] = $remaining;
	}

	public static function clearRegisteredPortals() : void{
		self::$registeredPortals = [];
	}

	/**
	 * @return list<Vector3>
	 */
	public static function getRegisteredPortals(string $worldName) : array{
		return self::$registeredPortals[$worldName] ?? [];
	}

	public static function findNearestPortal(World $world, Vector3 $targetPos, int $searchRadius = 128) : ?Vector3{
		if($searchRadius < 0){
			throw new \InvalidArgumentException("Search radius must not be negative");
		}
		$tx = $targetPos->getFloorX();
		$tz = $targetPos->getFloorZ();
		$nearest = null;
		$minDistSq = INF;
		for($chunkX = ($tx - $searchRadius) >> 4; $chunkX <= ($tx + $searchRadius) >> 4; ++$chunkX){
			for($chunkZ = ($tz - $searchRadius) >> 4; $chunkZ <= ($tz + $searchRadius) >> 4; ++$chunkZ){
				$chunk = $world->loadChunk($chunkX, $chunkZ);
				if($chunk === null){
					continue;
				}
				foreach($chunk->getSubChunks() as $subY => $subChunk){
					$layers = $subChunk->getBlockLayers();
					if($layers === []){
						continue;
					}
					$portalStates = [];
					foreach($layers[0]->getPalette() as $stateId){
						if(($stateId >> Block::INTERNAL_STATE_DATA_BITS) === BlockTypeIds::NETHER_PORTAL){
							$portalStates[$stateId] = true;
						}
					}
					if($portalStates === []){
						continue;
					}
					for($x = max(0, $tx - $searchRadius - ($chunkX << 4)); $x <= min(15, $tx + $searchRadius - ($chunkX << 4)); ++$x){
						for($z = max(0, $tz - $searchRadius - ($chunkZ << 4)); $z <= min(15, $tz + $searchRadius - ($chunkZ << 4)); ++$z){
							$worldX = ($chunkX << 4) + $x;
							$worldZ = ($chunkZ << 4) + $z;
							if(($worldX - $tx) ** 2 + ($worldZ - $tz) ** 2 > $searchRadius ** 2){
								continue;
							}
							for($y = 0; $y < 16; ++$y){
								$worldY = ($subY << 4) + $y;
								if($worldY < $world->getMinY() || $worldY >= $world->getMaxY() || !isset($portalStates[$layers[0]->get($x, $y, $z)])){
									continue;
								}
								if($worldY > $world->getMinY() && ($chunk->getBlockStateId($x, $worldY - 1, $z) >> Block::INTERNAL_STATE_DATA_BITS) === BlockTypeIds::NETHER_PORTAL){
									continue;
								}
								$center = new Vector3($worldX + 0.5, $worldY, $worldZ + 0.5);
								$distance = $center->distanceSquared($targetPos);
								if($distance < $minDistSq){
									$nearest = $center;
									$minDistSq = $distance;
								}
							}
						}
					}
				}
			}
		}
		if($nearest !== null){
			self::registerPortal($world->getDisplayName(), $nearest);
		}
		return $nearest;
	}

	public static function createNetherPortal(World $world, Vector3 $targetPos, int $axis = Axis::X) : Vector3{
		$x = $targetPos->getFloorX();
		$y = (int) max(32, min(118, $targetPos->getFloorY()));
		$z = $targetPos->getFloorZ();

		$obsidian = VanillaBlocks::OBSIDIAN();
		$portalBlock = VanillaBlocks::NETHER_PORTAL()->setAxis($axis);

		if($axis === Axis::X){
			// Bottom frame (Y)
			for($dx = -1; $dx <= 2; ++$dx){
				$world->setBlockAt($x + $dx, $y, $z, $obsidian);
			}
			// Top frame (Y + 4)
			for($dx = -1; $dx <= 2; ++$dx){
				$world->setBlockAt($x + $dx, $y + 4, $z, $obsidian);
			}
			// Sides (Y + 1 .. Y + 3)
			for($dy = 1; $dy <= 3; ++$dy){
				$world->setBlockAt($x - 1, $y + $dy, $z, $obsidian);
				$world->setBlockAt($x + 2, $y + $dy, $z, $obsidian);
			}
			// Inner portal blocks
			for($dy = 1; $dy <= 3; ++$dy){
				for($dx = 0; $dx <= 1; ++$dx){
					$world->setBlockAt($x + $dx, $y + $dy, $z, $portalBlock, false);
				}
			}
			// Clear air clearance
			for($dy = 1; $dy <= 2; ++$dy){
				for($dx = 0; $dx <= 1; ++$dx){
					$world->setBlockAt($x + $dx, $y + $dy, $z - 1, VanillaBlocks::AIR(), false);
					$world->setBlockAt($x + $dx, $y + $dy, $z + 1, VanillaBlocks::AIR(), false);
				}
			}

			$spawnPos = new Vector3($x + 0.5, $y + 1.0, $z + 0.5);
		}else{
			// Bottom frame (Y)
			for($dz = -1; $dz <= 2; ++$dz){
				$world->setBlockAt($x, $y, $z + $dz, $obsidian);
			}
			// Top frame (Y + 4)
			for($dz = -1; $dz <= 2; ++$dz){
				$world->setBlockAt($x, $y + 4, $z + $dz, $obsidian);
			}
			// Sides (Y + 1 .. Y + 3)
			for($dy = 1; $dy <= 3; ++$dy){
				$world->setBlockAt($x, $y + $dy, $z - 1, $obsidian);
				$world->setBlockAt($x, $y + $dy, $z + 2, $obsidian);
			}
			// Inner portal blocks
			for($dy = 1; $dy <= 3; ++$dy){
				for($dz = 0; $dz <= 1; ++$dz){
					$world->setBlockAt($x, $y + $dy, $z + $dz, $portalBlock, false);
				}
			}
			// Clear air clearance
			for($dy = 1; $dy <= 2; ++$dy){
				for($dz = 0; $dz <= 1; ++$dz){
					$world->setBlockAt($x - 1, $y + $dy, $z + $dz, VanillaBlocks::AIR(), false);
					$world->setBlockAt($x + 1, $y + $dy, $z + $dz, VanillaBlocks::AIR(), false);
				}
			}

			$spawnPos = new Vector3($x + 0.5, $y + 1.0, $z + 0.5);
		}

		self::registerPortal($world->getDisplayName(), $spawnPos);
		return $spawnPos;
	}

	public static function createEndPlatform(World $world) : Vector3{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$air = VanillaBlocks::AIR();

		for($x = 98; $x <= 102; ++$x){
			for($z = -2; $z <= 2; ++$z){
				$world->setBlockAt($x, 48, $z, $obsidian);
				for($y = 49; $y <= 51; ++$y){
					$world->setBlockAt($x, $y, $z, $air, false);
				}
			}
		}

		return new Vector3(100.5, 49.0, 0.5);
	}

	public static function canTeleport(Player $player, int $currentTick) : bool{
		self::init();
		if(self::$cooldowns === null){
			return true;
		}

		$last = self::$cooldowns[$player] ?? null;
		if($last !== null && ($currentTick - $last) < self::PORTAL_COOLDOWN_TICKS){
			return false;
		}

		return true;
	}

	public static function setDestinationResolver(?\Closure $resolver) : void{
		self::$destinationResolver = $resolver;
	}

	public static function getDimensionId(World $world) : int{
		try{
			$name = strtolower($world->getDisplayName());
		}catch(\Error){
			$name = "world";
		}
		try{
			$folderName = strtolower($world->getFolderName());
		}catch(\Error){
			$folderName = $name;
		}
		if(in_array($folderName, ["nether", "world_nether", "hell"], true) || in_array($name, ["nether", "world_nether", "hell"], true)){
			return DimensionIds::NETHER;
		}
		if(in_array($folderName, ["the_end", "end", "world_the_end", "world_end"], true) || in_array($name, ["the_end", "end", "world_the_end", "world_end"], true)){
			return DimensionIds::THE_END;
		}
		return DimensionIds::OVERWORLD;
	}

	public static function resolveDestinationWorld(Player $player, int $targetDimension) : ?World{
		if(self::$destinationResolver !== null){
			$resolved = (self::$destinationResolver)($player, $targetDimension);
			if($resolved !== null){
				return $resolved;
			}
		}

		try{
			$worldManager = $player->getServer()->getWorldManager();
		}catch(\Throwable){
			return null;
		}

		if($targetDimension === DimensionIds::OVERWORLD){
			$defaultWorld = $worldManager->getDefaultWorld();
			if($defaultWorld !== null && self::getDimensionId($defaultWorld) === DimensionIds::OVERWORLD){
				return $defaultWorld;
			}
		}

		$candidates = match($targetDimension){
			DimensionIds::NETHER => ["nether", "world_nether", "hell"],
			DimensionIds::THE_END => ["the_end", "end", "world_the_end", "world_end"],
			default => []
		};

		foreach($candidates as $name){
			if($worldManager->isWorldLoaded($name)){
				return $worldManager->getWorldByName($name);
			}
			if($worldManager->isWorldGenerated($name)){
				if($worldManager->loadWorld($name)){
					return $worldManager->getWorldByName($name);
				}
			}
		}

		foreach($worldManager->getWorlds() as $loadedWorld){
			if(self::getDimensionId($loadedWorld) === $targetDimension){
				return $loadedWorld;
			}
		}

		return null;
	}

	public static function recordTeleport(Player $player, int $currentTick) : void{
		self::init();
		if(self::$cooldowns !== null){
			self::$cooldowns[$player] = $currentTick;
		}
		if(self::$portalWaitTicks !== null){
			unset(self::$portalWaitTicks[$player]);
		}
		if(self::$inPortalThisTick !== null){
			self::$inPortalThisTick[$player] = false;
		}
	}

	public static function getPortalWaitTicks(Player $player) : int{
		return $player->isCreative() ? self::CREATIVE_PORTAL_TICKS : self::SURVIVAL_PORTAL_TICKS;
	}

	public static function getPlayerWaitTicks(Player $player) : int{
		self::init();
		return self::$portalWaitTicks?->offsetExists($player) ? (self::$portalWaitTicks[$player] ?? 0) : 0;
	}

	public static function handlePlayerInNetherPortal(Player $player, int $currentTick = 0) : bool{
		self::init();
		if($currentTick === 0){
			try{
				$currentTick = $player->getServer()->getTick();
			}catch(\Throwable){
				$currentTick = 0;
			}
		}

		$inPortalThisTick = self::$inPortalThisTick;
		if($inPortalThisTick !== null){
			$inPortalThisTick[$player] = true;
		}

		if(!self::canTeleport($player, $currentTick)){
			return false;
		}
		if(self::$lastPortalTicks !== null){
			if((self::$lastPortalTicks[$player] ?? null) === $currentTick){
				return false;
			}
			self::$lastPortalTicks[$player] = $currentTick;
		}

		$wait = 1;
		$portalWaitTicks = self::$portalWaitTicks;
		if($portalWaitTicks !== null){
			$wait = ($portalWaitTicks[$player] ?? 0) + 1;
			$portalWaitTicks[$player] = $wait;
		}

		if($wait >= self::getPortalWaitTicks($player)){
			$currentDim = self::getDimensionId($player->getWorld());
			$targetDim = $currentDim === DimensionIds::NETHER ? DimensionIds::OVERWORLD : DimensionIds::NETHER;
			$destWorld = self::resolveDestinationWorld($player, $targetDim);
			if($destWorld !== null){
				return self::teleport($player, $destWorld, $targetDim, $currentTick);
			}
		}

		return false;
	}

	public static function handlePlayerInEndPortal(Player $player, int $currentTick = 0) : bool{
		self::init();
		if($currentTick === 0){
			try{
				$currentTick = $player->getServer()->getTick();
			}catch(\Throwable){
				$currentTick = 0;
			}
		}

		$inPortalThisTick = self::$inPortalThisTick;
		if($inPortalThisTick !== null){
			$inPortalThisTick[$player] = true;
		}

		if(!self::canTeleport($player, $currentTick)){
			return false;
		}

		$currentDim = self::getDimensionId($player->getWorld());
		$targetDim = $currentDim === DimensionIds::THE_END ? DimensionIds::OVERWORLD : DimensionIds::THE_END;
		$destWorld = self::resolveDestinationWorld($player, $targetDim);
		if($destWorld !== null){
			return self::teleport($player, $destWorld, $targetDim, $currentTick);
		}

		return false;
	}

	public static function resetPortalWait(Player $player) : void{
		self::init();
		if(self::$portalWaitTicks !== null){
			unset(self::$portalWaitTicks[$player]);
		}
		if(self::$lastPortalTicks !== null){
			unset(self::$lastPortalTicks[$player]);
		}
	}

	public static function onPlayerUpdate(Player $player) : void{
		self::init();
		$inPortalThisTick = self::$inPortalThisTick;
		if($inPortalThisTick !== null){
			$wasInPortal = $inPortalThisTick[$player] ?? false;
			if(!$wasInPortal){
				self::resetPortalWait($player);
			}
			$inPortalThisTick[$player] = false;
		}
	}

	public static function teleport(Player $player, World $destinationWorld, int $targetDimension, int $currentTick = 0) : bool{
		$sourcePos = $player->getPosition();
		$sourceDimension = self::getDimensionId($player->getWorld());

		if($targetDimension === DimensionIds::THE_END){
			self::createEndPlatform($destinationWorld);
			$destPos = new Vector3(100.5, 49.0, 0.5);
		}else{
			$targetPos = self::scaleCoordinates($sourcePos, $sourceDimension, $targetDimension);
			$existingPortal = self::findNearestPortal($destinationWorld, $targetPos);
			if($existingPortal !== null){
				$destPos = $existingPortal;
			}else{
				$destPos = self::createNetherPortal($destinationWorld, $targetPos);
			}
		}

		$destPosition = Position::fromObject($destPos, $destinationWorld);
		$success = $player->teleport($destPosition);
		if($success){
			$destinationWorld->addSound($destPos, new PortalTravelSound());
			self::recordTeleport($player, $currentTick);
		}

		return $success;
	}
}
