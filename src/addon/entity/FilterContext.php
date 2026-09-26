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

namespace pocketmine\addon\entity;

use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * The entities a filter can name as its subject.
 */
final class FilterContext{
	public function __construct(
		public readonly Entity $self,
		public readonly ?Entity $other = null,
		public readonly ?Entity $damager = null,
		public readonly ?int $damageCause = null,
		public readonly bool $damageFatal = false
	){}

	public function subject(string $name) : ?Entity{
		return match($name){
			"self" => $this->self,
			"other" => $this->other,
			"target" => $this->self->getTargetEntity(),
			"damager" => $this->damager ?? $this->other,
			"player" => $this->other instanceof Player ? $this->other : ($this->damager instanceof Player ? $this->damager : null),
			"parent" => $this->self->getOwningEntity(),
			default => null,
		};
	}
}
