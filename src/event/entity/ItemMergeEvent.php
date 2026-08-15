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

namespace pocketmine\event\entity;

use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;

/**
 * Called when an item entity tries to merge into another item entity.
 *
 * @phpstan-extends EntityEvent<ItemEntity>
 */
class ItemMergeEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		ItemEntity $entity,
		protected ItemEntity $target
	){
		$this->entity = $entity;
	}

	/**
	 * Returns the merge destination.
	 */
	public function getTarget() : ItemEntity{
		return $this->target;
	}

}
