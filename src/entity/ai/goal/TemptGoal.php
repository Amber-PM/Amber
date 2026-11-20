<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Animal;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class TemptGoal extends Goal{

	private ?Player $temptingPlayer = null;

	public function __construct(
		private Animal $animal,
		private float $speedMultiplier = 1.0,
		private float $maxDistance = 10.0,
		int $priority = 4
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		$players = $this->animal->getWorld()->getPlayers();
		$animalLoc = $this->animal->getLocation();
		$maxDistSq = $this->maxDistance * $this->maxDistance;

		foreach($players as $player){
			if($player->isSpectator()){
				continue;
			}
			if($player->getLocation()->distanceSquared($animalLoc) <= $maxDistSq){
				if($this->animal->isBreedingItem($player->getInventory()->getItemInHand())){
					$this->temptingPlayer = $player;
					return true;
				}
			}
		}

		return false;
	}

	public function shouldContinue() : bool{
		if($this->temptingPlayer === null || $this->temptingPlayer->isClosed() || !$this->temptingPlayer->isAlive() || $this->temptingPlayer->isSpectator()){
			return false;
		}

		if(!$this->animal->isBreedingItem($this->temptingPlayer->getInventory()->getItemInHand())){
			return false;
		}

		$distSq = $this->animal->getLocation()->distanceSquared($this->temptingPlayer->getLocation());
		return $distSq <= ($this->maxDistance * $this->maxDistance);
	}

	public function tick() : void{
		if($this->temptingPlayer === null){
			return;
		}

		$this->animal->lookAt($this->temptingPlayer->getLocation()->asVector3());
		$distSq = $this->animal->getLocation()->distanceSquared($this->temptingPlayer->getLocation());

		// Keep 2-3 blocks distance
		if($distSq > 6.25){
			$this->animal->getNavigator()->moveTo($this->temptingPlayer->getLocation()->asVector3(), $this->speedMultiplier);
		}else{
			$this->animal->getNavigator()->clearPath();
		}
	}

	public function stop() : void{
		$this->temptingPlayer = null;
		$this->animal->getNavigator()->clearPath();
	}
}
