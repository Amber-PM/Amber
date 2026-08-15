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
