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
use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use pocketmine\camera\preset\BuiltInCameraPresets;
use pocketmine\camera\preset\CameraPreset;
use pocketmine\camera\preset\CameraPresetBuilder;
use pocketmine\camera\preset\CameraPresetRegistry;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class CameraPresetRegistryTest extends TestCase{

	protected function setUp() : void{
		CameraPresetRegistry::reset();
	}

	protected function tearDown() : void{
		CameraPresetRegistry::reset();
	}

	public function testDuplicateRegistrationThrows() : void{
		$registry = CameraPresetRegistry::getInstance();
		$preset1 = (new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE))->build();
		$preset2 = (new CameraPresetBuilder("amber:test", BuiltInCameraPresets::FREE))->build();

		$registry->register($preset1);
		$this->expectException(InvalidArgumentException::class);
		$registry->register($preset2);
	}

	public function testBuiltinCollisionThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		(new CameraPresetBuilder("minecraft:free", BuiltInCameraPresets::FIRST_PERSON))->build();
	}

	public function testGetIndexBeforeFreezeThrows() : void{
		$registry = CameraPresetRegistry::getInstance();
		$this->expectException(LogicException::class);
		$registry->getIndex(BuiltInCameraPresets::FREE, ProtocolInfo::PROTOCOL_1_20_0);
	}

	public function testGetPresetsPacketBeforeFreezeThrows() : void{
		$registry = CameraPresetRegistry::getInstance();
		$this->expectException(LogicException::class);
		$registry->getPresetsPacket(ProtocolInfo::PROTOCOL_1_20_0);
	}

	public function testReturnedPacketCannotMutateFutureCatalogs() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->register((new CameraPresetBuilder("amber:offset", BuiltInCameraPresets::FREE))
			->setViewOffset(new Vector2(1.0, 2.0))
			->build());
		$registry->freeze();

		$protocolId = ProtocolInfo::PROTOCOL_1_21_20;
		$index = $registry->getIndex("amber:offset", $protocolId);
		self::assertNotNull($index);
		$first = $registry->getPresetsPacket($protocolId);
		$first->getPresets()[$index]->getViewOffset()->x = 99.0;

		$second = $registry->getPresetsPacket($protocolId);
		self::assertNotSame($first, $second);
		self::assertSame(1.0, $second->getPresets()[$index]->getViewOffset()?->x);
	}

	public function testRegistrationAfterFreezeThrows() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->freeze();

		$preset = (new CameraPresetBuilder("amber:late", BuiltInCameraPresets::FREE))->build();
		$this->expectException(LogicException::class);
		$registry->register($preset);
	}

	public function testCapacityLimit() : void{
		$registry = CameraPresetRegistry::getInstance();
		for($i = 0; $i < CameraPresetRegistry::MAX_CUSTOM_PRESETS; ++$i){
			$registry->register((new CameraPresetBuilder("amber:preset_$i", BuiltInCameraPresets::FREE))->build());
		}

		$overflow = (new CameraPresetBuilder("amber:overflow", BuiltInCameraPresets::FREE))->build();
		$this->expectException(OverflowException::class);
		$registry->register($overflow);
	}

	public function testDeterministicOrdering() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->register((new CameraPresetBuilder("amber:first", BuiltInCameraPresets::FREE))->build());
		$registry->register((new CameraPresetBuilder("amber:second", BuiltInCameraPresets::FREE))->build());
		$registry->register((new CameraPresetBuilder("amber:third", BuiltInCameraPresets::FREE))->build());
		$registry->freeze();

		$idxFirst = $registry->getIndex("amber:first", ProtocolInfo::PROTOCOL_1_20_0);
		$idxSecond = $registry->getIndex("amber:second", ProtocolInfo::PROTOCOL_1_20_0);
		$idxThird = $registry->getIndex("amber:third", ProtocolInfo::PROTOCOL_1_20_0);

		self::assertNotNull($idxFirst);
		self::assertNotNull($idxSecond);
		self::assertNotNull($idxThird);
		self::assertTrue($idxFirst < $idxSecond);
		self::assertTrue($idxSecond < $idxThird);
	}

	public function testProtocolSpecificBuiltins() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->freeze();

		// 589 4 baseline presets
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::FIRST_PERSON, ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::FREE, ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::THIRD_PERSON, ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::THIRD_PERSON_FRONT, ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNull($registry->getIndex(BuiltInCameraPresets::FOLLOW_ORBIT, ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNull($registry->getIndex(BuiltInCameraPresets::FIXED_BOOM, ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNull($registry->getIndex(BuiltInCameraPresets::CONTROL_SCHEME_CAMERA, ProtocolInfo::PROTOCOL_1_20_0));

		// 712 adds follow_orbit
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::FOLLOW_ORBIT, ProtocolInfo::PROTOCOL_1_21_20));
		self::assertNull($registry->getIndex(BuiltInCameraPresets::FIXED_BOOM, ProtocolInfo::PROTOCOL_1_21_20));
		self::assertNull($registry->getIndex(BuiltInCameraPresets::CONTROL_SCHEME_CAMERA, ProtocolInfo::PROTOCOL_1_21_20));

		// 766 adds fixed_boom
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::FIXED_BOOM, ProtocolInfo::PROTOCOL_1_21_50));
		self::assertNull($registry->getIndex(BuiltInCameraPresets::CONTROL_SCHEME_CAMERA, ProtocolInfo::PROTOCOL_1_21_50));

		// 800 adds control_scheme_camera
		self::assertNotNull($registry->getIndex(BuiltInCameraPresets::CONTROL_SCHEME_CAMERA, ProtocolInfo::PROTOCOL_1_21_80));
	}

	public function testProtocolSpecificCustomFiltering() : void{
		$registry = CameraPresetRegistry::getInstance();
		$p712 = (new CameraPresetBuilder("amber:orbit_custom", BuiltInCameraPresets::FREE))
			->setViewOffset(new Vector2(1.0, 1.0)) // requires 712
			->build();
		$registry->register($p712);
		$registry->freeze();

		self::assertNull($registry->getIndex("amber:orbit_custom", ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNotNull($registry->getIndex("amber:orbit_custom", ProtocolInfo::PROTOCOL_1_21_20));
	}

	public function testUnknownParentThrowsOnFreeze() : void{
		$registry = CameraPresetRegistry::getInstance();
		$child = (new CameraPresetBuilder("amber:child", "amber:nonexistent"))->build();
		$registry->register($child);

		$this->expectException(InvalidArgumentException::class);
		$registry->freeze();
	}

	public function testControlSchemeCameraCannotBeInherited() : void{
		$registry = CameraPresetRegistry::getInstance();
		$registry->register((new CameraPresetBuilder("amber:platformer", BuiltInCameraPresets::CONTROL_SCHEME_CAMERA))->build());

		$this->expectException(InvalidArgumentException::class);
		$registry->freeze();
	}

	public function testDirectSelfCycleThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		(new CameraPresetBuilder("amber:loop", "amber:loop"))->build();
	}

	public function testFailedFreezeLeavesRegistryUnfrozenAndUsable() : void{
		$registry = CameraPresetRegistry::getInstance();
		$child = (new CameraPresetBuilder("amber:orphan", "amber:missing_parent"))->build();
		$registry->register($child);

		try{
			$registry->freeze();
			self::fail("Expected freeze to throw InvalidArgumentException");
		}catch(InvalidArgumentException $e){
			self::assertFalse($registry->isFrozen());
		}

		$parent = (new CameraPresetBuilder("amber:missing_parent", BuiltInCameraPresets::FREE))->build();
		$registry->register($parent);
		$registry->freeze();

		self::assertTrue($registry->isFrozen());
		self::assertNotNull($registry->getIndex("amber:orphan", ProtocolInfo::PROTOCOL_1_21_0));
	}

	public function testMultiNodeCycleThrowsOnFreeze() : void{
		$registry = CameraPresetRegistry::getInstance();
		$nodeA = (new CameraPresetBuilder("amber:node_a", "amber:node_b"))->build();
		$nodeB = (new CameraPresetBuilder("amber:node_b", "amber:node_a"))->build();

		$registry->register($nodeA);
		$registry->register($nodeB);

		$this->expectException(InvalidArgumentException::class);
		$registry->freeze();
	}

	public function testTransitiveMinimumProtocol() : void{
		$registry = CameraPresetRegistry::getInstance();
		// parent requires 712 due to viewOffset
		$parent = (new CameraPresetBuilder("amber:parent_cam", BuiltInCameraPresets::FREE))
			->setViewOffset(new Vector2(2.0, 2.0))
			->build();
		// child has no special features (own min = 589), but inherits from parent_cam
		$child = (new CameraPresetBuilder("amber:child_cam", "amber:parent_cam"))->build();

		$registry->register($parent);
		$registry->register($child);
		$registry->freeze();

		// in 589 both should be filtered out because parent is not available on 589
		self::assertNull($registry->getIndex("amber:parent_cam", ProtocolInfo::PROTOCOL_1_20_0));
		self::assertNull($registry->getIndex("amber:child_cam", ProtocolInfo::PROTOCOL_1_20_0));

		// in 712 both should be available
		self::assertNotNull($registry->getIndex("amber:parent_cam", ProtocolInfo::PROTOCOL_1_21_20));
		self::assertNotNull($registry->getIndex("amber:child_cam", ProtocolInfo::PROTOCOL_1_21_20));
	}

	public function testTopologicalOrderInCatalog() : void{
		$registry = CameraPresetRegistry::getInstance();
		$child = (new CameraPresetBuilder("amber:child_ordered", "amber:parent_ordered"))->build();
		$parent = (new CameraPresetBuilder("amber:parent_ordered", BuiltInCameraPresets::FREE))->build();

		$registry->register($child);
		$registry->register($parent);
		$registry->freeze();

		$idxParent = $registry->getIndex("amber:parent_ordered", ProtocolInfo::PROTOCOL_1_21_80);
		$idxChild = $registry->getIndex("amber:child_ordered", ProtocolInfo::PROTOCOL_1_21_80);

		self::assertNotNull($idxParent);
		self::assertNotNull($idxChild);
		self::assertTrue($idxParent < $idxChild);
	}
}
