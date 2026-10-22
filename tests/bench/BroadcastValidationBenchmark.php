<?php

declare(strict_types=1);

namespace pocketmine\world;

require_once __DIR__ . '/../../vendor/autoload.php';

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use ReflectionClass;
use function hrtime;
use function number_format;
use function printf;

$iterations = isset($argv[1]) ? (int) $argv[1] : 200000;
$batch = 1000;

function measure(string $label, int $iterations, \Closure $run) : float{
	$start = hrtime(true);
	$run($iterations);
	$elapsed = (hrtime(true) - $start) / 1e6;
	printf("%-48s %10s ms  %8s ns/call\n", $label, number_format($elapsed, 2), number_format(($elapsed * 1e6) / $iterations, 0));
	return $elapsed;
}

$closure = fn(TypeConverter $typeConverter) : array => [];

$checked = measure('TypeConverter::broadcastByTypeConverter', $iterations, function(int $n) use ($closure) : void{
	for($i = 0; $i < $n; ++$i){
		TypeConverter::broadcastByTypeConverter([], $closure);
	}
});

$unchecked = measure('TypeConverter::broadcastByTypeConverterUnchecked', $iterations, function(int $n) use ($closure) : void{
	for($i = 0; $i < $n; ++$i){
		TypeConverter::broadcastByTypeConverterUnchecked([], $closure);
	}
});

$reflection = new ReflectionClass(World::class);
$world = $reflection->newInstanceWithoutConstructor();
$buffers = $reflection->getProperty('packetBuffersByChunkTypeConverter');
$private = $reflection->getMethod('broadcastPacketToPlayersByTypeConverterUsingChunk');
$position = new Vector3(0, 0, 0);

$publicWorld = measure('World::broadcastPacketToViewersByTypeConverter', $iterations, function(int $n) use ($world, $buffers, $position, $closure, $batch) : void{
	for($i = 0; $i < $n; ++$i){
		$world->broadcastPacketToViewersByTypeConverter($position, $closure);
		if($i % $batch === 0){
			$buffers->setValue($world, []);
		}
	}
});

$privateWorld = measure('World::broadcastPacketToPlayersByTypeConverterUsingChunk', $iterations, function(int $n) use ($world, $buffers, $private, $closure, $batch) : void{
	for($i = 0; $i < $n; ++$i){
		$private->invoke($world, 0, 0, $closure);
		if($i % $batch === 0){
			$buffers->setValue($world, []);
		}
	}
});

printf("\nTypeConverter validation overhead: %s ms over %d calls\n", number_format($checked - $unchecked, 2), $iterations);
printf("World validation overhead (includes ReflectionMethod::invoke cost on the private path): %s ms over %d calls\n", number_format($publicWorld - $privateWorld, 2), $iterations);
