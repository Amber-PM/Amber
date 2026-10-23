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
use pocketmine\block\tile\Dispenser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
final class DispenserBoundaryTest extends TestCase{
	public function testBucketRemainsInDispenserAtWorldHeightLimit() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld", "getTile", "addSound"])->getMock();
		$world->method("isInWorld")->willReturnCallback(fn(int $x, int $y, int $z) => $y >= -64 && $y < 320);
		$tile = null;
		$world->method("getTile")->willReturnCallback(function() use (&$tile){ return $tile; });
		foreach([[319, Facing::UP], [-64, Facing::DOWN]] as [$y, $facing]){
			foreach([VanillaItems::WATER_BUCKET(), VanillaItems::LAVA_BUCKET()] as $bucket){
				$tile = new Dispenser($world, new Vector3(0, $y, 0));
				$tile->getInventory()->setItem(0, $bucket);
				$block = VanillaBlocks::DISPENSER()->setFacing($facing);
				$block->position($world, 0, $y, 0);
				$block->dispense();
				self::assertSame($bucket->getTypeId(), $tile->getInventory()->getItem(0)->getTypeId());
				self::assertSame(1, $tile->getInventory()->getItem(0)->getCount());
			}
		}
	}

	public function testBucketRemainsInDispenserBesideUnloadedChunk() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld", "getTile", "addSound", "getBlockAt", "loadChunk"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$tile = new Dispenser($world, new Vector3(15, 64, 0));
		$tile->getInventory()->setItem(0, VanillaItems::WATER_BUCKET());
		$world->method("getTile")->willReturn($tile);
		$world->expects(self::never())->method("getBlockAt");
		$world->expects(self::never())->method("loadChunk");
		$block = VanillaBlocks::DISPENSER()->setFacing(Facing::EAST);
		$block->position($world, 15, 64, 0);
		$block->dispense();
		self::assertSame(VanillaItems::WATER_BUCKET()->getTypeId(), $tile->getInventory()->getItem(0)->getTypeId());
	}
	public function testEveryRegisteredBehaviorKeepsItemsWithoutReadingUnavailableTarget() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["isInWorld", "isChunkLoaded", "addSound", "loadChunk", "getBlockAt", "dropItem", "getCollidingEntities"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(false);
		$world->expects(self::never())->method("loadChunk");
		$world->expects(self::never())->method("getBlockAt");
		$world->expects(self::never())->method("dropItem");
		$world->expects(self::never())->method("getCollidingEntities");
		$items = [VanillaItems::ARROW(), VanillaItems::SNOWBALL(), VanillaItems::EGG(), VanillaItems::SPLASH_POTION(), VanillaItems::EXPERIENCE_BOTTLE(), VanillaItems::ENDER_PEARL(), VanillaItems::BUCKET(), VanillaItems::WATER_BUCKET(), VanillaItems::LAVA_BUCKET(), VanillaBlocks::TNT()->asItem(), VanillaItems::BONE_MEAL(), VanillaItems::DIAMOND_HELMET(), VanillaBlocks::CARVED_PUMPKIN()->asItem(), VanillaBlocks::MOB_HEAD()->asItem(), VanillaItems::DIAMOND()];
		$source = new \pocketmine\block\dispenser\BlockSource($world, new Vector3(15, 64, 0), Facing::EAST);
		foreach($items as $item){
			$result = \pocketmine\block\dispenser\DispenseBehaviorRegistry::getInstance()->get($item)->dispense($source, clone $item);
			self::assertTrue($result->equalsExact($item), $item->getName());
		}
	}

}
