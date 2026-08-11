<?php

declare(strict_types=1);

namespace pocketmine\player;

enum InputLockFlags : int {

	case MOVEMENT = 1 << 0;
	case ROTATION = 1 << 1;
	case JUMP     = 1 << 2;
	case SNEAK    = 1 << 3;
	case MOUNT    = 1 << 4;
	case DISMOUNT = 1 << 5;
	case HOTBAR   = 1 << 6;
	case ATTACK   = 1 << 7;

	public static function all() : int{
		$mask = 0;
		foreach(self::cases() as $case){
			$mask |= $case->value;
		}
		return $mask;
	}
}
