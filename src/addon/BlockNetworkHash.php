<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use function hash;
use function hexdec;
use function ksort;
use function pack;
use function unpack;

/**
 * Block network IDs as clients compute them when StartGamePacket::$blockNetworkIdsAreHashes is set:
 * FNV-1a 32 over the little-endian NBT of {"name": ..., "states": {...}} (states sorted by name), as a
 * signed int. Checked against every state of the BDS 1.26.50 palette.
 */
final class BlockNetworkHash{

	private function __construct(){
		//NOOP
	}

	public static function compute(BlockStateData $state) : int{
		$states = $state->getStates();
		ksort($states);
		$sorted = CompoundTag::create();
		foreach($states as $name => $value){
			$sorted->setTag($name, $value);
		}
		$bytes = (new LittleEndianNbtSerializer())->write(new TreeRoot(CompoundTag::create()
			->setString("name", $state->getName())
			->setTag("states", $sorted)));
		return unpack("l", pack("l", (int) hexdec(hash("fnv1a32", $bytes))))[1];
	}

	/** FNV-1 64 of a block name, as fixed-width hex: palette index order sorts blocks by this. */
	public static function nameOrderKey(string $name) : string{
		return hash("fnv164", $name);
	}
}
