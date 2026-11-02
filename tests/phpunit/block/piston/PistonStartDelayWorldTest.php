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

final class PistonStartDelayWorldTest extends TestCase{

	use NativeRedstoneWorldTrait;

	protected function tearDown() : void{
		$this->closeNativeWorlds();
	}

	private function buildLeverAndPiston() : World{
		$world = $this->nativeWorld();
		for($x = 12; $x <= 20; ++$x){
			for($z = 12; $z <= 20; ++$z){
				$world->setBlockAt($x, 63, $z, VanillaBlocks::GRASS());
			}
		}
		$world->setBlockAt(15, 64, 16, VanillaBlocks::LEVER()->setFacing(LeverFacing::UP_AXIS_X));
		$world->setBlockAt(16, 64, 16, VanillaBlocks::PISTON()->setFacing(Facing::NORTH));
		$this->nativeTicks($world, 5);
		return $world;
	}

	private function setLever(World $world, bool $on) : void{
		$lever = $world->getBlockAt(15, 64, 16);
		self::assertInstanceOf(Lever::class, $lever);
		$world->setBlockAt(15, 64, 16, $lever->setActivated($on));
	}

	private function arm(World $world) : PistonArm{
		$arm = $world->getTileAt(16, 64, 16);
		self::assertInstanceOf(PistonArm::class, $arm);
		return $arm;
	}

	private function started(World $world) : bool{
		$arm = $this->arm($world);
		return $arm->isMoving() || $arm->isExtended();
	}

	public function testPistonStartsAfterTheDelayNotInstantly() : void{
		$world = $this->buildLeverAndPiston();
		$this->setLever($world, true);
		$this->nativeTicks($world, 1);
		self::assertFalse($this->started($world), "the piston waits before it starts");
		$this->nativeTicks($world, 20);
		self::assertTrue($this->arm($world)->isExtended());
		self::assertFalse($this->arm($world)->isMoving());
	}

	public function testPulseShorterThanTheDelayStillMovesThePiston() : void{
		$world = $this->buildLeverAndPiston();
		$this->setLever($world, true);
		$this->nativeTicks($world, 1);
		$this->setLever($world, false);
		$moved = false;
		for($tick = 0; $tick < 40; ++$tick){
			$this->nativeTicks($world, 1);
			$moved = $moved || $this->started($world);
		}
		self::assertTrue($moved, "a short pulse still extends the piston");
		self::assertFalse($this->arm($world)->isExtended(), "then it follows the lever back");
		self::assertFalse($this->arm($world)->isMoving());
	}

	/**
	 * @return \Generator<string, array{int, int, bool}>
	 */
	public static function flickProvider() : \Generator{
		yield "one flick per tick ending on" => [1, 5, true];
		yield "one flick per tick ending off" => [1, 6, false];
		yield "one flick every two ticks ending on" => [2, 5, true];
		yield "one flick every three ticks ending off" => [3, 4, false];
	}

	/**
	 * @dataProvider flickProvider
	 */
	public function testFastFlickingEndsWithThePistonFollowingTheLever(int $ticksBetween, int $flicks, bool $endsOn) : void{
		$world = $this->buildLeverAndPiston();
		$on = false;
		$moved = false;
		for($i = 0; $i < $flicks; ++$i){
			$on = !$on;
			$this->setLever($world, $on);
			for($t = 0; $t < $ticksBetween; ++$t){
				$this->nativeTicks($world, 1);
				$moved = $moved || $this->started($world);
			}
		}
		self::assertSame($endsOn, $on);
		$this->nativeTicks($world, 80);
		self::assertTrue($moved, "the piston reacted to the flicking");
		self::assertSame($endsOn, $this->arm($world)->isExtended(), "the piston settles on the lever state");
		self::assertFalse($this->arm($world)->isMoving());
		$piston = $world->getBlockAt(16, 64, 16);
		self::assertInstanceOf(Piston::class, $piston);
	}
}
