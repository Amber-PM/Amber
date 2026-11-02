<?php

declare(strict_types=1);

namespace OptimizationRegression;

use pocketmine\scheduler\AsyncTask;
use pmmp\thread\ThreadSafeArray;

final class GateTask extends AsyncTask{
	public function __construct(private ThreadSafeArray $gate){}

	public function onRun() : void{
		$this->gate["started"] = true;
		$deadline = hrtime(true) + 20000000000;
		while(!($this->gate["release"] ?? false)){
			if(hrtime(true) > $deadline){
				throw new \RuntimeException("Gate timeout");
			}
			usleep(1000);
		}
	}
}
