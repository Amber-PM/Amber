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

namespace pocketmine\camera\preset;

use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use function is_finite;

final class CameraPresetBuilder{
	private ?Vector3 $position = null;
	private ?float $pitch = null;
	private ?float $yaw = null;
	private ?float $rotationSpeed = null;
	private ?bool $snapToTarget = null;
	private ?Vector2 $horizontalRotationLimit = null;
	private ?Vector2 $verticalRotationLimit = null;
	private ?bool $continueTargeting = null;
	private ?float $blockListeningRadius = null;
	private ?Vector2 $viewOffset = null;
	private ?Vector3 $entityOffset = null;
	private ?float $radius = null;
	private ?float $yawLimitMin = null;
	private ?float $yawLimitMax = null;
	private ?CameraAudioListener $audioListener = null;
	private ?bool $playerEffects = null;
	private ?CameraControlScheme $controlScheme = null;

	public static function create(string $identifier, string $parent) : self{
		return new self($identifier, $parent);
	}

	public function __construct(
		private readonly string $identifier,
		private readonly string $parent
	){
		CameraPreset::validateIdentifier($identifier, true);
		CameraPreset::validateParentIdentifier($parent);
		if($parent === $identifier){
			throw new \InvalidArgumentException("Camera preset cannot be its own parent: $identifier");
		}
	}

	public function setPosition(?Vector3 $position) : self{
		if($position !== null && (!is_finite($position->x) || !is_finite($position->y) || !is_finite($position->z))){
			throw new \InvalidArgumentException("Position vector contains NaN or infinite coordinates");
		}
		$this->position = $position !== null ? clone $position : null;
		return $this;
	}

	public function setRotation(?float $pitch, ?float $yaw) : self{
		if($pitch !== null && (!is_finite($pitch) || $pitch < -90.0 || $pitch > 90.0)){
			throw new \InvalidArgumentException("Pitch must be a finite float between -90.0 and 90.0 degrees");
		}
		if($yaw !== null && !is_finite($yaw)){
			throw new \InvalidArgumentException("Yaw must be a finite float");
		}
		$this->pitch = $pitch;
		$this->yaw = $yaw;
		return $this;
	}

	public function setRotationSpeed(?float $speed) : self{
		if($speed !== null && (!is_finite($speed) || $speed < 0.0)){
			throw new \InvalidArgumentException("Rotation speed must be a finite non-negative number");
		}
		$this->rotationSpeed = $speed;
		return $this;
	}

	public function setSnapToTarget(?bool $snap) : self{
		$this->snapToTarget = $snap;
		return $this;
	}

	public function setHorizontalRotationLimit(?Vector2 $limit) : self{
		if($limit !== null && (!is_finite($limit->x) || !is_finite($limit->y))){
			throw new \InvalidArgumentException("Horizontal limit contains NaN or infinite coordinates");
		}
		$this->horizontalRotationLimit = $limit !== null ? clone $limit : null;
		return $this;
	}

	public function setVerticalRotationLimit(?Vector2 $limit) : self{
		if($limit !== null && (!is_finite($limit->x) || !is_finite($limit->y))){
			throw new \InvalidArgumentException("Vertical limit contains NaN or infinite coordinates");
		}
		$this->verticalRotationLimit = $limit !== null ? clone $limit : null;
		return $this;
	}

	public function setContinueTargeting(?bool $continue) : self{
		$this->continueTargeting = $continue;
		return $this;
	}

	public function setBlockListeningRadius(?float $radius) : self{
		if($radius !== null && (!is_finite($radius) || $radius < 0.0)){
			throw new \InvalidArgumentException("Block listening radius must be a finite non-negative number");
		}
		$this->blockListeningRadius = $radius;
		return $this;
	}

	public function setViewOffset(?Vector2 $viewOffset) : self{
		if($viewOffset !== null && (!is_finite($viewOffset->x) || !is_finite($viewOffset->y))){
			throw new \InvalidArgumentException("View offset contains NaN or infinite coordinates");
		}
		$this->viewOffset = $viewOffset !== null ? clone $viewOffset : null;
		return $this;
	}

	public function setEntityOffset(?Vector3 $entityOffset) : self{
		if($entityOffset !== null && (!is_finite($entityOffset->x) || !is_finite($entityOffset->y) || !is_finite($entityOffset->z))){
			throw new \InvalidArgumentException("Entity offset contains NaN or infinite coordinates");
		}
		$this->entityOffset = $entityOffset !== null ? clone $entityOffset : null;
		return $this;
	}

	public function setRadius(?float $radius) : self{
		if($radius !== null && (!is_finite($radius) || $radius < 0.0)){
			throw new \InvalidArgumentException("Radius must be a finite non-negative number");
		}
		$this->radius = $radius;
		return $this;
	}

	public function setYawLimits(?float $min, ?float $max) : self{
		if($min !== null && !is_finite($min)){
			throw new \InvalidArgumentException("YawLimitMin must be a finite number");
		}
		if($max !== null && !is_finite($max)){
			throw new \InvalidArgumentException("YawLimitMax must be a finite number");
		}
		if($min !== null && $max !== null && $min > $max){
			throw new \InvalidArgumentException("YawLimitMin cannot be greater than YawLimitMax");
		}
		$this->yawLimitMin = $min;
		$this->yawLimitMax = $max;
		return $this;
	}

	public function setAudioListener(?CameraAudioListener $listener) : self{
		$this->audioListener = $listener;
		return $this;
	}

	public function setPlayerEffects(?bool $effects) : self{
		$this->playerEffects = $effects;
		return $this;
	}

	public function setControlScheme(?CameraControlScheme $controlScheme) : self{
		$this->controlScheme = $controlScheme;
		return $this;
	}

	public function build() : CameraPreset{
		return new CameraPreset(
			identifier: $this->identifier,
			parent: $this->parent,
			position: $this->position,
			pitch: $this->pitch,
			yaw: $this->yaw,
			rotationSpeed: $this->rotationSpeed,
			snapToTarget: $this->snapToTarget,
			horizontalRotationLimit: $this->horizontalRotationLimit,
			verticalRotationLimit: $this->verticalRotationLimit,
			continueTargeting: $this->continueTargeting,
			blockListeningRadius: $this->blockListeningRadius,
			viewOffset: $this->viewOffset,
			entityOffset: $this->entityOffset,
			radius: $this->radius,
			yawLimitMin: $this->yawLimitMin,
			yawLimitMax: $this->yawLimitMax,
			audioListener: $this->audioListener,
			playerEffects: $this->playerEffects,
			controlScheme: $this->controlScheme,
		);
	}
}
