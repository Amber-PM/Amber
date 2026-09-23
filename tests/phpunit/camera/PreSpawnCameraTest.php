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

use PHPUnit\Framework\TestCase;
use pocketmine\camera\preset\BuiltInCameraPresets;
use pocketmine\camera\preset\CameraPresetBuilder;
use pocketmine\camera\preset\CameraPresetRegistry;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class PreSpawnCameraTest extends TestCase{

	protected function setUp() : void{
		CameraPresetRegistry::reset();
	}

	protected function tearDown() : void{
		CameraPresetRegistry::reset();
	}

	public function testPreSpawnPacketSelectedForProtocol589() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->register((new CameraPresetBuilder("amber:modern", BuiltInCameraPresets::FREE))->setRadius(5.0)->build()); // requires 712
		$registry->register((new CameraPresetBuilder("amber:simple", BuiltInCameraPresets::FREE))->build()); // requires 589
		$registry->freeze();

		$packet589 = $registry->getPresetsPacket(ProtocolInfo::PROTOCOL_1_20_0);
		$presets589 = $packet589->getPresets();

		// Should have 4 built-ins + 1 custom (amber:simple). amber:modern must be excluded because it requires 712.
		self::assertCount(5, $presets589);
		$names589 = array_map(fn($p) => $p->getName(), $presets589);
		self::assertContains(BuiltInCameraPresets::FREE, $names589);
		self::assertContains("amber:simple", $names589);
		self::assertNotContains("amber:modern", $names589);
		self::assertNotContains(BuiltInCameraPresets::FOLLOW_ORBIT, $names589);
	}

	public function testPreSpawnPacketSelectedForProtocol712() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->register((new CameraPresetBuilder("amber:modern", BuiltInCameraPresets::FREE))->setRadius(5.0)->build());
		$registry->register((new CameraPresetBuilder("amber:simple", BuiltInCameraPresets::FREE))->build());
		$registry->freeze();

		$packet712 = $registry->getPresetsPacket(ProtocolInfo::PROTOCOL_1_21_20);
		$presets712 = $packet712->getPresets();

		// Should have 5 built-ins (including follow_orbit) + 2 customs
		self::assertCount(7, $presets712);
		$names712 = array_map(fn($p) => $p->getName(), $presets712);
		self::assertContains(BuiltInCameraPresets::FOLLOW_ORBIT, $names712);
		self::assertContains("amber:modern", $names712);
		self::assertContains("amber:simple", $names712);
		self::assertNotContains(BuiltInCameraPresets::CONTROL_SCHEME_CAMERA, $names712);
	}

	public function testPreSpawnPacketSelectedForProtocol800() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->freeze();

		$packet800 = $registry->getPresetsPacket(ProtocolInfo::PROTOCOL_1_21_80);
		$presets800 = $packet800->getPresets();

		// Should have all 7 built-ins
		self::assertCount(7, $presets800);
		$names800 = array_map(fn($p) => $p->getName(), $presets800);
		self::assertContains(BuiltInCameraPresets::CONTROL_SCHEME_CAMERA, $names800);
	}
}
