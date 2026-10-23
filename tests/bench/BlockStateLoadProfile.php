<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

require_once __DIR__ . '/../../vendor/autoload.php';

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function count;
use function hrtime;
use function json_decode;
use function number_format;
use function printf;
use function str_replace;
use const JSON_THROW_ON_ERROR;

function timed(string $label, \Closure $run) : mixed{
	$start = hrtime(true);
	$result = $run();
	printf("  %-28s %9s ms\n", $label, number_format((hrtime(true) - $start) / 1e6, 2));
	return $result;
}

echo "warm-up\n";
$upgrader = timed('global handlers (statics)', fn() => GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader());

foreach(['', '-1.20.0', '-1.26.20'] as $suffix){
	echo "dataset '$suffix'\n";
	$palette = timed('read palette file', fn() => Filesystem::fileGetContents(str_replace('.nbt', $suffix . '.nbt', BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT)));
	$meta = timed('read meta map file', fn() => Filesystem::fileGetContents(str_replace('.json', $suffix . '.json', BedrockDataFiles::BLOCK_STATE_META_MAP_JSON)));
	timed('json_decode meta map', fn() => json_decode($meta, flags: JSON_THROW_ON_ERROR));
	$states = timed('parse NBT palette', fn() => BlockStateDictionary::loadPaletteFromString($palette));
	timed('upgrade() per state', function() use ($states, $upgrader) : void{
		foreach($states as $state){
			$upgrader->upgrade($state);
		}
	});
	timed('loadFromString (total)', fn() => BlockStateDictionary::loadFromString($palette, $meta));
	printf("  states: %d\n", count($states));
}