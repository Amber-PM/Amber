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

require_once __DIR__ . '/../../../support/redstone/NativeRedstoneWorldTrait.php';

use PHPUnit\Framework\TestCase;
use pocketmine\block\Lever;
use pocketmine\block\Piston;
use pocketmine\block\tile\PistonArm;
use pocketmine\block\utils\LeverFacing;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\redstone\NativeRedstoneWorldTrait;
use pocketmine\world\World;
use function abs;
use function count;
use function max;

final class PistonDustRingWorldTest extends TestCase{

	use NativeRedstoneWorldTrait;

	protected function tearDown() : void{
		$this->closeNativeWorlds();
	}

	private function placeFloor(World $world) : void{
		for($x = 12; $x <= 20; ++$x){
			for($z = 12; $z <= 20; ++$z){
				$world->setBlockAt($x, 63, $z, VanillaBlocks::GRASS());
			}
		}
	}

	private function toggleLever(World $world, int $x, int $z) : void{
		$lever = $world->getBlockAt($x, 64, $z);
		self::assertInstanceOf(Lever::class, $lever);
		$world->setBlockAt($x, 64, $z, $lever->setActivated(!$lever->isActivated()));
	}

	private function assertPiston(World $world, int $x, int $z, bool $extended) : void{
		$piston = $world->getBlockAt($x, 64, $z);
		$arm = $world->getTileAt($x, 64, $z);
		self::assertInstanceOf(Piston::class, $piston);
		self::assertInstanceOf(PistonArm::class, $arm);
		self::assertSame($extended, $piston->isPowered(), "piston at $x:$z power");
		self::assertSame($extended, $arm->isExtended(), "piston at $x:$z extension");
	}

	/**
	 * @return \Generator<string, array{list<string>}>
	 */
	public static function buildOrderProvider() : \Generator{
		yield "lever last" => [["dust", "piston", "lever", "toggle"]];
		yield "pistons last" => [["dust", "lever", "toggle", "piston"]];
		yield "dust last" => [["piston", "lever", "toggle", "dust"]];
	}

	/**
	 * layout read back from the test server world
	 * lever in the middle with a 3x3 dust ring and 12 pistons touching the ring facing out
	 *
	 * @param list<string> $order
	 * @dataProvider buildOrderProvider
	 */
	public function testLeverDustRingExtendsPistonsTouchingTheRing(array $order) : void{
		$world = $this->nativeWorld();
		$this->placeFloor($world);
		$cx = 16;
		$cz = 16;
		$pistons = [];
		foreach($order as $step){
			for($x = -2; $x <= 2; ++$x){
				for($z = -2; $z <= 2; ++$z){
					$ring = max(abs($x), abs($z));
					if($step === "dust" && $ring === 1){
						$world->setBlockAt($cx + $x, 64, $cz + $z, VanillaBlocks::REDSTONE_WIRE());
					}elseif($step === "piston" && $ring === 2 && abs($x) !== abs($z)){
						$facing = abs($x) === 2 ? ($x > 0 ? Facing::EAST : Facing::WEST) : ($z > 0 ? Facing::SOUTH : Facing::NORTH);
						$world->setBlockAt($cx + $x, 64, $cz + $z, VanillaBlocks::PISTON()->setFacing($facing));
						$pistons[] = [$cx + $x, $cz + $z];
					}
				}
			}
			if($step === "lever"){
				$world->setBlockAt($cx, 64, $cz, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X));
			}elseif($step === "toggle"){
				$this->toggleLever($world, $cx, $cz);
			}
			$this->nativeTicks($world, 5);
		}
		$this->nativeTicks($world, 10);
		self::assertSame(12, count($pistons));
		foreach($pistons as [$x, $z]){
			$this->assertPiston($world, $x, $z, true);
		}

		$this->toggleLever($world, $cx, $cz);
		$this->nativeTicks($world, 15);
		foreach($pistons as [$x, $z]){
			$this->assertPiston($world, $x, $z, false);
		}
	}

	public function testDustDoesNotPowerPistonThroughItsFront() : void{
		$world = $this->nativeWorld();
		$this->placeFloor($world);
		$world->setBlockAt(15, 64, 16, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X)->setActivated(true));
		$world->setBlockAt(16, 64, 16, VanillaBlocks::REDSTONE_WIRE());
		$world->setBlockAt(16, 64, 17, VanillaBlocks::PISTON()->setFacing(Facing::NORTH));
		$world->setBlockAt(16, 64, 15, VanillaBlocks::PISTON()->setFacing(Facing::NORTH));
		$this->nativeTicks($world, 15);
		self::assertSame(15, $world->getBlockAt(16, 64, 16)->getOutputSignalStrength());
		$this->assertPiston($world, 16, 17, false);
		$this->assertPiston($world, 16, 15, true);
	}
}
