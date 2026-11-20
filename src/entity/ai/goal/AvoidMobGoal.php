<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Entity;
use pocketmine\entity\Mob;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

final class AvoidMobGoal extends Goal{

	private ?Entity $mobToAvoid = null;

	/**
	 * @param string[] $avoidNetworkTypeIds
	 */
	public function __construct(
		private Mob $mob,
		private array $avoidNetworkTypeIds,
		private float $maxDistance = 6.0,
		private float $speedMultiplier = 1.2,
		int $priority = 3
	){
		parent::__construct($priority, Goal::FLAG_MOVE);
	}

	public function canStart() : bool{
		$this->mobToAvoid = $this->findMobToAvoid();
		return $this->mobToAvoid !== null;
	}

	public function shouldContinue() : bool{
		if($this->mobToAvoid === null || $this->mobToAvoid->isClosed() || !$this->mobToAvoid->isAlive()){
			return false;
		}

		$distSq = $this->mob->getLocation()->distanceSquared($this->mobToAvoid->getLocation());
		return $distSq <= ($this->maxDistance * $this->maxDistance);
	}

	public function tick() : void{
		if($this->mobToAvoid === null){
			return;
		}

		// Calculate opposite direction vector
		$loc = $this->mob->getLocation();
		$threatLoc = $this->mobToAvoid->getLocation();

		$dx = $loc->x - $threatLoc->x;
		$dz = $loc->z - $threatLoc->z;

		$fleeTarget = new Vector3($loc->x + $dx * 2, $loc->y, $loc->z + $dz * 2);
		$this->mob->getNavigator()->moveTo($fleeTarget, $this->speedMultiplier);
	}

	public function stop() : void{
		$this->mobToAvoid = null;
		$this->mob->getNavigator()->clearPath();
	}

	private function findMobToAvoid() : ?Entity{
		$bb = AxisAlignedBB::one()->offset(
			$this->mob->getLocation()->x,
			$this->mob->getLocation()->y,
			$this->mob->getLocation()->z
		)->expandedCopy($this->maxDistance, 3.0, $this->maxDistance);

		$nearby = $this->mob->getWorld()->getNearbyEntities($bb);
		foreach($nearby as $entity){
			if($entity->isClosed() || !$entity->isAlive() || $entity === $this->mob){
				continue;
			}
			$typeId = $entity::getNetworkTypeId();
			foreach($this->avoidNetworkTypeIds as $avoidId){
				if($typeId === $avoidId){
					return $entity;
				}
			}
		}

		return null;
	}
}
