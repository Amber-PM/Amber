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

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;

final class ArmorStandItemTest extends TestCase{
	public static function placementYawCases() : \Generator{
		foreach([
			[0.0, 180.0], [90.0, 270.0], [180.0, 0.0], [270.0, 90.0],
			[45.0, 225.0], [135.0, 315.0], [225.0, 45.0], [315.0, 135.0],
			[15.0, 180.0], [22.49, 180.0], [22.5, 225.0], [22.51, 225.0],
			[-22.51, 135.0], [-22.5, 180.0], [-22.49, 180.0],
			[337.49, 135.0], [337.5, 180.0], [360.0, 180.0],
			[-90.0, 90.0], [-180.0, 0.0], [-450.0, 90.0], [720.0, 180.0]
		] as [$yaw, $expected]){
			yield "yaw=$yaw" => [$yaw, $expected];
		}
	}

	/** @dataProvider placementYawCases */
	public function testPlacedStandFacesPlayerInFortyFiveDegreeSteps(float $yaw, float $expected) : void{
		\pocketmine\timings\Timings::init();
		$world = $this->createMock(\pocketmine\world\World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($this->createMock(\pocketmine\Server::class));
		$entities = [];
		$world->method("addEntity")->willReturnCallback(function(\pocketmine\entity\Entity $entity) use (&$entities) : void{
			$entities[] = $entity;
		});
		$player = $this->createMock(\pocketmine\player\Player::class);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(\pocketmine\player\Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->method("getLocation")->willReturn(new \pocketmine\entity\Location(0, 64, 0, $world, $yaw, 40));
		$clicked = \pocketmine\block\VanillaBlocks::STONE();
		$clicked->position($world, 0, 63, 1);
		$replace = \pocketmine\block\VanillaBlocks::AIR();
		$replace->position($world, 0, 64, 1);
		$item = VanillaItems::ARMOR_STAND()->setCount(2);
		try{
			$returned = [];
			self::assertSame(ItemUseResult::SUCCESS, $item->onInteractBlock($player, $replace, $clicked, \pocketmine\math\Facing::UP, \pocketmine\math\Vector3::zero(), $returned));
			self::assertCount(1, $entities);
			$stand = $entities[0];
			self::assertInstanceOf(\pocketmine\entity\object\ArmorStand::class, $stand);
			self::assertSame($expected, $stand->getLocation()->yaw);
			self::assertSame(0.0, $stand->getLocation()->pitch);
			self::assertSame(1, $item->getCount());
			$session = $this->createMock(\pocketmine\network\mcpe\NetworkSession::class);
			$player->method("getNetworkSession")->willReturn($session);
			$session->expects(self::once())->method("sendDataPacket")->with(self::callback(function($packet) use ($expected) : bool{
				self::assertInstanceOf(\pocketmine\network\mcpe\protocol\AddActorPacket::class, $packet);
				self::assertSame($expected, $packet->yaw);
				self::assertSame($expected, $packet->headYaw);
				self::assertSame($expected, $packet->bodyYaw);
				return true;
			}))->willReturn(true);
			(new \ReflectionMethod(\pocketmine\entity\Entity::class, "sendSpawnPacket"))->invoke($stand, $player);
		}finally{
			foreach($entities as $entity){
				$entity->close();
			}
		}
	}

	private ?ArmorStand $item = null;

	protected function setUp() : void{
		$this->item = VanillaItems::ARMOR_STAND();
	}

	protected function tearDown() : void{
		$this->item = null;
		parent::tearDown();
	}

	public function testArmorStandInstance() : void{
		self::assertInstanceOf(ArmorStand::class, $this->item);
	}

	public function testArmorStandName() : void{
		self::assertSame("Armor Stand", $this->item?->getName());
	}

	public function testArmorStandMaxStackSize() : void{
		self::assertSame(64, $this->item?->getMaxStackSize());
	}

	public function testArmorStandTypeId() : void{
		self::assertSame(ItemTypeIds::ARMOR_STAND, $this->item?->getTypeId());
	}
}
