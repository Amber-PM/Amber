<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Creeper;

final class CreeperSwellGoal extends Goal{

	public function __construct(
		private Creeper $creeper,
		int $priority = 2
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		$target = $this->creeper->getTargetEntity();
		return $target !== null && !$target->isClosed() && $target->isAlive();
	}

	public function shouldContinue() : bool{
		return $this->canStart();
	}

	public function tick() : void{
		$target = $this->creeper->getTargetEntity();
		if($target === null){
			$this->creeper->setIgnited(false);
			return;
		}

		$distSq = $this->creeper->getLocation()->distanceSquared($target->getLocation());

		// Start swelling at <= 2.5m (distSq <= 6.25)
		if($distSq <= 6.25){
			$this->creeper->setIgnited(true);
			$this->creeper->getNavigator()->clearPath();
		}elseif($distSq > 36.0){ // Stop swelling at > 6.0m (distSq > 36.0)
			$this->creeper->setIgnited(false);
		}

		$this->creeper->lookAt($target->getLocation()->asVector3());
		$this->creeper->tickFuse();
	}

	public function stop() : void{
		$this->creeper->setIgnited(false);
	}
}
