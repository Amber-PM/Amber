<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Mob;
use pocketmine\player\Player;

final class LookAtPlayerGoal extends Goal{

	private ?Player $targetPlayer = null;
	private int $lookTime = 0;

	public function __construct(
		private Mob $mob,
		private float $lookDistance = 6.0,
		int $priority = 8
	){
		parent::__construct($priority, Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		$players = $this->mob->getWorld()->getPlayers();
		$loc = $this->mob->getLocation();
		$maxDistSq = $this->lookDistance * $this->lookDistance;

		foreach($players as $player){
			if($player->isSpectator()){
				continue;
			}
			if($player->getLocation()->distanceSquared($loc) <= $maxDistSq){
				$this->targetPlayer = $player;
				return true;
			}
		}

		return false;
	}

	public function shouldContinue() : bool{
		return $this->lookTime > 0 &&
			$this->targetPlayer !== null &&
			!$this->targetPlayer->isClosed() &&
			$this->targetPlayer->isAlive() &&
			$this->targetPlayer->getLocation()->distanceSquared($this->mob->getLocation()) <= ($this->lookDistance * $this->lookDistance);
	}

	public function start() : void{
		$this->lookTime = 40; // 2 seconds
	}

	public function tick() : void{
		if($this->targetPlayer !== null){
			$this->mob->lookAt($this->targetPlayer->getLocation()->asVector3());
		}
		$this->lookTime--;
	}

	public function stop() : void{
		$this->targetPlayer = null;
		$this->lookTime = 0;
	}
}
