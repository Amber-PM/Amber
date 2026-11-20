<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\block\Water;
use pocketmine\entity\Mob;
use pocketmine\math\Vector3;

final class FloatGoal extends Goal{

	public function __construct(
		private Mob $mob,
		int $priority = 0
	){
		parent::__construct($priority, Goal::FLAG_JUMP);
	}

	public function canStart() : bool{
		return $this->mob->isUnderwater();
	}

	public function shouldContinue() : bool{
		return $this->canStart();
	}

	public function tick() : void{
		$motion = $this->mob->getMotion();
		// Push mob upward while submerged in water
		if($motion->y < 0.3){
			$this->mob->setMotion(new Vector3($motion->x, 0.3, $motion->z));
		}
	}
}
