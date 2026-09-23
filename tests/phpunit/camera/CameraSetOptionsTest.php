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

namespace pocketmine\camera;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use pocketmine\camera\options\CameraEaseType;
use pocketmine\camera\options\CameraSetOptions;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;

class CameraSetOptionsTest extends TestCase{

	public function testDefaults() : void{
		$options = new CameraSetOptions();
		self::assertNull($options->getPosition());
		self::assertNull($options->getPitch());
		self::assertNull($options->getYaw());
		self::assertNull($options->getFacingPosition());
		self::assertNull($options->getViewOffset());
		self::assertNull($options->getEntityOffset());
		self::assertNull($options->getEase());
		self::assertFalse($options->isDefault());
	}

	public function testSetPositionValid() : void{
		$options = new CameraSetOptions();
		$pos = new Vector3(10.5, 64.0, -20.25);
		$options->setPosition($pos);
		self::assertTrue($pos->equals($options->getPosition() ?? new Vector3(0, 0, 0)));

		$options->setPosition(null);
		self::assertNull($options->getPosition());
	}

	public function testSetPositionNonFinite() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setPosition(new Vector3(NAN, 0.0, 0.0));
	}

	public function testSetPositionInf() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setPosition(new Vector3(0.0, INF, 0.0));
	}

	public function testSetRotationValid() : void{
		$options = new CameraSetOptions();
		$options->setRotation(45.0, 90.0);
		self::assertSame(45.0, $options->getPitch());
		self::assertEqualsWithDelta(90.0, $options->getYaw(), 0.0001);

		$options->clearRotation();
		self::assertNull($options->getPitch());
		self::assertNull($options->getYaw());
	}

	public function testSetRotationPitchBoundaries() : void{
		$options = new CameraSetOptions();

		$options->setRotation(90.0, 0.0);
		self::assertSame(90.0, $options->getPitch());

		$options->setRotation(-90.0, 0.0);
		self::assertSame(-90.0, $options->getPitch());
	}

	public function testSetRotationPitchOutOfRangeThrows() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setRotation(90.1, 0.0);
	}

	public function testSetRotationPitchUnderRangeThrows() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setRotation(-90.1, 0.0);
	}

	public function testSetRotationYawNormalization() : void{
		$options = new CameraSetOptions();

		$options->setRotation(0.0, 0.0);
		self::assertEqualsWithDelta(0.0, $options->getYaw(), 0.0001);

		$options->setRotation(0.0, 190.0);
		self::assertEqualsWithDelta(-170.0, $options->getYaw(), 0.0001);

		$options->setRotation(0.0, 270.0);
		self::assertEqualsWithDelta(-90.0, $options->getYaw(), 0.0001);

		$options->setRotation(0.0, 360.0);
		self::assertEqualsWithDelta(0.0, $options->getYaw(), 0.0001);

		$options->setRotation(0.0, -190.0);
		self::assertEqualsWithDelta(170.0, $options->getYaw(), 0.0001);

		$options->setRotation(0.0, -360.0);
		self::assertEqualsWithDelta(0.0, $options->getYaw(), 0.0001);

		$options->setRotation(0.0, 720.0);
		self::assertEqualsWithDelta(0.0, $options->getYaw(), 0.0001);
	}

	public function testSetRotationNonFinite() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setRotation(NAN, 0.0);
	}

	public function testSetFacingPositionValid() : void{
		$options = new CameraSetOptions();
		$facing = new Vector3(0.0, 10.0, 0.0);
		$options->setFacingPosition($facing);
		self::assertTrue($facing->equals($options->getFacingPosition() ?? new Vector3(1, 1, 1)));

		$options->setFacingPosition(null);
		self::assertNull($options->getFacingPosition());
	}

	public function testSetFacingPositionNonFinite() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setFacingPosition(new Vector3(0.0, -INF, 0.0));
	}

	public function testViewOffset() : void{
		$options = new CameraSetOptions();
		$offset = new Vector2(0.0, 2.0);
		$options->setViewOffset($offset);
		self::assertNotNull($options->getViewOffset());
		self::assertSame($offset->x, $options->getViewOffset()->x);
		self::assertSame($offset->y, $options->getViewOffset()->y);

		$options->setViewOffset(null);
		self::assertNull($options->getViewOffset());
	}

	public function testViewOffsetNonFinite() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setViewOffset(new Vector2(NAN, 0.0));
	}

	public function testEntityOffset() : void{
		$options = new CameraSetOptions();
		$offset = new Vector3(1.0, 0.0, 0.0);
		$options->setEntityOffset($offset);
		self::assertTrue($offset->equals($options->getEntityOffset() ?? new Vector3(0, 0, 0)));

		$options->setEntityOffset(null);
		self::assertNull($options->getEntityOffset());
	}

	public function testEntityOffsetNonFinite() : void{
		$options = new CameraSetOptions();
		$this->expectException(InvalidArgumentException::class);
		$options->setEntityOffset(new Vector3(0.0, INF, 0.0));
	}

	public function testSetEase() : void{
		$options = new CameraSetOptions();
		$options->setEase(CameraEaseType::LINEAR, 2.5);
		self::assertNotNull($options->getEase());
		self::assertSame(CameraEaseType::LINEAR, $options->getEase()->getType());
		self::assertSame(2.5, $options->getEase()->getDurationSeconds());

		$options->setEase(null, 0.0);
		self::assertNull($options->getEase());
	}

	public function testSetDefault() : void{
		$options = new CameraSetOptions();
		$options->setDefault(true);
		self::assertTrue($options->isDefault());
	}
}
