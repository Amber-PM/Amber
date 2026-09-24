<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use pocketmine\addon\block\AddonBlock;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\CombatMemory;
use pocketmine\addon\script\ScriptHost;
use pocketmine\block\Block;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\Position;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function str_replace;
use function strtolower;

/**
 * The add-on runtime's event wiring, owned by an internal plugin (it is never listed as one).
 *
 * It turns server events into script events (world.afterEvents.* on the next tick, world.beforeEvents.*
 * synchronously so scripts can cancel them), runs item and block custom components, and remembers who fought
 * whom for tamed mobs. Priorities are chosen so plugins keep the last word: before-events run at LOW, after-
 * events at MONITOR, so a plugin can still cancel what a script let through, and a script sees what plugins
 * decided.
 */
final class AddonRuntime extends PluginBase{
	private ?AddonManager $manager = null;

	/** @internal */
	public function attach(AddonManager $manager) : void{
		$this->manager = $manager;
	}

	private function host() : ?ScriptHost{
		$host = $this->manager?->getScriptHost();
		return $host !== null && $host->isRunning() ? $host : null;
	}

	protected function onEnable() : void{
		$pm = $this->getServer()->getPluginManager();
		$monitor = EventPriority::MONITOR;

		//combat memory for owner_hurt_by_target / owner_hurt_target, and hurt/hit script events
		$pm->registerEvent(EntityDamageEvent::class, function(EntityDamageEvent $event) : void{
			$entity = $event->getEntity();
			$damager = $event instanceof EntityDamageByEntityEvent ? $event->getDamager() : null;
			if($damager !== null){
				CombatMemory::record($entity, $damager, $this->getServer()->getTick());
			}
			$host = $this->host();
			if($host === null){
				return;
			}
			$projectile = $event instanceof EntityDamageByChildEntityEvent ? $event->getChild() : null;
			$host->queueEvent("entityHurt", [
				"entity" => $entity->getId(),
				"damage" => $event->getFinalDamage(),
				"src" => ["cause" => self::causeName($event->getCause()), "damager" => $damager?->getId(), "projectile" => $projectile?->getId()],
			]);
			if($damager !== null && $projectile === null){
				$host->queueEvent("entityHitEntity", ["damager" => $damager->getId(), "entity" => $entity->getId()]);
				if($damager instanceof Player){
					$this->itemHook($damager, $damager->getInventory()->getItemInHand(), "onHitEntity", ["entity" => $entity->getId()]);
				}
			}
		}, $monitor, $this);

		$pm->registerEvent(EntityDeathEvent::class, function(EntityDeathEvent $event) : void{
			$entity = $event->getEntity();
			$cause = $entity->getLastDamageCause();
			$damager = $cause instanceof EntityDamageByEntityEvent ? $cause->getDamager() : null;
			$this->host()?->queueEvent("entityDie", [
				"entity" => $entity->getId(),
				"src" => ["cause" => $cause === null ? "none" : self::causeName($cause->getCause()), "damager" => $damager?->getId(), "projectile" => $cause instanceof EntityDamageByChildEntityEvent ? $cause->getChild()?->getId() : null],
			]);
		}, $monitor, $this);

		$pm->registerEvent(EntitySpawnEvent::class, function(EntitySpawnEvent $event) : void{
			$entity = $event->getEntity();
			$this->host()?->queueEvent("entitySpawn", ["entity" => $entity->getId(), "cause" => $entity instanceof AddonEntity ? "Spawned" : "Spawned"]);
		}, $monitor, $this);

		$pm->registerEvent(EntityDespawnEvent::class, function(EntityDespawnEvent $event) : void{
			$host = $this->host();
			if($host === null){
				return;
			}
			$entity = $event->getEntity();
			$host->before("entityRemove", ["entity" => $entity->getId()]);
			$host->queueEvent("entityRemove", ["entity" => $entity->getId(), "type" => ScriptHost::typeId($entity)]);
			$host->queueEvent("__gone", ["entity" => $entity->getId()]);
		}, $monitor, $this);

		$pm->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			$player = $event->getPlayer();
			$host = $this->host();
			$host?->queueEvent("playerJoin", ["player" => $player->getId(), "name" => $player->getName()]);
			$host?->queueEvent("playerSpawn", ["player" => $player->getId(), "initial" => true]);
		}, $monitor, $this);

		$pm->registerEvent(PlayerRespawnEvent::class, function(PlayerRespawnEvent $event) : void{
			$this->host()?->queueEvent("playerSpawn", ["player" => $event->getPlayer()->getId(), "initial" => false]);
		}, $monitor, $this);

		$pm->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			$host = $this->host();
			if($host === null){
				return;
			}
			$player = $event->getPlayer();
			$host->before("playerLeave", ["player" => $player->getId()]);
			$host->queueEvent("playerLeave", ["player" => $player->getId(), "name" => $player->getName()]);
			$host->queueEvent("__quit", ["player" => $player->getId()]);
		}, $monitor, $this);

		//block breaking: scripts may cancel it (before), then learn of it (after); custom components run on add-on blocks
		$pm->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			$host = $this->host();
			if($host === null){
				return;
			}
			$data = ["player" => $event->getPlayer()->getId(), "block" => $this->blockData($event->getBlock()), "item" => ScriptHost::itemWire($event->getItem())];
			if(($host->before("playerBreakBlock", $data)["cancel"] ?? false) === true){
				$event->cancel();
			}
		}, EventPriority::LOW, $this);
		$pm->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			$block = $event->getBlock();
			$player = $event->getPlayer();
			$data = ["player" => $player->getId(), "block" => $this->blockData($block), "item" => ScriptHost::itemWire($event->getItem())];
			$this->host()?->queueEvent("playerBreakBlock", $data);
			if($block instanceof AddonBlock){
				$this->blockHook($block, "onPlayerDestroy", ["player" => $player->getId(), "block" => $data["block"]]);
			}
			$this->itemHook($player, $event->getItem(), "onMineBlock", ["block" => $data["block"]]);
		}, $monitor, $this);

		$pm->registerEvent(BlockPlaceEvent::class, function(BlockPlaceEvent $event) : void{
			$player = $event->getPlayer();
			foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
				$position = new Position($x, $y, $z, $player->getWorld());
				$data = $this->blockData($block, $position);
				$this->host()?->queueEvent("playerPlaceBlock", ["player" => $player->getId(), "block" => $data]);
				if($block instanceof AddonBlock){
					$this->blockHook($block, "onPlace", ["block" => $data, "player" => $player->getId()]);
				}
			}
		}, $monitor, $this);

		$pm->registerEvent(PlayerItemUseEvent::class, function(PlayerItemUseEvent $event) : void{
			$data = ["player" => $event->getPlayer()->getId(), "item" => ScriptHost::itemWire($event->getItem())];
			if(($this->host()?->before("itemUse", $data)["cancel"] ?? false) === true){
				$event->cancel();
			}
		}, EventPriority::LOW, $this);
		$pm->registerEvent(PlayerItemUseEvent::class, function(PlayerItemUseEvent $event) : void{
			$this->host()?->queueEvent("itemUse", ["player" => $event->getPlayer()->getId(), "item" => ScriptHost::itemWire($event->getItem())]);
		}, $monitor, $this);

		$pm->registerEvent(PlayerInteractEvent::class, function(PlayerInteractEvent $event) : void{
			if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
				return;
			}
			$host = $this->host();
			if($host === null){
				return;
			}
			$data = ["player" => $event->getPlayer()->getId(), "block" => $this->blockData($event->getBlock()), "item" => ScriptHost::itemWire($event->getItem()), "face" => self::faceName($event->getFace()), "faceLocation" => ["x" => $event->getTouchVector()->x, "y" => $event->getTouchVector()->y, "z" => $event->getTouchVector()->z]];
			if(($host->before("playerInteractWithBlock", $data)["cancel"] ?? false) === true || (!$event->getItem()->isNull() && ($host->before("itemUseOn", $data)["cancel"] ?? false) === true)){
				$event->cancel();
			}
		}, EventPriority::LOW, $this);
		$pm->registerEvent(PlayerInteractEvent::class, function(PlayerInteractEvent $event) : void{
			if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
				return;
			}
			$host = $this->host();
			$data = ["player" => $event->getPlayer()->getId(), "block" => $this->blockData($event->getBlock()), "item" => ScriptHost::itemWire($event->getItem()), "face" => self::faceName($event->getFace())];
			$host?->queueEvent("playerInteractWithBlock", $data);
			if(!$event->getItem()->isNull()){
				$host?->queueEvent("itemUseOn", $data);
			}
		}, $monitor, $this);

		$pm->registerEvent(PlayerEntityInteractEvent::class, function(PlayerEntityInteractEvent $event) : void{
			$data = ["player" => $event->getPlayer()->getId(), "entity" => $event->getEntity()->getId(), "item" => ScriptHost::itemWire($event->getPlayer()->getInventory()->getItemInHand())];
			if(($this->host()?->before("playerInteractWithEntity", $data)["cancel"] ?? false) === true){
				$event->cancel();
			}
		}, EventPriority::LOW, $this);
		$pm->registerEvent(PlayerEntityInteractEvent::class, function(PlayerEntityInteractEvent $event) : void{
			$this->host()?->queueEvent("playerInteractWithEntity", ["player" => $event->getPlayer()->getId(), "entity" => $event->getEntity()->getId(), "item" => ScriptHost::itemWire($event->getPlayer()->getInventory()->getItemInHand())]);
		}, $monitor, $this);

		$pm->registerEvent(PlayerChatEvent::class, function(PlayerChatEvent $event) : void{
			$host = $this->host();
			if($host === null){
				return;
			}
			$result = $host->before("chatSend", ["player" => $event->getPlayer()->getId(), "message" => $event->getMessage()]);
			if(($result["cancel"] ?? false) === true){
				$event->cancel();
				return;
			}
			if(is_string($result["message"] ?? null)){
				$event->setMessage($result["message"]);
			}
			if(is_array($result["targets"] ?? null)){
				$ids = array_map("intval", $result["targets"]);
				$event->setRecipients(array_values(array_filter($event->getRecipients(), static fn($r) : bool => !$r instanceof Player || in_array($r->getId(), $ids, true))));
			}
		}, EventPriority::LOW, $this);
		$pm->registerEvent(PlayerChatEvent::class, function(PlayerChatEvent $event) : void{
			$this->host()?->queueEvent("chatSend", ["player" => $event->getPlayer()->getId(), "message" => $event->getMessage()]);
		}, $monitor, $this);

		$pm->registerEvent(ProjectileHitEntityEvent::class, function(ProjectileHitEntityEvent $event) : void{
			$projectile = $event->getEntity();
			$this->host()?->queueEvent("projectileHitEntity", $this->projectileData($projectile) + ["entity" => $event->getEntityHit()->getId()]);
		}, $monitor, $this);
		$pm->registerEvent(ProjectileHitBlockEvent::class, function(ProjectileHitBlockEvent $event) : void{
			$projectile = $event->getEntity();
			$this->host()?->queueEvent("projectileHitBlock", $this->projectileData($projectile) + ["block" => $this->blockData($event->getBlockHit())]);
		}, $monitor, $this);

		$pm->registerEvent(PlayerItemConsumeEvent::class, function(PlayerItemConsumeEvent $event) : void{
			$player = $event->getPlayer();
			$this->host()?->queueEvent("itemCompleteUse", ["player" => $player->getId(), "item" => ScriptHost::itemWire($event->getItem())]);
			$this->itemHook($player, $event->getItem(), "onConsume", []);
		}, $monitor, $this);

		$pm->registerEvent(PlayerItemHeldEvent::class, function(PlayerItemHeldEvent $event) : void{
			$player = $event->getPlayer();
			$this->host()?->queueEvent("playerHotbarSelectedSlotChange", ["player" => $player->getId(), "from" => $player->getInventory()->getHeldItemIndex(), "to" => $event->getSlot(), "item" => ScriptHost::itemWire($event->getItem())]);
		}, $monitor, $this);

		$pm->registerEvent(EntityTeleportEvent::class, function(EntityTeleportEvent $event) : void{
			$host = $this->host();
			$player = $event->getEntity();
			$from = $event->getFrom();
			$to = $event->getTo();
			if($host === null || !$player instanceof Player || $from->getWorld() === $to->getWorld()){
				return;
			}
			$host->queueEvent("playerDimensionChange", [
				"player" => $player->getId(),
				"from" => $host->dimensionId($from->getWorld()), "to" => $host->dimensionId($to->getWorld()),
				"fromLocation" => ["x" => $from->x, "y" => $from->y, "z" => $from->z], "toLocation" => ["x" => $to->x, "y" => $to->y, "z" => $to->z],
			]);
		}, $monitor, $this);

		$pm->registerEvent(EntityEffectAddEvent::class, function(EntityEffectAddEvent $event) : void{
			$effect = $event->getEffect();
			$data = ["entity" => $event->getEntity()->getId(), "effect" => ["type" => "minecraft:" . strtolower(str_replace(" ", "_", $effect->getType()->getName()->getText())), "duration" => $effect->getDuration(), "amplifier" => $effect->getAmplifier()]];
			if(($this->host()?->before("effectAdd", $data)["cancel"] ?? false) === true){
				$event->cancel();
				return;
			}
			$this->host()?->queueEvent("effectAdd", $data);
		}, EventPriority::LOW, $this);

		$pm->registerEvent(PlayerGameModeChangeEvent::class, function(PlayerGameModeChangeEvent $event) : void{
			$player = $event->getPlayer();
			$data = ["player" => $player->getId(), "from" => ScriptHost::gameModeName($player->getGamemode()), "to" => ScriptHost::gameModeName($event->getNewGamemode())];
			if(($this->host()?->before("playerGameModeChange", $data)["cancel"] ?? false) === true){
				$event->cancel();
				return;
			}
			$this->host()?->queueEvent("playerGameModeChange", $data);
		}, EventPriority::LOW, $this);

		$pm->registerEvent(EntityExplodeEvent::class, function(EntityExplodeEvent $event) : void{
			$host = $this->host();
			if($host === null){
				return;
			}
			$world = $event->getPosition()->getWorld();
			$blocks = array_map(static fn(Block $b) : array => ["x" => $b->getPosition()->x, "y" => $b->getPosition()->y, "z" => $b->getPosition()->z], $event->getBlockList());
			$data = ["source" => $event->getEntity()->getId(), "dim" => $host->dimensionId($world), "blocks" => $blocks];
			$result = $host->before("explosion", $data);
			if(($result["cancel"] ?? false) === true){
				$event->cancel();
				return;
			}
			if(is_array($result["blocks"] ?? null) && count($result["blocks"]) !== count($blocks)){
				$keep = [];
				foreach($result["blocks"] as $b){
					$keep[(int) $b["x"] . ":" . (int) $b["y"] . ":" . (int) $b["z"]] = true;
				}
				$event->setBlockList(array_values(array_filter($event->getBlockList(), static fn(Block $b) : bool => isset($keep[$b->getPosition()->x . ":" . $b->getPosition()->y . ":" . $b->getPosition()->z]))));
			}
			$host->queueEvent("explosion", $data);
		}, EventPriority::LOW, $this);
	}

	/** @return array<string, mixed> */
	private function blockData(Block $block, ?Position $at = null) : array{
		$position = $at ?? $block->getPosition();
		$wire = ScriptHost::blockWire($block);
		return [
			"dim" => $this->host()?->dimensionId($position->getWorld()) ?? "minecraft:overworld",
			"x" => $position->getFloorX(), "y" => $position->getFloorY(), "z" => $position->getFloorZ(),
			"type" => $wire["type"], "states" => $wire["states"], "solid" => $wire["solid"],
		];
	}

	/** @return array<string, mixed> */
	private function projectileData(Projectile $projectile) : array{
		$pos = $projectile->getPosition();
		return [
			"projectile" => $projectile->getId(),
			"source" => $projectile->getOwningEntityId(),
			"dim" => $this->host()?->dimensionId($projectile->getWorld()) ?? "minecraft:overworld",
			"location" => ["x" => $pos->x, "y" => $pos->y, "z" => $pos->z],
		];
	}

	/** @param array<string, mixed> $data */
	private function itemHook(Player $player, Item $item, string $hook, array $data) : void{
		$names = $this->manager?->getItemCustomComponents($item) ?? [];
		if($names !== []){
			$this->host()?->hook("item", array_keys($names), $hook, $data + ["player" => $player->getId(), "item" => ScriptHost::itemWire($item), "params" => $names]);
		}
	}

	/** @param array<string, mixed> $data */
	private function blockHook(AddonBlock $block, string $hook, array $data) : void{
		$names = $this->manager?->getBlockCustomComponents($block) ?? [];
		if($names !== []){
			$this->host()?->hook("block", array_keys($names), $hook, $data + ["params" => $names]);
		}
	}

	public static function faceName(int $face) : string{
		return match($face){
			Facing::DOWN => "Down",
			Facing::UP => "Up",
			Facing::NORTH => "North",
			Facing::SOUTH => "South",
			Facing::WEST => "West",
			default => "East",
		};
	}

	public static function causeName(int $cause) : string{
		return match($cause){
			EntityDamageEvent::CAUSE_CONTACT => "contact",
			EntityDamageEvent::CAUSE_ENTITY_ATTACK => "entityAttack",
			EntityDamageEvent::CAUSE_PROJECTILE => "projectile",
			EntityDamageEvent::CAUSE_SUFFOCATION => "suffocation",
			EntityDamageEvent::CAUSE_FALL => "fall",
			EntityDamageEvent::CAUSE_FIRE => "fire",
			EntityDamageEvent::CAUSE_FIRE_TICK => "fireTick",
			EntityDamageEvent::CAUSE_LAVA => "lava",
			EntityDamageEvent::CAUSE_DROWNING => "drowning",
			EntityDamageEvent::CAUSE_BLOCK_EXPLOSION => "blockExplosion",
			EntityDamageEvent::CAUSE_ENTITY_EXPLOSION => "entityExplosion",
			EntityDamageEvent::CAUSE_VOID => "void",
			EntityDamageEvent::CAUSE_SUICIDE => "suicide",
			EntityDamageEvent::CAUSE_MAGIC => "magic",
			EntityDamageEvent::CAUSE_STARVATION => "starve",
			EntityDamageEvent::CAUSE_FALLING_BLOCK => "fallingBlock",
			default => "override",
		};
	}
}
