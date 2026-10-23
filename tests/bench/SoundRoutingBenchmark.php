<?php

declare(strict_types=1);

namespace pocketmine\world;

require_once __DIR__ . '/../../vendor/autoload.php';

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ProtocolSound;
use ReflectionClass;
use function hrtime;
use function number_format;
use function printf;
use function spl_object_id;

$iterations = isset($argv[1]) ? (int) $argv[1] : 200000;
$playerCount = isset($argv[2]) ? (int) $argv[2] : 100;
$batch = 1000;

function measure(string $label, int $iterations, \Closure $run) : float{
	$start = hrtime(true);
	$run($iterations);
	$elapsed = (hrtime(true) - $start) / 1e6;
	printf("%-48s %10s ms  %8s ns/call\n", $label, number_format($elapsed, 2), number_format(($elapsed * 1e6) / $iterations, 0));
	return $elapsed;
}

$reflection = new ReflectionClass(World::class);
$world = $reflection->newInstanceWithoutConstructor();

$listeners = [];
for($i = 0; $i < $playerCount; ++$i){
	$player = new class extends Player{
		public function __construct(){}

		public function __destruct(){}
	};
	$listeners[spl_object_id($player)] = $player;
}
$reflection->getProperty('playerChunkListeners')->setValue($world, [World::chunkHash(0, 0) => $listeners]);
$buffers = $reflection->getProperty('packetBuffersByChunkTypeConverter');

$position = new Vector3(0, 0, 0);
$sound = new class extends ProtocolSound{
	public function encode(Vector3 $pos) : array{
		return [];
	}
};

$nullPlayers = measure("addSound, players = null ($playerCount players)", $iterations, function(int $n) use ($world, $buffers, $position, $sound, $batch) : void{
	for($i = 0; $i < $n; ++$i){
		$world->addSound($position, $sound);
		if($i % $batch === 0){
			$buffers->setValue($world, []);
		}
	}
});

$buffers->setValue($world, []);

$explicitPlayers = measure("addSound, explicit viewers ($playerCount players)", $iterations, function(int $n) use ($world, $buffers, $position, $sound, $batch) : void{
	for($i = 0; $i < $n; ++$i){
		$world->addSound($position, $sound, $world->getViewersForPosition($position));
		if($i % $batch === 0){
			$buffers->setValue($world, []);
		}
	}
});

printf("\nExplicit viewers minus null players: %s ms over %d calls (explicit path still runs the array comparison)\n", number_format($explicitPlayers - $nullPlayers, 2), $iterations);
