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

namespace pocketmine\world\gamerule;

final class GameRuleDefinition{

	public function __construct(
		private string $name,
		private int $type,
		private bool|int $defaultValue,
		private bool $playerModifiable = false
	){}

	public function getName() : string{
		return $this->name;
	}

	public function getType() : int{
		return $this->type;
	}

	public function getDefaultValue() : bool|int{
		return $this->defaultValue;
	}

	public function isPlayerModifiable() : bool{
		return $this->playerModifiable;
	}
}
