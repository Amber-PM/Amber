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

namespace pocketmine\form;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * The validated answers of a CustomForm, by element ID.
 */
final class CustomFormResponse{
	/**
	 * @param array<string, string|bool|float|int> $values
	 */
	public function __construct(
		private array $values
	){}

	/** @return array<string, string|bool|float|int> */
	public function getAll() : array{ return $this->values; }

	public function getString(string $id) : string{
		$value = $this->values[$id] ?? null;
		return is_string($value) ? $value : throw new \InvalidArgumentException("\"$id\" is not a text input");
	}

	public function getBool(string $id) : bool{
		$value = $this->values[$id] ?? null;
		return is_bool($value) ? $value : throw new \InvalidArgumentException("\"$id\" is not a toggle");
	}

	public function getFloat(string $id) : float{
		$value = $this->values[$id] ?? null;
		return is_float($value) ? $value : throw new \InvalidArgumentException("\"$id\" is not a slider");
	}

	/** The chosen index of a dropdown or step slider. */
	public function getInt(string $id) : int{
		$value = $this->values[$id] ?? null;
		return is_int($value) ? $value : throw new \InvalidArgumentException("\"$id\" is not a dropdown or step slider");
	}
}
