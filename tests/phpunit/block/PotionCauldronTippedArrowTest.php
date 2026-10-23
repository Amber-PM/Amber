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
use pocketmine\item\Arrow;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;
use function count;

final class PotionCauldronTippedArrowTest extends TestCase{

	public function testDipArrowsConvertsUpTo16ArrowsAndDecreasesFillLevel() : void{
		$setBlocks = [];
		$sounds = [];

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) use (&$setBlocks) : bool{
			$setBlocks[] = [$pos, $block];
			return true;
		});
		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) use (&$sounds) : void{
			$sounds[] = [$pos, $sound];
		});

		$cauldron = VanillaBlocks::POTION_CAULDRON();
		$cauldron->setPotionItem(VanillaItems::POTION()->setType(PotionType::SWIFTNESS));
		$cauldron->setFillLevel(3);
		$cauldron->position($world, 10, 64, 10);

		$arrows = VanillaItems::ARROW()->setCount(64);
		$returnedItems = [];

		$result = $cauldron->onInteract($arrows, Facing::UP, Vector3::zero(), null, $returnedItems);
		self::assertTrue($result);
		self::assertSame(48, $arrows->getCount());

		self::assertCount(1, $returnedItems);
		$tippedArrow = $returnedItems[0];
		self::assertInstanceOf(Arrow::class, $tippedArrow);
		self::assertSame(PotionType::SWIFTNESS, $tippedArrow->getTipType());
		self::assertSame(16, $tippedArrow->getCount());

		self::assertCount(1, $setBlocks);
		/** @var PotionCauldron $newBlock */
		$newBlock = $setBlocks[0][1];
		self::assertInstanceOf(PotionCauldron::class, $newBlock);
		self::assertSame(2, $newBlock->getFillLevel());
		self::assertCount(1, $sounds);
	}

	public function testDipPartialStackOfArrows() : void{
		$setBlocks = [];

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) use (&$setBlocks) : bool{
			$setBlocks[] = [$pos, $block];
			return true;
		});

		$cauldron = VanillaBlocks::POTION_CAULDRON();
		$cauldron->setPotionItem(VanillaItems::POTION()->setType(PotionType::SLOWNESS));
		$cauldron->setFillLevel(1);
		$cauldron->position($world, 0, 64, 0);

		$arrows = VanillaItems::ARROW()->setCount(7);
		$returnedItems = [];

		$result = $cauldron->onInteract($arrows, Facing::UP, Vector3::zero(), null, $returnedItems);
		self::assertTrue($result);
		self::assertSame(0, $arrows->getCount());

		self::assertCount(1, $returnedItems);
		$tipped = $returnedItems[0];
		self::assertInstanceOf(Arrow::class, $tipped);
		self::assertSame(PotionType::SLOWNESS, $tipped->getTipType());
		self::assertSame(7, $tipped->getCount());

		// Cauldron was at fill level 1, so withFillLevel(0) turns it into regular empty Cauldron!
		self::assertCount(1, $setBlocks);
		self::assertInstanceOf(Cauldron::class, $setBlocks[0][1]);
	}

	public function testDipAlreadyMatchingTippedArrowsDoesNothing() : void{
		$setBlocks = [];

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) use (&$setBlocks) : bool{
			$setBlocks[] = [$pos, $block];
			return true;
		});

		$cauldron = VanillaBlocks::POTION_CAULDRON();
		$cauldron->setPotionItem(VanillaItems::POTION()->setType(PotionType::SWIFTNESS));
		$cauldron->setFillLevel(3);
		$cauldron->position($world, 0, 64, 0);

		$arrows = VanillaItems::ARROW()->setTipType(PotionType::SWIFTNESS)->setCount(16);
		$returnedItems = [];

		$result = $cauldron->onInteract($arrows, Facing::UP, Vector3::zero(), null, $returnedItems);
		self::assertTrue($result);
		self::assertSame(16, $arrows->getCount());
		self::assertEmpty($returnedItems);
		self::assertEmpty($setBlocks);
	}
}
