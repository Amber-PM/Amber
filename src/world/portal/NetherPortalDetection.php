<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\portal;

use pocketmine\math\Axis;
use pocketmine\math\Vector3;

final class NetherPortalDetection{

	public function __construct(
		private int $axis,
		private int $hMin,
		private int $hMax,
		private int $yMin,
		private int $yMax,
		private int $c
	){}

	public function getAxis() : int{
		return $this->axis;
	}

	public function getHMin() : int{
		return $this->hMin;
	}

	public function getHMax() : int{
		return $this->hMax;
	}

	public function getYMin() : int{
		return $this->yMin;
	}

	public function getYMax() : int{
		return $this->yMax;
	}

	public function getC() : int{
		return $this->c;
	}

	public function getWidth() : int{
		return $this->hMax - $this->hMin + 1;
	}

	public function getHeight() : int{
		return $this->yMax - $this->yMin + 1;
	}

	public function getCenter() : Vector3{
		$midH = ($this->hMin + $this->hMax) / 2.0 + 0.5;
		$midY = ($this->yMin + $this->yMax) / 2.0 + 0.5;
		$cPos = $this->c + 0.5;

		return $this->axis === Axis::X
			? new Vector3($midH, $midY, $cPos)
			: new Vector3($cPos, $midY, $midH);
	}

	public function contains(int $x, int $y, int $z) : bool{
		if($y < $this->yMin || $y > $this->yMax){
			return false;
		}

		if($this->axis === Axis::X){
			return $z === $this->c && $x >= $this->hMin && $x <= $this->hMax;
		}

		return $x === $this->c && $z >= $this->hMin && $z <= $this->hMax;
	}

	/**
	 * @return list<Vector3>
	 */
	public function getAllInnerPositions() : array{
		$positions = [];
		for($y = $this->yMin; $y <= $this->yMax; ++$y){
			for($h = $this->hMin; $h <= $this->hMax; ++$h){
				$x = $this->axis === Axis::X ? $h : $this->c;
				$z = $this->axis === Axis::X ? $this->c : $h;
				$positions[] = new Vector3($x, $y, $z);
			}
		}

		return $positions;
	}
}
