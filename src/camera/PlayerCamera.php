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

namespace pocketmine\camera;

use pocketmine\camera\options\CameraEase;
use pocketmine\camera\options\CameraEaseType;
use pocketmine\camera\options\CameraFadeOptions;
use pocketmine\camera\options\CameraSetOptions;
use pocketmine\camera\options\CameraShakeType;
use pocketmine\camera\preset\CameraPresetRegistry;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\CameraInstructionPacket;
use pocketmine\network\mcpe\protocol\CameraShakePacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstructionColor;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstructionTime;
use pocketmine\network\mcpe\protocol\types\camera\CameraFovInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEase;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEaseType;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionRotation;
use pocketmine\network\mcpe\protocol\types\camera\CameraTargetInstruction;
use pocketmine\player\Player;
use function is_finite;
use function spl_object_id;

final class PlayerCamera{
	private ?string $lastSetPreset = null;

	public function __construct(
		private readonly Player $player
	){}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getLastSetPreset() : ?string{
		return $this->lastSetPreset;
	}

	public function supportsTargeting() : bool{
		return $this->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_20;
	}

	public function supportsFov() : bool{
		return $this->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_100;
	}

	public function supportsAttachment() : bool{
		return $this->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_120;
	}

	public function set(string $presetIdentifier, ?CameraSetOptions $options = null) : bool{
		$registry = CameraPresetRegistry::getInstance();
		if(!$registry->isRegistered($presetIdentifier)){
			throw new \InvalidArgumentException("Unregistered camera preset: $presetIdentifier");
		}

		$protocolId = $this->getProtocolId();
		$presetIndex = $registry->getIndex($presetIdentifier, $protocolId);
		if($presetIndex === null){
			return false;
		}

		$options = $options ?? CameraSetOptions::create();

		if($options->getViewOffset() !== null && $protocolId < ProtocolInfo::PROTOCOL_1_21_20){
			return false;
		}
		if($options->getEntityOffset() !== null && $protocolId < ProtocolInfo::PROTOCOL_1_21_40){
			return false;
		}

		$ease = null;
		if(($optEase = $options->getEase()) !== null){
			$ease = new CameraSetInstructionEase(
				CameraSetInstructionEaseType::fromName($optEase->getType()->value),
				$optEase->getDurationSeconds()
			);
		}

		$rot = null;
		if($options->getPitch() !== null && $options->getYaw() !== null){
			$rot = new CameraSetInstructionRotation($options->getPitch(), $options->getYaw());
		}

		$instruction = new CameraSetInstruction(
			preset: $presetIndex,
			ease: $ease,
			cameraPosition: $options->getPosition(),
			rotation: $rot,
			facingPosition: $options->getFacingPosition(),
			viewOffset: $options->getViewOffset(),
			entityOffset: $options->getEntityOffset(),
			default: $options->isDefault() ? true : null,
			ignoreStartingValuesComponent: false
		);

		$pk = CameraInstructionPacket::create(
			set: $instruction,
			clear: null,
			fade: null,
			target: null,
			removeTarget: null,
			fieldOfView: null,
			spline: null,
			attachToEntity: null,
			detachFromEntity: null
		);

		if(!$this->sendPacket($pk)){
			return false;
		}

		$this->lastSetPreset = $presetIdentifier;
		return true;
	}

	public function clear() : bool{
		$pk = CameraInstructionPacket::create(
			set: null,
			clear: true,
			fade: null,
			target: null,
			removeTarget: $this->supportsTargeting() ? true : null,
			fieldOfView: null,
			spline: null,
			attachToEntity: null,
			detachFromEntity: $this->supportsAttachment() ? true : null
		);

		if(!$this->sendPacket($pk)){
			return false;
		}

		$this->lastSetPreset = null;
		return true;
	}

	public function fade(CameraFadeOptions $options) : bool{
		$c = $options->getColor();
		$fadeColor = new CameraFadeInstructionColor($c->getR() / 255.0, $c->getG() / 255.0, $c->getB() / 255.0);
		$fadeTime = new CameraFadeInstructionTime($options->getFadeInSeconds(), $options->getHoldSeconds(), $options->getFadeOutSeconds());

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: new CameraFadeInstruction($fadeTime, $fadeColor),
			target: null,
			removeTarget: null,
			fieldOfView: null,
			spline: null,
			attachToEntity: null,
			detachFromEntity: null
		);

		return $this->sendPacket($pk);
	}

	public function shake(float $intensity, float $durationSeconds, CameraShakeType $type = CameraShakeType::POSITIONAL) : bool{
		if(!is_finite($intensity) || $intensity < 0.0 || !is_finite($durationSeconds) || $durationSeconds < 0.0){
			throw new \InvalidArgumentException("Shake intensity and duration must be finite non-negative numbers");
		}

		$pk = CameraShakePacket::create($intensity, $durationSeconds, $type->value, CameraShakePacket::ACTION_ADD);
		return $this->sendPacket($pk);
	}

	public function stopShake(CameraShakeType $type = CameraShakeType::POSITIONAL) : bool{
		$pk = CameraShakePacket::create(0.0, 0.0, $type->value, CameraShakePacket::ACTION_STOP);
		return $this->sendPacket($pk);
	}

	public function setFov(float $fieldOfView, float $easeDuration = 0.0, CameraEaseType $easeType = CameraEaseType::LINEAR) : bool{
		if(!$this->supportsFov()){
			return false;
		}
		if(!is_finite($fieldOfView) || $fieldOfView < 1.0 || $fieldOfView > 170.0){
			throw new \InvalidArgumentException("Field of view must be between 1.0 and 170.0 degrees");
		}
		if(!is_finite($easeDuration) || $easeDuration < 0.0){
			throw new \InvalidArgumentException("Ease duration must be a non-negative finite number");
		}

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: null,
			target: null,
			removeTarget: null,
			fieldOfView: new CameraFovInstruction($fieldOfView, $easeDuration, $easeType->value, false),
			spline: null,
			attachToEntity: null,
			detachFromEntity: null
		);

		return $this->sendPacket($pk);
	}

	public function clearFov(?CameraEase $ease = null) : bool{
		if(!$this->supportsFov()){
			return false;
		}

		$easeDuration = $ease?->getDurationSeconds() ?? 0.0;
		$easeType = $ease?->getType()->value ?? CameraEaseType::LINEAR->value;

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: null,
			target: null,
			removeTarget: null,
			fieldOfView: new CameraFovInstruction(70.0, $easeDuration, $easeType, true),
			spline: null,
			attachToEntity: null,
			detachFromEntity: null
		);

		return $this->sendPacket($pk);
	}

	public function setTarget(Entity $entity, ?Vector3 $offset = null) : bool{
		if(!$this->supportsTargeting()){
			return false;
		}
		if(!$entity->isAlive() || $entity->isClosed()){
			throw new \InvalidArgumentException("Cannot target closed or dead entity");
		}
		if($entity->getWorld() !== $this->player->getWorld()){
			throw new \InvalidArgumentException("Cannot target entity in a different world");
		}
		if(!$this->isEntityVisibleToPlayer($entity)){
			return false;
		}
		if($offset !== null && (!is_finite($offset->x) || !is_finite($offset->y) || !is_finite($offset->z))){
			throw new \InvalidArgumentException("Target offset contains NaN or infinite coordinates");
		}

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: null,
			target: new CameraTargetInstruction($offset, $entity->getId()),
			removeTarget: null,
			fieldOfView: null,
			spline: null,
			attachToEntity: null,
			detachFromEntity: null
		);

		return $this->sendPacket($pk);
	}

	public function clearTarget() : bool{
		if(!$this->supportsTargeting()){
			return false;
		}

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: null,
			target: null,
			removeTarget: true,
			fieldOfView: null,
			spline: null,
			attachToEntity: null,
			detachFromEntity: null
		);

		return $this->sendPacket($pk);
	}

	public function attachToEntity(Entity $entity) : bool{
		if(!$this->supportsAttachment()){
			return false;
		}
		if(!$entity->isAlive() || $entity->isClosed()){
			throw new \InvalidArgumentException("Cannot attach to closed or dead entity");
		}
		if($entity->getWorld() !== $this->player->getWorld()){
			throw new \InvalidArgumentException("Cannot attach to entity in a different world");
		}
		if(!$this->isEntityVisibleToPlayer($entity)){
			return false;
		}

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: null,
			target: null,
			removeTarget: null,
			fieldOfView: null,
			spline: null,
			attachToEntity: $entity->getId(),
			detachFromEntity: null
		);

		return $this->sendPacket($pk);
	}

	public function detachFromEntity() : bool{
		if(!$this->supportsAttachment()){
			return false;
		}

		$pk = CameraInstructionPacket::create(
			set: null,
			clear: null,
			fade: null,
			target: null,
			removeTarget: null,
			fieldOfView: null,
			spline: null,
			attachToEntity: null,
			detachFromEntity: true
		);

		return $this->sendPacket($pk);
	}

	private function sendPacket(ClientboundPacket $packet) : bool{
		return $this->player->getNetworkSession()->sendDataPacket($packet);
	}

	private function isEntityVisibleToPlayer(Entity $entity) : bool{
		if($entity === $this->player){
			return true;
		}

		return isset($entity->getViewers()[spl_object_id($this->player)]);
	}

	private function getProtocolId() : int{
		return $this->player->getNetworkSession()->getProtocolId();
	}
}
