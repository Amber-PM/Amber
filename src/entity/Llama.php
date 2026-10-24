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

use pocketmine\block\Carpet;
use pocketmine\block\ItemBlock;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\projectile\LlamaSpit;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\inventory\LlamaInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\sound\PopSound;
use function max;
use function min;
use function mt_rand;

class Llama extends Living implements Ageable{

	public const VARIANT_CREAMY = 0;
	public const VARIANT_WHITE = 1;
	public const VARIANT_BROWN = 2;
	public const VARIANT_GRAY = 3;

	public const TAG_VARIANT = "Variant";
	public const TAG_STRENGTH = "Strength";
	public const TAG_TEMPER = "Temper";
	public const TAG_TAMED = "Tamed";
	public const TAG_CHESTED = "Chested";
	public const TAG_CARPET = "Carpet";
	public const TAG_INVENTORY = "Inventory";
	public const TAG_AGE = "Age";
	public const TAG_IN_LOVE = "InLove";
	public const TAG_BREEDING_COOLDOWN = "BreedingCooldown";
	public const BREEDING_COOLDOWN_TICKS = 6000;

	protected int $variant = self::VARIANT_CREAMY;
	protected int $strength = 1;
	protected int $temper = 0;
	protected int $maxTemper = 100;
	protected bool $tamed = false;
	protected bool $chested = false;
	protected ?DyeColor $carpetColor = null;
	protected ?Item $carpetItem = null;
	protected ?LlamaInventory $inventory = null;

	protected int $age = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldownTicks = 0;
	protected int $spitCooldownTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::LLAMA;
	}

	public function getName() : string{
		return "Llama";
	}

	public function getDefaultMaxHealth() : int{
		return 20;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ? new EntitySizeInfo(0.935, 0.45) : new EntitySizeInfo(1.87, 0.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$health = mt_rand(15, 30);
		$this->setMaxHealth($health);
		parent::initEntity($nbt);
		if($nbt->getTag("Health") === null){
			$this->setHealth((float) $this->getMaxHealth());
		}

		$this->variant = $nbt->getInt(self::TAG_VARIANT, self::VARIANT_CREAMY);
		if($this->variant < 0 || $this->variant > 3){
			$this->variant = self::VARIANT_CREAMY;
		}

		$this->strength = $nbt->getInt(self::TAG_STRENGTH, mt_rand(1, 5));
		if($this->strength < 1 || $this->strength > 5){
			$this->strength = 1;
		}

		$this->temper = $nbt->getInt(self::TAG_TEMPER, 0);
		$this->tamed = $nbt->getByte(self::TAG_TAMED, 0) !== 0;
		$this->chested = $nbt->getByte(self::TAG_CHESTED, 0) !== 0;

		$carpetTag = $nbt->getCompoundTag(self::TAG_CARPET);
		if($carpetTag !== null){
			$this->carpetItem = Item::nbtDeserialize($carpetTag);
			$block = $this->carpetItem->getBlock();
			if($block instanceof Carpet){
				$this->carpetColor = $block->getColor();
			}
		}

		if($this->chested){
			$this->inventory = new LlamaInventory($this, $this->getInventorySize());
			$invTag = $nbt->getListTag(self::TAG_INVENTORY);
			if($invTag !== null){
				/** @var CompoundTag $itemTag */
				foreach($invTag as $itemTag){
					$this->inventory->setItem($itemTag->getByte("Slot"), Item::nbtDeserialize($itemTag));
				}
			}
		}

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldownTicks = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_VARIANT, $this->variant);
		$nbt->setInt(self::TAG_STRENGTH, $this->strength);
		$nbt->setInt(self::TAG_TEMPER, $this->temper);
		$nbt->setByte(self::TAG_TAMED, $this->tamed ? 1 : 0);
		$nbt->setByte(self::TAG_CHESTED, $this->chested ? 1 : 0);

		if($this->carpetItem !== null){
			$nbt->setTag(self::TAG_CARPET, $this->carpetItem->nbtSerialize());
		}

		if($this->inventory !== null){
			$invTag = new ListTag();
			foreach($this->inventory->getContents() as $slot => $item){
				$invTag->push($item->nbtSerialize($slot));
			}
			$nbt->setTag(self::TAG_INVENTORY, $invTag);
		}

		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldownTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		$properties->setInt(EntityMetadataProperties::STRENGTH, $this->strength);
		$properties->setInt(EntityMetadataProperties::MAX_STRENGTH, 5);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->carpetColor !== null ? DyeColorIdMap::getInstance()->toId($this->carpetColor) : 255);
		$properties->setGenericFlag(EntityMetadataFlags::CHESTED, $this->chested);
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby) : void{
		$wasBaby = $this->isBaby();
		$this->age = $baby ? -24000 : 0;
		if($wasBaby !== $baby){
			$this->setScale($baby ? 0.5 : 1.0);
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
			$this->setScale($this->isBaby() ? 0.5 : 1.0);
			$this->networkPropertiesDirty = true;
		}
	}

	public function getVariant() : int{
		return $this->variant;
	}

	public function setVariant(int $variant) : void{
		if($variant < 0 || $variant > 3){
			throw new \InvalidArgumentException("Invalid llama variant $variant");
		}
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;
	}

	public function getStrength() : int{
		return $this->strength;
	}

	public function setStrength(int $strength) : void{
		$this->strength = max(1, min(5, $strength));
		$this->networkPropertiesDirty = true;
	}

	public function getInventorySize() : int{
		return 3 * $this->strength;
	}

	public function getTemper() : int{
		return $this->temper;
	}

	public function setTemper(int $temper) : void{
		$this->temper = max(0, min($this->maxTemper, $temper));
	}

	public function isTamed() : bool{
		return $this->tamed;
	}

	public function setTamed(bool $tamed) : void{
		$this->tamed = $tamed;
		$this->networkPropertiesDirty = true;
	}

	public function isChested() : bool{
		return $this->chested;
	}

	public function setChested(bool $chested) : void{
		$this->chested = $chested;
		if($chested && $this->inventory === null){
			$this->inventory = new LlamaInventory($this, $this->getInventorySize());
		}elseif(!$chested){
			$this->inventory = null;
		}
		$this->networkPropertiesDirty = true;
	}

	public function getCarpetColor() : ?DyeColor{
		return $this->carpetColor;
	}

	public function getCarpetItem() : ?Item{
		return $this->carpetItem !== null ? clone $this->carpetItem : null;
	}

	public function setCarpet(?Item $carpet) : void{
		if($carpet !== null){
			$block = $carpet->getBlock();
			if($block instanceof Carpet){
				$this->carpetItem = clone $carpet;
				$this->carpetItem->setCount(1);
				$this->carpetColor = $block->getColor();
			}else{
				throw new \InvalidArgumentException("Item must be a carpet");
			}
		}else{
			$this->carpetItem = null;
			$this->carpetColor = null;
		}
		$this->networkPropertiesDirty = true;
	}

	public function getInventory() : ?LlamaInventory{
		return $this->inventory;
	}

	public function canBreed() : bool{
		return $this->tamed && !$this->isBaby() && $this->inLoveTicks <= 0 && $this->breedingCooldownTicks <= 0;
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

	public function spit(Entity $target) : void{
		if($this->spitCooldownTicks > 0){
			return;
		}
		$this->spitCooldownTicks = 40; // 2 second cooldown

		$targetPos = $target->getLocation();
		$diff = $targetPos->subtractVector($this->location);
		$diff->y += $target->getEyeHeight() * 0.5 - 0.1;
		$dist = $diff->length();
		if($dist <= 0){
			return;
		}

		$dir = $diff->divide($dist);

		$spit = new LlamaSpit(Location::fromObject($this->location->add(0, $this->getEyeHeight(), 0), $this->getWorld()), $this);
		$spit->setMotion($dir->multiply(1.5));
		$spit->spawnToAll();
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if(!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager !== null && $damager->isAlive()){
				$this->spit($damager);
			}
		}
	}

	public function interact(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Opening chested inventory when sneaking
		if($player->isSneaking() && $this->chested && $this->inventory !== null){
			$player->setCurrentWindow($this->inventory);
			return true;
		}

		// Wheat feeding
		if($item->getTypeId() === ItemTypeIds::WHEAT){
			$this->heal(new EntityRegainHealthEvent($this, 2.0, EntityRegainHealthEvent::CAUSE_EATING));
			$this->temper = min($this->maxTemper, $this->temper + 3);
			if($this->isBaby()){
				$this->age = (int) min(0, $this->age + 2400);
			}
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HappyVillagerParticle());
			return true;
		}

		// Hay Bale feeding (breeding or big temper boost)
		if($item->getTypeId() === VanillaBlocks::HAY_BALE()->asItem()->getTypeId()){
			$this->heal(new EntityRegainHealthEvent($this, 10.0, EntityRegainHealthEvent::CAUSE_EATING));
			$this->temper = min($this->maxTemper, $this->temper + 10);
			if($this->isBaby()){
				$this->age = (int) min(0, $this->age + 6000);
			}
			$item->pop();
			$player->getInventory()->setItemInHand($item);

			if($this->canBreed()){
				$this->setInLove();
				$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle());
			}else{
				$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HappyVillagerParticle());
			}
			return true;
		}

		// Chest attaching
		if(!$this->chested && $item->getTypeId() === VanillaBlocks::CHEST()->asItem()->getTypeId()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setChested(true);
			return true;
		}

		// Carpet decoration
		$block = $item->getBlock();
		if($block instanceof Carpet){
			if($this->carpetItem !== null){
				// Drop existing carpet
				$this->getWorld()->dropItem($this->location, $this->carpetItem);
			}
			$carpetItem = $item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setCarpet($carpetItem);
			return true;
		}

		// Shearing carpet
		if($this->carpetItem !== null && $item->getTypeId() === ItemTypeIds::SHEARS){
			$this->getWorld()->dropItem($this->location, $this->carpetItem);
			$this->setCarpet(null);
			$this->getWorld()->addSound($this->location, new PopSound());
			return true;
		}

		// Taming attempt if not yet tamed
		if(!$this->tamed && !$this->isBaby()){
			$chance = $this->temper >= 100 ? 100 : max(10, (int) ($this->temper * 0.8));
			if(mt_rand(1, 100) <= $chance){
				$this->setTamed(true);
				$this->getWorld()->addParticle($this->location->add(0, 1.5, 0), new HeartParticle());
			}else{
				$this->temper = min($this->maxTemper, $this->temper + 5);
				$this->getWorld()->addParticle($this->location->add(0, 1.5, 0), new SmokeParticle());
			}
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

		if($this->spitCooldownTicks > 0){
			$this->spitCooldownTicks = max(0, $this->spitCooldownTicks - $tickDiff);
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks = max(0, $this->inLoveTicks - $tickDiff);
			if($this->inLoveTicks % 20 === 0 && $this->inLoveTicks > 0){
				$this->getWorld()->addParticle($this->location->add(0, 1.5, 0), new HeartParticle());
			}

			if(!$this->isBaby() && $this->inLoveTicks > 0){
				foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(8, 8, 8), $this) as $nearby){
					if($nearby instanceof Llama && !$nearby->isBaby() && $nearby->isInLove() && $nearby->getId() !== $this->getId()){
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

		// Spit at nearby hostile wolves
		if($this->spitCooldownTicks <= 0 && !$this->isBaby()){
			foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(10, 5, 10), $this) as $nearby){
				if($nearby instanceof Wolf && $nearby->isAngry()){
					$this->spit($nearby);
					break;
				}
			}
		}

		return $hasUpdate;
	}

	public function breedWith(Llama $partner) : void{
		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;
		$partner->breedingCooldownTicks = self::BREEDING_COOLDOWN_TICKS;

		$baby = new Llama(Location::fromObject($this->location, $this->getWorld()));
		$baby->setBaby(true);

		// Variant from parents
		$baby->setVariant(mt_rand(0, 1) === 0 ? $this->variant : $partner->variant);

		// Strength inheritance: 1 to 5 with small chance to be stronger/weaker
		$baseStrength = mt_rand(0, 1) === 0 ? $this->strength : $partner->strength;
		$rand = mt_rand(1, 100);
		if($rand <= 10 && $baseStrength < 5){
			$baseStrength++;
		}elseif($rand <= 20 && $baseStrength > 1){
			$baseStrength--;
		}
		$baby->setStrength($baseStrength);
		$baby->spawnToAll();

		$this->getWorld()->addParticle($this->location->add(0, 1.5, 0), new HeartParticle());
		$this->getWorld()->addParticle($partner->location->add(0, 1.5, 0), new HeartParticle());
	}

	public function getDrops() : array{
		$drops = [];
		if(!$this->isBaby()){
			$leatherCount = mt_rand(0, 2);
			if($leatherCount > 0){
				$drops[] = VanillaItems::LEATHER()->setCount($leatherCount);
			}
			if($this->chested){
				$drops[] = VanillaBlocks::CHEST()->asItem();
				if($this->inventory !== null){
					foreach($this->inventory->getContents() as $item){
						$drops[] = clone $item;
					}
				}
			}
			if($this->carpetItem !== null){
				$drops[] = clone $this->carpetItem;
			}
		}
		return $drops;
	}
}
