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

namespace pocketmine\block\tile;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\ExperienceManager;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\BeaconTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionProperty;

final class BeaconTest extends TestCase{

	private function createMockPlayer() : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getXpManager", "isCreative", "onDispose"])
			->getMock();

		$xpManager = $this->createMock(ExperienceManager::class);
		$player->method("getXpManager")->willReturn($xpManager);
		$player->method("isCreative")->willReturn(false);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	public function testPyramidCalculationLayers1Through4() : void{
		/** @var array<string, Block> $blocks */
		$blocks = [];

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks){
			$key = "$x:$y:$z";
			return $blocks[$key] ?? VanillaBlocks::AIR();
		});

		$beacon = new Beacon($world, new Vector3(0, 64, 0));

		// Tier 0: No blocks below
		self::assertSame(0, $beacon->calculatePyramid());

		// Layer 1: 3x3 of Iron Blocks at y=63
		for($x = -1; $x <= 1; ++$x){
			for($z = -1; $z <= 1; ++$z){
				$blocks["$x:63:$z"] = VanillaBlocks::IRON();
			}
		}
		self::assertSame(1, $beacon->calculatePyramid());

		// Layer 2: 5x5 of Gold Blocks at y=62
		for($x = -2; $x <= 2; ++$x){
			for($z = -2; $z <= 2; ++$z){
				$blocks["$x:62:$z"] = VanillaBlocks::GOLD();
			}
		}
		self::assertSame(2, $beacon->calculatePyramid());

		// Layer 3: 7x7 of Diamond Blocks at y=61
		for($x = -3; $x <= 3; ++$x){
			for($z = -3; $z <= 3; ++$z){
				$blocks["$x:61:$z"] = VanillaBlocks::DIAMOND();
			}
		}
		self::assertSame(3, $beacon->calculatePyramid());

		// Layer 4: 9x9 of Netherite Blocks at y=60
		for($x = -4; $x <= 4; ++$x){
			for($z = -4; $z <= 4; ++$z){
				$blocks["$x:60:$z"] = VanillaBlocks::NETHERITE();
			}
		}
		self::assertSame(4, $beacon->calculatePyramid());
	}

	public function testIncompleteLayerCapsTier() : void{
		/** @var array<string, Block> $blocks */
		$blocks = [];

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks){
			$key = "$x:$y:$z";
			return $blocks[$key] ?? VanillaBlocks::AIR();
		});

		$beacon = new Beacon($world, new Vector3(0, 64, 0));

		// Complete Layer 1
		for($x = -1; $x <= 1; ++$x){
			for($z = -1; $z <= 1; ++$z){
				$blocks["$x:63:$z"] = VanillaBlocks::IRON();
			}
		}

		// Incomplete Layer 2 (missing 2, 62, 2)
		for($x = -2; $x <= 2; ++$x){
			for($z = -2; $z <= 2; ++$z){
				if($x === 2 && $z === 2){
					continue; // left as air!
				}
				$blocks["$x:62:$z"] = VanillaBlocks::GOLD();
			}
		}

		self::assertSame(1, $beacon->calculatePyramid());
	}

	public function testSkyClearance() : void{
		/** @var array<string, Block> $blocks */
		$blocks = [];

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMaxY")->willReturn(70);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks){
			$key = "$x:$y:$z";
			return $blocks[$key] ?? VanillaBlocks::AIR();
		});

		$beacon = new Beacon($world, new Vector3(0, 64, 0));

		// Air to sky: clear!
		self::assertTrue($beacon->isSkyClear());

		// Glass block at y=66 (transparent): clear!
		$blocks["0:66:0"] = VanillaBlocks::GLASS();
		self::assertTrue($beacon->isSkyClear());

		// Opaque stone block at y=67: obstructed!
		$blocks["0:67:0"] = VanillaBlocks::STONE();
		self::assertFalse($beacon->isSkyClear());
	}

	public function testPaymentItemValidation() : void{
		self::assertTrue(Beacon::isValidPaymentItem(VanillaItems::IRON_INGOT()));
		self::assertTrue(Beacon::isValidPaymentItem(VanillaItems::GOLD_INGOT()));
		self::assertTrue(Beacon::isValidPaymentItem(VanillaItems::DIAMOND()));
		self::assertTrue(Beacon::isValidPaymentItem(VanillaItems::EMERALD()));
		self::assertTrue(Beacon::isValidPaymentItem(VanillaItems::NETHERITE_INGOT()));

		self::assertFalse(Beacon::isValidPaymentItem(VanillaItems::STICK()));
		self::assertFalse(Beacon::isValidPaymentItem(VanillaItems::APPLE()));
		self::assertFalse(Beacon::isValidPaymentItem(VanillaBlocks::DIRT()->asItem()));
	}

	public function testBeaconTransactionSuccess() : void{
		$player = $this->createMockPlayer();

		/** @var array<string, Block> $blocks */
		$blocks = [];
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks){
			$key = "$x:$y:$z";
			return $blocks[$key] ?? VanillaBlocks::AIR();
		});

		// Build 3x3 iron layer
		for($x = -1; $x <= 1; ++$x){
			for($z = -1; $z <= 1; ++$z){
				$blocks["$x:63:$z"] = VanillaBlocks::IRON();
			}
		}

		$beacon = new Beacon($world, new Vector3(0, 64, 0));
		$payment = VanillaItems::DIAMOND();

		$inv = new SimpleInventory(1);
		$inv->setItem(0, $payment);

		$tx = new BeaconTransaction($player, $beacon, $payment, Beacon::EFFECT_SPEED, 0);
		$tx->addAction(new SlotChangeAction($inv, 0, $payment, VanillaItems::AIR()));
		$tx->validate();
		$tx->execute();

		self::assertSame(Beacon::EFFECT_SPEED, $beacon->getPrimaryEffect());
		self::assertSame(0, $beacon->getSecondaryEffect());
	}

	public function testBeaconTransactionInvalidEffectThrows() : void{
		$player = $this->createMockPlayer();

		/** @var array<string, Block> $blocks */
		$blocks = [];
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks){
			$key = "$x:$y:$z";
			return $blocks[$key] ?? VanillaBlocks::AIR();
		});

		// Tier 1 pyramid only supports SPEED (1) and HASTE (3)
		for($x = -1; $x <= 1; ++$x){
			for($z = -1; $z <= 1; ++$z){
				$blocks["$x:63:$z"] = VanillaBlocks::IRON();
			}
		}

		$beacon = new Beacon($world, new Vector3(0, 64, 0));
		$payment = VanillaItems::DIAMOND();

		// STRENGTH (5) requires tier 3, should throw
		$this->expectException(TransactionValidationException::class);
		new BeaconTransaction($player, $beacon, $payment, Beacon::EFFECT_STRENGTH, 0);
	}
}
