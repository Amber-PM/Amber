<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Living;
use pocketmine\entity\Mob;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class HurtByTargetGoal extends Goal{

	private ?Living $attacker = null;

	public function __construct(
		private Mob $mob,
		int $priority = 1
	){
		parent::__construct($priority, Goal::FLAG_TARGET);
	}

	public function canStart() : bool{
		$lastDamage = $this->mob->getLastDamageCause();
		if($lastDamage instanceof EntityDamageByEntityEvent){
			$damager = $lastDamage->getDamager();
			if($damager instanceof Living && !$damager->isClosed() && $damager->isAlive() && $damager !== $this->mob){
				$this->attacker = $damager;
				return true;
			}
		}

		return false;
	}

	public function shouldContinue() : bool{
		return $this->attacker !== null &&
			!$this->attacker->isClosed() &&
			$this->attacker->isAlive() &&
			$this->mob->getTargetEntity() === $this->attacker;
	}

	public function start() : void{
		$this->mob->setTargetEntity($this->attacker);
	}

	public function stop() : void{
		$this->attacker = null;
	}
}
