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
 * @author PocketmineMV Team
 * @link https://github.com/vapebw/PocketmineMV
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\shape;

use pocketmine\math\Vector3;

final class ShapeHandle{

	private bool $removed = false;

	public function __construct(
		private readonly int $networkId,
		private readonly \Closure $remover,
		private readonly ?\Closure $updater = null
	){}

	public function getNetworkId() : int{
		return $this->networkId;
	}

	public function isRemoved() : bool{
		return $this->removed;
	}

	public function update(Shape $newShape, ?Vector3 $newPos = null) : void{
		if($this->removed || $this->updater === null){
			return;
		}
		($this->updater)($newShape, $newPos);
	}

	// idempotent, safe to call twice
	public function remove() : void{
		if($this->removed){
			return;
		}
		$this->removed = true;
		($this->remover)();
	}
}
