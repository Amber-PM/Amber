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

namespace pocketmine\world\hopper;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\tile\Barrel;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\block\tile\Tile;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_fill;

final class HopperTickerTest extends TestCase{

	private array $blocks = [];

	private array $tiles = [];
	private World $world;
	private array $entities = [];
	protected function setUp() : void{
		$this->blocks = $this->tiles = [];
		$this->entities = [];
		$this->world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt", "getTileAt", "isInWorld", "isChunkLoaded", "getNearbyEntities", "iterateEntityCandidates", "removeTile", "getDisplayName"
		])->getMock();
		$this->world->method("getDisplayName")->willReturn("test");
		$this->world->method("isChunkLoaded")->willReturn(true);
		$this->world->method("isInWorld")->willReturnCallback(fn(int $x, int $y, int $z) => $y >= -64 && $y < 320);
		$this->world->method("getNearbyEntities")->willReturnCallback(fn() => $this->entities);
		$this->world->method("iterateEntityCandidates")->willReturnCallback(function() : \Generator{ yield from $this->entities; });
		$this->world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) : Block{
			$block = clone ($this->blocks["$x:$y:$z"] ?? VanillaBlocks::AIR());
			$block->position($this->world, $x, $y, $z);
			return $block;
		});
		$this->world->method("getTileAt")->willReturnCallback(fn(int $x, int $y, int $z) : ?Tile => $this->tiles["$x:$y:$z"] ?? null);
	}

	private function hopper(int $x, int $y = 64, int $facing = Facing::EAST) : HopperTile{
		$this->blocks["$x:$y:0"] = VanillaBlocks::HOPPER()->setFacing($facing);
		return $this->tiles["$x:$y:0"] = new HopperTile($this->world, new Vector3($x, $y, 0));
	}

	private function barrel(int $x, int $y = 64) : Barrel{
		return $this->tiles["$x:$y:0"] = new Barrel($this->world, new Vector3($x, $y, 0));
	}

	public function testEntityFloodIsScannedInBoundedResumableSlices() : void{
		$hopper = $this->hopper(0);
		for($i = 0; $i < 130; ++$i){
			$entity = $this->createMock(Entity::class);
			(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
			$this->entities[] = $entity;
		}
		$item = $this->getMockBuilder(ItemEntity::class)->disableOriginalConstructor()->onlyMethods(["isClosed", "flagForDespawn"])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($item, true);
		(new \ReflectionProperty(Entity::class, "location"))->setValue($item, new \pocketmine\entity\Location(0.5, 65, 0.5, $this->world, 0, 0));
		(new \ReflectionProperty(Entity::class, "boundingBox"))->setValue($item, new \pocketmine\math\AxisAlignedBB(0.25, 65, 0.25, 0.75, 65.5, 0.75));
		(new \ReflectionProperty(ItemEntity::class, "item"))->setValue($item, VanillaItems::DIAMOND());
		$item->method("isClosed")->willReturn(false);
		$this->entities[] = $item;
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->tick(1);
		self::assertSame([], $hopper->getInventory()->getContents());
		$ticker->tick(2);
		self::assertSame([], $hopper->getInventory()->getContents());
		$ticker->tick(3);
		self::assertSame(1, $hopper->getInventory()->getItem(0)->getCount());
	}

	public function testUnevenChunksDoNotSpendTheBudgetRevisitingTheSameHopper() : void{
		$ticker = new HopperTicker($this->world);
		$targets = [];
		foreach([0, 2, 4, 6, 8, 10, 12, 32] as $x){
			$hopper = $this->hopper($x);
			$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND());
			$targets[] = $this->barrel($x + 1);
			$ticker->onTileAdded($hopper);
		}
		$ticker->tick(1);
		foreach($targets as $target){
			self::assertSame(1, $target->getInventory()->getItem(0)->getCount());
		}
	}

	public function testLargeHopperFarmDefersWorkFairly() : void{
		$ticker = new HopperTicker($this->world, 2);
		$targets = [];
		foreach([0, 2, 16, 18, 32] as $x){
			$hopper = $this->hopper($x);
			$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND());
			$targets[] = $this->barrel($x + 1);
			$ticker->onTileAdded($hopper);
		}
		$ticker->tick(1);
		self::assertSame(2, $ticker->getMovedCount());
		$ticker->tick(2);
		self::assertLessThanOrEqual(4, $ticker->getMovedCount());
		for($tick = 3; $tick <= 8; ++$tick){ $ticker->tick($tick); }
		foreach($targets as $target){
			self::assertSame(1, $target->getInventory()->getItem(0)->getCount());
		}
	}

	public function testEmptyReceiverCannotForwardInSameTick() : void{
		foreach([false, true] as $reverse){
			$this->blocks = $this->tiles = [];
			$a = $this->hopper(0);
			$b = $this->hopper(1);
			$destination = $this->barrel(2);
			$a->getInventory()->setItem(0, VanillaItems::DIAMOND());
			$ticker = new HopperTicker($this->world);
			foreach($reverse ? [$b, $a] : [$a, $b] as $tile){
				$ticker->onTileAdded($tile);
			}
			$ticker->tick(8);
			self::assertTrue($destination->getInventory()->getContents() === [], "An item must not cross an empty receiver in the same tick");
			self::assertSame(1, $b->getInventory()->getItem(0)->getCount());
			for($tick = 9; $tick < 16; ++$tick){
				$ticker->tick($tick);
				self::assertTrue($destination->getInventory()->getContents() === []);
			}
			$ticker->tick(16);
			self::assertSame(1, $destination->getInventory()->getItem(0)->getCount());
		}
	}

	public function testTransferCooldownIsIndividualAndAllowsPushAndPull() : void{
		$hopper = $this->hopper(0);
		$destination = $this->barrel(1);
		$source = $this->barrel(0, 65);
		$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(2));
		$source->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(2));
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->tick(3);
		self::assertSame(1, $destination->getInventory()->getItem(0)->getCount());
		self::assertSame(1, $source->getInventory()->getItem(0)->getCount());
		self::assertSame(2, $hopper->getInventory()->getItem(0)->getCount());
		for($tick = 4; $tick < 11; ++$tick){ $ticker->tick($tick); }
		self::assertSame(1, $destination->getInventory()->getItem(0)->getCount());
		$ticker->tick(11);
		self::assertSame(2, $destination->getInventory()->getItem(0)->getCount());
	}

	public function testPoweredHopperDoesNotTransfer() : void{
		$hopper = $this->hopper(0);
		$this->blocks["0:64:0"] = VanillaBlocks::HOPPER()->setFacing(Facing::EAST)->setPowered(true);
		$destination = $this->barrel(1);
		$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND());
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		for($tick = 0; $tick < 20; ++$tick){ $ticker->tick($tick); }
		self::assertTrue($destination->getInventory()->getContents() === []);
	}
	public function testFullDestinationDoesNotStartCooldown() : void{
		$hopper = $this->hopper(0);
		$destination = $this->barrel(1);
		$destination->getInventory()->setContents(array_fill(0, $destination->getInventory()->getSize(), VanillaItems::DIAMOND()->setCount(64)));
		$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND());
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->tick(3);
		self::assertSame(0, $hopper->getTransferCooldown());
		$destination->getInventory()->clear(0);
		$ticker->tick(4);
		self::assertSame(1, $destination->getInventory()->getItem(0)->getCount());
	}

	public function testCooldownPersistsAndRegistrationDoesNotResetIt() : void{
		$hopper = $this->hopper(0);
		$hopper->startTransferCooldown(100, 8);
		$reloaded = $this->hopper(0);
		$reloaded->readSaveData($hopper->saveNBT());
		self::assertSame(8, $reloaded->getTransferCooldown());
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($reloaded);
		$ticker->tick(1000);
		self::assertSame(7, $reloaded->getTransferCooldown());
		$ticker->onTileAdded($reloaded);
		$ticker->tick(1000);
		self::assertSame(7, $reloaded->getTransferCooldown());
		$ticker->tick(1001);
		self::assertSame(6, $reloaded->getTransferCooldown());
	}

	public function testRemovedTileIsNotTransferredUntilRegisteredAgain() : void{
		$hopper = $this->hopper(0);
		$destination = $this->barrel(1);
		$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND());
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->onTileRemoved($hopper);
		$ticker->tick(1);
		self::assertSame([], $destination->getInventory()->getContents());
		$ticker->onTileAdded($hopper);
		$ticker->tick(2);
		self::assertSame(1, $destination->getInventory()->getItem(0)->getCount());
	}

	public function testHopperPullsFromDispenserAndPushesToDropperWithCooldown() : void{
		$hopper = $this->hopper(0);
		$source = $this->tiles["0:65:0"] = new \pocketmine\block\tile\Dispenser($this->world, new Vector3(0, 65, 0));
		$target = $this->tiles["1:64:0"] = new \pocketmine\block\tile\Dropper($this->world, new Vector3(1, 64, 0));
		$source->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(2));
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->tick(1);
		self::assertSame(1, $hopper->getInventory()->getItem(0)->getCount());
		self::assertSame(8, $hopper->getTransferCooldown());
		$ticker->tick(8);
		self::assertTrue($target->getInventory()->getItem(0)->isNull());
		$ticker->tick(9);
		self::assertSame(1, $target->getInventory()->getItem(0)->getCount());
	}

	public function testChunkUnloadClearsRegistrationAndReloadPreservesCooldown() : void{
		$hopper = $this->hopper(0);
		$destination = $this->barrel(1);
		$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(2));
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->tick(1);
		$ticker->onChunkUnloaded(0, 0);
		$ticker->tick(100);
		self::assertSame(1, $destination->getInventory()->getItem(0)->getCount());
		self::assertSame(8, $hopper->getTransferCooldown());
		$ticker->onTileAdded($hopper);
		for($tick = 101; $tick < 109; ++$tick){
			$ticker->tick($tick);
		}
		self::assertSame(2, $destination->getInventory()->getItem(0)->getCount());
	}

	public function testItemEntityCollectionPreservesRemainderAndStartsCooldown() : void{
		$hopper = $this->hopper(0);
		$hopper->getInventory()->setContents(array_fill(0, 5, VanillaItems::DIAMOND()->setCount(64)));
		$hopper->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(63));
		$entity = $this->getMockBuilder(ItemEntity::class)->disableOriginalConstructor()->onlyMethods(["isClosed", "broadcastAnimation"])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new \ReflectionProperty(Entity::class, "id"))->setValue($entity, 123);
		(new \ReflectionProperty(Entity::class, "location"))->setValue($entity, new \pocketmine\entity\Location(0.5, 65, 0.5, $this->world, 0, 0));
		(new \ReflectionProperty(ItemEntity::class, "item"))->setValue($entity, VanillaItems::DIAMOND()->setCount(3));
		(new \ReflectionProperty(Entity::class, "boundingBox"))->setValue($entity, new \pocketmine\math\AxisAlignedBB(0.25, 65, 0.25, 0.75, 65.5, 0.75));
		$entity->method("isClosed")->willReturn(false);
		$entity->expects(self::once())->method("broadcastAnimation");
		$this->entities = [$entity];
		$ticker = new HopperTicker($this->world);
		$ticker->onTileAdded($hopper);
		$ticker->tick(1);
		self::assertSame(64, $hopper->getInventory()->getItem(0)->getCount());
		self::assertSame(2, $entity->getItem()->getCount());
		self::assertFalse($entity->isFlaggedForDespawn());
		self::assertSame(8, $hopper->getTransferCooldown());
		$hopper->getInventory()->clear(0);
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, false);
		try{
			$ticker->tick(9);
		}finally{
			(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		}
		self::assertSame(2, $hopper->getInventory()->getItem(0)->getCount());
		self::assertTrue($entity->isFlaggedForDespawn());
	}

}
