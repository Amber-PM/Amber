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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\world\sound\PopSound;
use function max;
use function min;
use function mt_rand;

class Shulker extends Living{

	public const TAG_PEEK = "Peek";
	public const TAG_ATTACH_FACE = "AttachFace";

	protected int $peek = 0; // 0 = closed, 100 = open
	protected int $attachFace = Facing::DOWN;

	public static function getNetworkTypeId() : string{
		return EntityIds::SHULKER;
	}

	public function getName() : string{
		return "Shulker";
	}

	public function getDefaultMaxHealth() : int{
		return 30;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.0, 1.0);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(30);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth(30.0);
		}

		$this->peek = $nbt->getByte(self::TAG_PEEK, 0);
		$this->attachFace = $nbt->getByte(self::TAG_ATTACH_FACE, Facing::DOWN);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_PEEK, $this->peek);
		$nbt->setByte(self::TAG_ATTACH_FACE, $this->attachFace);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setByte(EntityMetadataProperties::SHULKER_PEEK_ID, $this->peek);
		$properties->setByte(EntityMetadataProperties::SHULKER_ATTACH_FACE, $this->attachFace);
	}

	public function getPeek() : int{
		return $this->peek;
	}

	public function setPeek(int $peek) : void{
		$this->peek = max(0, min(100, $peek));
		$this->networkPropertiesDirty = true;
	}

	public function isClosedShell() : bool{
		return $this->peek === 0;
	}

	public function getAttachFace() : int{
		return $this->attachFace;
	}

	public function setAttachFace(int $face) : void{
		$this->attachFace = $face;
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Fire and lava immunity
		if(
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE ||
			$source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$source->getCause() === EntityDamageEvent::CAUSE_LAVA
		){
			$source->cancel();
			return;
		}

		// When shell is closed, takes 80% reduced damage (+20 armor)
		if($this->isClosedShell()){
			$source->setBaseDamage($source->getBaseDamage() * 0.2);
		}

		parent::attack($source);

		if(!$source->isCancelled() && mt_rand(0, 99) < 25){
			$this->teleportRandomly();
		}
	}

	public function teleportRandomly() : bool{
		$world = $this->getWorld();
		for($attempt = 0; $attempt < 16; ++$attempt){
			$x = (int) ($this->location->x + mt_rand(-16, 16));
			$y = (int) max(1, min($world->getMaxY() - 2, $this->location->y + mt_rand(-8, 8)));
			$z = (int) ($this->location->z + mt_rand(-16, 16));

			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->isSolid()){
				$this->teleport(new Vector3($x + 0.5, $y, $z + 0.5));
				$this->broadcastSound(new PopSound());
				return true;
			}
		}

		return false;
	}

	public function getDrops() : array{
		return [
			VanillaItems::SHULKER_SHELL()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
