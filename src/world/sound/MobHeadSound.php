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

use pocketmine\block\utils\MobHeadType;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

final class MobHeadSound implements Sound{
	private function __construct(private string $entityType, private string $event){}

	public static function forHead(MobHeadType $type) : ?self{
		$entity = match($type){
			MobHeadType::SKELETON => "minecraft:skeleton",
			MobHeadType::WITHER_SKELETON => "minecraft:wither_skeleton",
			MobHeadType::ZOMBIE => "minecraft:zombie",
			MobHeadType::CREEPER => "minecraft:creeper",
			MobHeadType::DRAGON => "minecraft:ender_dragon",
			MobHeadType::PIGLIN => "minecraft:piglin",
			MobHeadType::PLAYER => null
		};
		return $entity === null ? null : new self($entity, $type === MobHeadType::CREEPER ? LevelSoundEvent::FUSE : LevelSoundEvent::AMBIENT);
	}

	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::create($this->event, $pos, -1, $this->entityType, false, false, -1, null)];
	}
}
