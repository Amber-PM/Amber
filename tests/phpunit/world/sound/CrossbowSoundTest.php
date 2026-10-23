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

namespace pocketmine\world\sound;

use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

final class CrossbowSoundTest extends TestCase{

	public function testLoadStartSoundNormal() : void{
		$sound = new CrossbowLoadStartSound(false);
		self::assertFalse($sound->isQuickCharge());

		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_LOADING_START, $pk->sound);
	}

	public function testLoadStartSoundQuickCharge() : void{
		$sound = new CrossbowLoadStartSound(true);
		self::assertTrue($sound->isQuickCharge());

		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_QUICK_CHARGE_START, $pk->sound);
	}

	public function testLoadMiddleSoundNormal() : void{
		$sound = new CrossbowLoadMiddleSound(false);
		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_LOADING_MIDDLE, $pk->sound);
	}

	public function testLoadMiddleSoundQuickCharge() : void{
		$sound = new CrossbowLoadMiddleSound(true);
		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_QUICK_CHARGE_MIDDLE, $pk->sound);
	}

	public function testLoadEndSoundNormal() : void{
		$sound = new CrossbowLoadEndSound(false);
		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_LOADING_END, $pk->sound);
	}

	public function testLoadEndSoundQuickCharge() : void{
		$sound = new CrossbowLoadEndSound(true);
		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_QUICK_CHARGE_END, $pk->sound);
	}

	public function testShootSound() : void{
		$sound = new CrossbowShootSound();
		$pk = $sound->encode(Vector3::zero())[0];
		self::assertInstanceOf(LevelSoundEventPacket::class, $pk);
		self::assertSame(LevelSoundEvent::CROSSBOW_SHOOT, $pk->sound);
	}
}
