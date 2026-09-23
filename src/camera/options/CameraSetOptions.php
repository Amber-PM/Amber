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

use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use function fmod;
use function is_finite;

final class CameraSetOptions{
	private ?Vector3 $position = null;
	private ?float $pitch = null;
	private ?float $yaw = null;
	private ?CameraEase $ease = null;
	private ?Vector3 $facingPosition = null;
	private ?Vector2 $viewOffset = null;
	private ?Vector3 $entityOffset = null;
	private bool $default = false;

	public static function create() : self{
		return new self();
	}

	public function setPosition(?Vector3 $position) : self{
		if($position !== null){
			self::validateVector3($position, "position");
		}
		$this->position = $position !== null ? clone $position : null;
		return $this;
	}

	public function setRotation(float $pitch, float $yaw) : self{
		if(!is_finite($pitch) || $pitch < -90.0 || $pitch > 90.0){
			throw new \InvalidArgumentException("Pitch must be a finite float between -90.0 and 90.0 degrees");
		}
		if(!is_finite($yaw)){
			throw new \InvalidArgumentException("Yaw must be a finite float");
		}

		$this->pitch = $pitch;
		$this->yaw = self::normalizeYaw($yaw);
		return $this;
	}

	public function clearRotation() : self{
		$this->pitch = null;
		$this->yaw = null;
		return $this;
	}

	public function setEase(?CameraEaseType $type, float $durationSeconds) : self{
		$this->ease = $type !== null ? new CameraEase($type, $durationSeconds) : null;
		return $this;
	}

	public function setFacingPosition(?Vector3 $facingPosition) : self{
		if($facingPosition !== null){
			self::validateVector3($facingPosition, "facingPosition");
		}
		$this->facingPosition = $facingPosition !== null ? clone $facingPosition : null;
		return $this;
	}

	public function setViewOffset(?Vector2 $viewOffset) : self{
		if($viewOffset !== null){
			self::validateVector2($viewOffset, "viewOffset");
		}
		$this->viewOffset = $viewOffset !== null ? clone $viewOffset : null;
		return $this;
	}

	public function setEntityOffset(?Vector3 $entityOffset) : self{
		if($entityOffset !== null){
			self::validateVector3($entityOffset, "entityOffset");
		}
		$this->entityOffset = $entityOffset !== null ? clone $entityOffset : null;
		return $this;
	}

	public function setDefault(bool $default) : self{
		$this->default = $default;
		return $this;
	}

	public function getPosition() : ?Vector3{
		return $this->position !== null ? clone $this->position : null;
	}

	public function getPitch() : ?float{
		return $this->pitch;
	}

	public function getYaw() : ?float{
		return $this->yaw;
	}

	public function getEase() : ?CameraEase{
		return $this->ease;
	}

	public function getFacingPosition() : ?Vector3{
		return $this->facingPosition !== null ? clone $this->facingPosition : null;
	}

	public function getViewOffset() : ?Vector2{
		return $this->viewOffset !== null ? clone $this->viewOffset : null;
	}

	public function getEntityOffset() : ?Vector3{
		return $this->entityOffset !== null ? clone $this->entityOffset : null;
	}

	public function isDefault() : bool{
		return $this->default;
	}

	public static function normalizeYaw(float $yaw) : float{
		$yaw = fmod($yaw, 360.0);
		if($yaw > 180.0){
			$yaw -= 360.0;
		}elseif($yaw <= -180.0){
			$yaw += 360.0;
		}
		return $yaw;
	}

	private static function validateVector3(Vector3 $v, string $name) : void{
		if(!is_finite($v->x) || !is_finite($v->y) || !is_finite($v->z)){
			throw new \InvalidArgumentException("Vector3 '$name' contains NaN or infinite coordinates");
		}
	}

	private static function validateVector2(Vector2 $v, string $name) : void{
		if(!is_finite($v->x) || !is_finite($v->y)){
			throw new \InvalidArgumentException("Vector2 '$name' contains NaN or infinite coordinates");
		}
	}
}
