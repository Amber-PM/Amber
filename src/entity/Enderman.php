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

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\EndermanTeleportSound;
use function acos;
use function max;
use function mt_rand;

class Enderman extends Living{

	public const TAG_ANGRY = "Angry";
	public const TAG_CARRIED_BLOCK = "carriedBlock";
	public const TAG_CARRIED_BLOCK_DATA = "carriedBlockData";

	protected bool $angry = false;
	protected int $angryTicks = 0;
	protected ?Block $carriedBlock = null;

	public static function getNetworkTypeId() : string{
		return EntityIds::ENDERMAN;
	}

	public function getName() : string{
		return "Enderman";
	}

	public function getDefaultMaxHealth() : int{
		return 40;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(40);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(40.0);
		}

		$this->angry = $nbt->getByte(self::TAG_ANGRY, 0) !== 0;
		if($this->angry){
			$this->angryTicks = 500; // 25 seconds in BDS
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_ANGRY, $this->angry ? 1 : 0);
		if($this->carriedBlock !== null){
			$nbt->setShort(self::TAG_CARRIED_BLOCK, (int) $this->carriedBlock->getTypeId());
		}
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->angry);
	}

	public function isAngry() : bool{
		return $this->angry;
	}

	public function setAngry(bool $angry = true) : void{
		$this->angry = $angry;
		$this->angryTicks = $angry ? 500 : 0;
		$this->networkPropertiesDirty = true;
	}

	public function getCarriedBlock() : ?Block{
		return $this->carriedBlock;
	}

	public function setCarriedBlock(?Block $block) : void{
		$this->carriedBlock = $block;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Projectile evasion: Endermen never take projectile damage; they teleport away immediately!
		if($source instanceof EntityDamageByChildEntityEvent || $source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
			$source->cancel();
			$this->teleportRandomly();
			return;
		}

		parent::attack($source);

		if(!$source->isCancelled()){
			if($source instanceof EntityDamageByEntityEvent){
				$damager = $source->getDamager();
				if($damager instanceof Player){
					$this->setAngry(true);
				}
			}
			// Chance to teleport away when damaged
			if(mt_rand(0, 99) < 25){
				$this->teleportRandomly();
			}
		}
	}

	public function teleportRandomly() : bool{
		$world = $this->getWorld();
		for($attempt = 0; $attempt < 16; ++$attempt){
			$x = $this->location->x + mt_rand(-16, 16);
			$y = (int) max(1.0, min((float) $world->getMaxY() - 3.0, $this->location->y + mt_rand(-8, 8)));
			$z = $this->location->z + mt_rand(-16, 16);

			$targetPos = new Vector3($x + 0.5, $y, $z + 0.5);

			// Check block at feet and head are air/passable, block below is solid
			$blockBelow = $world->getBlockAt((int) $x, $y - 1, (int) $z);
			$blockFeet = $world->getBlockAt((int) $x, $y, (int) $z);
			$blockHead = $world->getBlockAt((int) $x, $y + 1, (int) $z);

			if($blockBelow->isSolid() && !$blockFeet->isSolid() && !$blockHead->isSolid() && $blockFeet->getTypeId() !== BlockTypeIds::WATER && $blockBelow->getTypeId() !== BlockTypeIds::WATER){
				$this->broadcastSound(new EndermanTeleportSound());
				$this->teleport($targetPos);
				$this->broadcastSound(new EndermanTeleportSound());
				return true;
			}
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		// Water contact damages Enderman and forces a teleport
		if($this->isUnderwater()){
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 1.0);
			$this->attack($ev);
			$this->teleportRandomly();
			return true;
		}

		// Anger timer
		if($this->angry){
			$this->angryTicks -= $tickDiff;
			if($this->angryTicks <= 0){
				$this->setAngry(false);
			}
		}

		// Stare detection: scan players within 64 blocks
		if(!$this->angry){
			$headPos = $this->location->add(0, 2.7, 0);
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(64, 32, 64), $this) as $nearby){
				if($nearby instanceof Player && $nearby->isAlive() && !$nearby->isCreative() && !$nearby->isSpectator()){
					// Check if player wears carved pumpkin
					$helmet = $nearby->getArmorInventory()->getHelmet();
					if($helmet->getTypeId() === VanillaBlocks::CARVED_PUMPKIN()->asItem()->getTypeId()){
						continue;
					}

					// Check if player is looking at the enderman
					$playerEye = $nearby->getEyePos();
					$direction = $headPos->subtractVector($playerEye)->normalize();
					$lookVector = $nearby->getDirectionVector()->normalize();

					$dot = $lookVector->dot($direction);
					// If looking directly at Enderman (within ~15 degrees, dot product > 0.96)
					if($dot > 0.96){
						$this->setAngry(true);
						break;
					}
				}
			}
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [
			VanillaItems::ENDER_PEARL()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
