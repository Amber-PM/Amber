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

namespace pocketmine\loot;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Zombie;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionProperty;

final class EntityLootIntegrationTest extends TestCase{

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		return $world;
	}

	public function testZombieDropsRottenFleshUsingLootTable() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 64, 0, $world, 0.0, 0.0);
		$zombie = new Zombie($location);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($zombie, true);

		// Verify Zombie implements getLootTableId()
		self::assertSame("entities/zombie", $zombie->getLootTableId());

		$allDrops = [];
		for($i = 0; $i < 25; ++$i){
			foreach($zombie->getDrops() as $item){
				$allDrops[] = $item;
			}
		}

		self::assertNotEmpty($allDrops, "Zombie must drop items from its loot table");
		foreach($allDrops as $drop){
			self::assertSame(VanillaItems::ROTTEN_FLESH()->getTypeId(), $drop->getTypeId());
		}
	}

	public function testLivingEntityQueriesLootTableOnDeath() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 64, 0, $world, 0.0, 0.0);

		$mockEntity = new class($location) extends Living{
			public static function getNetworkTypeId() : string{
				return "pocketmine:test_mob";
			}

			protected function getInitialSizeInfo() : EntitySizeInfo{
				return new EntitySizeInfo(1.0, 1.0);
			}

			public function getName() : string{
				return "TestMob";
			}

			public function getLootTableId() : string{
				return "entities/zombie";
			}
		};

		(new ReflectionProperty(Entity::class, "closed"))->setValue($mockEntity, true);

		$drops = [];
		for($i = 0; $i < 25; ++$i){
			foreach($mockEntity->getDrops() as $item){
				$drops[] = $item;
			}
		}
		self::assertNotEmpty($drops);
		foreach($drops as $item){
			self::assertSame(VanillaItems::ROTTEN_FLESH()->getTypeId(), $item->getTypeId());
		}

		// Simulate attack by Player
		$player = $this->createMock(Player::class);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$damageEvent = new EntityDamageByEntityEvent(
			damager: $player,
			entity: $mockEntity,
			cause: EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			damage: 10.0
		);
		$mockEntity->setLastDamageCause($damageEvent);

		$dropsWithPlayer = $mockEntity->getDrops();
		self::assertNotEmpty($dropsWithPlayer);
	}
}
