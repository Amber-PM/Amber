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
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Dispenser;
use pocketmine\block\tile\Dropper;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
final class HopperTransferIntegrationTest extends TestCase{
	public function testDropperTransfersOneItemThroughTransferService() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getTile", "addSound", "isInWorld", "isChunkLoaded"])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$source = new Dropper($world, new Vector3(0, 64, 0));
		$target = new Chest($world, new Vector3(1, 64, 0));
		$source->getInventory()->setItem(0, VanillaItems::DIAMOND());
		$world->method("getTile")->willReturnCallback(fn(Vector3 $pos) => $pos->getFloorX() === 0 ? $source : $target);
		$block = VanillaBlocks::DROPPER()->setFacing(Facing::EAST);
		$block->position($world, 0, 64, 0);
		$block->drop();
		self::assertTrue($source->getInventory()->getItem(0)->isNull());
		self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $target->getInventory()->getItem(0)->getTypeId());
		self::assertSame(1, $target->getInventory()->getItem(0)->getCount());
	}
	public function testPolicySupportsDispenserAndDropper() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->getMock();
		$world->method("isLoaded")->willReturn(true);
		$policy = new ContainerTransferPolicy();
		self::assertTrue($policy->isSupported(new Dropper($world, new Vector3(0, 64, 0))));
		self::assertTrue($policy->isSupported(new Dispenser($world, new Vector3(1, 64, 0))));
	}
}
