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
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\Position;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class ObserverTest extends TestCase{

	private function createObserver(int $facing = Facing::SOUTH, bool $powered = false) : Observer{
		$observer = new Observer(new BlockIdentifier(BlockTypeIds::OBSERVER), "Observer", new BlockTypeInfo(BlockBreakInfo::instant()));
		$observer->setFacing($facing);
		$observer->setPowered($powered);
		return $observer;
	}

	private function createMockPlayer(float $pitch, int $horizontalFacing, World $world) : Player{
		/** @var Player&\PHPUnit\Framework\MockObject\MockObject $player */
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getLocation", "getHorizontalFacing", "isConnected"])
			->getMock();

		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$player->method("getLocation")->willReturn(new Location(0, 64, 0, $world, 0, $pitch));
		$player->method("getHorizontalFacing")->willReturn($horizontalFacing);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testObserverPlacementFacing() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$observer = $this->createObserver();
		$tx = new BlockTransaction($world);
		$item = new Item(new ItemIdentifier(ItemTypeIds::fromBlockTypeId(BlockTypeIds::OBSERVER)), "Observer");
		$air = $this->createMock(Block::class);
		$air->position($world, 0, 64, 0);

		// Pitch > 45 (looking down) => observer face points DOWN (detects floor)
		$player1 = $this->createMockPlayer(50.0, Facing::NORTH, $world);
		$observer1 = $this->createObserver();
		$observer1->position($world, 0, 64, 0);
		$observer1->place($tx, $item, $air, $air, Facing::UP, Vector3::zero(), $player1);
		self::assertSame(Facing::DOWN, $observer1->getFacing());

		// Pitch < -45 (looking up) => observer face points UP (detects ceiling)
		$player2 = $this->createMockPlayer(-50.0, Facing::NORTH, $world);
		$observer2 = $this->createObserver();
		$observer2->position($world, 0, 64, 0);
		$observer2->place($tx, $item, $air, $air, Facing::DOWN, Vector3::zero(), $player2);
		self::assertSame(Facing::UP, $observer2->getFacing());

		// Horizontal North => observer face points NORTH
		$player3 = $this->createMockPlayer(0.0, Facing::NORTH, $world);
		$observer3 = $this->createObserver();
		$observer3->position($world, 0, 64, 0);
		$observer3->place($tx, $item, $air, $air, Facing::SOUTH, Vector3::zero(), $player3);
		self::assertSame(Facing::NORTH, $observer3->getFacing());
	}

	public function testRedstoneOutputDirectionality() : void{
		/** @var RedstoneEngine $engine */
		$engine = (new \ReflectionClass(RedstoneEngine::class))->newInstanceWithoutConstructor();

		// Observer facing NORTH => front detects NORTH, rear (output) is SOUTH
		$unpoweredObserver = $this->createObserver(Facing::NORTH, false);
		foreach(Facing::ALL as $face){
			self::assertSame(0, $unpoweredObserver->getRedstoneOutput($face, false, $engine));
			self::assertSame(0, $unpoweredObserver->getRedstoneOutput($face, true, $engine));
		}

		$poweredObserver = $this->createObserver(Facing::NORTH, true);
		// Rear face is SOUTH
		self::assertSame(15, $poweredObserver->getRedstoneOutput(Facing::SOUTH, false, $engine));
		self::assertSame(15, $poweredObserver->getRedstoneOutput(Facing::SOUTH, true, $engine));

		// All other faces output 0
		foreach([Facing::NORTH, Facing::UP, Facing::DOWN, Facing::EAST, Facing::WEST] as $face){
			self::assertSame(0, $poweredObserver->getRedstoneOutput($face, false, $engine));
		}
	}

	public function testObserverPulseLifecycle() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$engine = new RedstoneEngine($world, 1000);

		$observer = $this->createObserver(Facing::NORTH, false);
		$observer->position($world, 10, 64, 10);

		// Step 1: onRedstoneScheduledUpdate when unpowered turns ON
		$observer->onRedstoneScheduledUpdate($engine);
		self::assertTrue($observer->isPowered());

		// Step 2: onRedstoneScheduledUpdate when powered turns OFF
		$observer->onRedstoneScheduledUpdate($engine);
		self::assertFalse($observer->isPowered());
	}
}
