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
use pocketmine\block\Block;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\WindCharge as WindChargeEntity;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

final class MaceCombatTest extends TestCase{

	public function testMaceBaseDamageWithoutFall() : void{
		$world = $this->createMock(World::class);
		$player = $this->createMock(Player::class);
		$player->method("getFallDistance")->willReturn(1.0);

		$victim = $this->createMock(Living::class);
		$mace = new Mace(new ItemIdentifier(ItemTypeIds::MACE), "Mace");

		$damage = $mace->calculateSmashDamage($player, $victim);
		self::assertEquals(6.0, $damage);
	}

	public function testMaceSmashAttackNegatesFallDamageAndMultipliesOutput() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getNearbyEntities")->willReturn([]);

		$fallDistance = 10.0;
		$player = $this->createMock(Player::class);
		$player->method("getWorld")->willReturn($world);
		$player->method("getFallDistance")->willReturn($fallDistance);

		$negated = false;
		$player->expects(self::once())
			->method("resetFallDistance")
			->willReturnCallback(function() use (&$negated) : void{
				$negated = true;
			});

		$loc = new Location(0, 64, 0, $world, 0, 0);
		$pos = new Position(0, 64, 0, $world);
		$victim = $this->createMock(Living::class);
		$victim->method("getLocation")->willReturn($loc);
		$victim->method("getPosition")->willReturn($pos);
		$victim->method("getBoundingBox")->willReturn(new AxisAlignedBB(0, 64, 0, 1, 66, 1));

		$mace = new Mace(new ItemIdentifier(ItemTypeIds::MACE), "Mace");
		$mace->addEnchantment(new EnchantmentInstance(VanillaEnchantments::DENSITY(), 5));

		$damage = $mace->calculateSmashDamage($player, $victim);

		// Base: 6, extra: (10 - 1.5) * (1.0 + 5 * 0.5) = 8.5 * 3.5 = 29.75 => total = 35.75
		self::assertEqualsWithDelta(35.75, $damage, 0.01);
		self::assertTrue($negated);
	}

	public function testMaceWindBurstBouncesAttacker() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getNearbyEntities")->willReturn([]);

		$player = $this->createMock(Player::class);
		$player->method("getWorld")->willReturn($world);
		$player->method("getFallDistance")->willReturn(5.0);
		$player->method("getMotion")->willReturn(new Vector3(0, 0, 0));

		$bounced = false;
		$player->expects(self::once())
			->method("setMotion")
			->with(self::callback(function(Vector3 $motion) use (&$bounced) : bool{
				$bounced = $motion->y > 1.0;
				return true;
			}));

		$loc = new Location(0, 64, 0, $world, 0, 0);
		$pos = new Position(0, 64, 0, $world);
		$victim = $this->createMock(Living::class);
		$victim->method("getLocation")->willReturn($loc);
		$victim->method("getPosition")->willReturn($pos);
		$victim->method("getBoundingBox")->willReturn(new AxisAlignedBB(0, 64, 0, 1, 66, 1));

		$mace = new Mace(new ItemIdentifier(ItemTypeIds::MACE), "Mace");
		$mace->addEnchantment(new EnchantmentInstance(VanillaEnchantments::WIND_BURST(), 3));

		$mace->calculateSmashDamage($player, $victim);
		self::assertTrue($bounced);
	}

	public function testWindChargeKnocksBackNearbyEntities() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$pos = new Position(1, 64, 0, $world);
		$victim = $this->createMock(Living::class);
		$victim->method("isAlive")->willReturn(true);
		$victim->method("getPosition")->willReturn($pos);

		$victimKnockedBack = false;
		$victim->expects(self::once())
			->method("setMotion")
			->with(self::callback(function(Vector3 $motion) use (&$victimKnockedBack) : bool{
				$victimKnockedBack = $motion->x > 0.0 && $motion->y > 0.0;
				return true;
			}));

		$world->method("getNearbyEntities")->willReturn([$victim]);

		$loc = new Location(0, 64, 0, $world, 0, 0);
		$ref = new \ReflectionClass(WindChargeEntity::class);
		/** @var WindChargeEntity $windCharge */
		$windCharge = $ref->newInstanceWithoutConstructor();
		$entityRef = new \ReflectionClass(\pocketmine\entity\Entity::class);
		$idProp = $entityRef->getProperty("id");
		$idProp->setAccessible(true);
		$idProp->setValue($windCharge, 1);
		$locProp = $entityRef->getProperty("location");
		$locProp->setAccessible(true);
		$locProp->setValue($windCharge, $loc);
		$bbProp = $entityRef->getProperty("boundingBox");
		$bbProp->setAccessible(true);
		$bbProp->setValue($windCharge, new AxisAlignedBB(0, 64, 0, 0.25, 64.25, 0.25));

		$rayTrace = new RayTraceResult(new AxisAlignedBB(0, 64, 0, 1, 65, 1), 0, new Vector3(0, 64, 0));
		$event = new ProjectileHitBlockEvent($windCharge, $rayTrace, $this->createMock(Block::class));
		$windCharge->onHit($event);

		self::assertTrue($victimKnockedBack);
		self::assertTrue($windCharge->isFlaggedForDespawn());
	}
}
