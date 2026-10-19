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

use PHPUnit\Framework\Assert;
use pocketmine\data\bedrock\ItemTagToIdMap;
use pocketmine\data\bedrock\WorldDataVersions;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

set_error_handler(static function(int $severity, string $message, string $file, int $line) : never{
	throw new ErrorException($message, 0, $severity, $file, $line);
});

$root = dirname(__DIR__, 6);
$protocolFile = $root . '/vendor/vapebw/bedrock-protocol/src/ProtocolInfo.php';
$source = file_get_contents($protocolFile);
$source = preg_replace('/public const CURRENT_PROTOCOL = [^;]+;/', 'public const CURRENT_PROTOCOL = ' . (int) $argv[1] . ';', $source, 1);
eval(substr($source, 5));
require $root . '/vendor/autoload.php';
$rows = json_decode(file_get_contents(__DIR__ . '/protocol-resources.json'), true, flags: JSON_THROW_ON_ERROR);
Assert::assertSame([589, 594, 618, 622, 630, 649, 662, 671, 685, 686, 712, 729, 748, 766, 776, 786, 800, 818, 819, 827, 844, 859, 860, 898, 924, 944, 975, 1001, 2168, 2169, 2192, 2193], ProtocolInfo::ACCEPTED_PROTOCOL);
Assert::assertSame(2169, WorldDataVersions::NETWORK);
$dataDir = $root . '/vendor/vapebw/bedrock-data';
$expectedBlocks = [];
foreach($rows as [$protocol, $blocks, $items, $tags]){
	$translator = BlockTranslator::loadFromProtocolId($protocol);
	$dictionary = $translator->getBlockStateDictionary();
	$networkIds = $protocol >= 2192 ? json_decode(file_get_contents($dataDir . '/block_network_ids-1.26.50.json'), true, flags: JSON_THROW_ON_ERROR) : null;
	$key = $blocks . ($networkIds === null ? ':indexed' : ':hashed');
	$expectedBlocks[$key] ??= serialize(BlockStateDictionary::loadFromString(file_get_contents($dataDir . '/canonical_block_states' . $blocks . '.nbt'), file_get_contents($dataDir . '/block_state_meta_map' . $blocks . '.json'), $networkIds)->getStates());
	Assert::assertSame($expectedBlocks[$key], serialize($dictionary->getStates()), "Block resources for $protocol");
	$itemDictionary = ItemTypeDictionaryFromDataHelper::loadFromProtocolId($protocol);
	$itemData = json_decode(file_get_contents($dataDir . '/required_item_list' . $items . '.json'), true, flags: JSON_THROW_ON_ERROR);
	Assert::assertCount(count($itemData), $itemDictionary->getEntries());
	foreach($itemData as $name => $entry){
		Assert::assertSame($entry['runtime_id'], $itemDictionary->fromStringId($name), "Item $name for $protocol");
	}
	$tagMap = ItemTagToIdMap::getInstance($protocol);
	$tagData = json_decode(file_get_contents($dataDir . '/item_tags' . $tags . '.json'), true, flags: JSON_THROW_ON_ERROR);
	foreach($tagData as $tag => $ids){
		Assert::assertSame(array_values(array_unique($ids)), $tagMap->getIdsForTag($tag), "Tag $tag for $protocol");
	}
}
Assert::assertSame(BlockTranslator::loadFromProtocolId(2192), BlockTranslator::loadFromProtocolId(2193));
Assert::assertNotSame(BlockTranslator::loadFromProtocolId(2169), BlockTranslator::loadFromProtocolId(2192));
echo "Validated 32 protocols\n";
