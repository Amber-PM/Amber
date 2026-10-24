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
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Trident as TridentEntity;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\IncompatibleEnchantmentRegistry;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

final class TridentCombatTest extends TestCase{

	public function testTridentEnchantmentIncompatibilities() : void{
		$registry = IncompatibleEnchantmentRegistry::getInstance();
		$riptide = VanillaEnchantments::RIPTIDE();
		$loyalty = VanillaEnchantments::LOYALTY();
		$channeling = VanillaEnchantments::CHANNELING();

		self::assertFalse($registry->areCompatible($riptide, $loyalty));
		self::assertFalse($registry->areCompatible($riptide, $channeling));
	}

	public function testRiptideFailsWhenDry() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isRaining")->willReturn(false);
		$air = $this->createMock(Block::class);
		$air->method("getTypeId")->willReturn(BlockTypeIds::AIR);
		$world->method("getBlock")->willReturn($air);

		$player = $this->createMock(Player::class);
		$player->method("getWorld")->willReturn($world);
		$player->method("getLocation")->willReturn(new Location(0, 64, 0, $world, 0, 0));
		$player->method("getPosition")->willReturn(new Position(0, 64, 0, $world));
		$player->method("getItemUseDuration")->willReturn(20);
		$player->method("isUnderwater")->willReturn(false);

		$trident = new Trident(new ItemIdentifier(ItemTypeIds::TRIDENT), "Trident");
		$trident->addEnchantment(new EnchantmentInstance(VanillaEnchantments::RIPTIDE(), 3));

		$returned = [];
		$result = $trident->onReleaseUsing($player, $returned);
		self::assertSame(ItemUseResult::FAIL, $result);
	}

	public function testRiptideLaunchesPlayerInWater() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isRaining")->willReturn(false);
		$water = $this->createMock(Block::class);
		$water->method("getTypeId")->willReturn(BlockTypeIds::WATER);
		$world->method("getBlock")->willReturn($water);
		$world->method("getNearbyEntities")->willReturn([]);

		$player = $this->createMock(Player::class);
		$player->method("getWorld")->willReturn($world);
		$player->method("getLocation")->willReturn(new Location(0, 64, 0, $world, 0, 0));
		$player->method("getPosition")->willReturn(new Position(0, 64, 0, $world));
		$player->method("getDirectionVector")->willReturn(new Vector3(0, 1, 0));
		$player->method("getItemUseDuration")->willReturn(20);
		$player->method("isUnderwater")->willReturn(true);
		$player->method("getBoundingBox")->willReturn(new \pocketmine\math\AxisAlignedBB(0, 64, 0, 0.6, 65.8, 0.6));

		$player->expects(self::once())
			->method("setMotion")
			->with(self::callback(function(Vector3 $motion) : bool{
				// Speed for level 3 = 0.75 * 3 + 1.25 = 3.5
				return abs($motion->y - 3.5) < 0.01;
			}));

		$player->expects(self::once())
			->method("resetFallDistance");

		$trident = new Trident(new ItemIdentifier(ItemTypeIds::TRIDENT), "Trident");
		$trident->addEnchantment(new EnchantmentInstance(VanillaEnchantments::RIPTIDE(), 3));

		$returned = [];
		$result = $trident->onReleaseUsing($player, $returned);
		self::assertSame(ItemUseResult::SUCCESS, $result);
	}

	public function testLoyaltyTridentReturnsAfterDelay() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$player = $this->createMock(Player::class);
		$player->method("isAlive")->willReturn(true);
		$player->method("isClosed")->willReturn(false);
		$player->method("getEyePos")->willReturn(new Vector3(10, 65, 10));
		$player->method("getWorld")->willReturn($world);

		$tridentItem = new Trident(new ItemIdentifier(ItemTypeIds::TRIDENT), "Trident");
		$tridentItem->addEnchantment(new EnchantmentInstance(VanillaEnchantments::LOYALTY(), 3));

		$loc = new Location(10, 64, 20, $world, 0, 0);

		/** @var TridentEntity&\PHPUnit\Framework\MockObject\MockObject $entity */
		$entity = $this->getMockBuilder(TridentEntity::class)
			->disableOriginalConstructor()
			->onlyMethods(["getOwningEntity", "getWorld", "broadcastSound", "move", "updateMovement"])
			->getMock();

		$entity->method("getOwningEntity")->willReturn($player);
		$entity->method("getWorld")->willReturn($world);

		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "location"))->setValue($entity, $loc);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "motion"))->setValue($entity, Vector3::zero());
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($entity, false);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "networkProperties"))->setValue($entity, new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection());
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "boundingBox"))->setValue($entity, new \pocketmine\math\AxisAlignedBB(0, 0, 0, 0, 0, 0));
		(new \ReflectionProperty(TridentEntity::class, "item"))->setValue($entity, $tridentItem);
		(new \ReflectionProperty(TridentEntity::class, "canCollide"))->setValue($entity, true);

		// Hit block
		$block = $this->createMock(Block::class);
		$block->position($world, 10, 64, 20);
		$hitResult = new RayTraceResult(new \pocketmine\math\AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(10, 64, 20));
		$onHitBlock = new \ReflectionMethod(TridentEntity::class, "onHitBlock");
		$onHitBlock->invoke($entity, $block, $hitResult);

		// Tick 9 times: delay not reached yet
		$entityBaseTick = new \ReflectionMethod(TridentEntity::class, "entityBaseTick");
		for($i = 0; $i < 9; ++$i){
			$entityBaseTick->invoke($entity, 1);
		}

		// Tick 10: reaches 10 delay ticks and initiates return motion
		$entityBaseTick->invoke($entity, 1);

		self::assertGreaterThan(0.0, $entity->getMotion()->length());
	}
}
