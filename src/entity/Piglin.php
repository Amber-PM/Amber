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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use function array_column;
use function array_sum;
use function max;
use function min;
use function mt_rand;

class Piglin extends Living{
	use HostilePlayerTargeting { canTargetPlayer as private canTargetOrdinaryPlayer; }

	public const TAG_ADMIRING = "Admiring";
	public const TAG_ADMIRING_TICKS = "AdmiringTicks";
	public const BARTER_DURATION_TICKS = 120; // 6 seconds in BDS

	// Bedrock piglin.json: angry.duration (30 seconds) and follow_range (64 blocks).
	private const RETALIATION_TICKS = 600;
	private const RETALIATION_RANGE = 64;

	protected bool $admiring = false;
	protected int $admiringTicks = 0;
	private ?int $barterStartedAt = null;
	private int $meleeCooldown = 0;
	private ?int $retaliationTargetId = null;
	private int $retaliationUntilTick = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::PIGLIN;
	}

	public function getName() : string{
		return "Piglin";
	}

	public function getDefaultMaxHealth() : int{
		return 16;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(16);
		parent::initEntity($nbt);

		$this->admiring = $nbt->getByte(self::TAG_ADMIRING, 0) !== 0;
		$this->admiringTicks = $nbt->getInt(self::TAG_ADMIRING_TICKS, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_ADMIRING, $this->admiring ? 1 : 0);
		$nbt->setInt(self::TAG_ADMIRING_TICKS, $this->admiringTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ADMIRING, $this->admiring);
	}

	public function isAdmiring() : bool{
		return $this->admiring;
	}

	public function startBartering() : void{
		$this->admiring = true;
		$this->admiringTicks = self::BARTER_DURATION_TICKS;
		$this->barterStartedAt = $this->server->getTick();
		$this->scheduleUpdate();
		$this->networkPropertiesDirty = true;
	}

	public function finishBartering() : void{
		$this->admiring = false;
		$this->admiringTicks = 0;
		$this->barterStartedAt = null;
		$this->networkPropertiesDirty = true;

		// Drop barter loot
		$loot = $this->getRandomBarterLoot();
		// The ingot is spent on acceptance, even if a plugin cancels the payout.
		if($this->getWorld()->dropItem($this->location, $loot) !== null){
			$this->getWorld()->addParticle($this->location->add(0, 1.5, 0), new HappyVillagerParticle());
		}
	}

	public function isPacifiedBy(Player $player) : bool{
		// Player wearing any piece of gold armor pacifies Piglin
		foreach($player->getArmorInventory()->getContents() as $armor){
			$id = $armor->getTypeId();
			if(
				$id === ItemTypeIds::GOLDEN_HELMET ||
				$id === ItemTypeIds::GOLDEN_CHESTPLATE ||
				$id === ItemTypeIds::GOLDEN_LEGGINGS ||
				$id === ItemTypeIds::GOLDEN_BOOTS
			){
				return true;
			}
		}
		return false;
	}

	protected function canTargetPlayer(Player $player) : bool{
		return $this->canTargetOrdinaryPlayer($player) && !$this->isPacifiedBy($player);
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$this->isAlive() || $this->isClosed() || $source->isCancelled() || !$source instanceof EntityDamageByEntityEvent){
			return;
		}

		$attacker = $source->getDamager();
		if($attacker instanceof Player && $this->canRetaliateAgainst($attacker)){
			$this->retaliationTargetId = $attacker->getId();
			$this->retaliationUntilTick = $this->server->getTick() + self::RETALIATION_TICKS;
			$this->setTargetEntity($attacker);
			$this->scheduleUpdate();
		}
	}

	private function canRetaliateAgainst(Player $player) : bool{
		return $this->canTargetOrdinaryPlayer($player) &&
			$this->location->distanceSquared($player->getLocation()) <= self::RETALIATION_RANGE ** 2;
	}

	private function getRetaliationTarget() : ?Player{
		if($this->retaliationTargetId === null){
			return null;
		}
		if($this->lastUpdate < $this->retaliationUntilTick){
			$target = $this->getWorld()->getEntity($this->retaliationTargetId);
			if($target instanceof Player && $this->canRetaliateAgainst($target)){
				if($this->getTargetEntityId() !== $this->retaliationTargetId){
					$this->setTargetEntity($target);
				}
				return $target;
			}
		}
		if($this->getTargetEntityId() === $this->retaliationTargetId){
			$this->setTargetEntity(null);
		}
		$this->retaliationTargetId = null;
		return null;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Barter with Gold Ingot
		if($item->getTypeId() === ItemTypeIds::GOLD_INGOT && !$this->admiring && $this->isAlive()){
			$this->startBartering();
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->admiring){
			$elapsed = $this->barterStartedAt !== null ? min($tickDiff, max(0, $this->lastUpdate - $this->barterStartedAt)) : $tickDiff;
			$this->barterStartedAt = null;
			$this->admiringTicks -= $elapsed;
			if($this->admiringTicks <= 0){
				$this->finishBartering();
			}
		}

		$this->meleeCooldown = max(0, $this->meleeCooldown - $tickDiff);
		$retaliationTarget = $this->getRetaliationTarget();
		$target = $retaliationTarget ?? $this->findHostilePlayer(16);
		if(($retaliationTarget !== null || !$this->admiring) && $target !== null && $this->meleeCooldown === 0 && $this->canReachPlayerForMelee($target)){
			$this->meleeCooldown = 20;
			$target->attack(new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 5.0));
		}
		return true;
	}

	public function getRandomBarterLoot() : Item{
		// Bedrock behavior pack: loot_tables/entities/piglin_barter.json.
		$pool = [
			[5, static fn() => VanillaItems::ENCHANTED_BOOK()->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SOUL_SPEED(), mt_rand(1, 3)))],
			[8, static fn() => VanillaItems::IRON_BOOTS()->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SOUL_SPEED(), mt_rand(1, 3)))],
			[8, static fn() => VanillaItems::POTION()->setType(PotionType::FIRE_RESISTANCE)],
			[8, static fn() => VanillaItems::SPLASH_POTION()->setType(PotionType::FIRE_RESISTANCE)],
			[10, static fn() => VanillaItems::POTION()->setType(PotionType::WATER)],
			[10, static fn() => VanillaItems::IRON_NUGGET()->setCount(mt_rand(10, 36))],
			[10, static fn() => VanillaItems::ENDER_PEARL()->setCount(mt_rand(2, 4))],
			[20, static fn() => VanillaItems::STRING()->setCount(mt_rand(3, 9))],
			[20, static fn() => VanillaItems::NETHER_QUARTZ()->setCount(mt_rand(5, 12))],
			[40, static fn() => VanillaBlocks::OBSIDIAN()->asItem()],
			[40, static fn() => VanillaBlocks::CRYING_OBSIDIAN()->asItem()->setCount(mt_rand(1, 3))],
			[40, static fn() => VanillaItems::FIRE_CHARGE()],
			[40, static fn() => VanillaItems::LEATHER()->setCount(mt_rand(2, 4))],
			[40, static fn() => VanillaBlocks::SOUL_SAND()->asItem()->setCount(mt_rand(2, 8))],
			[40, static fn() => VanillaItems::NETHER_BRICK()->setCount(mt_rand(2, 8))],
			[40, static fn() => VanillaItems::ARROW()->setCount(mt_rand(6, 12))],
			[40, static fn() => VanillaBlocks::GRAVEL()->asItem()->setCount(mt_rand(8, 16))],
			[40, static fn() => VanillaBlocks::BLACKSTONE()->asItem()->setCount(mt_rand(8, 16))],
			[10, static fn() => VanillaBlocks::DRIED_GHAST()->asItem()],
		];

		$roll = mt_rand(1, array_sum(array_column($pool, 0)));
		foreach($pool as [$weight, $reward]){
			$roll -= $weight;
			if($roll <= 0){
				return $reward();
			}
		}

		throw new \LogicException("Piglin barter roll outside the loot table");
	}

	public function getDrops() : array{
		return [
			VanillaItems::RAW_PORKCHOP()->setCount(mt_rand(0, 1)),
			VanillaItems::GOLD_NUGGET()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
