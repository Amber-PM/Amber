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

use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\MobHeadType;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;

final class NotePlaybackTest extends TestCase{

	public function testMobHeadPlaysThroughInteractionAndRedstoneEdge() : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt", "addSound", "setBlock", "isInWorld", "isChunkLoaded"
		])->getMock();
		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$powered = true;
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world, &$powered) : Block{
			$block = $y === 65 ? VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::ZOMBIE)
				: ($x === 1 && $powered ? VanillaBlocks::REDSTONE() : VanillaBlocks::AIR());
			$block->position($world, $x, $y, $z);
			return $block;
		});
		$packets = [];
		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) use (&$packets) : void{
			$packets = [...$packets, ...$sound->encode($pos)];
		});

		$note = VanillaBlocks::NOTE_BLOCK();
		$note->position($world, 0, 64, 0);
		$note->onInteract(VanillaItems::AIR(), Facing::UP, new Vector3(0, 0, 0));
		$note->onAttack(VanillaItems::AIR(), Facing::UP);
		$engine = new RedstoneEngine($world, 100);
		$note->onRedstoneUpdate($engine);
		$note->onRedstoneUpdate($engine);
		self::assertCount(3, $packets);
		foreach($packets as $packet){
			self::assertInstanceOf(LevelSoundEventPacket::class, $packet);
			self::assertSame(LevelSoundEvent::AMBIENT, $packet->sound);
			self::assertSame("minecraft:zombie", $packet->entityType);
		}
		$powered = false;
		$note->onRedstoneUpdate($engine);
		$powered = true;
		$note->onRedstoneUpdate($engine);
		self::assertCount(4, $packets);
	}
	public function testAllSupportedHeadSoundsAndSilentPlayerHead() : void{
		foreach([
			[MobHeadType::SKELETON, "minecraft:skeleton"],
			[MobHeadType::WITHER_SKELETON, "minecraft:wither_skeleton"],
			[MobHeadType::ZOMBIE, "minecraft:zombie"],
			[MobHeadType::CREEPER, "minecraft:creeper"],
			[MobHeadType::DRAGON, "minecraft:ender_dragon"],
			[MobHeadType::PIGLIN, "minecraft:piglin"]
		] as [$type, $entity]){
			$sound = \pocketmine\world\sound\MobHeadSound::forHead($type);
			self::assertNotNull($sound);
			$packet = $sound->encode(new Vector3(0, 64, 0))[0];
			self::assertSame($entity, $packet->entityType);
			self::assertSame($type === MobHeadType::CREEPER ? LevelSoundEvent::FUSE : LevelSoundEvent::AMBIENT, $packet->sound);
		}
		self::assertNull(\pocketmine\world\sound\MobHeadSound::forHead(MobHeadType::PLAYER));
	}

}
