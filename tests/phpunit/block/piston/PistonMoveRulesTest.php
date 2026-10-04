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
use pocketmine\block\VanillaBlocks;

final class PistonMoveRulesTest extends TestCase{

	public function testImmovableBlocks() : void{
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::BEDROCK()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::OBSIDIAN()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::CRYING_OBSIDIAN()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::RESPAWN_ANCHOR()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::MONSTER_SPAWNER()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::ENCHANTING_TABLE()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::ENDER_CHEST()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::NETHER_PORTAL()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::END_PORTAL_FRAME()));
		self::assertTrue(PistonMoveRules::isImmovable(VanillaBlocks::BARRIER()));

		$head = new PistonHead(new BlockIdentifier(BlockTypeIds::PISTON_HEAD), "Piston Head", new BlockTypeInfo(BlockBreakInfo::instant()));
		self::assertTrue(PistonMoveRules::isImmovable($head));

		$moving = new MovingBlock(new BlockIdentifier(BlockTypeIds::MOVING_BLOCK), "Moving Block", new BlockTypeInfo(BlockBreakInfo::indestructible()));
		self::assertTrue(PistonMoveRules::isImmovable($moving));

		// Normal movable blocks
		self::assertFalse(PistonMoveRules::isImmovable(VanillaBlocks::STONE()));
		self::assertFalse(PistonMoveRules::isImmovable(VanillaBlocks::DIRT()));
		self::assertFalse(PistonMoveRules::isImmovable(VanillaBlocks::OAK_PLANKS()));
		self::assertFalse(PistonMoveRules::isImmovable(VanillaBlocks::SLIME()));
	}

	public function testBreakableOnPush() : void{
		self::assertTrue(PistonMoveRules::isBreakableOnPush(VanillaBlocks::TORCH()));
		self::assertTrue(PistonMoveRules::isBreakableOnPush(VanillaBlocks::REDSTONE_TORCH()));
		self::assertTrue(PistonMoveRules::isBreakableOnPush(VanillaBlocks::REDSTONE_WIRE()));
		self::assertTrue(PistonMoveRules::isBreakableOnPush(VanillaBlocks::DANDELION()));
		self::assertTrue(PistonMoveRules::isBreakableOnPush(VanillaBlocks::POPPY()));
		self::assertTrue(PistonMoveRules::isBreakableOnPush(VanillaBlocks::OAK_SAPLING()));

		self::assertFalse(PistonMoveRules::isBreakableOnPush(VanillaBlocks::STONE()));
		self::assertFalse(PistonMoveRules::isBreakableOnPush(VanillaBlocks::DIRT()));
		self::assertFalse(PistonMoveRules::isBreakableOnPush(VanillaBlocks::OBSIDIAN()));
		self::assertFalse(PistonMoveRules::isBreakableOnPush(VanillaBlocks::AIR()));
	}

	public function testSlimeAndHoneyAdhesion() : void{
		$slime = VanillaBlocks::SLIME();
		$stone = VanillaBlocks::STONE();
		$obsidian = VanillaBlocks::OBSIDIAN();

		// Slime sticks to movable solid blocks
		self::assertTrue(PistonMoveRules::canStickTogether($slime, $stone));
		self::assertTrue(PistonMoveRules::canStickTogether($stone, $slime));

		// Slime does not stick to immovable blocks
		self::assertFalse(PistonMoveRules::canStickTogether($slime, $obsidian));
		self::assertFalse(PistonMoveRules::canStickTogether($obsidian, $slime));

		// Normal blocks do not stick to each other
		self::assertFalse(PistonMoveRules::canStickTogether($stone, VanillaBlocks::DIRT()));

		// Honey block setup
		$honey = new class(new BlockIdentifier(BlockTypeIds::HONEY_BLOCK), "Honey Block", new BlockTypeInfo(BlockBreakInfo::instant())) extends \pocketmine\block\Transparent{};

		self::assertTrue(PistonMoveRules::canStickTogether($honey, $stone));
		self::assertTrue(PistonMoveRules::canStickTogether($stone, $honey));

		// Crucial Vanilla Rule: Slime and Honey DO NOT stick to each other
		self::assertFalse(PistonMoveRules::canStickTogether($slime, $honey));
		self::assertFalse(PistonMoveRules::canStickTogether($honey, $slime));
	}
}
