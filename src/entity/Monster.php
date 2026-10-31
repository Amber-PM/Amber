<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\HurtByTargetGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\NearestAttackableTargetGoal;
use pocketmine\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\player\Player;

abstract class Monster extends Mob{

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal(new MeleeAttackGoal($this, speedMultiplier: 1.0, priority: 2));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 0.8, priority: 7));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 6.0, priority: 8));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 9));

		$this->targetSelector->addGoal(new HurtByTargetGoal($this, 1));
		$this->targetSelector->addGoal(new NearestAttackableTargetGoal($this, [Player::class], targetRange: 16.0, priority: 2));
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
