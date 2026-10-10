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

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class BowTippedArrowTest extends TestCase{

	/** @var Player[] */
	private array $players = [];

	protected function tearDown() : void{
		foreach($this->players as $player){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
		$this->players = [];
	}

	private function createMockPlayer(
		bool $finiteResources = true,
		int $itemUseDuration = 20,
		?World $world = null
	) : Player{
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getServer")->willReturn($this->createMock(\pocketmine\Server::class));
			$world->updateEntities = [];
		}

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods([
				"getInventory",
				"getOffHandInventory",
				"getItemUseDuration",
				"hasFiniteResources",
				"getLocation",
				"getWorld",
				"isSpectator",
				"isCreative",
				"getEyePos",
				"getDirectionVector",
				"isConnected"
			])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($player, []);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$inventory = new PlayerInventory($player);
		$offHandInventory = new PlayerOffHandInventory($player);

		$player->method("getInventory")->willReturn($inventory);
		$player->method("getOffHandInventory")->willReturn($offHandInventory);
		$player->method("getItemUseDuration")->willReturn($itemUseDuration);
		$player->method("hasFiniteResources")->willReturn($finiteResources);
		$player->method("isCreative")->willReturn(!$finiteResources);
		$player->method("getLocation")->willReturn(new Location(0.0, 64.0, 0.0, $world, 0.0, 0.0));
		$player->method("getWorld")->willReturn($world);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getEyePos")->willReturn(new Vector3(0.0, 65.6, 0.0));
		$player->method("getDirectionVector")->willReturn(new Vector3(0.0, 0.0, 1.0));
		$player->method("isConnected")->willReturn(false);

		$this->players[] = $player;
		return $player;
	}

	public function testFindArrowPrefersOffHand() : void{
		$player = $this->createMockPlayer();
		$offHandArrow = VanillaItems::ARROW()->setTipType(PotionType::SWIFTNESS);
		$inventoryArrow = VanillaItems::ARROW();

		$player->getOffHandInventory()->setItem(0, $offHandArrow);
		$player->getInventory()->setItem(0, $inventoryArrow);

		$bow = VanillaItems::BOW();
		$findArrow = new ReflectionMethod(Bow::class, "findArrow");
		$result = $findArrow->invoke($bow, $player);

		self::assertIsArray($result);
		self::assertTrue($offHandArrow->equalsExact($result[0]));
		self::assertSame($player->getOffHandInventory(), $result[1]);
	}

	public function testFindArrowFallsBackToMainInventory() : void{
		$player = $this->createMockPlayer();
		$slownessArrow = VanillaItems::ARROW()->setTipType(PotionType::SLOWNESS);
		$normalArrow = VanillaItems::ARROW();

		$player->getInventory()->setItem(0, $slownessArrow);
		$player->getInventory()->setItem(1, $normalArrow);

		$bow = VanillaItems::BOW();
		$findArrow = new ReflectionMethod(Bow::class, "findArrow");
		$result = $findArrow->invoke($bow, $player);

		self::assertIsArray($result);
		self::assertTrue($slownessArrow->equalsExact($result[0]));
		self::assertSame($player->getInventory(), $result[1]);
	}

	public function testInfinityDoesNotConsumeUntippedArrow() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($this->createMock(\pocketmine\Server::class));
		$world->updateEntities = [];

		$player = $this->createMockPlayer(finiteResources: true, itemUseDuration: 20, world: $world);
		$normalArrow = VanillaItems::ARROW()->setCount(1);
		$player->getInventory()->setItem(0, $normalArrow);

		$bow = VanillaItems::BOW();
		$bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::INFINITY()));

		$capturedProjectile = null;
		$list = HandlerListManager::global()->getListFor(EntityShootBowEvent::class);
		$listener = new RegisteredListener(function(EntityShootBowEvent $event) use (&$capturedProjectile) : void{
			$capturedProjectile = $event->getProjectile();
			$event->cancel(); // Cancel to avoid needing full world spawn logic
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler("bow shoot test"));
		$list->register($listener);

		try{
			$returnedItems = [];
			$bow->onReleaseUsing($player, $returnedItems);

			self::assertInstanceOf(ArrowEntity::class, $capturedProjectile);
			self::assertNull($capturedProjectile->getPotionType());
			self::assertSame(ArrowEntity::PICKUP_CREATIVE, $capturedProjectile->getPickupMode());
		}finally{
			$list->unregister($listener);
		}

		// When shoot event is cancelled, projectile is flagged for despawn
		// Now let's test successful shot where arrow consumption is checked
		$listenerSuccess = new RegisteredListener(function(EntityShootBowEvent $event) use (&$capturedProjectile) : void{
			$capturedProjectile = $event->getProjectile();
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler("bow shoot test 2"));
		$list->register($listenerSuccess);

		try{
			$returnedItems = [];
			$bow->onReleaseUsing($player, $returnedItems);

			// Regular arrow should NOT be consumed due to Infinity!
			self::assertSame(1, $player->getInventory()->getItem(0)->getCount());
			self::assertInstanceOf(ArrowEntity::class, $capturedProjectile);
			self::assertNull($capturedProjectile->getPotionType());
			self::assertSame(ArrowEntity::PICKUP_CREATIVE, $capturedProjectile->getPickupMode());
		}finally{
			$list->unregister($listenerSuccess);
			(new ReflectionProperty(Entity::class, "closed"))->setValue($capturedProjectile, true);
		}
	}

	public function testInfinityConsumesTippedArrow() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($this->createMock(\pocketmine\Server::class));
		$world->updateEntities = [];

		$player = $this->createMockPlayer(finiteResources: true, itemUseDuration: 20, world: $world);
		$tippedArrow = VanillaItems::ARROW()->setTipType(PotionType::SWIFTNESS)->setCount(1);
		$player->getInventory()->setItem(0, $tippedArrow);

		$bow = VanillaItems::BOW();
		$bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::INFINITY()));

		$capturedProjectile = null;
		$list = HandlerListManager::global()->getListFor(EntityShootBowEvent::class);
		$listener = new RegisteredListener(function(EntityShootBowEvent $event) use (&$capturedProjectile) : void{
			$capturedProjectile = $event->getProjectile();
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler("bow shoot tipped test"));
		$list->register($listener);

		try{
			$returnedItems = [];
			$bow->onReleaseUsing($player, $returnedItems);

			// Tipped arrow MUST be consumed even with Infinity!
			self::assertTrue($player->getInventory()->getItem(0)->isNull());
			self::assertInstanceOf(ArrowEntity::class, $capturedProjectile);
			self::assertSame(PotionType::SWIFTNESS, $capturedProjectile->getPotionType());
			self::assertSame(ArrowEntity::PICKUP_ANY, $capturedProjectile->getPickupMode());
		}finally{
			$list->unregister($listener);
			(new ReflectionProperty(Entity::class, "closed"))->setValue($capturedProjectile, true);
		}
	}
}
