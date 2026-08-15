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

namespace pocketmine\event\world;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\shape\Shape;
use pocketmine\world\World;

class WorldShapeEvent extends WorldEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @param Player[] $recipients
	 */
	public function __construct(
		World $world,
		private Shape $shape,
		private Vector3 $position,
		private array $recipients
	){
		parent::__construct($world);
	}

	public function getShape() : Shape{
		return $this->shape;
	}

	public function setShape(Shape $shape) : void{
		$this->shape = $shape;
	}

	public function getPosition() : Vector3{
		return $this->position;
	}

	/**
	 * @return Player[]
	 */
	public function getRecipients() : array{
		return $this->recipients;
	}

	/**
	 * @param Player[] $recipients
	 */
	public function setRecipients(array $recipients) : void{
		$this->recipients = $recipients;
	}
}
