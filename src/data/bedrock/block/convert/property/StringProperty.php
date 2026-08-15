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

namespace pocketmine\data\bedrock\block\convert\property;

/**
 * @phpstan-template TBlock of object
 * @phpstan-extends Property<TBlock>
 */
interface StringProperty extends Property{
	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getPossibleValues() : array;

	/**
	 * TODO: These are only used for flattened IDs for now, we should expand their use to all properties
	 * in the future and remove the dependencies on BlockStateReader and BlockStateWriter
	 * @phpstan-param TBlock $block
	 */
	public function deserializePlain(object $block, string $raw) : void;

	/**
	 * TODO: These are only used for flattened IDs for now, we should expand their use to all properties
	 * in the future and remove the dependencies on BlockStateReader and BlockStateWriter
	 * @phpstan-param TBlock $block
	 */
	public function serializePlain(object $block) : string;
}
