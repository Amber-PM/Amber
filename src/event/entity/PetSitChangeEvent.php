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

use pocketmine\entity\TameableAnimal;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

/**
 * Called when a tamed pet's sitting state is changed.
 *
 * @phpstan-extends EntityEvent<TameableAnimal>
 */
class PetSitChangeEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		TameableAnimal $entity,
		private Player $player,
		private bool $sitting
	){
		$this->entity = $entity;
	}

	/**
	 * @return TameableAnimal
	 */
	public function getEntity() : TameableAnimal{
		/** @var TameableAnimal $entity */
		$entity = $this->entity;
		return $entity;
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting) : void{
		$this->sitting = $sitting;
	}
}
