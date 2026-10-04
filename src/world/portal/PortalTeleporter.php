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
use function max;
use function min;

final class PortalTeleporter{

	public const SURVIVAL_PORTAL_TICKS = 80;
	public const CREATIVE_PORTAL_TICKS = 1;
	public const PORTAL_COOLDOWN_TICKS = 300;

	/** @var \WeakMap<Player, int>|null */
	private static ?\WeakMap $cooldowns = null;

	/** @var \WeakMap<Player, int>|null */
	private static ?\WeakMap $portalWaitTicks = null;

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
		$worldName = $world->getDisplayName();
		$nearest = null;
		$minDistSq = (float) ($searchRadius * $searchRadius);

		// 1. Check registered portals in memory
		if(isset(self::$registeredPortals[$worldName])){
			foreach(self::$registeredPortals[$worldName] as $center){
				$dx = $center->x - $targetPos->x;
				$dz = $center->z - $targetPos->z;
				$distSq = $dx * $dx + $dz * $dz;
				if($distSq <= $minDistSq){
					$b = $world->getBlockAt($center->getFloorX(), $center->getFloorY(), $center->getFloorZ());
					if($b->getTypeId() === BlockTypeIds::NETHER_PORTAL){
						$minDistSq = $distSq;
						$nearest = $center;
					}
				}
			}
		}

		if($nearest !== null){
			return $nearest;
		}

		// 2. Fallback scan around targetPos (+- 4 blocks horizontal, 32..120 Y)
		$tx = $targetPos->getFloorX();
		$tz = $targetPos->getFloorZ();
		$localRadius = min($searchRadius, 4);

		for($x = $tx - $localRadius; $x <= $tx + $localRadius; ++$x){
			for($z = $tz - $localRadius; $z <= $tz + $localRadius; ++$z){
				for($y = max(World::Y_MIN, 32); $y <= min(World::Y_MAX, 120); ++$y){
					$b = $world->getBlockAt($x, $y, $z);
					if($b->getTypeId() === BlockTypeIds::NETHER_PORTAL){
						$center = new Vector3($x + 0.5, $y, $z + 0.5);
						self::registerPortal($worldName, $center);
						return $center;
					}
				}
			}
		}

		return null;
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

	public static function recordTeleport(Player $player, int $currentTick) : void{
		self::init();
		if(self::$cooldowns !== null){
			self::$cooldowns[$player] = $currentTick;
		}
		if(self::$portalWaitTicks !== null){
			unset(self::$portalWaitTicks[$player]);
		}
	}

	public static function getPortalWaitTicks(Player $player) : int{
		return $player->isCreative() ? self::CREATIVE_PORTAL_TICKS : self::SURVIVAL_PORTAL_TICKS;
	}

	public static function getPlayerWaitTicks(Player $player) : int{
		self::init();
		return self::$portalWaitTicks?->offsetExists($player) ? (self::$portalWaitTicks[$player] ?? 0) : 0;
	}

	public static function handlePlayerInNetherPortal(Player $player) : void{
		self::init();
		if(self::$portalWaitTicks !== null){
			$wait = (self::$portalWaitTicks[$player] ?? 0) + 1;
			self::$portalWaitTicks[$player] = $wait;
		}
	}

	public static function handlePlayerInEndPortal(Player $player) : void{
		self::init();
	}

	public static function resetPortalWait(Player $player) : void{
		self::init();
		if(self::$portalWaitTicks !== null){
			unset(self::$portalWaitTicks[$player]);
		}
	}

	public static function teleport(Player $player, World $destinationWorld, int $targetDimension, int $currentTick = 0) : bool{
		$sourcePos = $player->getPosition();
		$sourceDimension = DimensionIds::OVERWORLD;

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
