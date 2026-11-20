<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Living;
use pocketmine\entity\Mob;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;

final class NearestAttackableTargetGoal extends Goal{

	/**
	 * @param array<class-string<Living>> $targetClasses
	 */
	public function __construct(
		private Mob $mob,
		private array $targetClasses = [Player::class],
		private float $targetRange = 16.0,
		private bool $mustSee = true,
		int $priority = 1
	){
		parent::__construct($priority, Goal::FLAG_TARGET);
	}

	public function canStart() : bool{
		if($this->mob->getTargetEntity() !== null){
			return false;
		}

		$target = $this->findNearestTarget();
		if($target !== null){
			$this->mob->setTargetEntity($target);
			return true;
		}

		return false;
	}

	public function shouldContinue() : bool{
		$target = $this->mob->getTargetEntity();
		if($target === null || $target->isClosed() || !$target->isAlive()){
			return false;
		}

		if($target instanceof Player && ($target->isSpectator() || $target->isCreative())){
			return false;
		}

		$distSq = $this->mob->getLocation()->distanceSquared($target->getLocation());
		return $distSq <= ($this->targetRange * $this->targetRange * 1.5);
	}

	public function stop() : void{
		$this->mob->setTargetEntity(null);
	}

	private function findNearestTarget() : ?Living{
		$bb = AxisAlignedBB::one()->offset(
			$this->mob->getLocation()->x,
			$this->mob->getLocation()->y,
			$this->mob->getLocation()->z
		)->expandedCopy($this->targetRange, $this->targetRange / 2, $this->targetRange);

		$nearby = $this->mob->getWorld()->getNearbyEntities($bb);
		$closest = null;
		$closestDistSq = $this->targetRange * $this->targetRange;

		foreach($nearby as $entity){
			if(!$entity instanceof Living || $entity === $this->mob || $entity->isClosed() || !$entity->isAlive()){
				continue;
			}

			if($entity instanceof Player && ($entity->isSpectator() || $entity->isCreative())){
				continue;
			}

			$matchesClass = false;
			foreach($this->targetClasses as $targetClass){
				if($entity instanceof $targetClass){
					$matchesClass = true;
					break;
				}
			}

			if(!$matchesClass){
				continue;
			}

			$distSq = $this->mob->getLocation()->distanceSquared($entity->getLocation());
			if($distSq < $closestDistSq){
				$closestDistSq = $distSq;
				$closest = $entity;
			}
		}

		return $closest;
	}
}
