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

use ArrayObject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use pocketmine\camera\options\CameraEase;
use pocketmine\camera\options\CameraEaseType;
use pocketmine\camera\options\CameraFadeOptions;
use pocketmine\camera\options\CameraSetOptions;
use pocketmine\camera\options\CameraShakeType;
use pocketmine\camera\preset\BuiltInCameraPresets;
use pocketmine\camera\preset\CameraPresetRegistry;
use pocketmine\color\Color;
use pocketmine\entity\Entity;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\CameraInstructionPacket;
use pocketmine\network\mcpe\protocol\CameraShakePacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\world\World;
use function spl_object_id;

class PlayerCameraTest extends TestCase{

	protected function setUp() : void{
		CameraPresetRegistry::reset();
		CameraPresetRegistry::getInstance()->freeze();
	}

	protected function tearDown() : void{
		CameraPresetRegistry::reset();
	}

	/**
	 * @return array{Player&\PHPUnit\Framework\MockObject\MockObject, NetworkSession&\PHPUnit\Framework\MockObject\MockObject, ArrayObject<int, ClientboundPacket>}
	 */
	private function createPlayerAndSession(int $protocolId) : array{
		$sentPackets = new ArrayObject();
		$session = $this->getMockBuilder(NetworkSession::class)
			->disableOriginalConstructor()
			->getMock();
		$session->method("getProtocolId")->willReturn($protocolId);
		$session->method("sendDataPacket")->willReturnCallback(function(ClientboundPacket $pk) use ($sentPackets) : bool{
			$sentPackets->append($pk);
			return true;
		});

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->getMock();

		$refProp = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp->setValue($player, true);

		$loggerMock = $this->createMock(\Logger::class);
		$refLogger = new \ReflectionProperty(\pocketmine\player\Player::class, "logger");
		$refLogger->setValue($player, $loggerMock);

		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isAlive")->willReturn(true);
		$player->method("isClosed")->willReturn(false);

		return [$player, $session, $sentPackets];
	}

	public function testCapabilityMethods() : void{
		[$p711] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_0);
		$cam711 = new PlayerCamera($p711);
		self::assertFalse($cam711->supportsTargeting());
		self::assertFalse($cam711->supportsFov());
		self::assertFalse($cam711->supportsAttachment());

		[$p712] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$cam712 = new PlayerCamera($p712);
		self::assertTrue($cam712->supportsTargeting());

		[$p826] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_90);
		$cam826 = new PlayerCamera($p826);
		self::assertFalse($cam826->supportsFov());

		[$p827] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_100);
		$cam827 = new PlayerCamera($p827);
		self::assertTrue($cam827->supportsFov());

		[$p858] = $this->createPlayerAndSession(858);
		$cam858 = new PlayerCamera($p858);
		self::assertFalse($cam858->supportsAttachment());

		[$p859] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_120);
		$cam859 = new PlayerCamera($p859);
		self::assertTrue($cam859->supportsAttachment());
	}

	public function testSetPresetUnregisteredThrows() : void{
		[$player] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		$this->expectException(InvalidArgumentException::class);
		$camera->set("unknown:preset");
	}

	public function testSetPresetUnsupportedProtocolReturnsFalse() : void{
		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		// follow_orbit is not in protocol 589
		self::assertFalse($camera->set(BuiltInCameraPresets::FOLLOW_ORBIT));
		self::assertCount(0, $sentPackets);
		self::assertNull($camera->getLastSetPreset());
	}

	public function testSetPresetSuccess() : void{
		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		self::assertTrue($camera->set(BuiltInCameraPresets::FREE));
		self::assertCount(1, $sentPackets);
		self::assertInstanceOf(CameraInstructionPacket::class, $sentPackets[0]);
		self::assertSame(BuiltInCameraPresets::FREE, $camera->getLastSetPreset());
	}

	public function testSetWithViewOffsetGating() : void{
		$opts = (new CameraSetOptions())->setViewOffset(new Vector2(1.0, 1.0));

		[$p711, , $pk711] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_0);
		$cam711 = new PlayerCamera($p711);
		self::assertFalse($cam711->set(BuiltInCameraPresets::FREE, $opts));
		self::assertCount(0, $pk711);
		self::assertNull($cam711->getLastSetPreset());

		[$p712, , $pk712] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$cam712 = new PlayerCamera($p712);
		self::assertTrue($cam712->set(BuiltInCameraPresets::FREE, $opts));
		self::assertCount(1, $pk712);
		self::assertSame(BuiltInCameraPresets::FREE, $cam712->getLastSetPreset());
	}

	public function testSetWithEntityOffsetGating() : void{
		$opts = (new CameraSetOptions())->setEntityOffset(new Vector3(0.0, 1.5, 0.0));

		[$p747, , $pk747] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_30);
		$cam747 = new PlayerCamera($p747);
		self::assertFalse($cam747->set(BuiltInCameraPresets::FREE, $opts));
		self::assertCount(0, $pk747);

		[$p748, , $pk748] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_40);
		$cam748 = new PlayerCamera($p748);
		self::assertTrue($cam748->set(BuiltInCameraPresets::FREE, $opts));
		self::assertCount(1, $pk748);
	}

	public function testClear() : void{
		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		$camera->set(BuiltInCameraPresets::FREE);
		self::assertSame(BuiltInCameraPresets::FREE, $camera->getLastSetPreset());

		$camera->clear();
		self::assertNull($camera->getLastSetPreset());
		self::assertCount(2, $sentPackets);
		$clearPacket = $sentPackets[1];
		self::assertInstanceOf(CameraInstructionPacket::class, $clearPacket);
		self::assertTrue($clearPacket->getClear());
	}

	public function testFade() : void{
		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		$fade = new CameraFadeOptions(new Color(0, 0, 0), 0.5, 2.0, 0.5);
		$camera->fade($fade);
		self::assertCount(1, $sentPackets);
		$pk = $sentPackets[0];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk);
		self::assertNotNull($pk->getFade());
	}

	public function testShake() : void{
		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		$camera->shake(0.5, 2.0, CameraShakeType::POSITIONAL);
		self::assertCount(1, $sentPackets);
		$pk1 = $sentPackets[0];
		self::assertInstanceOf(CameraShakePacket::class, $pk1);
		self::assertSame(CameraShakePacket::ACTION_ADD, $pk1->getShakeAction());

		$camera->stopShake();
		self::assertCount(2, $sentPackets);
		$pk2 = $sentPackets[1];
		self::assertInstanceOf(CameraShakePacket::class, $pk2);
		self::assertSame(CameraShakePacket::ACTION_STOP, $pk2->getShakeAction());
	}

	public function testShakeValidationThrows() : void{
		[$player] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_20_0);
		$camera = new PlayerCamera($player);

		$this->expectException(InvalidArgumentException::class);
		$camera->shake(-1.0, 2.0);
	}

	public function testFovGating() : void{
		[$p826, , $pk826] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_90);
		$cam826 = new PlayerCamera($p826);
		self::assertFalse($cam826->setFov(70.0));
		self::assertCount(0, $pk826);
		self::assertFalse($cam826->clearFov());
		self::assertCount(0, $pk826);

		[$p827, , $pk827] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_100);
		$cam827 = new PlayerCamera($p827);
		self::assertTrue($cam827->setFov(70.0, 1.0, CameraEaseType::LINEAR));
		self::assertCount(1, $pk827);
		$pk1 = $pk827[0];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk1);
		self::assertNotNull($pk1->getFieldOfView());

		self::assertTrue($cam827->clearFov(new CameraEase(CameraEaseType::LINEAR, 1.0)));
		self::assertCount(2, $pk827);
		$pk2 = $pk827[1];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk2);
		self::assertTrue($pk2->getFieldOfView()?->getClear());
	}

	public function testFovValidationThrows() : void{
		[$player] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_100);
		$camera = new PlayerCamera($player);

		$this->expectException(InvalidArgumentException::class);
		$camera->setFov(0.0);
	}

	public function testTargetNotVisibleReturnsFalse() : void{
		$world = $this->createMock(World::class);

		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$player->method("getWorld")->willReturn($world);
		$camera = new PlayerCamera($player);

		$target = $this->getMockBuilder(Entity::class)
			->disableOriginalConstructor()
			->getMock();
		$refProp = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp->setValue($target, true);
		$target->method("isAlive")->willReturn(true);
		$target->method("isClosed")->willReturn(false);
		$target->method("getWorld")->willReturn($world);
		$target->method("getId")->willReturn(12345);
		$target->method("getViewers")->willReturn([]);

		self::assertFalse($camera->setTarget($target));
		self::assertCount(0, $sentPackets);
	}

	public function testTargetVisibleSuccess() : void{
		$world = $this->createMock(World::class);

		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$player->method("getWorld")->willReturn($world);
		$camera = new PlayerCamera($player);

		$target = $this->getMockBuilder(Entity::class)
			->disableOriginalConstructor()
			->getMock();
		$refProp = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp->setValue($target, true);
		$target->method("isAlive")->willReturn(true);
		$target->method("isClosed")->willReturn(false);
		$target->method("getWorld")->willReturn($world);
		$target->method("getId")->willReturn(12345);
		$target->method("getViewers")->willReturn([spl_object_id($player) => $player]);

		self::assertTrue($camera->setTarget($target));
		self::assertCount(1, $sentPackets);
		$pk1 = $sentPackets[0];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk1);
		self::assertNotNull($pk1->getTarget());

		self::assertTrue($camera->clearTarget());
		self::assertCount(2, $sentPackets);
		$pk2 = $sentPackets[1];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk2);
		self::assertTrue($pk2->getRemoveTarget());
	}

	public function testTargetSelfEntityAlwaysVisible() : void{
		$world = $this->createMock(World::class);

		[$player, , $sentPackets] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$player->method("getWorld")->willReturn($world);
		$player->method("getId")->willReturn(555);
		$camera = new PlayerCamera($player);

		self::assertTrue($camera->setTarget($player));
		self::assertCount(1, $sentPackets);
		$pk = $sentPackets[0];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk);
		self::assertSame(555, $pk->getTarget()?->getActorUniqueId());
	}

	public function testTargetEntityDeadThrows() : void{
		$world = $this->createMock(World::class);
		[$player] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$player->method("getWorld")->willReturn($world);
		$camera = new PlayerCamera($player);

		$target = $this->getMockBuilder(Entity::class)
			->disableOriginalConstructor()
			->getMock();
		$refProp = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp->setValue($target, true);
		$target->method("isAlive")->willReturn(false);
		$target->method("isClosed")->willReturn(false);

		$this->expectException(InvalidArgumentException::class);
		$camera->setTarget($target);
	}

	public function testTargetEntityDifferentWorldThrows() : void{
		$world1 = $this->createMock(World::class);
		$world2 = $this->createMock(World::class);
		[$player] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_20);
		$player->method("getWorld")->willReturn($world1);
		$camera = new PlayerCamera($player);

		$target = $this->getMockBuilder(Entity::class)
			->disableOriginalConstructor()
			->getMock();
		$refProp = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp->setValue($target, true);
		$target->method("isAlive")->willReturn(true);
		$target->method("isClosed")->willReturn(false);
		$target->method("getWorld")->willReturn($world2);

		$this->expectException(InvalidArgumentException::class);
		$camera->setTarget($target);
	}

	public function testAttachmentGatingAndExecution() : void{
		$world = $this->createMock(World::class);

		// 858 attachment not supported
		[$p858, , $pk858] = $this->createPlayerAndSession(858);
		$p858->method("getWorld")->willReturn($world);
		$cam858 = new PlayerCamera($p858);

		$target858 = $this->getMockBuilder(Entity::class)
			->disableOriginalConstructor()
			->getMock();
		$refProp858 = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp858->setValue($target858, true);
		$target858->method("isAlive")->willReturn(true);
		$target858->method("isClosed")->willReturn(false);
		$target858->method("getWorld")->willReturn($world);
		$target858->method("getId")->willReturn(999);
		$target858->method("getViewers")->willReturn([spl_object_id($p858) => $p858]);

		self::assertFalse($cam858->attachToEntity($target858));
		self::assertCount(0, $pk858);
		self::assertFalse($cam858->detachFromEntity());
		self::assertCount(0, $pk858);

		// 859 attachment supported
		[$p859, , $pk859] = $this->createPlayerAndSession(ProtocolInfo::PROTOCOL_1_21_120);
		$p859->method("getWorld")->willReturn($world);
		$cam859 = new PlayerCamera($p859);

		$target859 = $this->getMockBuilder(Entity::class)
			->disableOriginalConstructor()
			->getMock();
		$refProp859 = new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed");
		$refProp859->setValue($target859, true);
		$target859->method("isAlive")->willReturn(true);
		$target859->method("isClosed")->willReturn(false);
		$target859->method("getWorld")->willReturn($world);
		$target859->method("getId")->willReturn(999);
		$target859->method("getViewers")->willReturn([spl_object_id($p859) => $p859]);

		self::assertTrue($cam859->attachToEntity($target859));
		self::assertCount(1, $pk859);
		$pk1 = $pk859[0];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk1);
		self::assertSame(999, $pk1->getAttachToEntity());

		self::assertTrue($cam859->detachFromEntity());
		self::assertCount(2, $pk859);
		$pk2 = $pk859[1];
		self::assertInstanceOf(CameraInstructionPacket::class, $pk2);
		self::assertTrue($pk2->getDetachFromEntity());
	}
}
