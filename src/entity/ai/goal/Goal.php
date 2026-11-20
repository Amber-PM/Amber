<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

abstract class Goal{

	public const FLAG_MOVE = 1 << 0;
	public const FLAG_LOOK = 1 << 1;
	public const FLAG_JUMP = 1 << 2;
	public const FLAG_TARGET = 1 << 3;

	public function __construct(
		protected int $priority,
		protected int $flags = 0
	){}

	public function getPriority() : int{
		return $this->priority;
	}

	public function setPriority(int $priority) : void{
		$this->priority = $priority;
	}

	public function getFlags() : int{
		return $this->flags;
	}

	abstract public function canStart() : bool;

	public function shouldContinue() : bool{
		return $this->canStart();
	}

	public function start() : void{}

	public function tick() : void{}

	public function stop() : void{}

	public function isInterruptible() : bool{
		return true;
	}
}
