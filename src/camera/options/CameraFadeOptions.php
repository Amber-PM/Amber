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

namespace pocketmine\camera\options;

use pocketmine\color\Color;
use function is_finite;

final class CameraFadeOptions{

	public function __construct(
		private readonly Color $color = new Color(0, 0, 0),
		private readonly float $fadeInSeconds = 0.5,
		private readonly float $holdSeconds = 1.0,
		private readonly float $fadeOutSeconds = 0.5
	){
		if(!is_finite($this->fadeInSeconds) || $this->fadeInSeconds < 0.0 ||
			!is_finite($this->holdSeconds) || $this->holdSeconds < 0.0 ||
			!is_finite($this->fadeOutSeconds) || $this->fadeOutSeconds < 0.0){
			throw new \InvalidArgumentException("Fade durations must be non-negative finite numbers");
		}
	}

	public function getColor() : Color{
		return $this->color;
	}

	public function getFadeInSeconds() : float{
		return $this->fadeInSeconds;
	}

	public function getHoldSeconds() : float{
		return $this->holdSeconds;
	}

	public function getFadeOutSeconds() : float{
		return $this->fadeOutSeconds;
	}
}
