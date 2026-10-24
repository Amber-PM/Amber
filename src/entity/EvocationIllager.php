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

namespace pocketmine\entity;

use pocketmine\block\utils\DyeColor;
use pocketmine\entity\raid\Raider;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\world\raid\Raid;
use pocketmine\world\raid\RaiderDrops;
use function atan2;
use function cos;
use function count;
use function floor;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

class EvocationIllager extends Living implements Raider{

	public static function getNetworkTypeId() : string{ return EntityIds::EVOCATION_ILLAGER; }

	public const SPELL_NONE = 0;
	public const SPELL_FANGS = 1;
	public const SPELL_SUMMON_VEX = 2;
	public const SPELL_WOLOLO = 3;

	private const COLOR_FANGS = 0xFF664D59;
	private const COLOR_VEX = 0xFFB3B3CC;
	private const COLOR_WOLOLO = 0xFFB38033;

	private const TAG_WAVE = "Wave";

	private ?Raid $raid = null;
	private int $wave = 0;
	private bool $celebrating = false;

	private int $activeSpell = self::SPELL_NONE;
	private int $spellCastTicks = 0;
	private int $spellDuration = 0;
	private int $fangCooldown = 0;
	private int $vexCooldown = 100; // Initial delay before first vex summon
	private int $wololoCooldown = 0;

	private ?Living $combatTarget = null;
	private ?Living $wololoTarget = null;
	private float $movementSpeed = 0.3;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6, 1.75);
	}

	public function getName() : string{
		return "Evoker";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(24);
		parent::initEntity($nbt);

		$this->wave = $nbt->getInt(self::TAG_WAVE, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_WAVE, $this->wave);
		return $nbt;
	}

	public function isRaidCaptain() : bool{
		return false;
	}

	public function setRaidCaptain(bool $isCaptain) : void{
		// Evokers are spellcasters, not standard squad captains
	}

	public function getRaid() : ?Raid{
		return $this->raid;
	}

	public function setRaid(?Raid $raid) : void{
		$this->raid = $raid;
	}

	public function getWave() : int{
		return $this->wave;
	}

	public function setWave(int $wave) : void{
		$this->wave = $wave;
	}

	public function isCelebrating() : bool{
		return $this->celebrating;
	}

	public function setCelebrating(bool $celebrating) : void{
		$this->celebrating = $celebrating;
		$this->networkPropertiesDirty = true;
	}

	public function hasRaidGoal() : bool{
		return $this->raid !== null && !$this->raid->isFinished();
	}

	public function getCombatTarget() : ?Living{
		return $this->combatTarget;
	}

	public function setCombatTarget(?Living $target) : void{
		$this->combatTarget = $target;
	}

	public function isCastingSpell() : bool{
		return $this->activeSpell !== self::SPELL_NONE;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CELEBRATING, $this->celebrating);
		$properties->setGenericFlag(EntityMetadataFlags::EVOKER_SPELL, $this->activeSpell !== self::SPELL_NONE);

		$color = match($this->activeSpell){
			self::SPELL_FANGS => self::COLOR_FANGS,
			self::SPELL_SUMMON_VEX => self::COLOR_VEX,
			self::SPELL_WOLOLO => self::COLOR_WOLOLO,
			default => 0
		};
		$properties->setInt(EntityMetadataProperties::EVOKER_SPELL_CASTING_COLOR, $color);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->fangCooldown > 0){
			$this->fangCooldown -= $tickDiff;
		}
		if($this->vexCooldown > 0){
			$this->vexCooldown -= $tickDiff;
		}
		if($this->wololoCooldown > 0){
			$this->wololoCooldown -= $tickDiff;
		}

		// Handle active spell casting
		if($this->activeSpell !== self::SPELL_NONE){
			$this->spellCastTicks += $tickDiff;

			if($this->spellCastTicks >= $this->spellDuration){
				$this->executeSpell();
				$this->activeSpell = self::SPELL_NONE;
				$this->spellCastTicks = 0;
				$this->networkPropertiesDirty = true;
			}
			return true;
		}

		// Check for Wololo Easter Egg (Blue sheep within 16 blocks)
		if($this->wololoCooldown <= 0){
			$this->wololoTarget = $this->findBlueSheep();
			if($this->wololoTarget !== null){
				$this->startCasting(self::SPELL_WOLOLO, 60); // 3 seconds
				return true;
			}
		}

		// Update combat target
		if($this->combatTarget === null || !$this->combatTarget->isAlive() || $this->combatTarget->isClosed() || $this->combatTarget->getPosition()->distance($this->location) > 32){
			$this->combatTarget = $this->findTarget();
		}

		if($this->combatTarget !== null){
			$targetPos = $this->combatTarget->getLocation();
			$dist = $this->location->distance($targetPos);

			// Look at target
			$xDist = $targetPos->x - $this->location->x;
			$zDist = $targetPos->z - $this->location->z;
			$yaw = atan2($zDist, $xDist) * 180 / M_PI - 90;
			$this->setRotation($yaw, 0.0);

			// Choose spell to cast
			if($this->vexCooldown <= 0 && $this->countNearbyVexes() < 8){
				$this->startCasting(self::SPELL_SUMMON_VEX, 100); // 5s cast time
				$this->vexCooldown = 340; // 17s cooldown
			}elseif($this->fangCooldown <= 0 && $dist <= 20.0){
				$this->startCasting(self::SPELL_FANGS, 40); // 2s cast time
				$this->fangCooldown = 100; // 5s cooldown
			}else{
				// Flee if too close (< 8 blocks), or advance if too far
				if($dist < 8.0 && $this->onGround){
					// Run away
					$motionX = -($xDist / $dist) * $this->movementSpeed * 0.35;
					$motionZ = -($zDist / $dist) * $this->movementSpeed * 0.35;
					$this->setMotion(new Vector3($motionX, $this->motion->y, $motionZ));
				}
			}
		}elseif($this->raid !== null && $this->onGround){
			$center = $this->raid->getCenter();
			$cDist = $this->location->distance($center);
			if($cDist > 4.0){
				$xDist = $center->x - $this->location->x;
				$zDist = $center->z - $this->location->z;
				$motionX = ($xDist / $cDist) * $this->movementSpeed * 0.25;
				$motionZ = ($zDist / $cDist) * $this->movementSpeed * 0.25;
				$this->setMotion(new Vector3($motionX, $this->motion->y, $motionZ));
			}
		}

		return true;
	}

	private function startCasting(int $spell, int $duration): void{
		$this->activeSpell = $spell;
		$this->spellDuration = $duration;
		$this->spellCastTicks = 0;
		$this->networkPropertiesDirty = true;
		$this->getWorld()->broadcastPacketToViewers($this->location, LevelSoundEventPacket::nonActorSound(LevelSoundEvent::CAST_SPELL, $this->location, false));
	}

	private function executeSpell() : void{
		switch($this->activeSpell){
			case self::SPELL_FANGS:
				$this->castFangs();
				break;
			case self::SPELL_SUMMON_VEX:
				$this->castSummonVexes();
				break;
			case self::SPELL_WOLOLO:
				$this->castWololo();
				break;
		}
	}

	private function castFangs() : void{
		if($this->combatTarget === null || !$this->combatTarget->isAlive()){
			return;
		}

		$targetPos = $this->combatTarget->getLocation();
		$dist = $this->location->distance($targetPos);

		if($dist <= 3.0){
			// Circle formation around Evoker
			// Inner ring: 5 fangs at radius 1.5
			for($i = 0; $i < 5; $i++){
				$angle = ($i * 2 * M_PI) / 5;
				$x = $this->location->x + 1.5 * cos($angle);
				$z = $this->location->z + 1.5 * sin($angle);
				$y = $this->getWorld()->getHighestBlockAt((int) floor($x), (int) floor($z)) ?? (int) $this->location->y;
				$fang = new EvocationFang(Location::fromObject(new Vector3($x, $y + 1, $z), $this->getWorld()), $this);
				$fang->spawnToAll();
			}

			// Outer ring: 8 fangs at radius 2.5
			for($i = 0; $i < 8; $i++){
				$angle = ($i * 2 * M_PI) / 8;
				$x = $this->location->x + 2.5 * cos($angle);
				$z = $this->location->z + 2.5 * sin($angle);
				$y = $this->getWorld()->getHighestBlockAt((int) floor($x), (int) floor($z)) ?? (int) $this->location->y;
				$fang = new EvocationFang(Location::fromObject(new Vector3($x, $y + 1, $z), $this->getWorld()), $this);
				$fang->spawnToAll();
			}
		}else{
			// Line formation towards target: 16 fangs spaced 1.25 blocks apart
			$dir = $targetPos->subtractVector($this->location)->normalize();
			for($i = 1; $i <= 16; $i++){
				$step = $i * 1.25;
				if($step > $dist + 2.0){
					break;
				}
				$pos = $this->location->add($dir->x * $step, 0, $dir->z * $step);
				$y = $this->getWorld()->getHighestBlockAt((int) floor($pos->x), (int) floor($pos->z)) ?? (int) $this->location->y;
				$fang = new EvocationFang(Location::fromObject(new Vector3($pos->x, $y + 1, $pos->z), $this->getWorld()), $this);
				$fang->spawnToAll();
			}
		}
	}

	private function castSummonVexes() : void{
		// Summon 3 Vexes around evoker
		for($i = 0; $i < 3; $i++){
			$angle = ($i * 2 * M_PI) / 3;
			$x = $this->location->x + 1.5 * cos($angle);
			$z = $this->location->z + 1.5 * sin($angle);
			$y = $this->location->y + 0.5;

			$vex = new Vex(Location::fromObject(new Vector3($x, $y, $z), $this->getWorld()));
			$vex->setDecayTimer(mt_rand(600, 2380)); // 30s to 119s matching BDS
			$vex->setCombatTarget($this->combatTarget);
			$vex->spawnToAll();
		}
	}

	private function castWololo() : void{
		if($this->wololoTarget !== null && $this->wololoTarget->isAlive()){
			if(method_exists($this->wololoTarget, "setColor")){
				$this->wololoTarget->setColor(DyeColor::RED);
			}
			$this->wololoCooldown = 200; // 10s cooldown
			$this->wololoTarget = null;
		}
	}

	private function findBlueSheep() : ?Living{
		$bb = $this->boundingBox->expandedCopy(16, 8, 16);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if($entity instanceof Living && $entity->isAlive() && $entity->getName() === "Sheep"){
				if(method_exists($entity, "getColor") && $entity->getColor() === DyeColor::BLUE){
					return $entity;
				}
			}
		}
		return null;
	}

	private function countNearbyVexes() : int{
		$count = 0;
		$bb = $this->boundingBox->expandedCopy(16, 16, 16);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if($entity instanceof Vex && $entity->isAlive()){
				$count++;
			}
		}
		return $count;
	}

	private function findTarget() : ?Living{
		$bestTarget = null;
		$bestDist = 32.0;

		$bb = $this->boundingBox->expandedCopy(32, 16, 32);
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$entity->isAlive() || $entity instanceof Raider || $entity instanceof Vex){
				continue;
			}
			if($entity instanceof Player || ($entity instanceof Villager && !$entity->isBaby()) || $entity->getName() === "IronGolem" || $entity->getName() === "WanderingTrader"){
				$d = $this->location->distance($entity->getLocation());
				if($d < $bestDist){
					$bestDist = $d;
					$bestTarget = $entity;
				}
			}
		}

		return $bestTarget;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && !$damager instanceof Raider && !$damager instanceof Vex){
				$this->combatTarget = $damager;
			}
		}
	}

	protected function onDeath() : void{
		parent::onDeath();

		$lastDamage = $this->getLastDamageCause();
		$killer = $lastDamage instanceof EntityDamageByEntityEvent ? $lastDamage->getDamager() : null;

		if($this->raid !== null){
			$this->raid->onRaiderDeath($this, $killer);
		}
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::TOTEM() // Guaranteed 1 Totem of Undying!
		];

		// 0-1 Emerald
		if(mt_rand(0, 1) === 1){
			$drops[] = VanillaItems::EMERALD();
		}

		if($this->raid !== null){
			foreach(RaiderDrops::getDrops() as $d){
				$drops[] = $d;
			}
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 10;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::TOTEM();
	}
}
