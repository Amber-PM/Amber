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

namespace pocketmine\world\redstone;

use pocketmine\block\RedstoneComparator;
use pocketmine\block\tile\Container;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_key_first;
use function count;
use function floor;
use function max;
use function min;

/**
 * Containers do not cause block updates when their contents change, so the comparators reading one are re-checked
 * every redstone tick.
 */
final class ContainerWatch{
	/** @var array<int, int> comparator hash => signal it last read from the container behind it */
	private array $watched = [];

	public function __construct(private World $world){}

	/**
	 * The signal of the container at the position (0 when empty, 1-15 by how full it is), or null when there is no
	 * container there. Does not load chunks.
	 */
	public function readContainer(Vector3 $pos) : ?int{
		$x = $pos->getFloorX();
		$z = $pos->getFloorZ();
		if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
			return null; //getTileAt() would load the chunk
		}
		$tile = $this->world->getTileAt($x, $pos->getFloorY(), $z);
		return $tile instanceof Container ? self::containerSignal($tile) : null;
	}

	public function watch(Vector3 $comparator, ?int $signal) : void{
		$hash = World::blockHash($comparator->getFloorX(), $comparator->getFloorY(), $comparator->getFloorZ());
		if($signal === null){
			unset($this->watched[$hash]);
		}else{
			$this->watched[$hash] = $signal;
		}
	}

	public function getWatchedCount() : int{
		return count($this->watched);
	}

	public function clear() : void{
		$this->watched = [];
	}

	public function check(RedstoneEngine $engine, int $limit) : int{
		$checked = 0;
		$limit = min($limit, count($this->watched));
		while($checked < $limit && $this->watched !== []){
			$hash = array_key_first($this->watched);
			$signal = $this->watched[$hash];
			++$checked;
			unset($this->watched[$hash]);
			$this->watched[$hash] = $signal;

			World::getBlockXYZ($hash, $x, $y, $z);
			if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			$comparator = $this->world->getBlockAt($x, $y, $z);
			if(!$comparator instanceof RedstoneComparator){
				unset($this->watched[$hash]);
				continue;
			}
			$back = $comparator->getPosition()->getSide($comparator->getFacing());
			if(!$this->world->isChunkLoaded($back->getFloorX() >> 4, $back->getFloorZ() >> 4)){
				continue;
			}
			if($this->readContainer($back) !== $signal){
				$engine->request($x, $y, $z);
			}
		}
		return $checked;
	}

	/** The signal a comparator reads from a container: 0 when empty, 1-15 by how full it is. */
	public static function containerSignal(Container $container) : int{
		$inventory = $container->getInventory();
		$size = $inventory->getSize();
		if($size === 0){
			return 0;
		}
		$fullness = 0.0;
		$any = false;
		foreach($inventory->getContents() as $item){
			$fullness += min(1, max(0, $item->getCount()) / max(1, min($inventory->getMaxStackSize(), $item->getMaxStackSize())));
			$any = true;
		}
		return $any ? min(15, (int) floor(1 + ($fullness / $size) * 14)) : 0;
	}
}
