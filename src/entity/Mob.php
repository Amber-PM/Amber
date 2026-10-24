<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\ai\goal\GoalSelector;
use pocketmine\entity\ai\navigation\Navigator;
use pocketmine\entity\ai\path\AStarPathfinder;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use function atan2;
use function max;
use function rad2deg;
use function sqrt;

abstract class Mob extends Living{

	protected GoalSelector $goalSelector;
	protected GoalSelector $targetSelector;
	protected Navigator $navigator;
	protected ?Entity $combatTarget = null;
	protected float $movementSpeed = 0.23;
	protected float $attackDamage = 2.0;
	protected int $attackCooldown = 0;
	protected int $attackIntervalTicks = 20;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
		$this->initAI();
	}

	public function initAI() : void{
		$this->goalSelector = new GoalSelector();
		$this->targetSelector = new GoalSelector();
		$this->navigator = new Navigator($this, new AStarPathfinder($this->getWorld()));
		$this->navigator->setBaseSpeed($this->movementSpeed);
		$this->registerGoals();
	}

	abstract protected function registerGoals() : void;

	public function getGoalSelector() : GoalSelector{
		return $this->goalSelector;
	}

	public function getTargetSelector() : GoalSelector{
		return $this->targetSelector;
	}

	public function getNavigator() : Navigator{
		return $this->navigator;
	}

	public function getTargetEntity() : ?Entity{
		if($this->combatTarget !== null && ($this->combatTarget->isClosed() || !$this->combatTarget->isAlive())){
			$this->combatTarget = null;
		}
		return $this->combatTarget ?? (isset($this->server) ? parent::getTargetEntity() : null);
	}

	public function setTargetEntity(?Entity $target) : void{
		if($target !== null && ($target->isClosed() || !$target->isAlive() || $target === $this)){
			$this->combatTarget = null;
			if(isset($this->server)){
				parent::setTargetEntity(null);
			}
			return;
		}
		$this->combatTarget = $target;
		if(isset($this->server)){
			parent::setTargetEntity($target);
		}else{
			$this->targetId = $target?->getId();
		}
	}

	public function getMovementSpeed() : float{
		return isset($this->moveSpeedAttr) ? parent::getMovementSpeed() : $this->movementSpeed;
	}

	public function setMovementSpeed(float $v, bool $fit = false) : void{
		$this->movementSpeed = $v;
		if(isset($this->moveSpeedAttr)){
			parent::setMovementSpeed($v, $fit);
		}
		if(isset($this->navigator)){
			$this->navigator->setBaseSpeed($v);
		}
	}

	public function getAttackDamage() : float{
		return $this->attackDamage;
	}

	public function setAttackDamage(float $damage) : void{
		$this->attackDamage = $damage;
	}

	public function lookAt(Vector3 $target) : void{
		$loc = $this->location;
		$dx = $target->x - $loc->x;
		$dy = $target->y - ($loc->y + $this->size->getEyeHeight());
		$dz = $target->z - $loc->z;

		$horizontalDist = sqrt($dx * $dx + $dz * $dz);
		if($horizontalDist > 0.0001){
			$yaw = rad2deg(atan2(-$dx, $dz));
			$pitch = -rad2deg(atan2($dy, $horizontalDist));
			$this->setRotation($yaw, $pitch);
		}
	}

	public function attackTarget(Living $target) : bool{
		if($this->attackCooldown > 0){
			return false;
		}
		if($this->location->distanceSquared($target->location) > 4.0){ // 2-block reach
			return false;
		}

		$ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->attackDamage);
		$target->attack($ev);
		$this->attackCooldown = $this->attackIntervalTicks;
		return true;
	}

	public function tickAI(int $tickDiff = 1) : void{
		if($this->attackCooldown > 0){
			$this->attackCooldown = max(0, $this->attackCooldown - $tickDiff);
		}

		$this->targetSelector->tick();
		$this->goalSelector->tick();
		$this->navigator->tick();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->isAlive()){
			$this->tickAI($tickDiff);
			$hasUpdate = true;
		}
		return $hasUpdate;
	}
}
