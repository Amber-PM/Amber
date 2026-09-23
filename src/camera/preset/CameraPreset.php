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

use pocketmine\camera\options\CameraSetOptions;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\camera\CameraPreset as ProtocolCameraPreset;
use pocketmine\network\mcpe\protocol\types\ControlScheme as ProtocolControlScheme;
use function explode;
use function is_finite;
use function preg_match;
use function strtolower;

final class CameraPreset{

	private string $identifier;
	private string $parent;
	private ?Vector3 $position;
	private ?float $pitch;
	private ?float $yaw;
	private ?float $rotationSpeed;
	private ?bool $snapToTarget;
	private ?Vector2 $horizontalRotationLimit;
	private ?Vector2 $verticalRotationLimit;
	private ?bool $continueTargeting;
	private ?float $blockListeningRadius;
	private ?Vector2 $viewOffset;
	private ?Vector3 $entityOffset;
	private ?float $radius;
	private ?float $yawLimitMin;
	private ?float $yawLimitMax;
	private ?CameraAudioListener $audioListener;
	private ?bool $playerEffects;
	private ?CameraControlScheme $controlScheme;

	public function __construct(
		string $identifier,
		string $parent,
		?Vector3 $position = null,
		?float $pitch = null,
		?float $yaw = null,
		?float $rotationSpeed = null,
		?bool $snapToTarget = null,
		?Vector2 $horizontalRotationLimit = null,
		?Vector2 $verticalRotationLimit = null,
		?bool $continueTargeting = null,
		?float $blockListeningRadius = null,
		?Vector2 $viewOffset = null,
		?Vector3 $entityOffset = null,
		?float $radius = null,
		?float $yawLimitMin = null,
		?float $yawLimitMax = null,
		?CameraAudioListener $audioListener = null,
		?bool $playerEffects = null,
		?CameraControlScheme $controlScheme = null,
	){
		$isBuiltin = BuiltInCameraPresets::isBuiltin($identifier) && $parent === "";
		if($isBuiltin){
			self::validateIdentifier($identifier, false);
		}else{
			self::validateIdentifier($identifier, true);
			self::validateParentIdentifier($parent);
			if($parent === $identifier){
				throw new \InvalidArgumentException("Camera preset cannot be its own parent: $identifier");
			}
		}

		if($position !== null){
			self::validateVector3($position, "position");
		}
		if($horizontalRotationLimit !== null){
			self::validateVector2($horizontalRotationLimit, "horizontalRotationLimit");
		}
		if($verticalRotationLimit !== null){
			self::validateVector2($verticalRotationLimit, "verticalRotationLimit");
		}
		if($viewOffset !== null){
			self::validateVector2($viewOffset, "viewOffset");
		}
		if($entityOffset !== null){
			self::validateVector3($entityOffset, "entityOffset");
		}

		if($pitch !== null && (!is_finite($pitch) || $pitch < -90.0 || $pitch > 90.0)){
			throw new \InvalidArgumentException("Pitch must be a finite float between -90.0 and 90.0 degrees");
		}
		if($yaw !== null && !is_finite($yaw)){
			throw new \InvalidArgumentException("Yaw must be a finite float");
		}
		if($rotationSpeed !== null && (!is_finite($rotationSpeed) || $rotationSpeed < 0.0)){
			throw new \InvalidArgumentException("Rotation speed must be a finite non-negative number");
		}
		if($radius !== null && (!is_finite($radius) || $radius < 0.0)){
			throw new \InvalidArgumentException("Radius must be a finite non-negative number");
		}
		if($blockListeningRadius !== null && (!is_finite($blockListeningRadius) || $blockListeningRadius < 0.0)){
			throw new \InvalidArgumentException("Block listening radius must be a finite non-negative number");
		}
		if($yawLimitMin !== null && !is_finite($yawLimitMin)){
			throw new \InvalidArgumentException("YawLimitMin must be a finite number");
		}
		if($yawLimitMax !== null && !is_finite($yawLimitMax)){
			throw new \InvalidArgumentException("YawLimitMax must be a finite number");
		}
		if($yawLimitMin !== null && $yawLimitMax !== null && $yawLimitMin > $yawLimitMax){
			throw new \InvalidArgumentException("YawLimitMin cannot be greater than YawLimitMax");
		}

		$this->identifier = $identifier;
		$this->parent = $parent;
		$this->position = $position !== null ? clone $position : null;
		$this->pitch = $pitch;
		$this->yaw = $yaw !== null ? CameraSetOptions::normalizeYaw($yaw) : null;
		$this->rotationSpeed = $rotationSpeed;
		$this->snapToTarget = $snapToTarget;
		$this->horizontalRotationLimit = $horizontalRotationLimit !== null ? clone $horizontalRotationLimit : null;
		$this->verticalRotationLimit = $verticalRotationLimit !== null ? clone $verticalRotationLimit : null;
		$this->continueTargeting = $continueTargeting;
		$this->blockListeningRadius = $blockListeningRadius;
		$this->viewOffset = $viewOffset !== null ? clone $viewOffset : null;
		$this->entityOffset = $entityOffset !== null ? clone $entityOffset : null;
		$this->radius = $radius;
		$this->yawLimitMin = $yawLimitMin;
		$this->yawLimitMax = $yawLimitMax;
		$this->audioListener = $audioListener;
		$this->playerEffects = $playerEffects;
		$this->controlScheme = $controlScheme;
	}

	public static function isValidIdentifier(string $identifier) : bool{
		return preg_match('/^[a-zA-Z0-9_.-]+:[a-zA-Z0-9_.\/-]+$/', $identifier) === 1;
	}

	public static function validateIdentifier(string $identifier, bool $isCustom) : void{
		if(!self::isValidIdentifier($identifier)){
			throw new \InvalidArgumentException("Invalid namespaced identifier '$identifier'");
		}
		if($isCustom){
			$namespace = explode(':', $identifier, 2)[0];
			if(strtolower($namespace) === 'minecraft'){
				throw new \InvalidArgumentException("Custom preset identifier cannot use reserved 'minecraft' namespace: $identifier");
			}
		}
	}

	public static function validateParentIdentifier(string $parent) : void{
		if(!self::isValidIdentifier($parent)){
			throw new \InvalidArgumentException("Invalid parent preset identifier '$parent'");
		}
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

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function getParent() : string{
		return $this->parent;
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

	public function getRotationSpeed() : ?float{
		return $this->rotationSpeed;
	}

	public function getSnapToTarget() : ?bool{
		return $this->snapToTarget;
	}

	public function getHorizontalRotationLimit() : ?Vector2{
		return $this->horizontalRotationLimit !== null ? clone $this->horizontalRotationLimit : null;
	}

	public function getVerticalRotationLimit() : ?Vector2{
		return $this->verticalRotationLimit !== null ? clone $this->verticalRotationLimit : null;
	}

	public function getContinueTargeting() : ?bool{
		return $this->continueTargeting;
	}

	public function getBlockListeningRadius() : ?float{
		return $this->blockListeningRadius;
	}

	public function getViewOffset() : ?Vector2{
		return $this->viewOffset !== null ? clone $this->viewOffset : null;
	}

	public function getEntityOffset() : ?Vector3{
		return $this->entityOffset !== null ? clone $this->entityOffset : null;
	}

	public function getRadius() : ?float{
		return $this->radius;
	}

	public function getYawLimitMin() : ?float{
		return $this->yawLimitMin;
	}

	public function getYawLimitMax() : ?float{
		return $this->yawLimitMax;
	}

	public function getAudioListener() : ?CameraAudioListener{
		return $this->audioListener;
	}

	public function getPlayerEffects() : ?bool{
		return $this->playerEffects;
	}

	public function getControlScheme() : ?CameraControlScheme{
		return $this->controlScheme;
	}

	public function getMinimumProtocol() : int{
		if($this->controlScheme !== null){
			return ProtocolInfo::PROTOCOL_1_21_80;
		}
		if($this->yawLimitMin !== null || $this->yawLimitMax !== null){
			return ProtocolInfo::PROTOCOL_1_21_60;
		}
		if($this->blockListeningRadius !== null){
			return ProtocolInfo::PROTOCOL_1_21_50;
		}
		if($this->horizontalRotationLimit !== null || $this->verticalRotationLimit !== null || $this->continueTargeting !== null){
			return ProtocolInfo::PROTOCOL_1_21_40;
		}
		if($this->rotationSpeed !== null || $this->snapToTarget !== null || $this->entityOffset !== null){
			return ProtocolInfo::PROTOCOL_1_21_30;
		}
		if($this->viewOffset !== null || $this->radius !== null){
			return ProtocolInfo::PROTOCOL_1_21_20;
		}
		if($this->audioListener !== null || $this->playerEffects !== null){
			return ProtocolInfo::PROTOCOL_1_20_10;
		}
		return ProtocolInfo::PROTOCOL_1_20_0;
	}

	/**
	 * @internal Used internally by CameraPresetRegistry to compile wire presets
	 */
	public function toProtocol() : ProtocolCameraPreset{
		$protoControlScheme = null;
		if($this->controlScheme !== null){
			$protoControlScheme = ProtocolControlScheme::from($this->controlScheme->value);
		}

		return new ProtocolCameraPreset(
			name: $this->identifier,
			parent: $this->parent,
			xPosition: $this->position?->x,
			yPosition: $this->position?->y,
			zPosition: $this->position?->z,
			pitch: $this->pitch,
			yaw: $this->yaw,
			rotationSpeed: $this->rotationSpeed,
			snapToTarget: $this->snapToTarget,
			horizontalRotationLimit: $this->horizontalRotationLimit !== null ? clone $this->horizontalRotationLimit : null,
			verticalRotationLimit: $this->verticalRotationLimit !== null ? clone $this->verticalRotationLimit : null,
			continueTargeting: $this->continueTargeting,
			blockListeningRadius: $this->blockListeningRadius,
			viewOffset: $this->viewOffset !== null ? clone $this->viewOffset : null,
			entityOffset: $this->entityOffset !== null ? clone $this->entityOffset : null,
			radius: $this->radius,
			yawLimitMin: $this->yawLimitMin,
			yawLimitMax: $this->yawLimitMax,
			audioListenerType: $this->audioListener?->value,
			playerEffects: $this->playerEffects,
			alignTargetAndCameraForward: null,
			aimAssist: null,
			controlScheme: $protoControlScheme
		);
	}

	public static function builtin(string $identifier) : self{
		return new self($identifier, '');
	}
}
