<?php

declare(strict_types=1);

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

class PlayerInputLockEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		private int $flags
	){
		$this->player = $player;
	}

	public function getFlags() : int{
		return $this->flags;
	}

	public function setFlags(int $flags) : void{
		$this->flags = $flags;
	}
}
