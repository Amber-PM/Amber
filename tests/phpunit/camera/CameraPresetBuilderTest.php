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
use pocketmine\camera\preset\BuiltInCameraPresets;
use pocketmine\camera\preset\CameraAudioListener;
use pocketmine\camera\preset\CameraControlScheme;
use pocketmine\camera\preset\CameraPresetBuilder;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class CameraPresetBuilderTest extends TestCase{

	public function testValidIdentifierAndParent() : void{
		$preset = (new CameraPresetBuilder("amber:security_cam", BuiltInCameraPresets::FREE))
			->setPosition(new Vector3(100.0, 64.0, 200.0))
			->setRotation(15.0, -90.0)
			->build();

		self::assertSame("amber:security_cam", $preset->getIdentifier());
		self::assertSame(BuiltInCameraPresets::FREE, $preset->getParent());
		self::assertNotNull($preset->getPosition());
		self::assertSame(100.0, $preset->getPosition()->x);
		self::assertSame(15.0, $preset->getPitch());
		self::assertEqualsWithDelta(-90.0, $preset->getYaw(), 0.0001);
		self::assertSame(ProtocolInfo::PROTOCOL_1_20_0, $preset->getMinimumProtocol());
	}

	public function testInvalidIdentifierThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		new CameraPresetBuilder("nonamespace", BuiltInCameraPresets::FREE);
	}

	public function testEmptyIdentifierThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		new CameraPresetBuilder("", BuiltInCameraPresets::FREE);
	}

	public function testEmptyParentThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		new CameraPresetBuilder("amber:test", "");
	}

	public function testInvalidPositionThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setPosition(new Vector3(NAN, 0.0, 0.0));
	}

	public function testInvalidPitchThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setRotation(95.0, 0.0);
	}

	public function testInvalidYawThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setRotation(0.0, INF);
	}

	public function testNegativeRadiusThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setRadius(-1.0);
	}

	public function testNegativeBlockListeningRadiusThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setBlockListeningRadius(-0.5);
	}

	public function testNegativeRotationSpeedThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setRotationSpeed(-1.0);
	}

	public function testZeroRotationSpeedAccepted() : void{
		$preset = (new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE))
			->setRotationSpeed(0.0)
			->build();
		self::assertSame(0.0, $preset->getRotationSpeed());
	}

	public function testYawLimitInvertedThrows() : void{
		$builder = new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE);
		$this->expectException(InvalidArgumentException::class);
		$builder->setYawLimits(50.0, 20.0);
	}

	public function testMinimumProtocolTiers() : void{
		// 1.20.10 audio listener or player effects
		$p1 = (new CameraPresetBuilder("amber:audio", BuiltInCameraPresets::FREE))
			->setAudioListener(CameraAudioListener::PLAYER)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_20_10, $p1->getMinimumProtocol());

		// 1.21.20 viewOffset or radius
		$p2 = (new CameraPresetBuilder("amber:view_offset", BuiltInCameraPresets::FREE))
			->setViewOffset(new Vector2(1.0, 1.0))
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_20, $p2->getMinimumProtocol());

		$p2b = (new CameraPresetBuilder("amber:radius", BuiltInCameraPresets::FREE))
			->setRadius(5.0)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_20, $p2b->getMinimumProtocol());

		// 1.21.30 rotationSpeed, snapToTarget, entityOffset
		$p3 = (new CameraPresetBuilder("amber:rot_speed", BuiltInCameraPresets::FREE))
			->setRotationSpeed(2.0)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_30, $p3->getMinimumProtocol());

		$p3b = (new CameraPresetBuilder("amber:snap", BuiltInCameraPresets::FREE))
			->setSnapToTarget(true)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_30, $p3b->getMinimumProtocol());

		$p3c = (new CameraPresetBuilder("amber:ent_offset", BuiltInCameraPresets::FREE))
			->setEntityOffset(new Vector3(0.0, 1.5, 0.0))
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_30, $p3c->getMinimumProtocol());

		// 1.21.40 limits or continueTargeting
		$p4 = (new CameraPresetBuilder("amber:limits", BuiltInCameraPresets::FREE))
			->setHorizontalRotationLimit(new Vector2(-45.0, 45.0))
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_40, $p4->getMinimumProtocol());

		// 1.21.50 blockListeningRadius
		$p5 = (new CameraPresetBuilder("amber:listener_radius", BuiltInCameraPresets::FREE))
			->setBlockListeningRadius(16.0)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_50, $p5->getMinimumProtocol());

		// 1.21.60 yawLimitMin/Max
		$p6 = (new CameraPresetBuilder("amber:yaw_limits", BuiltInCameraPresets::FREE))
			->setYawLimits(-90.0, 90.0)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_60, $p6->getMinimumProtocol());

		// 1.21.80 controlScheme
		$p7 = (new CameraPresetBuilder("amber:ctrl_scheme", BuiltInCameraPresets::FREE))
			->setControlScheme(CameraControlScheme::CAMERA_RELATIVE)
			->build();
		self::assertSame(ProtocolInfo::PROTOCOL_1_21_80, $p7->getMinimumProtocol());
	}

	public function testToProtocolConversion() : void{
		$preset = (new CameraPresetBuilder("amber:complex", BuiltInCameraPresets::FREE))
			->setPosition(new Vector3(10.0, 20.0, 30.0))
			->setRotation(25.0, 45.0)
			->setAudioListener(CameraAudioListener::CAMERA)
			->setPlayerEffects(true)
			->setControlScheme(CameraControlScheme::CAMERA_RELATIVE)
			->build();

		$proto = $preset->toProtocol();
		self::assertSame("amber:complex", $proto->getName());
		self::assertSame(BuiltInCameraPresets::FREE, $proto->getParent());
		self::assertSame(10.0, $proto->getXPosition());
		self::assertSame(20.0, $proto->getYPosition());
		self::assertSame(30.0, $proto->getZPosition());
		self::assertSame(25.0, $proto->getPitch());
		self::assertEqualsWithDelta(45.0, $proto->getYaw(), 0.0001);
		self::assertSame(0, $proto->getAudioListenerType()); // CameraAudioListener::CAMERA->value
		self::assertTrue($proto->getPlayerEffects());
	}
}
