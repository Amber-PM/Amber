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

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\boss\DragonFightManager;
use pocketmine\entity\boss\EnderDragon;
use pocketmine\entity\boss\Wither;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\EndCrystal;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use pocketmine\timings\Timings;
use pocketmine\world\World;

final class BossFightTest extends TestCase{

	protected function setUp() : void{
		Timings::init();
	}

	private function initLivingAttributes(Living $living, float $maxHealth, float $health) : void{
		$attr = clone AttributeFactory::getInstance()->mustGet(Attribute::HEALTH);
		$attr->setMaxValue($maxHealth);
		$attr->setDefaultValue($maxHealth);
		$attr->setValue($health);

		$attrMap = new AttributeMap();
		$attrMap->add($attr);

		$livingRef = new \ReflectionClass(Living::class);
		$hProp = $livingRef->getProperty("healthAttr");
		$hProp->setAccessible(true);
		$hProp->setValue($living, $attr);

		$mapProp = $livingRef->getProperty("attributeMap");
		$mapProp->setAccessible(true);
		$mapProp->setValue($living, $attrMap);

		$effProp = $livingRef->getProperty("effectManager");
		$effProp->setAccessible(true);
		$effProp->setValue($living, new EffectManager($living));

		$armProp = $livingRef->getProperty("armorInventory");
		$armProp->setAccessible(true);
		$armProp->setValue($living, new ArmorInventory($living));

		$abs = AttributeFactory::getInstance()->mustGet(Attribute::ABSORPTION);
		$absProp = $livingRef->getProperty("absorptionAttr");
		$absProp->setAccessible(true);
		$absProp->setValue($living, $abs);

		$entityRef = new \ReflectionClass(\pocketmine\entity\Entity::class);
		$maxHProp = $entityRef->getProperty("maxHealth");
		$maxHProp->setAccessible(true);
		$maxHProp->setValue($living, (int) $maxHealth);

		$healthProp = $entityRef->getProperty("health");
		$healthProp->setAccessible(true);
		$healthProp->setValue($living, $health);

		$idProp = $entityRef->getProperty("id");
		$idProp->setAccessible(true);
		$idProp->setValue($living, 1);
	}

	public function testDragonRegeneratesHealthFromEndCrystal() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$ref = new \ReflectionClass(EnderDragon::class);
		/** @var EnderDragon $dragon */
		$dragon = $ref->newInstanceWithoutConstructor();
		$this->initLivingAttributes($dragon, 200.0, 100.0);

		$loc = new Location(0, 64, 0, $world, 0, 0);
		$locProp = $ref->getProperty("location");
		$locProp->setAccessible(true);
		$locProp->setValue($dragon, $loc);

		$crystalRef = new \ReflectionClass(EndCrystal::class);
		/** @var EndCrystal $crystal */
		$crystal = $crystalRef->newInstanceWithoutConstructor();
		$crystalLoc = new Location(0, 74, 0, $world, 0, 0);
		$cLocProp = $crystalRef->getProperty("location");
		$cLocProp->setAccessible(true);
		$cLocProp->setValue($crystal, $crystalLoc);

		$dragon->tickCrystalHealing($crystal);
		self::assertGreaterThan(100.0, $dragon->getHealth());
		self::assertSame($crystal, $dragon->getHealingCrystal());
	}

	public function testDragonPhaseTransitions() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$ref = new \ReflectionClass(EnderDragon::class);
		/** @var EnderDragon $dragon */
		$dragon = $ref->newInstanceWithoutConstructor();
		$dragon->setPhase(EnderDragon::PHASE_CIRCLING);
		self::assertSame(EnderDragon::PHASE_CIRCLING, $dragon->getPhase());

		$dragon->setPhase(EnderDragon::PHASE_PERCHED);
		self::assertSame(EnderDragon::PHASE_PERCHED, $dragon->getPhase());

		$dragon->startDeathSequence();
		self::assertSame(EnderDragon::PHASE_DYING, $dragon->getPhase());
	}

	public function testWitherShieldBlocksProjectilesWhenLowHealth() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$ref = new \ReflectionClass(Wither::class);
		/** @var Wither $wither */
		$wither = $ref->newInstanceWithoutConstructor();
		$this->initLivingAttributes($wither, 300.0, 200.0);
		$wither->setInvulnerableTicks(0);

		// > 50% HP: not armored
		self::assertFalse($wither->isArmored());

		// <= 50% HP: armored
		$wither->setHealth(150.0);
		self::assertTrue($wither->isArmored());

		// Attack with projectile
		$ev = new EntityDamageEvent($wither, EntityDamageEvent::CAUSE_PROJECTILE, 10.0);
		$wither->attack($ev);
		self::assertTrue($ev->isCancelled());

		// Attack with melee
		$meleeEv = new EntityDamageEvent($wither, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 10.0);
		$wither->attack($meleeEv);
		self::assertFalse($meleeEv->isCancelled());
	}

	public function testDragonFightManagerTracking() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$manager = new DragonFightManager($world);
		self::assertFalse($manager->isDragonKilled());
		self::assertNull($manager->getDragon());

		$ref = new \ReflectionClass(EnderDragon::class);
		/** @var EnderDragon $dragon */
		$dragon = $ref->newInstanceWithoutConstructor();
		$manager->setDragon($dragon);
		self::assertSame($dragon, $manager->getDragon());

		$crystalRef = new \ReflectionClass(EndCrystal::class);
		/** @var EndCrystal $crystal */
		$crystal = $crystalRef->newInstanceWithoutConstructor();
		$idProp = $crystalRef->getProperty("id");
		$idProp->setAccessible(true);
		$idProp->setValue($crystal, 100);

		$manager->registerCrystal($crystal);
		self::assertCount(1, $manager->getCrystals());

		$manager->unregisterCrystal($crystal);
		self::assertCount(0, $manager->getCrystals());
	}
}
