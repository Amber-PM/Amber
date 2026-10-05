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
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\object\FireworkRocket as FireworkRocketEntity;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;
use function count;

final class CrossbowTestDouble extends Crossbow{
	/** @var array<array{ammo: Item, yawOffset: float, isExtra: bool}> */
	public array $shotProjectiles = [];
	public bool $shouldCancel = false;

	protected function shootProjectile(Player $player, Item $ammo, float $yawOffset, bool $isExtra = false) : bool{
		if($this->shouldCancel){
			return false;
		}
		$this->shotProjectiles[] = [
			"ammo" => clone $ammo,
			"yawOffset" => $yawOffset,
			"isExtra" => $isExtra
		];
		return true;
	}
}

final class CrossbowRealArrowTestDouble extends Crossbow{
	/** @var ArrowEntity[] */
	public array $createdArrows = [];

	protected function createArrow(Location $location, Player $player) : ArrowEntity{
		$arrow = (new ReflectionClass(ArrowEntity::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($arrow, 200 + count($this->createdArrows));
		(new ReflectionProperty(Entity::class, "size"))->setValue($arrow, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($arrow, new Vector3(0.0, 0.0, 1.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($arrow, new AxisAlignedBB(0, 0, 0, 0.25, 0.25, 0.25));
		(new ReflectionProperty(ArrowEntity::class, "pickupMode"))->setValue($arrow, ArrowEntity::PICKUP_ANY);
		(new ReflectionProperty(ArrowEntity::class, "pierceLevel"))->setValue($arrow, 0);
		(new ReflectionProperty(ArrowEntity::class, "piercedEntityIds"))->setValue($arrow, []);
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, $location);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($arrow, []);

		$this->createdArrows[] = $arrow;
		return $arrow;
	}
}

final class CrossbowItemTest extends TestCase{

	private function createMockPlayer(
		bool $finiteResources = true,
		int $itemUseDuration = 0,
		?World $world = null,
		bool $isSpectator = false
	) : Player{
		if($world === null){
			$worldMock = $this->createMock(World::class);
			$worldMock->method("isLoaded")->willReturn(true);
			$world = $worldMock;
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

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
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
		$player->method("isSpectator")->willReturn($isSpectator);
		$player->method("getEyePos")->willReturn(new Vector3(0.0, 65.6, 0.0));
		$player->method("getDirectionVector")->willReturn(new Vector3(0.0, 0.0, 1.0));
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testDurabilityAndFuel() : void{
		$crossbow = VanillaItems::CROSSBOW();
		self::assertSame(465, $crossbow->getMaxDurability());
		self::assertSame(300, $crossbow->getFuelTime());
	}

	private function createFireworkPlayer(array &$entities, bool $isSpectator = false) : Player{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($this->createMock(Server::class));
		$world->method("addEntity")->willReturnCallback(function(Entity $entity) use (&$entities) : void{
			$entities[] = $entity;
		});
		$player = $this->createMockPlayer(world: $world, isSpectator: $isSpectator);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		return $player;
	}

	public function testFireworkShootEventCancellationPreservesCharge() : void{
		$entities = [];
		$player = $this->createFireworkPlayer($entities);
		$crossbow = VanillaItems::CROSSBOW()->setChargedItem(VanillaItems::FIREWORK_ROCKET());
		$calls = 0;
		$list = HandlerListManager::global()->getListFor(EntityShootBowEvent::class);
		$listener = new RegisteredListener(function(EntityShootBowEvent $event) use (&$calls, $player, $crossbow) : void{
			++$calls;
			self::assertSame($player, $event->getEntity());
			self::assertSame($crossbow, $event->getBow());
			self::assertInstanceOf(FireworkRocketEntity::class, $event->getProjectile());
			$event->cancel();
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler("crossbow firework cancellation"));
		$list->register($listener);
		try{
			$returnedItems = [];
			$result = $crossbow->onClickAir($player, new Vector3(0, 0, 1), $returnedItems);
			self::assertSame(1, $calls);
			self::assertSame(ItemUseResult::FAIL, $result);
			self::assertTrue($crossbow->isCharged());
			self::assertSame(0, $crossbow->getDamage());
			self::assertCount(1, $entities);
			self::assertTrue($entities[0]->isFlaggedForDespawn());
		}finally{
			$list->unregister($listener);
			foreach($entities as $entity){
				$entity->close();
			}
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testFireworkShootEventCanChangeForce() : void{
		$entities = [];
		$player = $this->createFireworkPlayer($entities);
		$crossbow = VanillaItems::CROSSBOW()->setChargedItem(VanillaItems::FIREWORK_ROCKET());
		$list = HandlerListManager::global()->getListFor(EntityShootBowEvent::class);
		$listener = new RegisteredListener(function(EntityShootBowEvent $event) : void{
			self::assertSame(1.6, $event->getForce());
			$event->setForce(2.0);
		}, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler("crossbow firework force"));
		$list->register($listener);
		try{
			$returnedItems = [];
			$result = $crossbow->onClickAir($player, new Vector3(0, 0, 1), $returnedItems);
			self::assertSame(ItemUseResult::SUCCESS, $result);
			self::assertFalse($crossbow->isCharged());
			self::assertSame(1, $crossbow->getDamage());
			self::assertCount(1, $entities);
			self::assertInstanceOf(FireworkRocketEntity::class, $entities[0]);
			self::assertEqualsWithDelta(2.0, $entities[0]->getMotion()->length(), 0.00001);
			self::assertTrue($entities[0]->isShotFromCrossbow());
			self::assertFalse($entities[0]->isFlaggedForDespawn());
		}finally{
			$list->unregister($listener);
			foreach($entities as $entity){
				$entity->close();
			}
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testSpectatorFireworkShotPreservesCharge() : void{
		$entities = [];
		$player = $this->createFireworkPlayer($entities, isSpectator: true);
		$crossbow = VanillaItems::CROSSBOW()->setChargedItem(VanillaItems::FIREWORK_ROCKET());
		try{
			$returnedItems = [];
			self::assertSame(ItemUseResult::FAIL, $crossbow->onClickAir($player, new Vector3(0, 0, 1), $returnedItems));
			self::assertTrue($crossbow->isCharged());
			self::assertSame(0, $crossbow->getDamage());
			self::assertCount(1, $entities);
			self::assertTrue($entities[0]->isFlaggedForDespawn());
		}finally{
			foreach($entities as $entity){
				$entity->close();
			}
			(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		}
	}

	public function testRegistryAndParser() : void{
		$crossbow = VanillaItems::CROSSBOW();
		self::assertInstanceOf(Crossbow::class, $crossbow);
		self::assertSame(ItemTypeIds::CROSSBOW, $crossbow->getTypeId());

		$parsed = StringToItemParser::getInstance()->parse("crossbow");
		self::assertInstanceOf(Crossbow::class, $parsed);
		self::assertSame(ItemTypeIds::CROSSBOW, $parsed->getTypeId());
	}

	public function testBedrockSerializationRoundtrip() : void{
		$crossbow = VanillaItems::CROSSBOW();
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();

		$data = $serializer->serializeType($crossbow);
		self::assertSame(ItemTypeNames::CROSSBOW, $data->getName());

		$deserialized = $deserializer->deserializeType(new SavedItemData(ItemTypeNames::CROSSBOW));
		self::assertInstanceOf(Crossbow::class, $deserialized);
		self::assertSame(ItemTypeIds::CROSSBOW, $deserialized->getTypeId());
	}

	public function testQuickChargeFormula() : void{
		$crossbow = VanillaItems::CROSSBOW();
		self::assertSame(25, $crossbow->getChargeDuration());

		$quickCharge = VanillaEnchantments::QUICK_CHARGE();

		$crossbow->addEnchantment(new EnchantmentInstance($quickCharge, 1));
		self::assertSame(20, $crossbow->getChargeDuration());

		$crossbow->addEnchantment(new EnchantmentInstance($quickCharge, 2));
		self::assertSame(15, $crossbow->getChargeDuration());

		$crossbow->addEnchantment(new EnchantmentInstance($quickCharge, 3));
		self::assertSame(10, $crossbow->getChargeDuration());

		$crossbow->addEnchantment(new EnchantmentInstance($quickCharge, 4));
		self::assertSame(5, $crossbow->getChargeDuration());
	}

	public function testIsValidAmmo() : void{
		$crossbow = VanillaItems::CROSSBOW();
		self::assertTrue($crossbow->isValidAmmo(VanillaItems::ARROW()));
		self::assertTrue($crossbow->isValidAmmo(VanillaItems::FIREWORK_ROCKET()));
		self::assertFalse($crossbow->isValidAmmo(VanillaItems::BOW()));
		self::assertFalse($crossbow->isValidAmmo(VanillaItems::FEATHER()));
	}

	public function testInitialChargedState() : void{
		$crossbow = VanillaItems::CROSSBOW();
		self::assertFalse($crossbow->isCharged());
		self::assertNull($crossbow->getChargedItem());
	}

	public function testChargingStateAndNbt() : void{
		$crossbow = VanillaItems::CROSSBOW();
		$arrow = VanillaItems::ARROW()->setCount(5);
		$crossbow->setChargedItem($arrow);
		self::assertTrue($crossbow->isCharged());
		$charged = $crossbow->getChargedItem();
		self::assertNotNull($charged);
		self::assertSame(ItemTypeIds::ARROW, $charged->getTypeId());
		self::assertSame(5, $charged->getCount());

		// NBT roundtrip check
		$nbt = $crossbow->nbtSerialize();
		$deserialized = Item::nbtDeserialize($nbt);
		self::assertInstanceOf(Crossbow::class, $deserialized);
		self::assertTrue($deserialized->isCharged());
		$deserializedCharged = $deserialized->getChargedItem();
		self::assertNotNull($deserializedCharged);
		self::assertSame(ItemTypeIds::ARROW, $deserializedCharged->getTypeId());

		// Clear charged item
		$crossbow->setChargedItem(null);
		self::assertFalse($crossbow->isCharged());
	}

	public function testFindAmmoItemNone() : void{
		$crossbow = VanillaItems::CROSSBOW();
		$player = $this->createMockPlayer();
		self::assertNull($crossbow->findAmmoItem($player));
	}

	public function testFindAmmoItemPriority() : void{
		$crossbow = VanillaItems::CROSSBOW();
		$player = $this->createMockPlayer();

		// Main inventory has arrow
		$player->getInventory()->setItem(0, VanillaItems::ARROW()->setCount(10));
		$found = $crossbow->findAmmoItem($player);
		self::assertNotNull($found);
		self::assertSame(ItemTypeIds::ARROW, $found->getTypeId());
		self::assertSame(10, $found->getCount());

		// Offhand has firework rocket - offhand has priority over main inventory
		$player->getOffHandInventory()->setItem(0, VanillaItems::FIREWORK_ROCKET()->setCount(3));
		$foundOffhand = $crossbow->findAmmoItem($player);
		self::assertNotNull($foundOffhand);
		self::assertSame(ItemTypeIds::FIREWORK_ROCKET, $foundOffhand->getTypeId());
		self::assertSame(3, $foundOffhand->getCount());
	}

	public function testCanStartUsingItem() : void{
		$crossbow = VanillaItems::CROSSBOW();
		$playerFinite = $this->createMockPlayer(finiteResources: true);
		$playerCreative = $this->createMockPlayer(finiteResources: false);

		// Finite resources with no ammo cannot start using
		self::assertFalse($crossbow->canStartUsingItem($playerFinite));

		// Creative resources without ammo can start using
		self::assertTrue($crossbow->canStartUsingItem($playerCreative));

		// Finite resources with ammo can start using
		$playerFinite->getInventory()->setItem(0, VanillaItems::ARROW());
		self::assertTrue($crossbow->canStartUsingItem($playerFinite));

		// Already charged crossbow cannot start using (it should fire instead)
		$crossbow->setChargedItem(VanillaItems::ARROW());
		self::assertFalse($crossbow->canStartUsingItem($playerFinite));
		self::assertFalse($crossbow->canStartUsingItem($playerCreative));
	}

	public function testOnReleaseUsingIncomplete() : void{
		$crossbow = VanillaItems::CROSSBOW();
		// Charge duration is 25 ticks, player only used for 10 ticks
		$player = $this->createMockPlayer(finiteResources: true, itemUseDuration: 10);
		$player->getInventory()->setItem(0, VanillaItems::ARROW()->setCount(5));

		$returnedItems = [];
		$result = $crossbow->onReleaseUsing($player, $returnedItems);
		self::assertSame(ItemUseResult::FAIL, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertSame(5, $player->getInventory()->getItem(0)->getCount());
	}

	public function testOnReleaseUsingChargesAndConsumesAmmo() : void{
		$crossbow = VanillaItems::CROSSBOW();
		// Player used for 25 ticks (fully charged)
		$player = $this->createMockPlayer(finiteResources: true, itemUseDuration: 25);
		$player->getInventory()->setItem(0, VanillaItems::ARROW()->setCount(5));

		$returnedItems = [];
		$result = $crossbow->onReleaseUsing($player, $returnedItems);
		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertTrue($crossbow->isCharged());
		self::assertSame(4, $player->getInventory()->getItem(0)->getCount());
		$charged = $crossbow->getChargedItem();
		self::assertNotNull($charged);
		self::assertSame(ItemTypeIds::ARROW, $charged->getTypeId());
		self::assertSame(1, $charged->getCount());
	}

	public function testFireWithoutMultishot() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->setChargedItem(VanillaItems::ARROW());

		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertSame(1, $crossbow->getDamage()); // 1 durability point
		self::assertCount(1, $crossbow->shotProjectiles);
		self::assertSame(0.0, $crossbow->shotProjectiles[0]["yawOffset"]);
		self::assertFalse($crossbow->shotProjectiles[0]["isExtra"]);
		self::assertSame(ItemTypeIds::ARROW, $crossbow->shotProjectiles[0]["ammo"]->getTypeId());
	}

	public function testFireWithMultishot() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::MULTISHOT(), 1));
		$crossbow->setChargedItem(VanillaItems::ARROW());

		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertSame(3, $crossbow->getDamage()); // 3 durability points for multishot
		self::assertCount(3, $crossbow->shotProjectiles);
		self::assertSame(-10.0, $crossbow->shotProjectiles[0]["yawOffset"]);
		self::assertTrue($crossbow->shotProjectiles[0]["isExtra"]);
		self::assertSame(0.0, $crossbow->shotProjectiles[1]["yawOffset"]);
		self::assertFalse($crossbow->shotProjectiles[1]["isExtra"]);
		self::assertSame(10.0, $crossbow->shotProjectiles[2]["yawOffset"]);
		self::assertTrue($crossbow->shotProjectiles[2]["isExtra"]);
	}

	public function testFireInfiniteResourcesDoesNotConsumeDurability() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->setChargedItem(VanillaItems::ARROW());

		$player = $this->createMockPlayer(finiteResources: false);
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertSame(0, $crossbow->getDamage());
		self::assertCount(1, $crossbow->shotProjectiles);
	}

	public function testFirePreservesChargeAndDurabilityWhenShootingCancelled() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->setChargedItem(VanillaItems::ARROW());
		$crossbow->shouldCancel = true;

		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::FAIL, $result);
		self::assertTrue($crossbow->isCharged());
		self::assertSame(0, $crossbow->getDamage());
		self::assertEmpty($crossbow->shotProjectiles);
	}

	public function testSpectatorShootBowCancelledPreservesCharge() : void{
		$crossbow = new CrossbowRealArrowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->setChargedItem(VanillaItems::ARROW());

		$player = $this->createMockPlayer(isSpectator: true);
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::FAIL, $result);
		self::assertTrue($crossbow->isCharged());
		self::assertSame(0, $crossbow->getDamage());
	}

	public function testMultishotExtraArrowsPickupRestrictionAndCollection() : void{
		$crossbow = new CrossbowRealArrowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::MULTISHOT(), 1));
		$crossbow->setChargedItem(VanillaItems::ARROW());

		$player = $this->createMockPlayer(finiteResources: true);
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertSame(3, $crossbow->getDamage());
		self::assertCount(3, $crossbow->createdArrows);

		[$extraLeft, $center, $extraRight] = $crossbow->createdArrows;

		// Extra projectiles must restrict survival pickup
		self::assertSame(ArrowEntity::PICKUP_CREATIVE, $extraLeft->getPickupMode());
		self::assertSame(ArrowEntity::PICKUP_ANY, $center->getPickupMode());
		self::assertSame(ArrowEntity::PICKUP_CREATIVE, $extraRight->getPickupMode());

		// Simulate all three arrows hitting a block
		$blockHitProp = new ReflectionProperty(ArrowEntity::class, "blockHit");
		$blockHitProp->setValue($extraLeft, new Vector3(0, 64, 0));
		$blockHitProp->setValue($center, new Vector3(0, 64, 0));
		$blockHitProp->setValue($extraRight, new Vector3(0, 64, 0));

		self::assertEmpty($player->getInventory()->getContents());

		// Attempt pickup on extra left arrow: should be denied in survival
		$extraLeft->onCollideWithPlayer($player);
		self::assertFalse($extraLeft->isFlaggedForDespawn());
		self::assertEmpty($player->getInventory()->getContents());

		// Attempt pickup on center arrow: should succeed
		$center->onCollideWithPlayer($player);
		self::assertTrue($center->isFlaggedForDespawn());
		self::assertSame(1, $player->getInventory()->getItem(0)->getCount());

		// Attempt pickup on extra right arrow: should be denied in survival
		$extraRight->onCollideWithPlayer($player);
		self::assertFalse($extraRight->isFlaggedForDespawn());
		// Total collected remains 1 (preventing duplicate arrow exploit)
		self::assertSame(1, $player->getInventory()->getItem(0)->getCount());
	}

	public function testFireUnchargedFails() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::FAIL, $result);
		self::assertSame(0, $crossbow->getDamage());
		self::assertEmpty($crossbow->shotProjectiles);
	}

	public function testFireworkRocketAmmoFiring() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->setChargedItem(VanillaItems::FIREWORK_ROCKET());

		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->fire($player, new Vector3(0, 0, 1), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertSame(1, $crossbow->getDamage());
		self::assertCount(1, $crossbow->shotProjectiles);
		self::assertSame(ItemTypeIds::FIREWORK_ROCKET, $crossbow->shotProjectiles[0]["ammo"]->getTypeId());
	}

	public function testOnClickAirAndInteractBlockWhenCharged() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow->setChargedItem(VanillaItems::ARROW());

		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->onClickAir($player, new Vector3(0, 0, 1), $returnedItems);
		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertFalse($crossbow->isCharged());
		self::assertCount(1, $crossbow->shotProjectiles);

		$crossbow2 = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$crossbow2->setChargedItem(VanillaItems::ARROW());
		$result2 = $crossbow2->onInteractBlock(
			$player,
			VanillaBlocks::AIR(),
			VanillaBlocks::STONE(),
			0,
			new Vector3(0, 0, 0),
			$returnedItems
		);
		self::assertSame(ItemUseResult::SUCCESS, $result2);
		self::assertFalse($crossbow2->isCharged());
		self::assertCount(1, $crossbow2->shotProjectiles);
	}

	public function testOnInteractBlockWhenUnchargedReturnsNone() : void{
		$crossbow = new CrossbowTestDouble(new ItemIdentifier(ItemTypeIds::CROSSBOW), "Crossbow");
		$player = $this->createMockPlayer();
		$returnedItems = [];
		$result = $crossbow->onInteractBlock(
			$player,
			VanillaBlocks::AIR(),
			VanillaBlocks::STONE(),
			0,
			new Vector3(0, 0, 0),
			$returnedItems
		);
		self::assertSame(ItemUseResult::NONE, $result);
		self::assertEmpty($crossbow->shotProjectiles);
	}
}
