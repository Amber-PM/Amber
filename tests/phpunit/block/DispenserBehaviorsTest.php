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

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\block\dispenser\ArmorDispenseBehavior;
use pocketmine\block\dispenser\BlockSource;
use pocketmine\block\dispenser\BoneMealDispenseBehavior;
use pocketmine\block\dispenser\BucketDispenseBehavior;
use pocketmine\block\dispenser\DispenseBehaviorRegistry;
use pocketmine\block\dispenser\ProjectileDispenseBehavior;
use pocketmine\block\dispenser\TNTDispenseBehavior;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class DispenserBehaviorsTest extends TestCase{

	public function testRegistryMappings() : void{
		$registry = DispenseBehaviorRegistry::getInstance();

		self::assertInstanceOf(ProjectileDispenseBehavior::class, $registry->get(VanillaItems::ARROW()));
		self::assertInstanceOf(ProjectileDispenseBehavior::class, $registry->get(VanillaItems::SNOWBALL()));
		self::assertInstanceOf(ProjectileDispenseBehavior::class, $registry->get(VanillaItems::EGG()));
		self::assertInstanceOf(ProjectileDispenseBehavior::class, $registry->get(VanillaItems::SPLASH_POTION()));
		self::assertInstanceOf(ProjectileDispenseBehavior::class, $registry->get(VanillaItems::EXPERIENCE_BOTTLE()));
		self::assertInstanceOf(ProjectileDispenseBehavior::class, $registry->get(VanillaItems::ENDER_PEARL()));

		self::assertInstanceOf(BucketDispenseBehavior::class, $registry->get(VanillaItems::BUCKET()));
		self::assertInstanceOf(BucketDispenseBehavior::class, $registry->get(VanillaItems::WATER_BUCKET()));
		self::assertInstanceOf(BucketDispenseBehavior::class, $registry->get(VanillaItems::LAVA_BUCKET()));

		self::assertInstanceOf(TNTDispenseBehavior::class, $registry->get(VanillaBlocks::TNT()->asItem()));
		self::assertInstanceOf(BoneMealDispenseBehavior::class, $registry->get(VanillaItems::BONE_MEAL()));

		self::assertInstanceOf(ArmorDispenseBehavior::class, $registry->get(VanillaItems::DIAMOND_HELMET()));
		self::assertInstanceOf(ArmorDispenseBehavior::class, $registry->get(VanillaItems::IRON_CHESTPLATE()));
		self::assertInstanceOf(ArmorDispenseBehavior::class, $registry->get(VanillaItems::GOLDEN_LEGGINGS()));
		self::assertInstanceOf(ArmorDispenseBehavior::class, $registry->get(VanillaItems::LEATHER_BOOTS()));
		self::assertInstanceOf(ArmorDispenseBehavior::class, $registry->get(VanillaBlocks::CARVED_PUMPKIN()->asItem()));
	}

	public function testBucketWaterPickup() : void{
		$world = $this->createMock(World::class);
		$sourcePos = new Vector3(10, 20, 30);
		$targetPos = $sourcePos->getSide(Facing::NORTH);

		$waterBlock = VanillaBlocks::WATER(); // still water by default

		$world->method("getBlock")->with($targetPos)->willReturn($waterBlock);

		$blockReplaced = null;
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $b) use (&$blockReplaced) : bool{
			$blockReplaced = $b;
			return true;
		});

		$source = new BlockSource($world, $sourcePos, Facing::NORTH);
		$behavior = new BucketDispenseBehavior();

		$result = $behavior->dispense($source, VanillaItems::BUCKET());

		self::assertInstanceOf(Item::class, $result);
		self::assertSame(VanillaItems::WATER_BUCKET()->getTypeId(), $result->getTypeId());
		self::assertInstanceOf(Air::class, $blockReplaced);
	}

	public function testBucketLavaPickup() : void{
		$world = $this->createMock(World::class);
		$sourcePos = new Vector3(10, 20, 30);
		$targetPos = $sourcePos->getSide(Facing::NORTH);

		$lavaBlock = VanillaBlocks::LAVA(); // still lava by default

		$world->method("getBlock")->with($targetPos)->willReturn($lavaBlock);

		$blockReplaced = null;
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $b) use (&$blockReplaced) : bool{
			$blockReplaced = $b;
			return true;
		});

		$source = new BlockSource($world, $sourcePos, Facing::NORTH);
		$behavior = new BucketDispenseBehavior();

		$result = $behavior->dispense($source, VanillaItems::BUCKET());

		self::assertInstanceOf(Item::class, $result);
		self::assertSame(VanillaItems::LAVA_BUCKET()->getTypeId(), $result->getTypeId());
		self::assertInstanceOf(Air::class, $blockReplaced);
	}

	public function testBucketWaterPlacement() : void{
		$world = $this->createMock(World::class);
		$sourcePos = new Vector3(10, 20, 30);
		$targetPos = $sourcePos->getSide(Facing::NORTH);

		$airBlock = VanillaBlocks::AIR();

		$world->method("getBlock")->with($targetPos)->willReturn($airBlock);

		$blockPlaced = null;
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $b) use (&$blockPlaced) : bool{
			$blockPlaced = $b;
			return true;
		});

		$source = new BlockSource($world, $sourcePos, Facing::NORTH);
		$behavior = new BucketDispenseBehavior();

		$result = $behavior->dispense($source, VanillaItems::WATER_BUCKET());

		self::assertInstanceOf(Item::class, $result);
		self::assertSame(VanillaItems::BUCKET()->getTypeId(), $result->getTypeId());
		self::assertInstanceOf(Water::class, $blockPlaced);
	}

	public function testArmorDispenseOntoLivingEntity() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$sourcePos = new Vector3(10, 20, 30);
		$targetPos = $sourcePos->getSide(Facing::NORTH);

		$living = $this->createMock(Living::class);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($living, true);

		$armorInv = new ArmorInventory($living);
		self::assertTrue($armorInv->getHelmet()->isNull());

		$loc = new Location($targetPos->x, $targetPos->y, $targetPos->z, $world, 0.0, 0.0);
		$living->method("getArmorInventory")->willReturn($armorInv);
		$living->method("getLocation")->willReturn($loc);

		$world->method("getCollidingEntities")->willReturnCallback(function(AxisAlignedBB $bb) use ($living) : array{
			return [$living];
		});

		$source = new BlockSource($world, $sourcePos, Facing::NORTH);
		$behavior = new ArmorDispenseBehavior();

		$helmet = VanillaItems::DIAMOND_HELMET();
		$result = $behavior->dispense($source, $helmet);

		// Armor was equipped
		self::assertFalse($armorInv->getHelmet()->isNull());
		self::assertSame(VanillaItems::DIAMOND_HELMET()->getTypeId(), $armorInv->getHelmet()->getTypeId());
		// Result item has 0 count (popped)
		self::assertSame(0, $result->getCount());
	}
}
