<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\path;

use pocketmine\math\Vector3;
use function count;

final class Path{

	private int $currentIndex = 0;

	/**
	 * @param Node[] $nodes
	 */
	public function __construct(
		private array $nodes,
		private Vector3 $target
	){}

	/**
	 * @return Node[]
	 */
	public function getNodes() : array{
		return $this->nodes;
	}

	public function getTarget() : Vector3{
		return $this->target;
	}

	public function getCurrentIndex() : int{
		return $this->currentIndex;
	}

	public function setCurrentIndex(int $index) : void{
		$this->currentIndex = $index;
	}

	public function advance() : void{
		$this->currentIndex++;
	}

	public function isFinished() : bool{
		return $this->currentIndex >= count($this->nodes);
	}

	public function getCurrentNode() : ?Node{
		return $this->nodes[$this->currentIndex] ?? null;
	}

	public function getEndNode() : ?Node{
		$count = count($this->nodes);
		return $count > 0 ? $this->nodes[$count - 1] : null;
	}
}
