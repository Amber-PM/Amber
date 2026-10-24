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

use pocketmine\block\utils\DelayedRedstoneReceiver;
use pocketmine\block\utils\RedstoneSource;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use pocketmine\world\redstone\RedstoneEngine;
use function abs;
use function ceil;
use function max;
use function min;
use function sqrt;

class Target extends Opaque implements RedstoneSource, DelayedRedstoneReceiver{

	protected int $power = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		// NOOP - target block redstone power is runtime signal state in Bedrock edition
	}

	public function getPower() : int{
		return $this->power;
	}

	/** @return $this */
	public function setPower(int $power) : self{
		$this->power = max(0, min(15, $power));
		return $this;
	}

	public function getRedstoneOutput(int $face, bool $strongOnly, RedstoneEngine $engine) : int{
		return $this->power;
	}

	public function calculateHitPower(RayTraceResult $hitResult) : int{
		$hit = $hitResult->getHitVector();
		$face = $hitResult->getHitFace();
		$pos = $this->position;

		$dx = abs($hit->x - ($pos->x + 0.5));
		$dy = abs($hit->y - ($pos->y + 0.5));
		$dz = abs($hit->z - ($pos->z + 0.5));

		$dist = match(Facing::axis($face)){
			Axis::X => sqrt($dy * $dy + $dz * $dz),
			Axis::Y => sqrt($dx * $dx + $dz * $dz),
			Axis::Z => sqrt($dx * $dx + $dy * $dy),
			default => 0.0,
		};

		$norm = min(1.0, $dist / 0.5);
		return max(1, (int) ceil(15.0 * (1.0 - $norm)));
	}

	public function onProjectileHit(Projectile $projectile, RayTraceResult $hitResult) : void{
		$power = $this->calculateHitPower($hitResult);
		if($power > $this->power){
			$this->power = $power;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			$world->getRedstoneEngine()?->requestAround($this->position);

			$delay = $projectile instanceof Arrow ? 8 * RedstoneEngine::REDSTONE_TICK : 4 * RedstoneEngine::REDSTONE_TICK;
			$world->getRedstoneEngine()?->schedule($this->position, $delay);
		}
	}

	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		// NOOP
	}

	public function onRedstoneScheduledUpdate(RedstoneEngine $engine) : void{
		if($this->power > 0){
			$this->power = 0;
			$engine->getWorld()->setBlock($this->position, $this);
			$engine->requestAround($this->position);
		}
	}
}
