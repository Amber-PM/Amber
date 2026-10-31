<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Mob;

final class MeleeAttackGoal extends Goal{

	public function __construct(
		private Mob $mob,
		private float $speedMultiplier = 1.0,
		int $priority = 2
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		$target = $this->mob->getTargetEntity();
		return $target !== null && !$target->isClosed() && $target->isAlive();
	}

	public function shouldContinue() : bool{
		return $this->canStart();
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if($target === null){
			return;
		}

		$this->mob->lookAt($target->getLocation()->asVector3());

		$distSq = $this->mob->getLocation()->distanceSquared($target->getLocation());

		// Attack if within reach
		if($distSq <= 4.0){
			$this->mob->attackTarget($target);
		}

		// Move towards target
		$this->mob->getNavigator()->moveTo($target->getLocation()->asVector3(), $this->speedMultiplier);
	}

	public function stop() : void{
		$this->mob->getNavigator()->clearPath();
	}
}
