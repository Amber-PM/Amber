<?php

/*
 *     _             _                __  __
 *    / \   _ __ ___ | |__   ___ _ __ |  \/  | __ _ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|| |\/| |/ _` | '_ \
 *  / ___ \| | | | | | |_) |  __/ |   | |  | | (_| | |_) |
 * /_/   \_\_| |_| |_|_.__/ \___|_|   |_|  |_|\__,_| .__/
 *                                                 |_|
 *
 * Amber - High-Performance Minecraft: Bedrock Edition Server
 * https://github.com/Amber-PM/Amber
 *
 * Copyright (c) 2026 Amber-PM
 * Licensed under Apache-2.0 or MIT
 */

declare(strict_types=1);

namespace pocketmine\camera;

use ArrayObject;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use pocketmine\camera\options\CameraEase;
use pocketmine\camera\options\CameraEaseType;
use pocketmine\camera\options\CameraFadeOptions;
use pocketmine\camera\options\CameraSetOptions;
use pocketmine\camera\options\CameraShakeType;
use pocketmine\camera\preset\BuiltInCameraPresets;
use pocketmine\camera\preset\CameraAudioListener;
use pocketmine\camera\preset\CameraPreset;
use pocketmine\camera\preset\CameraPresetBuilder;
use pocketmine\camera\preset\CameraPresetRegistry;
use pocketmine\color\Color;
use pocketmine\entity\Entity;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\world\World;
use function spl_object_id;

final class CameraInvariantsAndRegressionTest extends TestCase{

	protected function setUp() : void{
		CameraPresetRegistry::reset();
	}

	protected function tearDown() : void{
		CameraPresetRegistry::reset();
	}

	/**
	 * @return array{Player&\PHPUnit\Framework\MockObject\MockObject, NetworkSession&\PHPUnit\Framework\MockObject\MockObject, ArrayObject<int, ClientboundPacket>, \stdClass}
	 */
	private function createPlayerWithFailableSession(int $protocolId) : array{
		$sentPackets = new ArrayObject();
		$state = new \stdClass();
		$state->sendResult = true;

		$session = $this->getMockBuilder(NetworkSession::class)
			->disableOriginalConstructor()
			->getMock();
		$session->method("getProtocolId")->willReturn($protocolId);
		$session->method("sendDataPacket")->willReturnCallback(function(ClientboundPacket $pk) use ($sentPackets, $state) : bool{
			if(!$state->sendResult){
				return false;
			}
			$sentPackets->append($pk);
			return true;
		});

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->getMock();

		$refProp = new \ReflectionProperty(Entity::class, "closed");
		$refProp->setValue($player, true);

		$loggerMock = $this->createMock(\Logger::class);
		$refLogger = new \ReflectionProperty(Player::class, "logger");
		$refLogger->setValue($player, $loggerMock);

		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isAlive")->willReturn(true);
		$player->method("isClosed")->willReturn(false);

		return [$player, $session, $sentPackets, $state];
	}

	public function testSendDataPacketFailurePropagatesAndDoesNotMutateState() : void{
		CameraPresetRegistry::getInstance()->freeze();
		[$player, , , $state] = $this->createPlayerWithFailableSession(ProtocolInfo::PROTOCOL_1_21_120);
		$camera = new PlayerCamera($player);

		$state->sendResult = true;
		self::assertTrue($camera->set(BuiltInCameraPresets::FREE));
		self::assertSame(BuiltInCameraPresets::FREE, $camera->getLastSetPreset());

		$state->sendResult = false;
		self::assertFalse($camera->set(BuiltInCameraPresets::FIRST_PERSON));
		self::assertSame(BuiltInCameraPresets::FREE, $camera->getLastSetPreset());

		self::assertFalse($camera->clear());
		self::assertSame(BuiltInCameraPresets::FREE, $camera->getLastSetPreset());

		self::assertFalse($camera->fade(new CameraFadeOptions(new Color(255, 0, 0))));

		self::assertFalse($camera->shake(1.0, 2.0));
		self::assertFalse($camera->stopShake());

		self::assertFalse($camera->setFov(85.0));
		self::assertFalse($camera->clearFov());

		$world = $this->createMock(World::class);
		$player->method("getWorld")->willReturn($world);
		$player->method("getId")->willReturn(1);

		$target = $this->getMockBuilder(Entity::class)->disableOriginalConstructor()->getMock();
		$refProp = new \ReflectionProperty(Entity::class, "closed");
		$refProp->setValue($target, true);
		$target->method("isAlive")->willReturn(true);
		$target->method("isClosed")->willReturn(false);
		$target->method("getWorld")->willReturn($world);
		$target->method("getId")->willReturn(2);
		$target->method("getViewers")->willReturn([spl_object_id($player) => $player]);

		self::assertFalse($camera->setTarget($target));
		self::assertFalse($camera->clearTarget());
		self::assertFalse($camera->attachToEntity($target));
		self::assertFalse($camera->detachFromEntity());

		$state->sendResult = true;
		self::assertTrue($camera->clear());
		self::assertNull($camera->getLastSetPreset());
	}

	public function testDirectConstructorEnforcesInvariants() : void{
		$invalidIdentifiers = [
			"",
			"nonamespace",
			":leading_colon",
			"trailing_colon:",
			"has space:preset",
			"amber:has space",
			"minecraft:custom_preset",
		];

		foreach($invalidIdentifiers as $invalidId){
			try{
				new CameraPreset($invalidId, BuiltInCameraPresets::FREE);
				self::fail("Expected InvalidArgumentException for identifier '$invalidId'");
			}catch(InvalidArgumentException $e){
				self::assertTrue(true);
			}
		}

		$invalidParents = [
			"",
			"nonamespace",
			":bad",
			"trailing:",
			"has space:preset",
		];

		foreach($invalidParents as $invalidParent){
			try{
				new CameraPreset("amber:valid_id", $invalidParent);
				self::fail("Expected InvalidArgumentException for parent '$invalidParent'");
			}catch(InvalidArgumentException $e){
				self::assertTrue(true);
			}
		}

		$this->expectException(InvalidArgumentException::class);
		new CameraPreset("amber:self_preset", "amber:self_preset");
	}

	public function testDirectConstructorNumericValidation() : void{
		try{
			new CameraPreset("amber:p1", BuiltInCameraPresets::FREE, pitch: 90.1);
			self::fail("Expected InvalidArgumentException for pitch > 90");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		try{
			new CameraPreset("amber:p2", BuiltInCameraPresets::FREE, pitch: -90.1);
			self::fail("Expected InvalidArgumentException for pitch < -90");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		try{
			new CameraPreset("amber:v1", BuiltInCameraPresets::FREE, position: new Vector3(NAN, 0, 0));
			self::fail("Expected InvalidArgumentException for NaN position");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		try{
			new CameraPreset("amber:v2", BuiltInCameraPresets::FREE, viewOffset: new Vector2(0, INF));
			self::fail("Expected InvalidArgumentException for INF viewOffset");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		try{
			new CameraPreset("amber:rs1", BuiltInCameraPresets::FREE, rotationSpeed: -0.01);
			self::fail("Expected InvalidArgumentException for negative rotation speed");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		try{
			new CameraPreset("amber:rad1", BuiltInCameraPresets::FREE, radius: -0.1);
			self::fail("Expected InvalidArgumentException for negative radius");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		try{
			new CameraPreset("amber:yaw1", BuiltInCameraPresets::FREE, yawLimitMin: 50.0, yawLimitMax: 10.0);
			self::fail("Expected InvalidArgumentException for yawLimitMin > yawLimitMax");
		}catch(InvalidArgumentException $e){
			self::assertTrue(true);
		}

		$zeroValid = new CameraPreset(
			identifier: "amber:zero_valid",
			parent: BuiltInCameraPresets::FREE,
			rotationSpeed: 0.0,
			radius: 0.0,
			blockListeningRadius: 0.0
		);
		self::assertSame(0.0, $zeroValid->getRotationSpeed());
		self::assertSame(0.0, $zeroValid->getRadius());
		self::assertSame(0.0, $zeroValid->getBlockListeningRadius());
	}

	public function testDeepImmutabilityVectorsCannotBeMutatedExternally() : void{
		$pos = new Vector3(10.0, 20.0, 30.0);
		$hLimit = new Vector2(-45.0, 45.0);
		$viewOffset = new Vector2(1.5, 2.5);

		$preset = new CameraPreset(
			identifier: "amber:immutable",
			parent: BuiltInCameraPresets::FREE,
			position: $pos,
			horizontalRotationLimit: $hLimit,
			viewOffset: $viewOffset
		);

		$pos->x = 999.0;
		$pos->y = 999.0;
		$hLimit->x = -999.0;
		$viewOffset->x = 999.0;

		self::assertSame(10.0, $preset->getPosition()?->x);
		self::assertSame(20.0, $preset->getPosition()?->y);
		self::assertSame(-45.0, $preset->getHorizontalRotationLimit()?->x);
		self::assertSame(1.5, $preset->getViewOffset()?->x);

		$retrievedPos = $preset->getPosition();
		self::assertNotNull($retrievedPos);
		$retrievedPos->x = -888.0;

		self::assertSame(10.0, $preset->getPosition()?->x);

		$options = CameraSetOptions::create();
		$optPos = new Vector3(5.0, 10.0, 15.0);
		$options->setPosition($optPos);

		$optPos->x = 999.0;
		self::assertSame(5.0, $options->getPosition()?->x);

		$retrievedOptPos = $options->getPosition();
		self::assertNotNull($retrievedOptPos);
		$retrievedOptPos->x = -777.0;
		self::assertSame(5.0, $options->getPosition()?->x);
	}

	public function testPreFreezeRegistryAccessThrowsLogicException() : void{
		$registry = CameraPresetRegistry::getInstance();
		$preset = (new CameraPresetBuilder("amber:pre_freeze", BuiltInCameraPresets::FREE))->build();
		$registry->register($preset);

		self::assertFalse($registry->isFrozen());

		try{
			$registry->getIndex("amber:pre_freeze", ProtocolInfo::PROTOCOL_1_21_0);
			self::fail("Expected LogicException when calling getIndex before freeze");
		}catch(LogicException $e){
			self::assertTrue(true);
		}

		try{
			$registry->getPresetsPacket(ProtocolInfo::PROTOCOL_1_21_0);
			self::fail("Expected LogicException when calling getPresetsPacket before freeze");
		}catch(LogicException $e){
			self::assertTrue(true);
		}

		$registry->freeze();
		self::assertTrue($registry->isFrozen());
		self::assertNotNull($registry->getIndex("amber:pre_freeze", ProtocolInfo::PROTOCOL_1_21_0));
		self::assertNotNull($registry->getPresetsPacket(ProtocolInfo::PROTOCOL_1_21_0));
	}
}
