<?php

declare(strict_types=1);

namespace OptimizationRegression;

use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\AsyncPool;
use pmmp\thread\ThreadSafeArray;

final class InspectGeneratorTask extends AsyncTask{
	public function __construct(private ThreadSafeArray $result){}

	public function onRun() : void{
		$this->result["constructions"] = RegressionGenerator::$constructions;
		$this->result["pool_source"] = (new \ReflectionClass(AsyncPool::class))->getFileName();
	}
}
