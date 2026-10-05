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

namespace pocketmine\block\piston;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\Chest as ChestBlock;
use pocketmine\block\Piston;
use pocketmine\block\PistonHead;
use pocketmine\block\StickyPiston;
use pocketmine\block\tile\Chest as ChestTile;
use pocketmine\block\tile\Tile;
use pocketmine\block\Torch;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

class TestPistonEntity extends Entity{
	public static function getNetworkTypeId() : string{ return "minecraft:test"; }
	protected function getInitialGravity() : float{ return 0.04; }
	protected function getInitialDragMultiplier() : float{ return 0.02; }
	protected function getInitialSizeInfo() : \pocketmine\entity\EntitySizeInfo{ return new \pocketmine\entity\EntitySizeInfo(1.0, 1.0); }

	public ?Vector3 $teleportedTo = null;

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		$this->teleportedTo = clone $pos;
		return true;
	}
}

final class PistonActionTest extends TestCase{

	/**
	 * @param Entity[] $entities
	 * @param array<string, Tile> $tiles
	 * @param list<Item> $droppedItems
	 * @return array{0: World, 1: array<string, Block>, 2: array<string, Tile>, 3: list<Item>}
	 */
	private function createTestWorld(array $entities = [], array &$tiles = [], array &$droppedItems = []) : array{
		/** @var array<string, Block> $blocks */
		$blocks = [];
		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods(["getBlockAt", "setBlockAt", "getBlock", "setBlock", "isInWorld", "isChunkLoaded", "isLoaded", "useBreakOn", "addSound", "getNearbyEntities", "getTile", "getTileAt", "addTile", "removeTile", "getDisplayName"])
			->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getDisplayName")->willReturn("test_world");

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world) : Block{
			$key = "$x:$y:$z";
			if(isset($blocks[$key])){
				$b = clone $blocks[$key];
				$b->position($world, $x, $y, $z);
				return $b;
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("getBlock")->willReturnCallback(function(Vector3 $pos) use ($world) : Block{
			return $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		});

		$world->method("getTileAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$tiles) : ?Tile{
			return $tiles["$x:$y:$z"] ?? null;
		});

		$world->method("getTile")->willReturnCallback(function(Vector3 $pos) use ($world) : ?Tile{
			return $world->getTileAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		});

		$world->method("addTile")->willReturnCallback(function(Tile $tile) use (&$tiles) : void{
			$pos = $tile->getPosition();
			$tiles[$pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ()] = $tile;
		});

		$world->method("removeTile")->willReturnCallback(function(Tile $tile) use (&$tiles) : void{
			$pos = $tile->getPosition();
			unset($tiles[$pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ()]);
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block) use (&$blocks, &$tiles, $world) : void{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$blocks[$key] = $clone;

			$tileClass = $clone->getIdInfo()->getTileClass();
			if(isset($tiles[$key])){
				$oldTile = $tiles[$key];
				if($tileClass === null || !($oldTile instanceof $tileClass)){
					unset($tiles[$key]);
					$oldTile->close();
				}
			}
			if($tileClass !== null && !isset($tiles[$key])){
				$newTile = new $tileClass($world, new Vector3($x, $y, $z));
				$tiles[$key] = $newTile;
			}
		});

		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) use ($world) : void{
			$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block);
		});

		$world->method("useBreakOn")->willReturnCallback(function(Vector3 $pos) use (&$blocks, &$tiles, &$droppedItems) : bool{
			$key = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
			if(!isset($blocks[$key])){
				return false;
			}
			$target = $blocks[$key];
			$affected = $target->getAffectedBlocks();
			foreach($affected as $affectedBlock){
				$aPos = $affectedBlock->getPosition();
				$aKey = $aPos->getFloorX() . ":" . $aPos->getFloorY() . ":" . $aPos->getFloorZ();
				if(isset($blocks[$aKey])){
					$b = $blocks[$aKey];
					unset($blocks[$aKey]);
					if(isset($tiles[$aKey])){
						$t = $tiles[$aKey];
						unset($tiles[$aKey]);
						$t->onBlockDestroyed();
					}
					foreach($b->getDrops(VanillaItems::AIR()) as $drop){
						$droppedItems[] = $drop;
					}
				}
			}
			return true;
		});

		$world->method("addSound");

		$world->method("getNearbyEntities")->willReturnCallback(function(AxisAlignedBB $bb) use (&$entities) : array{
			$result = [];
			foreach($entities as $entity){
				if($entity->getBoundingBox()->intersectsWith($bb)){
					$result[] = $entity;
				}
			}
			return $result;
		});

		return [$world, $blocks, $tiles, $droppedItems];
	}

	private function setBlock(World $world, Vector3 $pos, Block $block) : void{
		$block->position($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block);
	}

	public function testPistonExtendPushSingleBlock() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		self::assertTrue($piston->extend());
		self::assertTrue($piston->isExtended());

		// (0, 64, 1) should now have PistonHead
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertInstanceOf(PistonHead::class, $head);
		self::assertSame(Facing::SOUTH, $head->getFacing());
		self::assertFalse($head->isSticky());

		// (0, 64, 2) should now have Stone
		$stone = $world->getBlock(new Vector3(0, 64, 2));
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $stone->getTypeId());
	}

	public function testPistonRetractNonSticky() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		$piston->extend();
		self::assertTrue($piston->retract());
		self::assertFalse($piston->isExtended());

		// Head should be gone
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $head->getTypeId());

		// Stone remains at (0, 64, 2)
		$stone = $world->getBlock(new Vector3(0, 64, 2));
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $stone->getTypeId());
	}

	public function testStickyPistonExtendAndRetractPull() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$sticky = VanillaBlocks::STICKY_PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $sticky);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		self::assertTrue($sticky->extend());

		// Head is sticky
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertInstanceOf(PistonHead::class, $head);
		self::assertTrue($head->isSticky());

		// Stone moved to (0, 64, 2)
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlock(new Vector3(0, 64, 2))->getTypeId());

		// Retract pulls stone back to (0, 64, 1)
		self::assertTrue($sticky->retract());
		self::assertFalse($sticky->isExtended());

		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlock(new Vector3(0, 64, 1))->getTypeId());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock(new Vector3(0, 64, 2))->getTypeId());
	}

	public function testPistonBreaksFlowableOnPush() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::TORCH());

		self::assertTrue($piston->extend());
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertInstanceOf(PistonHead::class, $head);
	}

	public function testPistonBlockedByImmovable() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::BEDROCK());

		self::assertFalse($piston->extend());
		self::assertFalse($piston->isExtended());
		self::assertSame(VanillaBlocks::BEDROCK()->getTypeId(), $world->getBlock(new Vector3(0, 64, 1))->getTypeId());
	}

	public function testEntityDisplacementOnExtend() : void{
		$entityPos = new Vector3(0.5, 64.0, 2.5);
		$entityBB = new AxisAlignedBB(0.2, 64.0, 2.2, 0.8, 65.8, 2.8);

		$mockEntity = (new \ReflectionClass(TestPistonEntity::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($mockEntity, true);
		(new \ReflectionProperty(Entity::class, "id"))->setValue($mockEntity, 999);
		(new \ReflectionProperty(Entity::class, "boundingBox"))->setValue($mockEntity, $entityBB);
		(new \ReflectionProperty(Entity::class, "location"))->setValue($mockEntity, new \pocketmine\entity\Location($entityPos->x, $entityPos->y, $entityPos->z, null, 0.0, 0.0));

		[$world, $blocks] = $this->createTestWorld([$mockEntity]);
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		self::assertTrue($piston->extend());

		// Entity should have been displaced by +1 on Z (SOUTH)
		self::assertNotNull($mockEntity->teleportedTo);
		self::assertEqualsWithDelta(0.5, $mockEntity->teleportedTo->x, 0.001);
		self::assertEqualsWithDelta(64.0, $mockEntity->teleportedTo->y, 0.001);
		self::assertEqualsWithDelta(3.5, $mockEntity->teleportedTo->z, 0.001);
	}

	public function testStringToItemParser() : void{
		$parser = StringToItemParser::getInstance();

		$pistonItem = $parser->parse("piston");
		self::assertNotNull($pistonItem);
		self::assertSame(VanillaBlocks::PISTON()->getTypeId(), $pistonItem->getBlock()->getTypeId());

		$stickyPistonItem = $parser->parse("sticky_piston");
		self::assertNotNull($stickyPistonItem);
		self::assertSame(VanillaBlocks::STICKY_PISTON()->getTypeId(), $stickyPistonItem->getBlock()->getTypeId());
	}

	public function testVanillaBlocksMethods() : void{
		self::assertInstanceOf(Piston::class, VanillaBlocks::PISTON());
		self::assertInstanceOf(StickyPiston::class, VanillaBlocks::STICKY_PISTON());
		self::assertInstanceOf(PistonHead::class, VanillaBlocks::PISTON_HEAD());
	}

	public function testPistonExtendPushChestPreservesInventory() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$pos = new Vector3(0, 64, 0);
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);

		$chestPos = new Vector3(0, 64, 1);
		$chestBlock = VanillaBlocks::CHEST();
		$this->setBlock($world, $chestPos, $chestBlock);

		$chestTile = $world->getTile($chestPos);
		self::assertInstanceOf(ChestTile::class, $chestTile);
		$diamonds = VanillaItems::DIAMOND()->setCount(12);
		$chestTile->getInventory()->setItem(0, $diamonds);

		self::assertTrue($piston->extend());
		self::assertTrue($piston->isExtended());

		// (0, 64, 1) should now have PistonHead and no chest tile
		$head = $world->getBlock($chestPos);
		self::assertInstanceOf(PistonHead::class, $head);

		// (0, 64, 2) should now have Chest with the 12 diamonds preserved
		$destPos = new Vector3(0, 64, 2);
		$destBlock = $world->getBlock($destPos);
		self::assertInstanceOf(ChestBlock::class, $destBlock);
		$destTile = $world->getTile($destPos);
		self::assertInstanceOf(ChestTile::class, $destTile);
		$item = $destTile->getInventory()->getItem(0);
		self::assertTrue($item->equalsExact($diamonds));
		self::assertSame(12, $item->getCount());
	}

	public function testStickyPistonRetractPullChestPreservesInventory() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$pos = new Vector3(0, 64, 0);
		$sticky = VanillaBlocks::STICKY_PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $sticky);

		$chestPos = new Vector3(0, 64, 1);
		$chestBlock = VanillaBlocks::CHEST();
		$this->setBlock($world, $chestPos, $chestBlock);

		$chestTile = $world->getTile($chestPos);
		self::assertInstanceOf(ChestTile::class, $chestTile);
		$diamonds = VanillaItems::DIAMOND()->setCount(12);
		$chestTile->getInventory()->setItem(0, $diamonds);

		// Extend sticky piston, pushing chest to (0, 64, 2)
		self::assertTrue($sticky->extend());
		self::assertTrue($sticky->isExtended());

		$destPos = new Vector3(0, 64, 2);
		$destTile = $world->getTile($destPos);
		self::assertInstanceOf(ChestTile::class, $destTile);
		self::assertTrue($destTile->getInventory()->getItem(0)->equalsExact($diamonds));

		// Retract sticky piston, pulling chest back to (0, 64, 1)
		self::assertTrue($sticky->retract());
		self::assertFalse($sticky->isExtended());

		$pulledBlock = $world->getBlock($chestPos);
		self::assertInstanceOf(ChestBlock::class, $pulledBlock);
		$pulledTile = $world->getTile($chestPos);
		self::assertInstanceOf(ChestTile::class, $pulledTile);
		$item = $pulledTile->getInventory()->getItem(0);
		self::assertTrue($item->equalsExact($diamonds));
		self::assertSame(12, $item->getCount());

		// (0, 64, 2) should now be AIR with no tile
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($destPos)->getTypeId());
		self::assertNull($world->getTile($destPos));
	}

	public function testBreakExtendedPistonBaseRemovesHeadAndDropsPiston() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$basePos = new Vector3(0, 64, 0);
		$headPos = new Vector3(0, 64, 1);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $basePos, $piston);
		self::assertTrue($piston->extend());

		self::assertInstanceOf(Piston::class, $world->getBlock($basePos));
		self::assertInstanceOf(PistonHead::class, $world->getBlock($headPos));

		// Break base
		self::assertTrue($world->useBreakOn($basePos));

		// Both base and head should now be AIR
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($basePos)->getTypeId());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($headPos)->getTypeId());

		// Exactly 1 piston dropped
		self::assertCount(1, $droppedItems);
		self::assertSame(VanillaBlocks::PISTON()->asItem()->getTypeId(), $droppedItems[0]->getTypeId());
	}

	public function testBreakExtendedPistonHeadRemovesBaseAndDropsPiston() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$basePos = new Vector3(0, 64, 0);
		$headPos = new Vector3(0, 64, 1);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $basePos, $piston);
		self::assertTrue($piston->extend());

		self::assertInstanceOf(Piston::class, $world->getBlock($basePos));
		self::assertInstanceOf(PistonHead::class, $world->getBlock($headPos));

		// Break head
		self::assertTrue($world->useBreakOn($headPos));

		// Both base and head should now be AIR
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($basePos)->getTypeId());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($headPos)->getTypeId());

		// Exactly 1 piston dropped from the base
		self::assertCount(1, $droppedItems);
		self::assertSame(VanillaBlocks::PISTON()->asItem()->getTypeId(), $droppedItems[0]->getTypeId());
	}

	public function testOrphanedPistonHeadCleansUpWhenBaseDisappears() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$basePos = new Vector3(0, 64, 0);
		$headPos = new Vector3(0, 64, 1);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $basePos, $piston);
		self::assertTrue($piston->extend());

		$head = $world->getBlock($headPos);
		self::assertInstanceOf(PistonHead::class, $head);

		// Directly replace base with AIR (simulating base destruction that bypassed useBreakOn)
		$world->setBlock($basePos, VanillaBlocks::AIR());

		// Head receives neighbor update
		$head->onNearbyBlockChange();

		// Head should have cleaned itself up
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($headPos)->getTypeId());
	}

	public function testOrphanedPistonBaseCleansUpWhenHeadDisappears() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$basePos = new Vector3(0, 64, 0);
		$headPos = new Vector3(0, 64, 1);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $basePos, $piston);
		self::assertTrue($piston->extend());

		$base = $world->getBlock($basePos);
		self::assertInstanceOf(Piston::class, $base);

		// Directly replace head with AIR (simulating head removal bypassing useBreakOn)
		$world->setBlock($headPos, VanillaBlocks::AIR());

		// Base receives neighbor update
		$base->onNearbyBlockChange();

		// Base should have cleaned itself up
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock($basePos)->getTypeId());
		self::assertCount(1, $droppedItems);
		self::assertSame(VanillaBlocks::PISTON()->asItem()->getTypeId(), $droppedItems[0]->getTypeId());
	}

	public function testPushUnsupportedTileEntityRejected() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);

		$targetPos = new Vector3(0, 64, 1);
		// Monster Spawner is an immovable tile entity
		$this->setBlock($world, $targetPos, VanillaBlocks::MONSTER_SPAWNER());

		self::assertFalse($piston->extend());
		self::assertFalse($piston->isExtended());
		// Target block remains unmodified
		self::assertSame(VanillaBlocks::MONSTER_SPAWNER()->getTypeId(), $world->getBlock($targetPos)->getTypeId());
	}

	public function testPistonPushPairedChestsPreservesBothInventories() : void{
		/** @var array<string, Tile> $tiles */
		$tiles = [];
		/** @var list<Item> $droppedItems */
		$droppedItems = [];
		[$world, $blocks] = $this->createTestWorld([], $tiles, $droppedItems);

		$pos = new Vector3(0, 64, 0);
		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);

		$chest1Pos = new Vector3(0, 64, 1);
		$chest2Pos = new Vector3(0, 64, 2);
		$this->setBlock($world, $chest1Pos, VanillaBlocks::CHEST());
		$this->setBlock($world, $chest2Pos, VanillaBlocks::CHEST());

		$tile1 = $world->getTile($chest1Pos);
		$tile2 = $world->getTile($chest2Pos);
		self::assertInstanceOf(ChestTile::class, $tile1);
		self::assertInstanceOf(ChestTile::class, $tile2);

		$diamonds = VanillaItems::DIAMOND()->setCount(12);
		$emeralds = VanillaItems::EMERALD()->setCount(8);
		$tile1->getInventory()->setItem(0, $diamonds);
		$tile2->getInventory()->setItem(0, $emeralds);

		self::assertTrue($piston->extend());
		self::assertTrue($piston->isExtended());

		// (0, 64, 2) now has Chest 1 with 12 diamonds
		$newTile1 = $world->getTile(new Vector3(0, 64, 2));
		self::assertInstanceOf(ChestTile::class, $newTile1);
		self::assertTrue($newTile1->getInventory()->getItem(0)->equalsExact($diamonds));

		// (0, 64, 3) now has Chest 2 with 8 emeralds
		$newTile2 = $world->getTile(new Vector3(0, 64, 3));
		self::assertInstanceOf(ChestTile::class, $newTile2);
		self::assertTrue($newTile2->getInventory()->getItem(0)->equalsExact($emeralds));
	}

	private function createTestRedstoneEngine(World $world) : RedstoneEngine{
		$engine = (new \ReflectionClass(RedstoneEngine::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(RedstoneEngine::class, "world"))->setValue($engine, $world);
		return $engine;
	}

	public function testReconstructedExtendedPistonRetractsWhenPowerRemoved() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);

		// Extend piston
		self::assertTrue($piston->extend());
		self::assertTrue($piston->isExtended());
		self::assertInstanceOf(PistonHead::class, $world->getBlock(new Vector3(0, 64, 1)));

		// Simulate block reconstruction: fresh object with default false for powered and extended
		$reconstructed = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$reconstructed->position($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		self::assertFalse($reconstructed->isPowered());

		// Reconstructing dynamic state from world
		$reconstructed->readStateFromWorld();
		self::assertTrue($reconstructed->isExtended());

		// Redstone engine with 0 received power (no power sources around)
		$engine = $this->createTestRedstoneEngine($world);

		// Trigger redstone update with power removed
		$reconstructed->onRedstoneUpdate($engine);

		// Piston should have retracted: head is removed from (0, 64, 1)
		self::assertFalse($reconstructed->isExtended());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock(new Vector3(0, 64, 1))->getTypeId());
	}

	public function testReconstructedPistonWithoutReadStateRetractsOnRedstoneUpdate() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);

		// Extend piston
		self::assertTrue($piston->extend());
		self::assertInstanceOf(PistonHead::class, $world->getBlock(new Vector3(0, 64, 1)));

		// Fresh reconstructed instance where both powered and extended are false
		$freshPiston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$freshPiston->position($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		self::assertFalse($freshPiston->isPowered());

		$engine = $this->createTestRedstoneEngine($world);

		// onRedstoneUpdate should detect actual extension state in the world and retract
		$freshPiston->onRedstoneUpdate($engine);

		self::assertFalse($freshPiston->isExtended());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock(new Vector3(0, 64, 1))->getTypeId());
	}
}
