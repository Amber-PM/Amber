<?php

declare(strict_types=1);

use OptimizationRegression\GateTask;
use OptimizationRegression\InspectGeneratorTask;
use OptimizationRegression\RegressionGenerator;
use pocketmine\scheduler\AsyncPool;
use pocketmine\snooze\SleeperHandler;
use pocketmine\thread\ThreadManager;
use pocketmine\thread\ThreadSafeClassLoader;
use pocketmine\utils\MainLogger;
use pocketmine\world\generator\executor\AsyncGeneratorExecutor;
use pocketmine\world\generator\executor\GeneratorExecutorSetupParameters;
use pmmp\thread\ThreadSafeArray;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
require __DIR__ . '/RegressionGenerator.php';
require __DIR__ . '/GateTask.php';
require __DIR__ . '/InspectGeneratorTask.php';

function check(bool $condition, string $message) : void{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

function waitFor(Closure $finished) : void{
	$deadline = hrtime(true) + 5000000000;
	while(!$finished()){
		check(hrtime(true) < $deadline, 'Operation timeout');
		usleep(1000);
	}
}

function drainWorker(AsyncPool $pool, int $id) : void{
	waitFor(static fn() => !$pool->collectTasksFromWorker($id));
}

function retireIdleWorker(AsyncPool $pool, int $id) : void{
	$workers = (new ReflectionProperty(AsyncPool::class, 'workers'))->getValue($pool);
	$workers[$id]->lastUsed = time() - 301;
	unset($workers);
	check($pool->shutdownUnusedWorkers() === 1, 'Expected exactly one idle worker retired');
}

$sparse = in_array('--sparse', $argv, true);
$loader = new ThreadSafeClassLoader();
$loader->addPath('OptimizationRegression', __DIR__);
$logger = new MainLogger(null, false, 'optimization-regression', new DateTimeZone('UTC'), true);
GlobalLogger::set($logger);
pocketmine\timings\Timings::init();
$pool = new AsyncPool($sparse ? 3 : 2, 256, $loader, $logger, new SleeperHandler());
$executor = new AsyncGeneratorExecutor($logger, $pool, new GeneratorExecutorSetupParameters(-64, 320, 42, RegressionGenerator::class, ''));
$gate0 = new ThreadSafeArray();
$gate1 = new ThreadSafeArray();
$completed = 0;
$exit = 0;
$completion = static function() use (&$completed) : void{ ++$completed; };
try{
	$pool->submitTaskToWorker(new GateTask($gate0), 0);
	waitFor(static fn() => $gate0['started'] ?? false);
	if($sparse){
		$pool->submitTaskToWorker(new GateTask($gate1), 1);
		waitFor(static fn() => $gate1['started'] ?? false);
		$executor->populate(0, 0, null, [], $completion);
		drainWorker($pool, 2);
		$gate1['release'] = true;
		drainWorker($pool, 1);
		retireIdleWorker($pool, 1);
		check($pool->getRunningWorkers() === [0, 2], 'Expected sparse live IDs [0,2]');
		check($pool->selectWorker() === 2, 'Expected live worker2 selected');
		$executor->populate(16, 0, null, [], $completion);
		drainWorker($pool, 2);
		$executor->populate(32, 0, null, [], $completion);
		drainWorker($pool, 2);
		$inspection = new ThreadSafeArray();
		$pool->submitTaskToWorker(new InspectGeneratorTask($inspection), 2);
		drainWorker($pool, 2);
		echo json_encode(['running' => $pool->getRunningWorkers(), 'completed' => $completed, 'constructions' => $inspection['constructions'], 'worker_pool_source' => $inspection['pool_source']], JSON_THROW_ON_ERROR), PHP_EOL;
		check($inspection['constructions'] === 1, 'Live worker generator was registered again');
	}else{
		$executor->populate(0, 0, null, [], $completion);
		drainWorker($pool, 1);
		$gate0['release'] = true;
		drainWorker($pool, 0);
		$executor->populate(16, 0, null, [], $completion);
		drainWorker($pool, 0);
		check($completed === 2, 'Setup populations did not complete');
		$pool->submitTaskToWorker(new GateTask($gate1), 1);
		waitFor(static fn() => $gate1['started'] ?? false);
		retireIdleWorker($pool, 0);
		check($pool->getRunningWorkers() === [1], 'Expected only worker1 alive');
		check($pool->selectWorker() === 0, 'Expected retired worker0 selected');
		echo json_encode(['running' => $pool->getRunningWorkers(), 'selected' => 0, 'completed' => $completed], JSON_THROW_ON_ERROR), PHP_EOL;
		$executor->populate(32, 0, null, [], $completion);
		drainWorker($pool, 0);
	}
	check($completed === 3, 'Expected three completed populations');
	echo json_encode(['result' => 'PASS', 'case' => $sparse ? 'sparse' : 'retired', 'completed' => $completed, 'pool_source' => (new ReflectionClass(AsyncPool::class))->getFileName()], JSON_THROW_ON_ERROR), PHP_EOL;
}catch(Throwable $e){
	$exit = 2;
	echo json_encode(['result' => 'FAIL', 'class' => $e::class, 'message' => $e->getMessage(), 'completed' => $completed], JSON_THROW_ON_ERROR), PHP_EOL;
}finally{
	$gate0['release'] = true;
	$gate1['release'] = true;
	foreach(ThreadManager::getInstance()->getAll() as $thread){
		$thread->quit();
	}
}
exit($exit);
