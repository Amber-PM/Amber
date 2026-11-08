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

use pocketmine\block\Block;
use pocketmine\block\Chain;
use pocketmine\block\ChorusFlower;
use pocketmine\block\ChorusPlant;
use pocketmine\block\CopperGrate;
use pocketmine\block\Farmland;
use pocketmine\block\GrassPath;
use pocketmine\block\ItemFrame;
use pocketmine\block\Piston;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\tile\Container;
use pocketmine\block\utils\ComparatorSignalSource;
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
	/** @var array<int, int> comparator hash => signal it last read from the container or block behind it */
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

	/**
	 * container or measurable block signal at the position or null when nothing there can be measured
	 */
	private function readMeasurable(Vector3 $pos) : ?int{
		$signal = $this->readContainer($pos);
		if($signal !== null){
			return $signal;
		}
		$block = $this->world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		return $block instanceof ComparatorSignalSource && !$block instanceof ItemFrame ? $block->getComparatorSignal() : null;
	}

	/**
	 * what the comparator measures behind it and whether it was read through a block
	 * bedrock reads through one comparator conducting block and ignores the power of that block
	 *
	 * @return array{?int, bool}
	 */
	public function readFor(RedstoneComparator $comparator) : array{
		$facing = $comparator->getFacing();
		$back = $comparator->getPosition()->getSide($facing);
		if(!$this->world->isChunkLoaded($back->getFloorX() >> 4, $back->getFloorZ() >> 4)){
			return [null, false];
		}
		$signal = $this->readMeasurable($back);
		if($signal !== null){
			return [$signal, false];
		}
		if(!self::conductsForComparator($this->world->getBlockAt($back->getFloorX(), $back->getFloorY(), $back->getFloorZ()))){
			return [null, false];
		}
		$beyond = $back->getSide($facing);
		if(!$this->world->isInWorld($beyond->getFloorX(), $beyond->getFloorY(), $beyond->getFloorZ()) || !$this->world->isChunkLoaded($beyond->getFloorX() >> 4, $beyond->getFloorZ() >> 4)){
			return [null, false];
		}
		$frame = $this->world->getBlockAt($beyond->getFloorX(), $beyond->getFloorY(), $beyond->getFloorZ());
		if($frame instanceof ItemFrame){
			//the frame has to hang on the block in between
			return $frame->getFacing() === $facing ? [$frame->getComparatorSignal(), true] : [null, false];
		}
		$signal = $this->readMeasurable($beyond);
		return $signal !== null ? [$signal, true] : [null, false];
	}

	/** bedrock also reads through a few blocks that do not conduct power */
	public static function conductsForComparator(Block $block) : bool{
		return RedstoneEngine::isConductor($block) ||
			$block instanceof Piston ||
			$block instanceof Chain ||
			$block instanceof Farmland ||
			$block instanceof GrassPath ||
			$block instanceof CopperGrate ||
			$block instanceof ChorusPlant ||
			$block instanceof ChorusFlower;
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
			if($this->readFor($comparator)[0] !== $signal){
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
