<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\handler\fixtures {
	// use the real clock outside timeout tests
	final class ResourcePackTimeoutClock{
		public static ?float $now = null;
		/** @return array{int, int}|int|float|false */
		public static function read(bool $asNumber) : array|int|float|false{
			if(self::$now === null){ return \hrtime($asNumber); }
			return $asNumber ? (int) round(self::$now * 1_000_000_000) : [(int) self::$now, 0];
		}
	}
}

namespace pocketmine\network\mcpe {
	function hrtime(bool $asNumber = false) : array|int|float|false{
		return \pocketmine\network\mcpe\handler\fixtures\ResourcePackTimeoutClock::read($asNumber);
	}
}

namespace pocketmine\network\mcpe\handler {
	function hrtime(bool $asNumber = false) : array|int|float|false{
		return \pocketmine\network\mcpe\handler\fixtures\ResourcePackTimeoutClock::read($asNumber);
	}
}
