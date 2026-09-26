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

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\Goal;

/**
 * A goal written in PHP by a plugin (MobBrain::registerGoal()), for behaviors this server does not implement or new ones.
 */
final class CallbackGoal extends Goal{
	/**
	 * @param \Closure(AddonEntity, mixed[]) : bool $canStart
	 * @param (\Closure(AddonEntity, mixed[]) : void)|null $tick
	 * @param (\Closure(AddonEntity, mixed[]) : bool)|null $canContinue
	 * @param (\Closure(AddonEntity, mixed[]) : void)|null $stop
	 */
	public function __construct(
		AddonEntity $mob,
		array $data,
		int $priority,
		private int $flags,
		private \Closure $canStart,
		private ?\Closure $tick = null,
		private ?\Closure $canContinue = null,
		private ?\Closure $stop = null
	){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return $this->flags; }

	public function canStart() : bool{ return ($this->canStart)($this->mob, $this->data); }

	public function canContinue() : bool{
		return $this->canContinue !== null ? ($this->canContinue)($this->mob, $this->data) : $this->canStart();
	}

	public function tick() : void{
		if($this->tick !== null){
			($this->tick)($this->mob, $this->data);
		}
	}

	public function stop() : void{
		if($this->stop !== null){
			($this->stop)($this->mob, $this->data);
		}
	}
}
