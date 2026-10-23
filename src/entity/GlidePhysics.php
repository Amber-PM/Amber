<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\math\Vector3;
use function sqrt;

final class GlidePhysics{
	public static function calculateMotion(Vector3 $motion, Vector3 $direction, bool $boosting = false) : Vector3{
		$x = $motion->x;
		$y = $motion->y;
		$z = $motion->z;
		$speed = sqrt($x ** 2 + $z ** 2);
		$horizontal = sqrt($direction->x ** 2 + $direction->z ** 2);
		$lift = $horizontal ** 2;
		$y += -0.08 + $lift * 0.06;
		if($horizontal > 0.0){
			if($y < 0.0){
				$acceleration = -$y * 0.1 * $lift;
				$y += $acceleration;
				$x += $direction->x * $acceleration / $horizontal;
				$z += $direction->z * $acceleration / $horizontal;
			}
			if($direction->y > 0.0){
				$acceleration = $speed * $direction->y * 0.04;
				$y += $acceleration * 3.2;
				$x -= $direction->x * $acceleration / $horizontal;
				$z -= $direction->z * $acceleration / $horizontal;
			}
			$x += ($direction->x / $horizontal * $speed - $x) * 0.1;
			$z += ($direction->z / $horizontal * $speed - $z) * 0.1;
		}
		if($boosting){
			$x += $direction->x * 0.1 + ($direction->x * 1.5 - $x) * 0.5;
			$y += $direction->y * 0.1 + ($direction->y * 1.5 - $y) * 0.5;
			$z += $direction->z * 0.1 + ($direction->z * 1.5 - $z) * 0.5;
		}
		return new Vector3($x * 0.99, $y * 0.98, $z * 0.99);
	}
}
