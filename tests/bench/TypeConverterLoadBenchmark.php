<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

require_once __DIR__ . '/../../vendor/autoload.php';

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function array_slice;
use function count;
use function explode;
use function hrtime;
use function in_array;
use function ini_get;
use function memory_get_peak_usage;
use function memory_get_usage;
use function number_format;
use function printf;
use function str_starts_with;
use function substr;

$mode = 'full';
$only = [];
foreach(array_slice($argv, 1) as $arg){
	if($arg === '--blocks'){
		$mode = 'blocks';
	}elseif(str_starts_with($arg, '--protocols=')){
		foreach(explode(',', substr($arg, 12)) as $id){
			$only[] = (int) $id;
		}
	}
}

printf("mode=%s memory_limit=%s\n", $mode, ini_get('memory_limit'));
printf("%-10s %12s %12s %10s\n", 'protocol', 'time (ms)', 'delta (KB)', 'chunk id');

$groups = [];
$totalMs = 0.0;
foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
	if(count($only) > 0 && !in_array($protocolId, $only, true)){
		continue;
	}
	$memoryBefore = memory_get_usage();
	$start = hrtime(true);
	if($mode === 'blocks'){
		BlockTranslator::loadFromProtocolId($protocolId);
		$chunkId = '-';
	}else{
		$converter = TypeConverter::getInstance($protocolId);
		$chunkId = (string) $converter->getChunkProtocolId();
		$groups[$chunkId] = true;
	}
	$elapsed = (hrtime(true) - $start) / 1e6;
	$totalMs += $elapsed;
	printf("%-10d %12s %12s %10s\n", $protocolId, number_format($elapsed, 2), number_format((memory_get_usage() - $memoryBefore) / 1024, 0), $chunkId);
}

printf("total %s ms, peak %s MB\n", number_format($totalMs, 2), number_format(memory_get_peak_usage(true) / 1048576, 1));
if($mode === 'full'){
	printf("chunk protocol groups: %d, loaded item lists: %d\n", count($groups), ItemTypeDictionaryFromDataHelper::getLoadedCount());
}