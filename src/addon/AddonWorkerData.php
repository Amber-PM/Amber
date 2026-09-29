<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;
use function is_int;
use function is_string;

/**
 * Add-on block states for threads other than the main one, which never register add-on blocks.
 *
 * @internal
 */
final class AddonWorkerData{
	/** @var list<BlockStateData>|null */
	private static ?array $networkBlockStates = null;
	/** @phpstan-var array<int, BlockStateData> */
	private static array $stateData = [];

	private function __construct(){
		//NOOP
	}

	/** @return list<BlockStateData> */
	public static function getNetworkBlockStates() : array{
		return self::$networkBlockStates ?? AddonManager::getInstance()?->getNetworkBlockStates() ?? [];
	}

	public static function getStateData(int $internalStateId) : ?BlockStateData{
		return self::$stateData[$internalStateId] ?? null;
	}

	/**
	 * @param list<BlockStateData>       $networkBlockStates
	 * @param array<int, BlockStateData> $stateData
	 */
	public static function encode(array $networkBlockStates, array $stateData) : string{
		$serializer = new LittleEndianNbtSerializer();
		$states = [];
		foreach($networkBlockStates as $state){
			$states[] = $serializer->write(new TreeRoot($state->toNbt()));
		}
		$byStateId = [];
		foreach($stateData as $stateId => $state){
			$byStateId[$stateId] = $serializer->write(new TreeRoot($state->toNbt()));
		}
		return (string) igbinary_serialize([$states, $byStateId]);
	}

	public static function apply(string $payload) : void{
		$decoded = igbinary_unserialize($payload);
		if(!is_array($decoded) || !is_array($decoded[0] ?? null) || !is_array($decoded[1] ?? null)){
			throw new \InvalidArgumentException("Invalid add-on worker payload");
		}
		$serializer = new LittleEndianNbtSerializer();
		$states = [];
		foreach($decoded[0] as $nbt){
			if(!is_string($nbt)){
				throw new \InvalidArgumentException("Invalid add-on worker payload");
			}
			$states[] = BlockStateData::fromNbt($serializer->read($nbt)->mustGetCompoundTag());
		}
		$stateData = [];
		foreach($decoded[1] as $stateId => $nbt){
			if(!is_int($stateId) || !is_string($nbt)){
				throw new \InvalidArgumentException("Invalid add-on worker payload");
			}
			$stateData[$stateId] = BlockStateData::fromNbt($serializer->read($nbt)->mustGetCompoundTag());
		}
		self::$networkBlockStates = $states;
		self::$stateData = $stateData;
	}

	/** @internal for tests */
	public static function reset() : void{
		self::$networkBlockStates = null;
		self::$stateData = [];
	}
}
