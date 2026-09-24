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

use pocketmine\addon\entity\ai\Goal;
use function in_array;
use function is_array;

/**
 * minecraft:behavior.timer_flag_1/2/3: fires on_start, holds the controls it names for a duration, fires on_end,
 * then waits out its cooldown. Packs use them to time phases (sniffing, digging, boss moves).
 */
final class TimerFlagGoal extends Goal{
	private int $endAt = 0;
	private int $readyAt = 0;

	public function getFlags() : int{
		$controls = is_array($this->data["control_flags"] ?? null) ? $this->data["control_flags"] : ["look", "move"];
		return (in_array("move", $controls, true) ? self::FLAG_MOVE : 0) | (in_array("look", $controls, true) ? self::FLAG_LOOK : 0) | (in_array("jump", $controls, true) ? self::FLAG_JUMP : 0);
	}

	public function canStart() : bool{
		return $this->now() >= $this->readyAt;
	}

	public function start() : void{
		$this->endAt = $this->now() + (int) ($this->range("duration_range", 2, 2) * 20);
		$this->mob->triggerEventDefinition($this->data["on_start"] ?? null);
		if(($this->getFlags() & self::FLAG_MOVE) !== 0){
			$this->navigator()->stop();
		}
	}

	public function canContinue() : bool{
		return $this->now() < $this->endAt;
	}

	public function stop() : void{
		$this->readyAt = $this->now() + (int) ($this->range("cooldown_range", 10, 10) * 20);
		$this->mob->triggerEventDefinition($this->data["on_end"] ?? null);
	}
}
