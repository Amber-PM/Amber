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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\tile\Tile;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class DispenserRedstoneTest extends TestCase{
	private World $world;
	private RedstoneEngine $engine;
	private array $blocks = [];
	private bool $chunksLoaded = true;
	private TileDispenser|TileDropper $tile;
	private Dispenser|Dropper $receiver;
	private Inventory $inventory;
	private array $entities = [];

	public static function receivers() : array{
		return [[false], [true]];
	}

	private function initialize(bool $dropper) : void{
		$this->world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt", "setBlock", "getTile", "isInWorld", "isChunkLoaded", "isLoaded", "dropItem", "addParticle", "addSound", "getDisplayName", "removeTile", "iterateEntityCandidates"
		])->getMock();
		$this->world->method("isInWorld")->willReturn(true);
		$this->world->method("isChunkLoaded")->willReturnCallback(fn() => $this->chunksLoaded);
		$this->world->method("isLoaded")->willReturn(true);
		$this->world->method("getDisplayName")->willReturn("test");
		$this->engine = new RedstoneEngine($this->world, 1000);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($this->world, $this->engine);
		$this->world->method("iterateEntityCandidates")->willReturnCallback(function() : \Generator{ yield from $this->entities; });
		$this->world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) : Block{
			$block = clone ($this->blocks["$x:$y:$z"] ?? VanillaBlocks::AIR());
			$block->position($this->world, $x, $y, $z);
			return $block;
		});
		$this->world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) : void{
			$block = clone $block;
			$block->position($this->world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$this->blocks["{$pos->x}:{$pos->y}:{$pos->z}"] = $block;
			$this->engine->onBlockChanged($block);
		});
		$pos = new Vector3(0, 64, 0);
		$this->tile = $dropper ? new TileDropper($this->world, $pos) : new TileDispenser($this->world, $pos);
		$this->world->method("getTile")->willReturnCallback(fn(Vector3 $target) : ?Tile => $target->equals($pos) ? $this->tile : null);
		$this->receiver = ($dropper ? VanillaBlocks::DROPPER() : VanillaBlocks::DISPENSER())->setFacing(Facing::EAST);
		$this->receiver->position($this->world, 0, 64, 0);
		$this->blocks["0:64:0"] = clone $this->receiver;
		$this->inventory = $this->tile->getInventory();
		$this->inventory->setItem(0, VanillaItems::DIAMOND()->setCount(5));
	}

	private function power(bool $powered) : void{
		$this->blocks["0:65:0"] = $powered ? VanillaBlocks::REDSTONE() : VanillaBlocks::AIR();
		$this->receiver->onRedstoneUpdate($this->engine);
	}

	private function armorCrowd() : void{
		$this->initialize(false);
		(new \ReflectionProperty(RedstoneEngine::class, "maxUpdatesPerTick"))->setValue($this->engine, 8);
		$this->inventory->setItem(0, VanillaItems::DIAMOND_HELMET());
		for($i = 0; $i < 130; ++$i){
			$entity = $this->createMock(\pocketmine\entity\Entity::class);
			(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($entity, true);
			$this->entities[] = $entity;
		}
	}

	public function testArmorCrowdResumesTheSelectedStackWithoutLosingOrDuplicatingIt() : void{
		$this->armorCrowd();
		$living = $this->createMock(\pocketmine\entity\Living::class);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($living, true);
		$living->method("isAlive")->willReturn(true);
		$living->method("canBeCollidedWith")->willReturn(true);
		$living->method("getWorld")->willReturn($this->world);
		$living->method("getBoundingBox")->willReturn(new \pocketmine\math\AxisAlignedBB(1, 64, 0, 2, 66, 1));
		$living->method("getLocation")->willReturn(new \pocketmine\entity\Location(1.5, 64, 0.5, $this->world, 0, 0));
		$armor = new \pocketmine\inventory\ArmorInventory($living);
		$living->method("getArmorInventory")->willReturn($armor);
		$this->entities[] = $living;
		$this->world->expects(self::never())->method("dropItem");
		$this->power(true);
		for($tick = 1; $tick <= 4; ++$tick){ $this->engine->tick($tick); }
		self::assertSame(0, $this->tile->getPendingDispenseSlot());
		self::assertSame(1, $this->inventory->getItem(0)->getCount());
		for($tick = 5; $tick <= 60; ++$tick){ $this->engine->tick($tick); }
		self::assertNull($this->tile->getPendingDispenseSlot());
		self::assertTrue($this->inventory->getItem(0)->isNull());
		self::assertTrue($armor->getHelmet()->equalsExact(VanillaItems::DIAMOND_HELMET()));
	}

	public function testChangingDeferredArmorSlotCancelsItsOldAction() : void{
		$this->armorCrowd();
		$this->world->expects(self::never())->method("dropItem");
		$this->power(true);
		for($tick = 1; $tick <= 4; ++$tick){ $this->engine->tick($tick); }
		self::assertSame(0, $this->tile->getPendingDispenseSlot());
		$this->inventory->setItem(0, VanillaItems::DIAMOND()->setCount(3));
		for($tick = 5; $tick <= 60; ++$tick){ $this->engine->tick($tick); }
		self::assertNull($this->tile->getPendingDispenseSlot());
		self::assertTrue($this->inventory->getItem(0)->equalsExact(VanillaItems::DIAMOND()->setCount(3)));
	}

	#[DataProvider('receivers')]
	public function testFourTickDelayAndContinuousPower(bool $dropper) : void{
		$this->initialize($dropper);
		$this->power(true);
		self::assertSame(5, $this->inventory->getItem(0)->getCount());
		for($tick = 1; $tick < 4; ++$tick){
			$this->engine->tick($tick);
			self::assertSame(5, $this->inventory->getItem(0)->getCount());
		}
		$this->engine->tick(4);
		self::assertSame(4, $this->inventory->getItem(0)->getCount());
		for($tick = 5; $tick <= 10; ++$tick){
			$this->power(true);
			$this->engine->tick($tick);
		}
		self::assertSame(4, $this->inventory->getItem(0)->getCount());
		$this->power(false);
		$this->power(true);
		$this->engine->tick(13);
		self::assertSame(4, $this->inventory->getItem(0)->getCount());
		$this->engine->tick(14);
		self::assertSame(3, $this->inventory->getItem(0)->getCount());
	}

	#[DataProvider('receivers')]
	public function testPulseSurvivesFallingEdgeAndSelectsSlotAtExecution(bool $dropper) : void{
		$this->initialize($dropper);
		$this->power(true);
		$this->engine->tick(1);
		$this->power(false);
		$this->inventory->clear(0);
		$this->inventory->setItem(8, VanillaItems::EMERALD()->setCount(2));
		$this->engine->tick(3);
		self::assertSame(2, $this->inventory->getItem(8)->getCount());
		$this->engine->tick(4);
		self::assertSame(1, $this->inventory->getItem(8)->getCount());
	}

	#[DataProvider('receivers')]
	public function testReplacementCancelsEvenForIdenticalState(bool $dropper) : void{
		$this->initialize($dropper);
		$this->power(true);
		$this->world->setBlock($this->receiver->getPosition(), clone $this->receiver);
		self::assertFalse($this->engine->isScheduled($this->receiver->getPosition()));
		$this->engine->tick(4);
		self::assertSame(5, $this->inventory->getItem(0)->getCount());
	}
	#[DataProvider('receivers')]
	public function testScheduledActionWaitsForChunkReload(bool $dropper) : void{
		$this->initialize($dropper);
		$this->power(true);
		$this->chunksLoaded = false;
		$this->engine->tick(4);
		self::assertSame(5, $this->inventory->getItem(0)->getCount());
		$this->chunksLoaded = true;
		$this->engine->tick(5);
		self::assertSame(4, $this->inventory->getItem(0)->getCount());
	}

}
