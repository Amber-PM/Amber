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

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use function array_map;
use function json_encode;
use const JSON_THROW_ON_ERROR;

final class AddonWorkerDataTest extends TestCase{

	protected function tearDown() : void{
		AddonWorkerData::reset();
	}

	private static function palette(BlockStateData ...$states) : string{
		return (new NetworkNbtSerializer())->writeMultiple(array_map(static fn(BlockStateData $s) => new TreeRoot($s->toNbt()), $states));
	}

	public function testPayloadRoundTrip() : void{
		$states = [
			BlockStateData::current("test:lamp", ["lit" => new StringTag("on")]),
			BlockStateData::current("test:lamp", ["lit" => new StringTag("off")]),
		];
		$byStateId = [12345 => $states[1]];
		AddonWorkerData::apply(AddonWorkerData::encode($states, $byStateId));

		$decoded = AddonWorkerData::getNetworkBlockStates();
		self::assertCount(2, $decoded);
		self::assertTrue($decoded[0]->equals($states[0]));
		self::assertTrue($decoded[1]->equals($states[1]));
		$state = AddonWorkerData::getStateData(12345);
		self::assertNotNull($state);
		self::assertTrue($state->equals($states[1]));
		self::assertNull(AddonWorkerData::getStateData(1));
	}

	public function testWorkerPaletteIncludesAddonStates() : void{
		$vanilla = [BlockStateData::current(BlockTypeNames::AIR, []), BlockStateData::current(BlockTypeNames::INFO_UPDATE, []), BlockStateData::current(BlockTypeNames::SKELETON_SKULL, [])];
		$addon = BlockStateData::current("test:lamp", []);
		$palette = self::palette(...$vanilla);
		$metaMap = json_encode([0, 0, 0], JSON_THROW_ON_ERROR);

		self::assertNull(BlockStateDictionary::loadFromString($palette, $metaMap)->lookupStateIdFromData($addon));

		AddonWorkerData::apply(AddonWorkerData::encode([$addon], []));
		$dictionary = BlockStateDictionary::loadFromString($palette, $metaMap);
		self::assertNotNull($dictionary->lookupStateIdFromData($addon));
		self::assertCount(4, $dictionary->getStates());
	}
}
