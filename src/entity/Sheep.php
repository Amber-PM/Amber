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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\item\Durable;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\sound\DyeUseSound;
use pocketmine\world\sound\PopSound;
use function floor;
use function min;
use function mt_rand;

class Sheep extends Animal{

	public const TAG_SHEARED = "Sheared";
	public const TAG_COLOR = "Color";

	public static function getNetworkTypeId() : string{
		return EntityIds::SHEEP;
	}

	protected DyeColor $color = DyeColor::WHITE;
	protected bool $sheared = false;
	protected int $eatGrassCooldown = 100;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9);
	}

	public function getName() : string{
		return "Sheep";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(8);
		$this->setHealth(8.0);
	}

	public function getColor() : DyeColor{
		return $this->color;
	}

	public function setColor(DyeColor $color) : void{
		$this->color = $color;
		$this->networkPropertiesDirty = true;
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function setSheared(bool $sheared = true) : void{
		$this->sheared = $sheared;
		$this->networkPropertiesDirty = true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if(($shearedTag = $nbt->getTag(self::TAG_SHEARED)) instanceof ByteTag){
			$this->sheared = $shearedTag->getValue() !== 0;
		}

		if(($colorTag = $nbt->getTag(self::TAG_COLOR)) instanceof ByteTag){
			$this->color = DyeColorIdMap::getInstance()->fromId($colorTag->getValue()) ?? DyeColor::WHITE;
		}else{
			$this->color = self::getRandomColor();
		}
	}

	public static function getRandomColor() : DyeColor{
		$roll = mt_rand(0, 9999);
		if($roll < 8184){
			return DyeColor::WHITE;
		}
		if($roll < 8684){
			return DyeColor::BLACK;
		}
		if($roll < 9184){
			return DyeColor::GRAY;
		}
		if($roll < 9684){
			return DyeColor::LIGHT_GRAY;
		}
		if($roll < 9984){
			return DyeColor::BROWN;
		}
		return DyeColor::PINK;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SHEARED, $this->sheared ? 1 : 0);
		$nbt->setByte(self::TAG_COLOR, DyeColorIdMap::getInstance()->toId($this->color));
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
		$properties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($this->color));
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::WHEAT;
	}

	public function onInteract(Player $player, Vector3 $clickVector) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Shearing interaction
		if($item instanceof Durable && $item->getTypeId() === ItemTypeIds::SHEARS && !$this->isSheared() && !$this->isBaby()){
			$this->shear();
			if($player->hasFiniteResources()){
				$item->applyDamage(1);
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}

		// Dyeing interaction
		if($item instanceof Dye && !$this->isSheared()){
			$this->setColor($item->getColor());
			if($player->hasFiniteResources()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			$this->getWorld()->addSound($this->location, new DyeUseSound());
			return true;
		}

		return parent::onInteract($player, $clickVector);
	}

	public function shear() : void{
		$this->setSheared(true);
		$wool = VanillaBlocks::WOOL()->setColor($this->color)->asItem()->setCount(mt_rand(1, 3));
		$this->getWorld()->dropItem($this->location, $wool);
		$this->getWorld()->addSound($this->location, new PopSound());
	}

	public function eatGrass() : bool{
		$pos = $this->location->floor();
		$world = $this->getWorld();

		// Block under sheep
		$underPos = $pos->down();
		$underBlock = $world->getBlock($underPos);
		if($underBlock->getTypeId() === BlockTypeIds::GRASS){
			$world->setBlock($underPos, VanillaBlocks::DIRT());
			$this->onAteGrass();
			return true;
		}

		// Block at sheep feet (tallgrass, fern, etc.)
		$feetBlock = $world->getBlock($pos);
		if($feetBlock->getTypeId() === BlockTypeIds::TALL_GRASS || $feetBlock->getTypeId() === BlockTypeIds::FERN){
			$world->setBlock($pos, VanillaBlocks::AIR());
			$this->onAteGrass();
			return true;
		}

		return false;
	}

	protected function onAteGrass() : void{
		if($this->isSheared()){
			$this->setSheared(false);
		}
		if($this->isBaby()){
			$this->setAge(min(0, $this->age + 1200));
		}
		$this->getWorld()->addSound($this->location, new PopSound());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->eatGrassCooldown <= 0){
			if(mt_rand(0, $this->isBaby() ? 50 : 1000) === 0){
				$this->eatGrass();
				$this->eatGrassCooldown = mt_rand(100, 300);
			}
		}else{
			$this->eatGrassCooldown -= $tickDiff;
		}

		return $hasUpdate;
	}

	public function breed(Animal $partner) : ?Animal{
		$baby = parent::breed($partner);
		if($baby instanceof Sheep && $partner instanceof Sheep){
			$baby->setColor(mt_rand(0, 1) === 0 ? $this->color : $partner->getColor());
		}
		return $baby;
	}

	public function getDrops() : array{
		$drops = [];
		if(!$this->sheared){
			$drops[] = VanillaBlocks::WOOL()->setColor($this->color)->asItem();
		}
		$drops[] = ($this->isOnFire() ? VanillaItems::COOKED_MUTTON() : VanillaItems::RAW_MUTTON())->setCount(mt_rand(1, 2));
		return $drops;
	}
}
