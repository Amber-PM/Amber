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

use pocketmine\network\mcpe\protocol\ProtocolInfo;

final class BuiltInCameraPresets{
	public const FIRST_PERSON = 'minecraft:first_person';
	public const FREE = 'minecraft:free';
	public const THIRD_PERSON = 'minecraft:third_person';
	public const THIRD_PERSON_FRONT = 'minecraft:third_person_front';
	public const FOLLOW_ORBIT = 'minecraft:follow_orbit';
	public const FIXED_BOOM = 'minecraft:fixed_boom';
	public const CONTROL_SCHEME_CAMERA = 'minecraft:control_scheme_camera';

	private function __construct(){}

	/**
	 * @return array<string, int> identifier => minimum protocol ID
	 */
	public static function getAllWithMinProtocols() : array{
		return [
			self::FIRST_PERSON => ProtocolInfo::PROTOCOL_1_20_0,
			self::FREE => ProtocolInfo::PROTOCOL_1_20_0,
			self::THIRD_PERSON => ProtocolInfo::PROTOCOL_1_20_0,
			self::THIRD_PERSON_FRONT => ProtocolInfo::PROTOCOL_1_20_0,
			self::FOLLOW_ORBIT => ProtocolInfo::PROTOCOL_1_21_20,
			self::FIXED_BOOM => ProtocolInfo::PROTOCOL_1_21_50,
			self::CONTROL_SCHEME_CAMERA => ProtocolInfo::PROTOCOL_1_21_80,
		];
	}

	public static function isBuiltin(string $identifier) : bool{
		return isset(self::getAllWithMinProtocols()[$identifier]);
	}

	public static function getMinimumProtocol(string $identifier) : ?int{
		return self::getAllWithMinProtocols()[$identifier] ?? null;
	}

	/**
	 * @return string[]
	 */
	public static function getAll() : array{
		return array_keys(self::getAllWithMinProtocols());
	}

	public static function isAvailableOn(string $identifier, int $protocolId) : bool{
		$min = self::getMinimumProtocol($identifier);
		return $min !== null && $protocolId >= $min;
	}

	/**
	 * @return string[]
	 */
	public static function getPresetsForProtocol(int $protocolId) : array{
		$presets = [
			self::FIRST_PERSON,
			self::FREE,
			self::THIRD_PERSON,
			self::THIRD_PERSON_FRONT,
		];

		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_20){
			$presets[] = self::FOLLOW_ORBIT;
		}
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_50){
			$presets[] = self::FIXED_BOOM;
		}
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_80){
			$presets[] = self::CONTROL_SCHEME_CAMERA;
		}

		return $presets;
	}
}
