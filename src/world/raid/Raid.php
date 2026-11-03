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

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EvocationIllager;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Pillager;
use pocketmine\entity\raid\Raider;
use pocketmine\entity\Ravager;
use pocketmine\entity\Villager;
use pocketmine\entity\Vindicator;
use pocketmine\entity\Witch;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\sound\BellRingSound;
use pocketmine\world\sound\RaidHornSound;
use pocketmine\world\World;
use function cos;
use function count;
use function max;
use function mt_rand;
use function sin;
use const M_PI;

class Raid{

	public const STATE_PREPARING = 0;
	public const STATE_IN_PROGRESS = 1;
	public const STATE_VICTORY = 2;
	public const STATE_DEFEAT = 3;
	public const STATE_STOPPED = 4;

	private static int $raidIdCounter = 1;

	private int $id;
	private World $world;
	private Vector3 $center;
	private int $badOmenLevel;
	private int $totalWaves;
	private int $currentWave = 0;
	private int $state = self::STATE_PREPARING;
	private int $stateTicks = 0;
	private int $totalTicks = 0;

	/** @var array<int, Living&Raider> */
	private array $raiders = [];
	private int $totalWaveRaiders = 0;

	/** @var array<int, Player> */
	private array $participatingPlayers = [];

	private int $bossBarEntityId;

	public function __construct(World $world, Vector3 $center, int $badOmenLevel = 1, int $difficulty = 2){
		$this->id = self::$raidIdCounter++;
		$this->world = $world;
		$this->center = $center;
		$this->badOmenLevel = max(1, $badOmenLevel);
		$this->bossBarEntityId = Entity::nextRuntimeId();

		// Calculate total waves based on difficulty
		$baseWaves = match($difficulty){
			1 => 3, // Easy
			2 => 5, // Normal
			3 => 7, // Hard
			default => 5
		};

		if($this->badOmenLevel > 1){
			$baseWaves++; // Extra bonus wave for high bad omen level
		}

		$this->totalWaves = $baseWaves;
		$this->state = self::STATE_PREPARING;
		$this->stateTicks = 60; // 3 second initial horn buildup
	}

	public function getId() : int{
		return $this->id;
	}

	public function getWorld() : World{
		return $this->world;
	}

	public function getCenter() : Vector3{
		return $this->center;
	}

	public function getBadOmenLevel() : int{
		return $this->badOmenLevel;
	}

	public function getTotalWaves() : int{
		return $this->totalWaves;
	}

	public function getCurrentWave() : int{
		return $this->currentWave;
	}

	public function getState() : int{
		return $this->state;
	}

	public function isFinished() : bool{
		return $this->state === self::STATE_VICTORY || $this->state === self::STATE_DEFEAT || $this->state === self::STATE_STOPPED;
	}

	/**
	 * @return array<int, Living&Raider>
	 */
	public function getRaiders() : array{
		return $this->raiders;
	}

	public function tick() : void{
		if($this->isFinished()){
			return;
		}

		$this->totalTicks++;

		if($this->state === self::STATE_PREPARING){
			if(--$this->stateTicks <= 0){
				$this->startNextWave();
			}
			return;
		}

		if($this->state === self::STATE_IN_PROGRESS){
			// Clean up any dead/despawned raiders
			foreach($this->raiders as $id => $raider){
				if(!$raider->isAlive() || $raider->isClosed() || $raider->getWorld() !== $this->world){
					unset($this->raiders[$id]);
					$this->updateBossBar();
				}
			}

			// Update boss bar periodically
			if($this->totalTicks % 20 === 0){
				$this->updateBossBar();

				// Check defeat condition (no living villagers remaining in village)
				$hasVillagers = false;
				foreach($this->world->getNearbyEntities($this->world->getEntity($this->bossBarEntityId)?->getBoundingBox() ?? \pocketmine\math\AxisAlignedBB::one()->offset($this->center->x, $this->center->y, $this->center->z)->expandedCopy(64, 32, 64)) as $e){
					if($e instanceof Villager && $e->isAlive()){
						$hasVillagers = true;
						break;
					}
				}

				if(!$hasVillagers && $this->totalTicks > 200){
					$this->triggerDefeat();
					return;
				}
			}

			// If all raiders in wave are defeated
			if(count($this->raiders) === 0){
				if($this->currentWave >= $this->totalWaves){
					$this->triggerVictory();
				}else{
					$this->state = self::STATE_PREPARING;
					$this->stateTicks = 80; // 4 seconds between waves
					$this->updateBossBar();
				}
			}
		}
	}

	public function startNextWave() : void{
		$this->currentWave++;
		$this->state = self::STATE_IN_PROGRESS;

		// Play raid horn
		$this->world->addSound($this->center, new RaidHornSound());

		// Spawn wave mobs
		$this->spawnWaveRaiders($this->currentWave);
		$this->totalWaveRaiders = count($this->raiders);
		$this->updateBossBar();
	}

	private function spawnWaveRaiders(int $wave) : void{
		// Wave composition
		$pillagers = 0;
		$vindicators = 0;
		$ravagers = 0;
		$evokers = 0;
		$witches = 0;

		switch($wave){
			case 1:
				$pillagers = 4;
				break;
			case 2:
				$pillagers = 3;
				$vindicators = 2;
				break;
			case 3:
				$pillagers = 3;
				$ravagers = 1;
				break;
			case 4:
				$pillagers = 2;
				$vindicators = 2;
				$witches = 1;
				break;
			case 5:
				$pillagers = 4;
				$vindicators = 1;
				$ravagers = 1;
				break;
			case 6:
				$vindicators = 4;
				$evokers = 1;
				break;
			case 7:
				$pillagers = 2;
				$vindicators = 3;
				$ravagers = 1;
				$evokers = 1;
				break;
			default:
				// Bonus waves
				$pillagers = 2;
				$vindicators = 2;
				$ravagers = 1;
				$evokers = 1;
				break;
		}

		$firstRaider = true;
		for($i = 0; $i < $pillagers; $i++){
			$pos = $this->findSpawnPosition();
			$pillager = new Pillager(Location::fromObject($pos, $this->world));
			$pillager->setRaid($this);
			$pillager->setWave($wave);
			if($firstRaider && $wave === 1){
				$pillager->setRaidCaptain(true);
				$firstRaider = false;
			}
			$pillager->spawnToAll();
			$this->raiders[$pillager->getId()] = $pillager;
		}

		for($i = 0; $i < $vindicators; $i++){
			$pos = $this->findSpawnPosition();
			$vindicator = new Vindicator(Location::fromObject($pos, $this->world));
			$vindicator->setRaid($this);
			$vindicator->setWave($wave);
			$vindicator->spawnToAll();
			$this->raiders[$vindicator->getId()] = $vindicator;
		}

		for($i = 0; $i < $ravagers; $i++){
			$pos = $this->findSpawnPosition();
			$ravager = new Ravager(Location::fromObject($pos, $this->world));
			$ravager->setRaid($this);
			$ravager->setWave($wave);
			$ravager->spawnToAll();
			$this->raiders[$ravager->getId()] = $ravager;
		}

		for($i = 0; $i < $evokers; $i++){
			$pos = $this->findSpawnPosition();
			$evoker = new EvocationIllager(Location::fromObject($pos, $this->world));
			$evoker->setRaid($this);
			$evoker->setWave($wave);
			$evoker->spawnToAll();
			$this->raiders[$evoker->getId()] = $evoker;
		}

		for($i = 0; $i < $witches; $i++){
			$pos = $this->findSpawnPosition();
			if(class_exists(Witch::class)){
				$witch = new Witch(Location::fromObject($pos, $this->world));
				$witch->spawnToAll();
				// Witch participates if treated as living hostile
			}
		}
	}

	private function findSpawnPosition() : Vector3{
		// Spawn in circle around village center at 32-48 blocks radius
		$angle = (mt_rand(0, 360) * M_PI) / 180.0;
		$distance = mt_rand(32, 48);
		$x = (int) floor($this->center->x + ($distance * cos($angle)));
		$z = (int) floor($this->center->z + ($distance * sin($angle)));
		$y = $this->world->getHighestBlockAt($x, $z) ?? (int) $this->center->y;

		return new Vector3($x + 0.5, $y + 1.0, $z + 0.5);
	}

	public function onRaiderDeath(Raider $raider, ?Entity $killer = null) : void{
		if($raider instanceof Living){
			unset($this->raiders[$raider->getId()]);
		}

		if($killer instanceof Player){
			$this->participatingPlayers[$killer->getId()] = $killer;
		}

		$this->updateBossBar();
	}

	public function triggerVictory() : void{
		$this->state = self::STATE_VICTORY;

		// Play celebratory sound
		$this->world->addSound($this->center, new RaidHornSound());

		// Grant Hero of the Village to participating players (duration: 48000 ticks / 40 minutes)
		$villageHero = VanillaEffects::VILLAGE_HERO();
		$duration = 48000;
		$amplifier = max(0, $this->badOmenLevel - 1);

		foreach($this->participatingPlayers as $player){
			if($player->isOnline()){
				$player->getEffects()->add(new EffectInstance($villageHero, $duration, $amplifier));
			}
		}

		// Nearby villagers celebrate
		foreach($this->world->getNearbyEntities(\pocketmine\math\AxisAlignedBB::one()->offset($this->center->x, $this->center->y, $this->center->z)->expandedCopy(64, 32, 64)) as $e){
			if($e instanceof Villager && $e->isAlive()){
				$this->world->addParticle($e->getLocation()->add(0, 1.5, 0), new HappyVillagerParticle());
			}
		}

		$this->hideBossBar();
	}

	public function triggerDefeat() : void{
		$this->state = self::STATE_DEFEAT;

		// Surviving illagers celebrate
		foreach($this->raiders as $raider){
			$raider->setCelebrating(true);
		}

		$this->hideBossBar();
	}

	public function stop() : void{
		$this->state = self::STATE_STOPPED;
		$this->hideBossBar();
		$this->raiders = [];
	}

	public function onBellRung(Vector3 $bellPos) : void{
		if($this->center->distance($bellPos) <= 64){
			$this->world->addSound($bellPos, new BellRingSound());
			// Highlight living raiders
			foreach($this->raiders as $raider){
				if($raider instanceof Living && $raider->isAlive()){
					$this->world->addParticle($raider->getLocation()->add(0, 1, 0), new HappyVillagerParticle());
				}
			}
		}
	}

	public function updateBossBar() : void{
		$remaining = count($this->raiders);
		$title = "Raid - Raiders remaining: " . $remaining;
		$healthPct = $this->totalWaveRaiders > 0 ? (float) ($remaining / $this->totalWaveRaiders) : 0.0;

		$players = $this->world->getPlayers();
		foreach($players as $player){
			if($player->getPosition()->distance($this->center) <= 96){
				$pk = BossEventPacket::show($this->bossBarEntityId, $title, $healthPct, false, BossBarColor::RED);
				$player->getNetworkSession()->sendDataPacket($pk);
			}else{
				$pk = BossEventPacket::hide($this->bossBarEntityId);
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}

	public function hideBossBar() : void{
		$pk = BossEventPacket::hide($this->bossBarEntityId);
		foreach($this->world->getPlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}
}
