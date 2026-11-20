<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\path;

use pocketmine\math\Vector3;

final class Node{
	public float $gCost = 0.0;
	public float $hCost = 0.0;
	public float $fCost = 0.0;
	public ?Node $parent = null;

	public function __construct(
		public readonly int $x,
		public readonly int $y,
		public readonly int $z
	){}

	public function toVector3() : Vector3{
		return new Vector3($this->x + 0.5, $this->y, $this->z + 0.5);
	}

	public function equals(Node $other) : bool{
		return $this->x === $other->x && $this->y === $other->y && $this->z === $other->z;
	}

	public static function hash(int $x, int $y, int $z) : string{
		return "$x,$y,$z";
	}
}
