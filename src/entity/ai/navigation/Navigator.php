<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\navigation;

use pocketmine\entity\ai\path\AStarPathfinder;
use pocketmine\entity\ai\path\Path;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use function atan2;
use function floor;
use function rad2deg;
use function sqrt;

final class Navigator{

	private ?Path $path = null;
	private float $speedMultiplier = 1.0;
	private float $baseSpeed = 0.23;
	private int $stuckTicks = 0;
	private ?Vector3 $lastPosition = null;
	private ?Vector3 $lastTarget = null;
	private int $lastPathRecalculateTick = 0;

	public function __construct(
		private Living $entity,
		private AStarPathfinder $pathfinder
	){}

	public function getBaseSpeed() : float{
		return $this->baseSpeed;
	}

	public function setBaseSpeed(float $speed) : void{
		$this->baseSpeed = $speed;
	}

	public function getSpeedMultiplier() : float{
		return $this->speedMultiplier;
	}

	public function setSpeedMultiplier(float $multiplier) : void{
		$this->speedMultiplier = $multiplier;
	}

	public function getPath() : ?Path{
		return $this->path;
	}

	public function hasPath() : bool{
		return $this->path !== null && !$this->path->isFinished();
	}

	public function setPath(?Path $path, float $speedMultiplier = 1.0) : void{
		$this->path = $path;
		$this->speedMultiplier = $speedMultiplier;
		$this->stuckTicks = 0;
	}

	public function clearPath() : void{
		$this->path = null;
		$this->stuckTicks = 0;
		$this->lastTarget = null;
	}

	public function moveTo(Vector3 $target, float $speedMultiplier = 1.0, float $repathDistanceThreshold = 1.5) : bool{
		$currentTick = $this->entity->getWorld()->getServer()?->getTick() ?? 0;

		// Throttle path calculation to avoid re-pathing every single tick if target hasn't moved significantly
		if(
			$this->path !== null &&
			!$this->path->isFinished() &&
			$this->lastTarget !== null &&
			$this->lastTarget->distanceSquared($target) < ($repathDistanceThreshold * $repathDistanceThreshold) &&
			($currentTick - $this->lastPathRecalculateTick) < 15
		){
			$this->speedMultiplier = $speedMultiplier;
			return true;
		}

		$this->lastPathRecalculateTick = $currentTick;
		$this->lastTarget = $target;

		$size = $this->entity->getSize();
		$path = $this->pathfinder->findPath(
			$this->entity->getLocation(),
			$target,
			entityHeight: $size->getHeight(),
			entityWidth: $size->getWidth()
		);

		if($path !== null){
			$this->setPath($path, $speedMultiplier);
			return true;
		}

		return false;
	}

	public function tick() : void{
		if($this->path === null || $this->path->isFinished()){
			return;
		}

		$currentLoc = $this->entity->getLocation();

		// Check stuck progress
		if($this->lastPosition !== null && $this->lastPosition->distanceSquared($currentLoc) < 0.0004){
			if(++$this->stuckTicks > 40){
				$this->clearPath();
				return;
			}
		}else{
			$this->stuckTicks = 0;
		}
		$this->lastPosition = $currentLoc->asVector3();

		$node = $this->path->getCurrentNode();
		if($node === null){
			$this->clearPath();
			return;
		}

		$nodeVec = $node->toVector3();
		$dx = $nodeVec->x - $currentLoc->x;
		$dz = $nodeVec->z - $currentLoc->z;
		$horizontalDistSq = $dx * $dx + $dz * $dz;

		// Waypoint reached when horizontal distance < 0.6 blocks
		if($horizontalDistSq < 0.36){
			$this->path->advance();
			if($this->path->isFinished()){
				$this->clearPath();
				return;
			}
			$node = $this->path->getCurrentNode();
			if($node === null){
				$this->clearPath();
				return;
			}
			$nodeVec = $node->toVector3();
			$dx = $nodeVec->x - $currentLoc->x;
			$dz = $nodeVec->z - $currentLoc->z;
			$horizontalDistSq = $dx * $dx + $dz * $dz;
		}

		$dist = sqrt($horizontalDistSq);
		if($dist > 0.0001){
			$ux = $dx / $dist;
			$uz = $dz / $dist;

			$targetYaw = rad2deg(atan2(-$ux, $uz));
			$this->entity->setRotation($targetYaw, $this->entity->getLocation()->pitch);

			$effectiveSpeed = $this->baseSpeed * $this->speedMultiplier;
			$motionX = $ux * $effectiveSpeed;
			$motionZ = $uz * $effectiveSpeed;

			$currentMotion = $this->entity->getMotion();
			$motionY = $currentMotion->y;

			// Jump if next node is higher than entity standing level
			if($node->y > floor($currentLoc->y) + 0.1 && $this->entity->isOnGround()){
				$motionY = 0.42; // Minecraft jump velocity
			}

			$this->entity->setMotion(new Vector3($motionX, $motionY, $motionZ));
		}
	}
}
