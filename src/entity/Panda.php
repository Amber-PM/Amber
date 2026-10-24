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

use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use function max;
use function min;
use function mt_rand;

class Panda extends Living implements Ageable{

	public const GENE_NORMAL = 0;
	public const GENE_LAZY = 1;
	public const GENE_WORRIED = 2;
	public const GENE_PLAYFUL = 3;
	public const GENE_BROWN = 4;
	public const GENE_WEAK = 5;
	public const GENE_AGGRESSIVE = 6;

	public const TAG_VARIANT = "Variant";
	public const TAG_MARK_VARIANT = "MarkVariant";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected int $mainGene = self::GENE_NORMAL;
	protected int $hiddenGene = self::GENE_NORMAL;
	protected int $eatingTicks = 0;
	protected int $rollingTicks = 0;
	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::PANDA;
	}

	public function getName() : string{
		return "Panda";
	}

	public function getDefaultMaxHealth() : int{
		return $this->getExpressedGene() === self::GENE_WEAK ? 10 : 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.52, 0.5) : new EntitySizeInfo(1.3, 1.25);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->mainGene = $nbt->getInt(self::TAG_VARIANT, self::GENE_NORMAL);
		$this->hiddenGene = $nbt->getInt(self::TAG_MARK_VARIANT, self::GENE_NORMAL);
		if($this->mainGene < 0 || $this->mainGene > 6){
			$this->mainGene = self::GENE_NORMAL;
		}
		if($this->hiddenGene < 0 || $this->hiddenGene > 6){
			$this->hiddenGene = self::GENE_NORMAL;
		}

		$maxHealth = $this->getExpressedGene() === self::GENE_WEAK ? 10 : 20;
		$this->setMaxHealth($maxHealth);
		if($nbt->getTag("Health") === null){
			$this->setHealth((float) $maxHealth);
		}

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->mainGene);
		$nbt->setInt(self::TAG_MARK_VARIANT, $this->hiddenGene);
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	public function getExpressedGene() : int{
		// Brown (4) and Weak (5) are recessive
		if($this->mainGene === self::GENE_BROWN || $this->mainGene === self::GENE_WEAK){
			if($this->mainGene === $this->hiddenGene){
				return $this->mainGene;
			}
			// If hidden gene is dominant, express hidden gene, else normal
			if($this->hiddenGene !== self::GENE_BROWN && $this->hiddenGene !== self::GENE_WEAK){
				return $this->hiddenGene;
			}
			return self::GENE_NORMAL;
		}
		return $this->mainGene;
	}

	public function getMainGene() : int{
		return $this->mainGene;
	}

	public function setMainGene(int $gene) : void{
		$this->mainGene = $gene;
		$this->networkPropertiesDirty = true;
	}

	public function getHiddenGene() : int{
		return $this->hiddenGene;
	}

	public function setHiddenGene(int $gene) : void{
		$this->hiddenGene = $gene;
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->getExpressedGene());
		$properties->setInt(EntityMetadataProperties::MARK_VARIANT, $this->hiddenGene);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->eatingTicks > 0);
		$properties->setGenericFlag(EntityMetadataFlags::EATING, $this->eatingTicks > 0);
		$properties->setGenericFlag(EntityMetadataFlags::ROLLING, $this->rollingTicks > 0);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby) : void{
		$wasBaby = $this->isBaby();
		$this->age = $baby ? -24000 : 0;
		if($wasBaby !== $baby){
			$this->setScale($baby ? 0.4 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$wasBaby = $this->isBaby();
		$this->age = $age;
		if($wasBaby !== $this->isBaby()){
			$this->setScale($this->isBaby() ? 0.4 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function isEating() : bool{
		return $this->eatingTicks > 0;
	}

	public function setEating(int $ticks = 60) : void{
		$this->eatingTicks = $ticks;
		$this->networkPropertiesDirty = true;
	}

	public function isRolling() : bool{
		return $this->rollingTicks > 0;
	}

	public function setRolling(int $ticks = 30) : void{
		$this->rollingTicks = $ticks;
		$this->networkPropertiesDirty = true;
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::BAMBOO;
	}

	public function canBreed() : bool{
		return !$this->isBaby() && $this->inLoveTicks <= 0 && $this->breedingCooldownTicks <= 0;
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	public function setInLove(int $ticks = 600) : void{
		$this->inLoveTicks = $ticks;
	}

	public function getBreedingCooldownTicks() : int{
		return $this->breedingCooldownTicks;
	}

	public function setBreedingCooldownTicks(int $ticks) : void{
		$this->breedingCooldownTicks = $ticks;
	}

	public function countNearbyBambooBlocks(int $radius = 5) : int{
		$count = 0;
		$minX = (int) ($this->location->x - $radius);
		$maxX = (int) ($this->location->x + $radius);
		$minY = (int) max(0, $this->location->y - $radius);
		$maxY = (int) min(255, $this->location->y + $radius);
		$minZ = (int) ($this->location->z - $radius);
		$maxZ = (int) ($this->location->z + $radius);

		$world = $this->getWorld();
		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				for($y = $minY; $y <= $maxY; ++$y){
					$block = $world->getBlockAt($x, $y, $z);
					if($block->getName() === "Bamboo" || $block->getName() === "Bamboo Sapling"){
						$count++;
						if($count >= 8){
							return $count;
						}
					}
				}
			}
		}
		return $count;
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Bamboo or Cake feeding
		$isBamboo = $item->getTypeId() === ItemTypeIds::BAMBOO;
		$isCake = $item->getTypeId() === VanillaBlocks::CAKE()->asItem()->getTypeId();

		if($isBamboo || $isCake){
			$healAmount = $isCake ? 5.0 : 2.0;

			if($this->isBaby()){
				$this->age = (int) min(0, $this->age + ($isCake ? 6000 : 2400));
				$this->heal(new EntityRegainHealthEvent($this, $healAmount, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->setEating(60);
				$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HappyVillagerParticle());
				return true;
			}

			if($this->getHealth() < $this->getMaxHealth()){
				$this->heal(new EntityRegainHealthEvent($this, $healAmount, EntityRegainHealthEvent::CAUSE_EATING));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->setEating(60);
				$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HeartParticle());
				return true;
			}

			if($isBamboo && $this->canBreed()){
				// Bedrock parity: Pandas only breed if at least 8 bamboo blocks are within 5 blocks
				if($this->countNearbyBambooBlocks(5) >= 8){
					$this->setInLove();
					$this->getWorld()->addParticle($this->location->add(0, 0.7, 0), new HeartParticle());
				}
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				$this->setEating(60);
				return true;
			}

			// Just snack
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setEating(60);
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->age < 0){
			$wasBaby = $this->isBaby();
			$this->age += $tickDiff;
			if($wasBaby && !$this->isBaby()){
				$this->setScale(1.0);
				$this->networkPropertiesDirty = true;
			}
			$hasUpdate = true;
		}

		if($this->eatingTicks > 0){
			$this->eatingTicks = max(0, $this->eatingTicks - $tickDiff);
			if($this->eatingTicks === 0){
				$this->networkPropertiesDirty = true;
			}
			$hasUpdate = true;
		}

		if($this->rollingTicks > 0){
			$this->rollingTicks = max(0, $this->rollingTicks - $tickDiff);
			if($this->rollingTicks === 0){
				$this->networkPropertiesDirty = true;
			}
			$hasUpdate = true;
		}

		// Playful rolling
		if($this->getExpressedGene() === self::GENE_PLAYFUL && $this->rollingTicks <= 0 && $this->eatingTicks <= 0){
			if(mt_rand(1, 400) === 1){
				$this->setRolling(40);
			}
		}

		// Baby sneeze mechanic
		if($this->isBaby()){
			$chance = $this->getExpressedGene() === self::GENE_WEAK ? 2000 : 6000;
			if(mt_rand(1, $chance) <= $tickDiff){
				$this->sneeze();
			}
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
			}

			if(!$this->isBaby() && $this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof Panda && !$nearby->isBaby() && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
						$this->breedWith($nearby);
						break;
					}
				}
			}
			$hasUpdate = true;
		}

		if($this->breedingCooldownTicks > 0){
			$this->breedingCooldownTicks = max(0, $this->breedingCooldownTicks - $tickDiff);
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function sneeze() : void{
		// 0.1% chance (1 in 1000) to drop a slimeball
		if(mt_rand(1, 1000) === 1){
			$this->getWorld()->dropItem($this->location, VanillaItems::SLIMEBALL());
		}

		// Nearby adult pandas get startled
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(10, 5, 10), $this) as $nearby){
			if($nearby instanceof Panda && !$nearby->isBaby()){
				$nearby->jump();
			}
		}
	}

	public function breedWith(Panda $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		$baby = new Panda(Location::fromObject($this->location, $this->getWorld()));
		$baby->setBaby(true);

		// Mendelian genetics with 3.125% mutation rate
		$allele1 = mt_rand(0, 1) === 0 ? $this->mainGene : $this->hiddenGene;
		$allele2 = mt_rand(0, 1) === 0 ? $partner->mainGene : $partner->hiddenGene;

		if(mt_rand(1, 32) === 1){ // 3.125% mutation
			$allele1 = mt_rand(0, 6);
		}
		if(mt_rand(1, 32) === 1){
			$allele2 = mt_rand(0, 6);
		}

		$baby->setMainGene($allele1);
		$baby->setHiddenGene($allele2);
		$baby->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 1.0, 0), new HeartParticle());
	}
}
