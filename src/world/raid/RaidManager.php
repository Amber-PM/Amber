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

namespace pocketmine\world\raid;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Villager;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function count;

class RaidManager{
	use SingletonTrait;

	/** @var array<int, Raid> */
	private array $activeRaids = [];

	public function __construct(){
		self::setInstance($this);
	}

	/**
	 * @return array<int, Raid>
	 */
	public function getActiveRaids() : array{
		return $this->activeRaids;
	}

	public function getRaidAt(World $world, Vector3 $pos, float $radius = 64.0) : ?Raid{
		foreach($this->activeRaids as $raid){
			if($raid->getWorld() === $world && $raid->getCenter()->distance($pos) <= $radius && !$raid->isFinished()){
				return $raid;
			}
		}
		return null;
	}

	public function createRaid(World $world, Vector3 $center, int $badOmenLevel = 1, int $difficulty = 2) : Raid{
		$raid = new Raid($world, $center, $badOmenLevel, $difficulty);
		$this->activeRaids[$raid->getId()] = $raid;
		return $raid;
	}

	/**
	 * Checks if a player with Bad Omen entered a village, starting a raid if eligible.
	 */
	public function checkPlayerRaidTrigger(Player $player) : ?Raid{
		$effects = $player->getEffects();
		$badOmen = $effects->get(VanillaEffects::BAD_OMEN());
		if($badOmen === null){
			return null;
		}

		$pos = $player->getPosition();
		$world = $player->getWorld();

		// Check if raid already active near this location
		if($this->getRaidAt($world, $pos, 96.0) !== null){
			return null;
		}

		// Check if player is near a village (near a villager within 64 blocks)
		$nearVillage = false;
		$bb = AxisAlignedBB::one()->offset($pos->x, $pos->y, $pos->z)->expandedCopy(64, 32, 64);
		foreach($world->getNearbyEntities($bb) as $entity){
			if($entity instanceof Villager && $entity->isAlive()){
				$nearVillage = true;
				break;
			}
		}

		if(!$nearVillage){
			return null;
		}

		// Trigger raid
		$omenLevel = $badOmen->getAmplifier() + 1;
		$effects->remove(VanillaEffects::BAD_OMEN());

		$raid = $this->createRaid($world, $pos->floor(), $omenLevel, $world->getDifficulty());
		return $raid;
	}

	public function onBellRung(World $world, Vector3 $pos) : void{
		$raid = $this->getRaidAt($world, $pos, 64.0);
		$raid?->onBellRung($pos);
	}

	public function tick() : void{
		foreach($this->activeRaids as $id => $raid){
			$raid->tick();
			if($raid->isFinished()){
				unset($this->activeRaids[$id]);
			}
		}
	}
}
