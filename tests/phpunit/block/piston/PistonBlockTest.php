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

use PHPUnit\Framework\TestCase;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Piston;
use pocketmine\block\StickyPiston;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\PistonExtendSound;
use pocketmine\world\sound\PistonRetractSound;
use pocketmine\world\World;

final class PistonBlockTest extends TestCase{

	public function testPistonSounds() : void{
		$pos = new Vector3(10, 64, 20);

		$extend = new PistonExtendSound();
		$extendPackets = $extend->encode($pos);
		self::assertCount(1, $extendPackets);
		self::assertInstanceOf(LevelSoundEventPacket::class, $extendPackets[0]);
		self::assertSame(LevelSoundEvent::PISTON_OUT, $extendPackets[0]->sound);

		$retract = new PistonRetractSound();
		$retractPackets = $retract->encode($pos);
		self::assertCount(1, $retractPackets);
		self::assertInstanceOf(LevelSoundEventPacket::class, $retractPackets[0]);
		self::assertSame(LevelSoundEvent::PISTON_IN, $retractPackets[0]->sound);
	}

	public function testPistonProperties() : void{
		$piston = new Piston(new BlockIdentifier(BlockTypeIds::PISTON), "Piston", new BlockTypeInfo(BlockBreakInfo::instant()));
		self::assertSame(Facing::DOWN, $piston->getFacing());
		self::assertFalse($piston->isExtended());
		self::assertFalse($piston->isPowered());
		self::assertFalse($piston->isSticky());

		$piston->setFacing(Facing::UP);
		self::assertSame(Facing::UP, $piston->getFacing());

		$piston->setExtended(true);
		self::assertTrue($piston->isExtended());

		$piston->setPowered(true);
		self::assertTrue($piston->isPowered());

		$cloned = clone $piston;
		self::assertSame($piston->getStateId(), $cloned->getStateId());
		self::assertSame(Facing::UP, $cloned->getFacing());
		self::assertTrue($cloned->isExtended());
		self::assertTrue($cloned->isPowered());

		$cloned->setFacing(Facing::NORTH);
		self::assertNotSame($piston->getStateId(), $cloned->getStateId());
	}

	public function testStickyPistonProperties() : void{
		$sticky = new StickyPiston(new BlockIdentifier(BlockTypeIds::STICKY_PISTON), "Sticky Piston", new BlockTypeInfo(BlockBreakInfo::instant()));
		self::assertTrue($sticky->isSticky());
		self::assertSame(Facing::DOWN, $sticky->getFacing());
		self::assertFalse($sticky->isExtended());
		self::assertFalse($sticky->isPowered());

		$sticky->setFacing(Facing::NORTH);
		self::assertSame(Facing::NORTH, $sticky->getFacing());
	}

	private function createMockPlayer(float $pitch, int $horizontalFacing, World $world) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getLocation", "getHorizontalFacing", "isConnected"])
			->getMock();

		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$player->method("getLocation")->willReturn(new Location(0, 64, 0, $world, 0, $pitch));
		$player->method("getHorizontalFacing")->willReturn($horizontalFacing);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testPistonPlacementFacing() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$tx = new BlockTransaction($world);

		// Pitch > 45 (looking down) -> faces UP
		$piston1 = new Piston(new BlockIdentifier(BlockTypeIds::PISTON), "Piston", new BlockTypeInfo(BlockBreakInfo::instant()));
		$player1 = $this->createMockPlayer(50.0, Facing::NORTH, $world);
		$piston1->place($tx, VanillaItems::AIR(), VanillaBlocks::AIR(), VanillaBlocks::STONE(), Facing::UP, Vector3::zero(), $player1);
		self::assertSame(Facing::UP, $piston1->getFacing());

		// Pitch < -45 (looking up) -> faces DOWN
		$piston2 = new Piston(new BlockIdentifier(BlockTypeIds::PISTON), "Piston", new BlockTypeInfo(BlockBreakInfo::instant()));
		$player2 = $this->createMockPlayer(-60.0, Facing::NORTH, $world);
		$piston2->place($tx, VanillaItems::AIR(), VanillaBlocks::AIR(), VanillaBlocks::STONE(), Facing::DOWN, Vector3::zero(), $player2);
		self::assertSame(Facing::DOWN, $piston2->getFacing());

		// Horizontal pitch 0 looking NORTH -> faces opposite (SOUTH)
		$piston3 = new Piston(new BlockIdentifier(BlockTypeIds::PISTON), "Piston", new BlockTypeInfo(BlockBreakInfo::instant()));
		$player3 = $this->createMockPlayer(0.0, Facing::NORTH, $world);
		$piston3->place($tx, VanillaItems::AIR(), VanillaBlocks::AIR(), VanillaBlocks::STONE(), Facing::UP, Vector3::zero(), $player3);
		self::assertSame(Facing::SOUTH, $piston3->getFacing());
	}
}
