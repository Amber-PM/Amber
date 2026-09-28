<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
foreach($argv as $argument){
	if(str_starts_with($argument, '--baseline=')){
		$baseline = substr($argument, strlen('--baseline='));
		require $baseline . '/Pathfinder.php';
		require $baseline . '/Navigator.php';
	}
}

use pocketmine\addon\entity\ai\Pathfinder;
use pocketmine\addon\AddonTimings;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

final class PathfindingBenchWorld extends World{
	/** @var array<string, Chunk> */
	public array $chunks = [];
	public Server $benchServer;
	/** @var array<int, Entity> */
	public array $benchEntities = [];
	/** @var list<Entity> */
	public array $nearby = [];
	public function getChunk(int $chunkX, int $chunkZ) : ?Chunk{ return $this->chunks["$chunkX:$chunkZ"] ?? null; }
	public function getMinY() : int{ return -64; }
	public function getMaxY() : int{ return 320; }
	public function getServer() : Server{ return $this->benchServer; }
	public function addEntity(Entity $entity) : void{ $this->benchEntities[$entity->getId()] = $entity; }
	public function removeEntity(Entity $entity) : void{ unset($this->benchEntities[$entity->getId()], $this->updateEntities[$entity->getId()]); }
	public function getEntity(int $entityId) : ?Entity{ return $this->benchEntities[$entityId] ?? null; }
	public function getEntities() : array{ return $this->benchEntities; }
	public function getPlayers() : array{ return []; }
	public function getViewersForPosition(Vector3 $pos) : array{ return []; }
	public function isLoaded() : bool{ return true; }
	public function getBlockCollisionBoxes(AxisAlignedBB $bb) : array{ return []; }
	public function getNearbyEntities(AxisAlignedBB $bb, ?Entity $entity = null) : array{ return $this->nearby; }
	public function getDamageY() : int{ return -64; }
	public function getHighestBlockAt(int $x, int $z) : ?int{ return 63; }
	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block{ return $y === 63 ? VanillaBlocks::STONE() : VanillaBlocks::AIR(); }
}

$world = (new ReflectionClass(PathfindingBenchWorld::class))->newInstanceWithoutConstructor();
$stone = VanillaBlocks::STONE()->getStateId();
for($cx = 0; $cx <= 2; ++$cx){
	for($cz = 0; $cz <= 2; ++$cz){
		$chunk = new Chunk([], true);
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){ $chunk->setBlockStateId($x, 63, $z, $stone); }
		}
		$world->chunks["$cx:$cz"] = $chunk;
	}
}
$start = new Vector3(5.5, 64, 5.5);
$target = new Vector3(20.5, 64, 20.5);
$budgeted = static function(string $label) use($world, $start, $target) : void{
	$times = [];
	$grants = 0;
	$work = null;
	for($sample = 0; $sample < 15; ++$sample){
		foreach(['budgetTick' => -1, 'budgetUsed' => 0, 'waiting' => [], 'waitQueue' => null, 'reserved' => []] as $name => $value){ (new ReflectionProperty(Pathfinder::class, $name))->setValue(null, $value); }
		$begin = hrtime(true);
		for($id = 1; $id <= 16; ++$id){
			if(!Pathfinder::takeBudget(1000 + $sample, $id)){ continue; }
			$finder = new Pathfinder($world, 2, false, false);
			$finder->find($start, $target);
			if(method_exists(Pathfinder::class, 'completeBudget')){ Pathfinder::completeBudget(1000 + $sample, $id, $finder->getExpandedNodes()); }
		}
		$times[] = (hrtime(true) - $begin) / 1e6;
		$grants = (new ReflectionProperty(Pathfinder::class, 'budgetUsed'))->getValue();
		if(property_exists(Pathfinder::class, 'workUsed')){ $work = (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue(); }
	}
	sort($times);
	echo json_encode(['direct' => $label, 'grants' => $grants, 'work' => $work, 'median_ms' => $times[7], 'p95_ms' => $times[14]]) . "\n";
};
$measure = static function(int $n) use($world, $start, $target) : float{
	$times = [];
	for($sample = 0; $sample < 15; ++$sample){
		$begin = hrtime(true);
		for($i = 0; $i < $n; ++$i){ (new Pathfinder($world, 2, false, false))->find($start, $target); }
		$times[] = (hrtime(true) - $begin) / 1e6;
	}
	sort($times);
	return $times[7];
};
foreach([1, 3, 4, 5, 16] as $n){ echo "reachable,$n," . $measure($n) . "\n"; }
$budgeted('reachable_max_admitted');
for($x = 19; $x <= 21; ++$x){
	for($z = 19; $z <= 21; ++$z){
		if($x === 20 && $z === 20){ continue; }
		for($y = 64; $y <= 66; ++$y){ $world->chunks['1:1']->setBlockStateId($x & 15, $y, $z & 15, $stone); }
	}
}
foreach([1, 3, 4, 5, 16] as $n){ echo "enclosed,$n," . $measure($n) . "\n"; }
$budgeted('enclosed_max_admitted');

if(in_array('--fairness', $argv, true)){
	foreach([16, 400] as $nodes){
		foreach([100, 500, 1000] as $count){
			foreach(['budgetTick' => -1, 'budgetUsed' => 0, 'waiting' => [], 'waitQueue' => null, 'reserved' => []] as $name => $value){ (new ReflectionProperty(Pathfinder::class, $name))->setValue(null, $value); }
			$first = [];
			$waitStart = $nextAttempt = array_fill_keys(range(1, $count), 0);
			$maxWait = 0;
			for($tick = 0; $tick < 1000; ++$tick){
				foreach(array_keys($nextAttempt) as $id){
					if($tick < $nextAttempt[$id]){ continue; }
					if(Pathfinder::takeBudget($tick, $id)){
						$first[$id] ??= $tick;
						$maxWait = max($maxWait, $tick - $waitStart[$id]);
						$waitStart[$id] = $nextAttempt[$id] = $tick + 10;
						Pathfinder::completeBudget($tick, $id, $nodes);
					}
				}
			}
			echo json_encode(['scheduler_nodes_per_search' => $nodes, 'chasers' => $count, 'served' => count($first), 'last_first_admission_tick' => max($first), 'max_denied_wait_ticks' => $maxWait]) . "\n";
		}
	}
}

if(in_array('--live', $argv, true)){
	AddonTimings::init();
	$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
	$manager = (new ReflectionClass(WorldManager::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(WorldManager::class, 'worlds'))->setValue($manager, [$world]);
	(new ReflectionProperty(Server::class, 'worldManager'))->setValue($server, $manager);
	$world->benchServer = $server;
	$makeDefinition = static fn(bool $chaser) : AddonEntityDefinition => AddonEntityDefinition::fromJson(['minecraft:entity' => [
		'description' => ['identifier' => $chaser ? 'test:chaser' : 'test:target'],
		'components' => ['minecraft:health' => ['value' => 10, 'max' => 10], 'minecraft:movement' => ['value' => 0.2], 'minecraft:movement.basic' => []] + ($chaser ? [
			'minecraft:behavior.nearest_attackable_target' => ['priority' => 1, 'must_see' => false, 'entity_types' => [['max_dist' => 24, 'must_see' => false]]],
			'minecraft:behavior.move_towards_target' => ['priority' => 2, 'within_radius' => 0.5, 'speed_multiplier' => 1],
		] : []),
	]], 'phase2-bench.json', 'test');
	$chaserDefinition = $makeDefinition(true);
	$targetEntity = new AddonEntity(new Location(20.5, 64, 20.5, $world, 0, 0), $makeDefinition(false));
	$world->nearby = [$targetEntity];
	$counter = 10000;
	foreach(in_array('--control-only', $argv, true) ? [false] : [false, true] as $enclosed){
		for($x = 19; $x <= 21; ++$x){
			for($z = 19; $z <= 21; ++$z){
				if($x === 20 && $z === 20){ continue; }
				for($y = 64; $y <= 66; ++$y){ $world->chunks['1:1']->setBlockStateId($x & 15, $y, $z & 15, $enclosed ? $stone : VanillaBlocks::AIR()->getStateId()); }
			}
		}
		foreach([100, 500, 1000] as $count){
			$times = $grants = $work = [];
			for($sample = 0; $sample < 150; ++$sample){
				foreach(['budgetTick' => -1, 'budgetUsed' => 0, 'waiting' => [], 'waitQueue' => null, 'reserved' => []] as $name => $value){ (new ReflectionProperty(Pathfinder::class, $name))->setValue(null, $value); }
				$mobs = [];
				for($i = 0; $i < $count; ++$i){
					$mob = new AddonEntity(new Location(5.5, 64, 5.5, $world, 0, 0), $chaserDefinition);
					$mob->setHasGravity(false);
					$mob->setAlwaysActive(true);
					$mobs[] = $mob;
				}
				(new ReflectionProperty(Server::class, 'tickCounter'))->setValue($server, ++$counter);
				$begin = hrtime(true);
				foreach($mobs as $mob){ $mob->onUpdate($counter); }
				$times[] = (hrtime(true) - $begin) / 1e6;
				$grants[] = (new ReflectionProperty(Pathfinder::class, 'budgetUsed'))->getValue();
				if(property_exists(Pathfinder::class, 'workUsed')){ $work[] = (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue(); }
				foreach($mobs as $mob){ $mob->close(); }
				unset($mobs, $mob);
				gc_collect_cycles();
			}
			sort($times);
			echo json_encode(['enclosed' => $enclosed, 'chasers' => $count, 'samples' => 150, 'median_ms' => $times[75], 'p95_ms' => $times[142], 'max_ms' => max($times), 'max_grants' => max($grants), 'max_work' => $work === [] ? null : max($work)]) . "\n";
		}
	}
	$targetEntity->close();
}
