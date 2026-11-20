<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Sheep;
use function mt_rand;

final class EatGrassGoal extends Goal{

	private int $eatTimer = 0;

	public function __construct(
		private Sheep $sheep,
		int $priority = 6
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		// 1 in 1000 chance per tick for adult sheep, or higher if sheared
		$chance = $this->sheep->isSheared() ? 100 : 1000;
		if(mt_rand(1, $chance) !== 1){
			return false;
		}

		return $this->sheep->canEatGrassBlock();
	}

	public function shouldContinue() : bool{
		return $this->eatTimer > 0;
	}

	public function start() : void{
		$this->eatTimer = 36; // 1.8 seconds in BDS
		$this->sheep->getNavigator()->clearPath();
	}

	public function tick() : void{
		if(--$this->eatTimer <= 0){
			$this->sheep->eatGrassBlock();
		}
	}

	public function stop() : void{
		$this->eatTimer = 0;
	}
}
