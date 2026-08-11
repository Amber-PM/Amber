<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\___|_|  |_|_|_| |_|\___|     |_|  |_|_|
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

final class ShapeGroup{

	/** @var ShapeHandle[] */
	private array $handles = [];

	public function add(ShapeHandle $handle) : self{
		$this->handles[] = $handle;
		return $this;
	}

	public function remove() : void{
		foreach($this->handles as $handle){
			$handle->remove();
		}
		$this->handles = [];
	}

	public function update(int $index, Shape $newShape, ?Vector3 $newPos = null) : void{
		$this->handles[$index]?->update($newShape, $newPos);
	}

	public function get(int $index) : ?ShapeHandle{
		return $this->handles[$index] ?? null;
	}

	public function count() : int{
		return count($this->handles);
	}

	public function isAllRemoved() : bool{
		foreach($this->handles as $handle){
			if(!$handle->isRemoved()){
				return false;
			}
		}
		return true;
	}
}
