<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use function mt_rand;

final class RandomStrollGoal extends Goal{

	private int $cooldown = 0;

	public function __construct(
		private Mob $mob,
		private float $speedMultiplier = 0.8,
		private int $strollRadius = 8,
		int $priority = 7
	){
		parent::__construct($priority, Goal::FLAG_MOVE);
	}

	public function canStart() : bool{
		if($this->mob->getNavigator()->hasPath()){
			return false;
		}

		if(--$this->cooldown > 0){
			return false;
		}

		// 1 in 30 chance per tick when cooldown is expired
		return mt_rand(1, 30) === 1;
	}

	public function shouldContinue() : bool{
		return $this->mob->getNavigator()->hasPath();
	}

	public function start() : void{
		$loc = $this->mob->getLocation();
		$dx = mt_rand(-$this->strollRadius, $this->strollRadius);
		$dz = mt_rand(-$this->strollRadius, $this->strollRadius);

		$target = new Vector3($loc->x + $dx, $loc->y, $loc->z + $dz);
		$this->mob->getNavigator()->moveTo($target, $this->speedMultiplier);
		$this->cooldown = mt_rand(60, 140); // 3-7 seconds before next stroll
	}

	public function stop() : void{
		$this->mob->getNavigator()->clearPath();
	}
}
