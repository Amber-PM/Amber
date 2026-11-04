<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Mob;
use function mt_rand;

final class RandomLookAroundGoal extends Goal{

	private int $lookDuration = 0;
	private float $targetYaw = 0.0;
	private float $targetPitch = 0.0;

	public function __construct(
		private Mob $mob,
		int $priority = 9
	){
		parent::__construct($priority, Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		return mt_rand(1, 40) === 1;
	}

	public function shouldContinue() : bool{
		return $this->lookDuration > 0;
	}

	public function start() : void{
		$this->lookDuration = mt_rand(20, 40);
		$this->targetYaw = (float) mt_rand(0, 360);
		$this->targetPitch = (float) mt_rand(-20, 20);
	}

	public function tick() : void{
		$this->mob->setRotation($this->targetYaw, $this->targetPitch);
		$this->lookDuration--;
	}

	public function stop() : void{
		$this->lookDuration = 0;
	}
}
