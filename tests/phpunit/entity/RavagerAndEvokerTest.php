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
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\timings\Timings;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

class RavagerAndEvokerTest extends TestCase{

	protected function setUp() : void{
		parent::setUp();
		Timings::init();
	}

	private function createMockWorld(array $nearbyEntities = []) : World{
		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods([
				"isLoaded",
				"getHighestBlockAt",
				"getNearbyEntities",
				"getPlayers",
				"addSound",
				"broadcastPacketToViewers",
				"addParticle",
				"dropItem",
				"dropExperience",
				"getChunk",
				"getMinY",
				"getMaxY",
				"isInWorld",
				"getViewersForPosition"
			])
			->getMock();
		$world->method("isLoaded")->willReturn(true);
		$world->method("getHighestBlockAt")->willReturn(64);
		$world->method("getNearbyEntities")->willReturn($nearbyEntities);
		$world->method("getPlayers")->willReturn([]);
		$world->method("dropItem")->willReturn(null);
		$world->method("dropExperience")->willReturn([]);
		$world->method("getChunk")->willReturn(null);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$world->method("isInWorld")->willReturn(true);
		$world->method("getViewersForPosition")->willReturn([]);

		(new ReflectionProperty(World::class, "minY"))->setValue($world, -64);
		(new ReflectionProperty(World::class, "maxY"))->setValue($world, 320);
		(new ReflectionProperty(World::class, "damageY"))->setValue($world, -128);
		return $world;
	}

	private function createRavager(?World $world = null) : Ravager{
		$world = $world ?? $this->createMockWorld();
		$ravager = (new ReflectionClass(Ravager::class))->newInstanceWithoutConstructor();

		static $id = 4000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($ravager, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($ravager, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($ravager, new EntitySizeInfo(2.2, 1.95, 1.9));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($ravager, 1.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($ravager, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($ravager, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($ravager, new AttributeMap());
		(new ReflectionProperty(Entity::class, "motion"))->setValue($ravager, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($ravager, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($ravager, new AxisAlignedBB(0, 0, 0, 1.95, 2.2, 1.95));
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($ravager);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($ravager, new EffectManager($ravager));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($ravager, new ArmorInventory($ravager));
		(new ReflectionProperty(Entity::class, "lastUpdate"))->setValue($ravager, 0);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($ravager, true);

		$ravager->setMaxHealth(100);
		$ravager->setHealth(100);
		return $ravager;
	}

	private function createEvoker(?World $world = null) : EvocationIllager{
		$world = $world ?? $this->createMockWorld();
		$evoker = (new ReflectionClass(EvocationIllager::class))->newInstanceWithoutConstructor();

		static $id = 5000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($evoker, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($evoker, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($evoker, new EntitySizeInfo(1.9, 0.6, 1.75));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($evoker, 1.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($evoker, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($evoker, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($evoker, new AttributeMap());
		(new ReflectionProperty(Entity::class, "motion"))->setValue($evoker, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($evoker, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($evoker, new AxisAlignedBB(0, 0, 0, 0.6, 1.9, 0.6));
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($evoker);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($evoker, new EffectManager($evoker));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($evoker, new ArmorInventory($evoker));
		(new ReflectionProperty(Entity::class, "lastUpdate"))->setValue($evoker, 0);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($evoker, true);

		$evoker->setMaxHealth(24);
		$evoker->setHealth(24);
		return $evoker;
	}

	private function createVex(?World $world = null) : Vex{
		$world = $world ?? $this->createMockWorld();
		$vex = (new ReflectionClass(Vex::class))->newInstanceWithoutConstructor();

		static $id = 6000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($vex, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($vex, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($vex, new EntitySizeInfo(0.8, 0.4, 0.65));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($vex, 1.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($vex, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($vex, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($vex, new AttributeMap());
		(new ReflectionProperty(Entity::class, "motion"))->setValue($vex, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($vex, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($vex, new AxisAlignedBB(0, 0, 0, 0.4, 0.8, 0.4));
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($vex);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($vex, new EffectManager($vex));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($vex, new ArmorInventory($vex));
		(new ReflectionProperty(Entity::class, "lastUpdate"))->setValue($vex, 0);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($vex, true);

		$vex->setMaxHealth(14);
		$vex->setHealth(14);
		return $vex;
	}

	public function testRavagerPropertiesAndNBT() : void{
		$ravager = $this->createRavager();
		$this->assertSame(EntityIds::RAVAGER, Ravager::getNetworkTypeId());
		$this->assertSame(100, (int) $ravager->getMaxHealth());
		$this->assertSame(2.2, $ravager->getSize()->getHeight());
		$this->assertSame(1.95, $ravager->getSize()->getWidth());

		// Guaranteed saddle drop
		$drops = $ravager->getDrops();
		$hasSaddle = false;
		foreach($drops as $d){
			if($d->getName() === "Saddle"){
				$hasSaddle = true;
				break;
			}
		}
		$this->assertTrue($hasSaddle, "Ravager must drop guaranteed Saddle");

		$ravager->setStunTicks(35);
		$ravager->setWave(4);
		$nbt = $ravager->saveNBT();
		$this->assertSame(35, $nbt->getInt("StunTicks"));
		$this->assertSame(4, $nbt->getInt("Wave"));
	}

	public function testRavagerStunStateAndRoar() : void{
		$ravager = $this->createRavager();
		$ravager->setStunTicks(2);
		$this->assertTrue($ravager->isStunned());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($ravager, false);
		$ravagerTick = (new ReflectionClass(Ravager::class))->getMethod("entityBaseTick");

		// Tick down stun via entityBaseTick
		$ravagerTick->invoke($ravager, 1);
		$this->assertSame(1, $ravager->getStunTicks());
		$this->assertTrue($ravager->isStunned());

		// Ticking down to 0 triggers roar counter-attack
		$ravagerTick->invoke($ravager, 1);
		$this->assertSame(0, $ravager->getStunTicks());
		$this->assertFalse($ravager->isStunned());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($ravager, true);
	}

	public function testEvocationFangSnappingAndDamage() : void{
		$target = $this->createVex(null);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, false);
		$world = $this->createMockWorld([$target]);
		(new ReflectionProperty(Entity::class, "location"))->setValue($target, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($target, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));

		$fang = (new ReflectionClass(EvocationFang::class))->newInstanceWithoutConstructor();
		static $id = 7000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($fang, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($fang, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($fang, new EntitySizeInfo(0.8, 0.8));
		(new ReflectionProperty(Entity::class, "location"))->setValue($fang, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($fang, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($fang, new AxisAlignedBB(0, 0, 0, 0.8, 0.8, 0.8));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($fang, false);

		$this->assertFalse($fang->hasSnapped());

		// Tick 12 times to warmup and snap
		$tickMethod = (new ReflectionClass(EvocationFang::class))->getMethod("entityBaseTick");
		for($i = 0; $i < 12; $i++){
			$tickMethod->invoke($fang, 1);
		}

		$this->assertTrue($fang->hasSnapped());
		// Vex started at 14 HP, took 6.0 magic damage -> 8.0 HP
		$this->assertSame(8.0, $target->getHealth());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($fang, true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);
	}

	public function testEvokerPropertiesAndTotemDrop() : void{
		$evoker = $this->createEvoker();
		$this->assertSame(EntityIds::EVOCATION_ILLAGER, EvocationIllager::getNetworkTypeId());
		$this->assertSame(24, (int) $evoker->getMaxHealth());
		$this->assertSame(1.9, $evoker->getSize()->getHeight());
		$this->assertSame(0.6, $evoker->getSize()->getWidth());

		// Guaranteed Totem of Undying drop
		$drops = $evoker->getDrops();
		$hasTotem = false;
		foreach($drops as $d){
			if($d->getName() === "Totem of Undying"){
				$hasTotem = true;
				break;
			}
		}
		$this->assertTrue($hasTotem, "Evoker must drop guaranteed Totem of Undying");
	}

	public function testVexFlightAndDecay() : void{
		$vex = $this->createVex();
		$this->assertSame(EntityIds::VEX, Vex::getNetworkTypeId());
		$this->assertSame(14, (int) $vex->getMaxHealth());
		$this->assertTrue($vex->isFireProof());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($vex, false);
		$tickMethod = (new ReflectionClass(Vex::class))->getMethod("entityBaseTick");

		// Decay timer test
		$vex->setDecayTimer(1);
		$this->assertSame(1, $vex->getDecayTimer());

		// Tick 1: decay timer reaches 0
		$tickMethod->invoke($vex, 1);
		$this->assertSame(0, $vex->getDecayTimer());
		$this->assertSame(14.0, $vex->getHealth());

		// After reaching 0, 20 ticks later takes periodic damage (1 damage)
		for($i = 0; $i < 20; $i++){
			$tickMethod->invoke($vex, 1);
		}
		$this->assertSame(13.0, $vex->getHealth());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($vex, true);
	}
}
