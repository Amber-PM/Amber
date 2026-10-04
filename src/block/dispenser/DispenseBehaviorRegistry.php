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

namespace pocketmine\block\dispenser;

use pocketmine\item\Item;
use pocketmine\utils\SingletonTrait;

final class DispenseBehaviorRegistry{
	use SingletonTrait;

	/** @var array<int, DispenseBehavior> */
	private array $behaviors = [];
	private DispenseBehavior $defaultBehavior;

	public function __construct(){
		$this->defaultBehavior = new DefaultDispenseBehavior();
		$this->registerBehaviors();
	}

	private function registerBehaviors() : void{
		// Advanced behaviors will be registered here in Task 3
	}

	public function register(int $itemTypeId, DispenseBehavior $behavior) : void{
		$this->behaviors[$itemTypeId] = $behavior;
	}

	public function get(Item $item) : DispenseBehavior{
		return $this->behaviors[$item->getTypeId()] ?? $this->defaultBehavior;
	}

	public function getDefault() : DispenseBehavior{
		return $this->defaultBehavior;
	}
}
