<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Animal;
use pocketmine\math\AxisAlignedBB;
use function get_class;

final class FollowParentGoal extends Goal{

	private ?Animal $parent = null;

	public function __construct(
		private Animal $animal,
		private float $speedMultiplier = 1.1,
		int $priority = 6
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		if(!$this->animal->isBaby()){
			return false;
		}

		$this->parent = $this->findNearbyParent();
		return $this->parent !== null;
	}

	public function shouldContinue() : bool{
		return $this->animal->isBaby() &&
			$this->parent !== null &&
			!$this->parent->isClosed() &&
			$this->parent->isAlive();
	}

	public function tick() : void{
		if($this->parent === null){
			return;
		}

		$distSq = $this->animal->getLocation()->distanceSquared($this->parent->getLocation());
		if($distSq > 9.0){ // > 3 blocks away
			$this->animal->lookAt($this->parent->getLocation()->asVector3());
			$this->animal->getNavigator()->moveTo($this->parent->getLocation()->asVector3(), $this->speedMultiplier);
		}else{
			$this->animal->getNavigator()->clearPath();
		}
	}

	public function stop() : void{
		$this->parent = null;
		$this->animal->getNavigator()->clearPath();
	}

	private function findNearbyParent() : ?Animal{
		$bb = AxisAlignedBB::one()->offset(
			$this->animal->getLocation()->x,
			$this->animal->getLocation()->y,
			$this->animal->getLocation()->z
		)->expandedCopy(12.0, 6.0, 12.0);

		$class = get_class($this->animal);
		$nearby = $this->animal->getWorld()->getNearbyEntities($bb);

		foreach($nearby as $entity){
			if($entity instanceof $class && $entity instanceof Animal && !$entity->isBaby()){
				return $entity;
			}
		}

		return null;
	}
}
