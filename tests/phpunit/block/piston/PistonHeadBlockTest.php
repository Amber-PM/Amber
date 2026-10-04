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
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\MovingBlock;
use pocketmine\block\PistonHead;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;

final class PistonHeadBlockTest extends TestCase{

	public function testBlockTypeIdsConstants() : void{
		self::assertSame(10830, BlockTypeIds::PISTON);
		self::assertSame(10831, BlockTypeIds::STICKY_PISTON);
		self::assertSame(10832, BlockTypeIds::PISTON_HEAD);
		self::assertSame(10833, BlockTypeIds::MOVING_BLOCK);
	}

	public function testPistonHeadProperties() : void{
		$head = new PistonHead(new BlockIdentifier(BlockTypeIds::PISTON_HEAD), "Piston Head", new BlockTypeInfo(BlockBreakInfo::instant()));
		self::assertSame(Facing::DOWN, $head->getFacing());
		self::assertFalse($head->isSticky());

		$head->setFacing(Facing::UP);
		self::assertSame(Facing::UP, $head->getFacing());

		$head->setFacing(Facing::NORTH);
		self::assertSame(Facing::NORTH, $head->getFacing());

		$head->setSticky(true);
		self::assertTrue($head->isSticky());

		$head->setSticky(false);
		self::assertFalse($head->isSticky());

		// Piston head should not drop itself when broken
		self::assertEmpty($head->getDrops(VanillaItems::DIAMOND_PICKAXE()));
	}

	public function testPistonHeadStateCloning() : void{
		$head1 = new PistonHead(new BlockIdentifier(BlockTypeIds::PISTON_HEAD), "Piston Head", new BlockTypeInfo(BlockBreakInfo::instant()));
		$head1->setFacing(Facing::EAST);
		$head1->setSticky(true);

		$head2 = clone $head1;
		self::assertSame(Facing::EAST, $head2->getFacing());
		self::assertTrue($head2->isSticky());
		self::assertSame($head1->getStateId(), $head2->getStateId());

		$head2->setSticky(false);
		self::assertNotSame($head1->getStateId(), $head2->getStateId());
	}

	public function testMovingBlockProperties() : void{
		$moving = new MovingBlock(new BlockIdentifier(BlockTypeIds::MOVING_BLOCK), "Moving Block", new BlockTypeInfo(BlockBreakInfo::indestructible()));
		self::assertSame(BlockTypeIds::MOVING_BLOCK, $moving->getTypeId());
		self::assertEmpty($moving->getDrops(VanillaItems::DIAMOND_PICKAXE()));
		self::assertEmpty($moving->getCollisionBoxes());
	}
}
