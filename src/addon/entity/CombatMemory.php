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

namespace pocketmine\addon\entity;

use pocketmine\entity\Entity;

/**
 * Who last hurt, and who was last attacked by, any entity (players included), for owner_hurt_by_target and
 * owner_hurt_target. Held weakly, so it never keeps an entity alive.
 */
final class CombatMemory{
	/** @var \WeakMap<Entity, array{\WeakReference<Entity>, int}>|null */
	private static ?\WeakMap $hurtBy = null;
	/** @var \WeakMap<Entity, array{\WeakReference<Entity>, int}>|null */
	private static ?\WeakMap $attacked = null;

	private function __construct(){
		//NOOP
	}

	public static function record(Entity $victim, Entity $damager, int $tick) : void{
		self::$hurtBy ??= new \WeakMap();
		self::$attacked ??= new \WeakMap();
		self::$hurtBy[$victim] = [\WeakReference::create($damager), $tick];
		self::$attacked[$damager] = [\WeakReference::create($victim), $tick];
	}

	/** @return array{Entity, int}|null */
	public static function lastHurtBy(Entity $entity) : ?array{
		return self::read(self::$hurtBy, $entity);
	}

	/** @return array{Entity, int}|null */
	public static function lastAttacked(Entity $entity) : ?array{
		return self::read(self::$attacked, $entity);
	}

	/**
	 * @param \WeakMap<Entity, array{\WeakReference<Entity>, int}>|null $map
	 * @return array{Entity, int}|null
	 */
	private static function read(?\WeakMap $map, Entity $entity) : ?array{
		if($map === null || !isset($map[$entity])){
			return null;
		}
		[$reference, $tick] = $map[$entity];
		$other = $reference->get();
		return $other === null ? null : [$other, $tick];
	}
}
