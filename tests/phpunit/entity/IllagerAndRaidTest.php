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
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\timings\Timings;
use pocketmine\world\raid\Raid;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

class IllagerAndRaidTest extends TestCase{

	protected function setUp() : void{
		parent::setUp();
		Timings::init();
	}

	private function createMockWorld() : World{
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
				"dropExperience"
			])
			->getMock();
		$world->method("isLoaded")->willReturn(true);
		$world->method("getHighestBlockAt")->willReturn(64);
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("getPlayers")->willReturn([]);
		$world->method("dropItem")->willReturn(null);
		$world->method("dropExperience")->willReturn([]);
		return $world;
	}

	private function createPillager(?World $world = null, bool $isCaptain = false) : Pillager{
		$world = $world ?? $this->createMockWorld();
		$pillager = (new ReflectionClass(Pillager::class))->newInstanceWithoutConstructor();

		static $id = 1000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($pillager, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($pillager, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($pillager, new EntitySizeInfo(1.9, 0.6, 1.75));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($pillager, 1.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($pillager, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($pillager, new AttributeMap());
		(new ReflectionProperty(Entity::class, "motion"))->setValue($pillager, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($pillager, new AxisAlignedBB(0, 0, 0, 0.6, 1.9, 0.6));
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($pillager);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($pillager, new EffectManager($pillager));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($pillager, new ArmorInventory($pillager));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($pillager, true);

		$pillager->setMaxHealth(24);
		$pillager->setHealth(24);
		$pillager->setRaidCaptain($isCaptain);
		return $pillager;
	}

	private function createVindicator(?World $world = null, bool $isJohnny = false) : Vindicator{
		$world = $world ?? $this->createMockWorld();
		$vindicator = (new ReflectionClass(Vindicator::class))->newInstanceWithoutConstructor();

		static $id = 2000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($vindicator, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($vindicator, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($vindicator, new EntitySizeInfo(1.9, 0.6, 1.75));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($vindicator, 1.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($vindicator, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($vindicator, new AttributeMap());
		(new ReflectionProperty(Entity::class, "motion"))->setValue($vindicator, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($vindicator, new AxisAlignedBB(0, 0, 0, 0.6, 1.9, 0.6));
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($vindicator);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($vindicator, new EffectManager($vindicator));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($vindicator, new ArmorInventory($vindicator));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($vindicator, true);

		$vindicator->setMaxHealth(24);
		$vindicator->setHealth(24);
		$vindicator->setJohnny($isJohnny);
		return $vindicator;
	}

	private function createMockPlayer(?World $world = null) : Player{
		$world = $world ?? $this->createMockWorld();
		$player = $this->createMock(Player::class);
		static $id = 3000;
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, $id++);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($player, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, new Location(0.0, 65.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(0, 0, 0, 0.6, 1.8, 0.6));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);

		$player->method("isOnline")->willReturn(true);
		$player->method("isAlive")->willReturn(true);
		$player->method("getId")->willReturn($id);
		$player->method("getEffects")->willReturn((new ReflectionProperty(Living::class, "effectManager"))->getValue($player));

		return $player;
	}

	public function testPillagerPropertiesAndNBT() : void{
		$pillager = $this->createPillager(null, true);
		$this->assertSame(EntityIds::PILLAGER, Pillager::getNetworkTypeId());
		$this->assertSame(24, (int) $pillager->getMaxHealth());
		$this->assertSame(1.9, $pillager->getSize()->getHeight());
		$this->assertSame(0.6, $pillager->getSize()->getWidth());
		$this->assertTrue($pillager->isRaidCaptain());

		$pillager->setWave(3);
		$this->assertSame(3, $pillager->getWave());

		$nbt = $pillager->saveNBT();
		$this->assertSame(1, $nbt->getByte("IsCaptain"));
		$this->assertSame(3, $nbt->getInt("Wave"));

		$copy = $this->createPillager(null, false);
		(new ReflectionClass(Pillager::class))->getMethod("initEntity")->invoke($copy, $nbt);
		$this->assertTrue($copy->isRaidCaptain());
		$this->assertSame(3, $copy->getWave());
	}

	public function testPillagerCaptainDeathGivesBadOmen() : void{
		$world = $this->createMockWorld();
		$player = $this->createMockPlayer($world);
		$pillager = $this->createPillager($world, true);

		$ev = $this->createMock(EntityDamageByEntityEvent::class);
		$ev->method("getDamager")->willReturn($player);
		$pillager->setLastDamageCause($ev);
		(new ReflectionClass(Pillager::class))->getMethod("onDeath")->invoke($pillager);

		$this->assertTrue($player->getEffects()->has(VanillaEffects::BAD_OMEN()));
		$effect = $player->getEffects()->get(VanillaEffects::BAD_OMEN());
		$this->assertNotNull($effect);
		$this->assertSame(0, $effect->getAmplifier()); // Level 1
		$this->assertSame(120000, $effect->getDuration());

		// Kill second captain: increments amplifier
		$captain2 = $this->createPillager($world, true);
		$captain2->setLastDamageCause($ev);
		(new ReflectionClass(Pillager::class))->getMethod("onDeath")->invoke($captain2);

		$effect2 = $player->getEffects()->get(VanillaEffects::BAD_OMEN());
		$this->assertNotNull($effect2);
		$this->assertSame(1, $effect2->getAmplifier()); // Level 2
	}

	public function testPillagerCaptainDropsOminousBanner() : void{
		$pillager = $this->createPillager(null, true);
		$drops = $pillager->getDrops();

		$hasBanner = false;
		foreach($drops as $d){
			if($d->getName() === "Ominous Banner"){
				$hasBanner = true;
				break;
			}
		}
		$this->assertTrue($hasBanner, "Pillager captain must drop Ominous Banner on death");
	}

	public function testVindicatorPropertiesAndJohnny() : void{
		$vindicator = $this->createVindicator(null, false);
		$this->assertSame(EntityIds::VINDICATOR, Vindicator::getNetworkTypeId());
		$this->assertSame(24, (int) $vindicator->getMaxHealth());
		$this->assertFalse($vindicator->isJohnny());

		$vindicator->setNameTag("Johnny");
		$this->assertTrue($vindicator->isJohnny());

		$nbt = $vindicator->saveNBT();
		$this->assertSame(1, $nbt->getByte("IsJohnny"));
	}

	public function testRaidWaveCalculations() : void{
		$world = $this->createMockWorld();

		// Easy: 3 waves
		$raidEasy = new Raid($world, Vector3::zero(), 1, 1);
		$this->assertSame(3, $raidEasy->getTotalWaves());

		// Normal: 5 waves
		$raidNormal = new Raid($world, Vector3::zero(), 1, 2);
		$this->assertSame(5, $raidNormal->getTotalWaves());

		// Hard: 7 waves
		$raidHard = new Raid($world, Vector3::zero(), 1, 3);
		$this->assertSame(7, $raidHard->getTotalWaves());

		// Bonus wave for Bad Omen level > 1
		$raidBonus = new Raid($world, Vector3::zero(), 2, 2);
		$this->assertSame(6, $raidBonus->getTotalWaves());
	}

	public function testRaidVictoryGrantsHeroOfTheVillage() : void{
		$world = $this->createMockWorld();
		$player = $this->createMockPlayer($world);

		$raid = new Raid($world, Vector3::zero(), 1, 2);
		$pillager = $this->createPillager($world, false);
		$pillager->setRaid($raid);

		// Record player participation
		$raid->onRaiderDeath($pillager, $player);

		// Trigger victory
		$raid->triggerVictory();
		$this->assertSame(Raid::STATE_VICTORY, $raid->getState());
		$this->assertTrue($player->getEffects()->has(VanillaEffects::VILLAGE_HERO()));

		$hero = $player->getEffects()->get(VanillaEffects::VILLAGE_HERO());
		$this->assertNotNull($hero);
		$this->assertSame(48000, $hero->getDuration());
	}
}
