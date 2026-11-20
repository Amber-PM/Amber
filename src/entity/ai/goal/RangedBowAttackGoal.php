<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Location;
use pocketmine\entity\Mob;
use pocketmine\entity\projectile\Arrow;
use pocketmine\math\Vector3;
use pocketmine\world\sound\BowShootSound;
use function atan2;
use function cos;
use function deg2rad;
use function sin;

final class RangedBowAttackGoal extends Goal{

	private int $attackCooldown = 0;
	private int $strafeTime = 0;
	private bool $strafeClockwise = true;

	public function __construct(
		private Mob $mob,
		private float $speedMultiplier = 1.0,
		private int $attackInterval = 60, // 3.0 seconds in BDS
		private float $maxAttackDistance = 15.0,
		int $priority = 2
	){
		parent::__construct($priority, Goal::FLAG_MOVE | Goal::FLAG_LOOK);
	}

	public function canStart() : bool{
		$target = $this->mob->getTargetEntity();
		return $target !== null && !$target->isClosed() && $target->isAlive();
	}

	public function shouldContinue() : bool{
		return $this->canStart();
	}

	public function start() : void{
		$this->attackCooldown = 20; // 1s before first shot
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if($target === null){
			return;
		}

		$targetPos = $target->getLocation()->asVector3()->add(0, $target->getSize()->getEyeHeight() * 0.75, 0);
		$this->mob->lookAt($targetPos);

		$distSq = $this->mob->getLocation()->distanceSquared($target->getLocation());
		$maxDistSq = $this->maxAttackDistance * $this->maxAttackDistance;

		// Move towards or back away from target
		if($distSq > $maxDistSq){
			$this->mob->getNavigator()->moveTo($target->getLocation()->asVector3(), $this->speedMultiplier);
		}elseif($distSq < 16.0){ // Back away if target gets closer than 4 blocks
			$dx = $this->mob->getLocation()->x - $target->getLocation()->x;
			$dz = $this->mob->getLocation()->z - $target->getLocation()->z;
			$backTarget = $this->mob->getLocation()->add($dx * 2, 0, $dz * 2);
			$this->mob->getNavigator()->moveTo($backTarget, $this->speedMultiplier);
		}else{
			// Strafing in optimal shooting range
			$this->mob->getNavigator()->clearPath();
			if(++$this->strafeTime > 20){
				$this->strafeClockwise = !$this->strafeClockwise;
				$this->strafeTime = 0;
			}
		}

		if(--$this->attackCooldown <= 0){
			$this->shootArrow($targetPos);
			$this->attackCooldown = $this->attackInterval;
		}
	}

	private function shootArrow(Vector3 $targetPos) : void{
		$world = $this->mob->getWorld();
		$eyePos = $this->mob->getLocation()->add(0, $this->mob->getSize()->getEyeHeight(), 0);

		$dir = $targetPos->subtractVector($eyePos)->normalize();
		// Slight upward arc
		$dir = $dir->add(0, 0.1, 0)->normalize();

		$spawnLoc = Location::fromObject($eyePos->addVector($dir->multiply(0.5)), $world, $this->mob->getLocation()->yaw, $this->mob->getLocation()->pitch);
		$arrow = new Arrow($spawnLoc, $this->mob, critical: false);
		$arrow->setMotion($dir->multiply(1.6));
		$arrow->spawnToAll();

		$world->addSound($eyePos, new BowShootSound());
	}

	public function stop() : void{
		$this->mob->getNavigator()->clearPath();
	}
}
