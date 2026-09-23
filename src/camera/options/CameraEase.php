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

use function is_finite;

final class CameraEase{

	public function __construct(
		private readonly CameraEaseType $type,
		private readonly float $durationSeconds
	){
		if(!is_finite($this->durationSeconds) || $this->durationSeconds <= 0.0){
			throw new \InvalidArgumentException("Ease duration must be a finite positive number");
		}
	}

	public function getType() : CameraEaseType{
		return $this->type;
	}

	public function getDurationSeconds() : float{
		return $this->durationSeconds;
	}
}
