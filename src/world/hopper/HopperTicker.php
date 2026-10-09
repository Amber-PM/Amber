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

namespace pocketmine\world\hopper;

use pocketmine\block\Hopper;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\block\tile\Tile;
use pocketmine\world\World;
use function array_key_first;
use function count;
use function max;
use function min;

final class HopperTicker{
	public const TRANSFER_INTERVAL = 8;

	private array $hoppers = [];
	private array $hoppersByChunk = [];
	private int $moved = 0;
	private ?int $lastTick = null;
	private HopperTransfer $transfer;

	public function __construct(private World $world, private int $maxUpdatesPerTick = 256){
		$this->maxUpdatesPerTick = max(1, $maxUpdatesPerTick);
		$this->transfer = new HopperTransfer($world, new ContainerTransferPolicy());
	}

	public function onTileAdded(Tile $tile) : void{
		if($tile instanceof HopperTile){
			$pos = $tile->getPosition();
			$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$chunk = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
			if(!isset($this->hoppers[$hash])){
				$tile->resetCooldownClock();
			}
			$this->hoppers[$hash] = true;
			$this->hoppersByChunk[$chunk][$hash] = true;
		}
	}

	public function onTileRemoved(Tile $tile) : void{
		if($tile instanceof HopperTile){
			$this->transfer->forgetInventory($tile->getInventory());
			$pos = $tile->getPosition();
			$chunk = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
			$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$this->forgetPosition($hash, $chunk);
		}
	}

	private function forgetPosition(int $hash, int $chunk) : void{
		unset($this->hoppers[$hash], $this->hoppersByChunk[$chunk][$hash]);
		if(($this->hoppersByChunk[$chunk] ?? []) === []){
			unset($this->hoppersByChunk[$chunk]);
		}
	}

	public function onChunkUnloaded(int $x, int $z) : void{
		$chunk = World::chunkHash($x, $z);
		foreach($this->hoppersByChunk[$chunk] ?? [] as $hash => $_){
			unset($this->hoppers[$hash]);
		}
		unset($this->hoppersByChunk[$chunk]);
	}

	public function clear() : void{
		$this->transfer->clear();
		$this->hoppers = [];
		$this->hoppersByChunk = [];
		$this->lastTick = null;
	}

	public function getMovedCount() : int{
		return $this->moved;
	}

	public function tick(int $currentTick) : void{
		if($this->lastTick === $currentTick){
			return;
		}
		$this->lastTick = $currentTick;
		$limit = min(count($this->hoppers), $this->maxUpdatesPerTick);
		for($processed = 0; $processed < $limit && $this->hoppers !== []; ++$processed){
			$hash = array_key_first($this->hoppers);
			unset($this->hoppers[$hash]);
			$this->hoppers[$hash] = true;
			World::getBlockXYZ($hash, $x, $y, $z);
			$chunk = World::chunkHash($x >> 4, $z >> 4);
			if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
				$this->onChunkUnloaded($x >> 4, $z >> 4);
				continue;
			}
			$tile = $this->world->getTileAt($x, $y, $z);
			$block = $this->world->getBlockAt($x, $y, $z);
			if(!$tile instanceof HopperTile || $tile->isClosed() || !$block instanceof Hopper){
				$this->forgetPosition($hash, $chunk);
				continue;
			}
			$tile->advanceTransferCooldown($currentTick);
			if($tile->getTransferCooldown() > 0 || $block->isPowered()){
				continue;
			}
			$result = $this->transfer->transfer($tile, $block, $currentTick);
			$this->moved += $result->moved;
			if($result->moved > 0){
				$tile->startTransferCooldown($currentTick, self::TRANSFER_INTERVAL);
			}
		}
	}
}
