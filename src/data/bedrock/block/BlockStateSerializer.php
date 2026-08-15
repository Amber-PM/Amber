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

namespace pocketmine\data\bedrock\block;

/**
 * Implementors of this interface decide how blockstate IDs will be represented as NBT.
 *
 * @phpstan-type BlockStateId int
 */
interface BlockStateSerializer{

	/**
	 * Serializes an implementation-defined blockstate ID to NBT for storage.
	 *
	 * @phpstan-param BlockStateId $stateId
	 * @throws BlockStateSerializeException
	 */
	public function serialize(int $stateId) : BlockStateData;
}
