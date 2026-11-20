<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Animal;
use pocketmine\math\AxisAlignedBB;
use function get_class;

final class BreedGoal extends Goal{

	private ?Animal $partner = null;

	public function __construct(
		private Animal $animal,
		private float $speedMultiplier = 1.0,
		int $priority = 3
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		if(!$this->animal->isInLove()){
			return false;
		}

		$this->partner = $this->findNearbyPartner();
		return $this->partner !== null;
	}

	public function shouldContinue() : bool{
		return $this->animal->isInLove() &&
			$this->partner !== null &&
			!$this->partner->isClosed() &&
			$this->partner->isAlive() &&
			$this->partner->isInLove();
	}

	public function tick() : void{
		if($this->partner === null){
			return;
		}

		$this->animal->lookAt($this->partner->getLocation()->asVector3());
		$distSq = $this->animal->getLocation()->distanceSquared($this->partner->getLocation());

		if($distSq <= 4.0){
			$this->animal->breedWith($this->partner);
			$this->stop();
		}else{
			$this->animal->getNavigator()->moveTo($this->partner->getLocation()->asVector3(), $this->speedMultiplier);
		}
	}

	public function stop() : void{
		$this->partner = null;
		$this->animal->getNavigator()->clearPath();
	}

	private function findNearbyPartner() : ?Animal{
		$bb = AxisAlignedBB::one()->offset(
			$this->animal->getLocation()->x,
			$this->animal->getLocation()->y,
			$this->animal->getLocation()->z
		)->expandedCopy(8.0, 4.0, 8.0);

		$class = get_class($this->animal);
		$nearby = $this->animal->getWorld()->getNearbyEntities($bb);

		foreach($nearby as $entity){
			if($entity !== $this->animal && $entity instanceof $class && $entity instanceof Animal){
				if($entity->isInLove()){
					return $entity;
				}
			}
		}

		return null;
	}
}
