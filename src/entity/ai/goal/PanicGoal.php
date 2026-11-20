<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Mob;
use pocketmine\math\Vector3;
use function mt_rand;

final class PanicGoal extends Goal{

	private int $panicTicks = 0;
	private ?Vector3 $panicTarget = null;

	public function __construct(
		private Mob $mob,
		private float $speedMultiplier = 1.25,
		int $priority = 1
	){
		parent::__construct($priority, Goal::FLAG_MOVE);
	}

	public function canStart() : bool{
		return $this->mob->getLastDamageCause() !== null && $this->mob->getAttackTime() > 0;
	}

	public function shouldContinue() : bool{
		return $this->panicTicks > 0 && $this->mob->getNavigator()->hasPath();
	}

	public function start() : void{
		$this->panicTicks = 60; // 3 seconds
		$loc = $this->mob->getLocation();
		$randX = $loc->x + mt_rand(-12, 12);
		$randZ = $loc->z + mt_rand(-12, 12);
		$this->panicTarget = new Vector3($randX, $loc->y, $randZ);
		$this->mob->getNavigator()->moveTo($this->panicTarget, $this->speedMultiplier);
	}

	public function tick() : void{
		$this->panicTicks--;
	}

	public function stop() : void{
		$this->panicTicks = 0;
		$this->panicTarget = null;
		$this->mob->getNavigator()->clearPath();
	}
}
