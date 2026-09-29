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

use pocketmine\block\ActivatorRail;
use pocketmine\block\Block;
use pocketmine\block\Button;
use pocketmine\block\CopperBulb;
use pocketmine\block\DaylightSensor;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Lever;
use pocketmine\block\PoweredRail;
use pocketmine\block\PressurePlate;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneLamp;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\block\tile\Container;
use pocketmine\block\TNT;
use pocketmine\block\Trapdoor;
use pocketmine\block\WeightedPressurePlate;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_pop;
use function count;
use function floor;
use function max;
use function min;

/**
 * Redstone power for one world: wire networks, torches, repeaters, comparators, and what they power (lamps,
 * copper bulbs, doors, trapdoors, fence gates, TNT, powered and activator rails).
 *
 * Changes near redstone components are queued, and at most a set number of positions are evaluated per tick, so
 * a large or looping circuit slows down instead of stalling the server. Torches that toggle too fast burn out
 * for a while, as in the game.
 */
final class RedstoneEngine{
	/** Game ticks per redstone tick. */
	private const REDSTONE_TICK = 2;
	private const MAX_WIRE_NETWORK = 4096;
	private const TORCH_BURNOUT_TOGGLES = 8;
	private const TORCH_BURNOUT_WINDOW = 60;
	private const TORCH_BURNOUT_TIME = 160;

	private const HORIZONTAL = [Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST];

	/** @var \SplQueue<int> */
	private \SplQueue $queue;
	/** @var array<int, true> */
	private array $queued = [];
	/** @var array<int, list<int>> tick => block hashes whose delayed change is due */
	private array $delayed = [];
	/** @var array<int, int> block hash => tick its delayed change is due */
	private array $delayedIndex = [];
	/** @var array<int, bool> block hash => power last seen by an edge-triggered consumer (doors, TNT...) */
	private array $lastPowered = [];
	/** @var array<int, list<int>> torch hash => ticks it toggled at */
	private array $torchToggles = [];
	/** @var array<int, int> torch hash => tick its burnout ends */
	private array $burntOut = [];
	/** @var array<int, true> wires whose network was already recomputed this tick */
	private array $wiresDone = [];
	/** @var array<int, int> comparator hash => signal last read from the container behind it */
	private array $containerWatch = [];
	private bool $active = false;
	private int $processed = 0;

	public function __construct(
		private World $world,
		private int $maxUpdatesPerTick
	){
		$this->queue = new \SplQueue();
	}

	/** Positions evaluated since the engine was created. */
	public function getProcessedCount() : int{ return $this->processed; }

	public static function isComponent(Block $block) : bool{
		return $block instanceof RedstoneWire || $block instanceof RedstoneTorch || $block instanceof RedstoneRepeater ||
			$block instanceof RedstoneComparator || $block instanceof Lever || $block instanceof Button ||
			$block instanceof PressurePlate || $block instanceof Redstone || $block instanceof DaylightSensor ||
			$block instanceof RedstoneLamp || $block instanceof CopperBulb || $block instanceof Door ||
			$block instanceof Trapdoor || $block instanceof FenceGate || $block instanceof TNT ||
			$block instanceof PoweredRail || $block instanceof ActivatorRail;
	}

	/** A block redstone power can pass through (a full, non-transparent block). */
	public static function isConductor(Block $block) : bool{
		return $block->isSolid() && !$block->isTransparent() && $block->isFullCube() && !$block instanceof Redstone;
	}

	/**
	 * Called for every block that gets a neighbour update. Components are queued; a conductor queues the components
	 * around it, since power passes through it.
	 */
	public function onNeighbourUpdate(Block $block) : void{
		$pos = $block->getPosition();
		if(self::isComponent($block)){
			$this->active = true;
			$this->request($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			return;
		}
		if(!$this->active){
			return;
		}
		//whatever was here is gone: forget its state
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		unset($this->lastPowered[$hash], $this->torchToggles[$hash], $this->burntOut[$hash]);
		if(self::isConductor($block)){
			$this->requestComponentsAround($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		}
	}

	private function requestComponentsAround(int $x, int $y, int $z) : void{
		foreach(Facing::OFFSET as [$dx, $dy, $dz]){
			if($this->world->isInWorld($x + $dx, $y + $dy, $z + $dz) && self::isComponent($this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
				$this->request($x + $dx, $y + $dy, $z + $dz);
			}
		}
	}

	public function request(int $x, int $y, int $z) : void{
		if(!$this->world->isInWorld($x, $y, $z)){
			return;
		}
		$hash = World::blockHash($x, $y, $z);
		if(!isset($this->queued[$hash])){
			$this->queued[$hash] = true;
			$this->queue->enqueue($hash);
		}
	}

	/**
	 * Queues the components a change of power at this position can reach: those beside it, and those beside the
	 * conductors beside it.
	 */
	private function requestAround(int $x, int $y, int $z) : void{
		foreach(Facing::OFFSET as [$dx, $dy, $dz]){
			if(!$this->world->isInWorld($x + $dx, $y + $dy, $z + $dz)){
				continue;
			}
			$block = $this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
			if(self::isComponent($block)){
				$this->request($x + $dx, $y + $dy, $z + $dz);
			}elseif(self::isConductor($block)){
				$this->requestComponentsAround($x + $dx, $y + $dy, $z + $dz);
			}
		}
	}

	public function tick(int $currentTick) : void{
		foreach($this->delayed as $tick => $hashes){
			if($tick > $currentTick){
				continue;
			}
			unset($this->delayed[$tick]);
			foreach($hashes as $hash){
				if(($this->delayedIndex[$hash] ?? null) === $tick){
					unset($this->delayedIndex[$hash]);
					World::getBlockXYZ($hash, $x, $y, $z);
					$this->commitDelayed($x, $y, $z, $currentTick);
				}
			}
		}

		if($currentTick % self::REDSTONE_TICK === 0){
			$this->checkWatchedContainers();
		}

		$this->wiresDone = [];
		$budget = min($this->queue->count(), $this->maxUpdatesPerTick);
		for($i = 0; $i < $budget; ++$i){
			$hash = $this->queue->dequeue();
			unset($this->queued[$hash]);
			World::getBlockXYZ($hash, $x, $y, $z);
			if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			++$this->processed;
			$this->update($x, $y, $z, $currentTick);
		}
	}

	private function schedule(int $x, int $y, int $z, int $delay, int $currentTick) : void{
		$hash = World::blockHash($x, $y, $z);
		if(isset($this->delayedIndex[$hash])){
			return; //already changing; re-evaluated when it does
		}
		$due = $currentTick + max(1, $delay);
		$this->delayedIndex[$hash] = $due;
		$this->delayed[$due][] = $hash;
	}

	private function update(int $x, int $y, int $z, int $currentTick) : void{
		$block = $this->world->getBlockAt($x, $y, $z);
		$hash = World::blockHash($x, $y, $z);
		if($block instanceof RedstoneWire){
			if(!isset($this->wiresDone[$hash])){
				$this->updateWireNetwork($x, $y, $z);
			}
		}elseif($block instanceof RedstoneTorch){
			if($this->torchShouldBeLit($block, $currentTick) !== $block->isLit()){
				$this->schedule($x, $y, $z, self::REDSTONE_TICK, $currentTick);
			}
		}elseif($block instanceof RedstoneRepeater){
			if(!$this->isDiodeLocked($block) && ($this->diodeInput($block) > 0) !== $block->isPowered()){
				$this->schedule($x, $y, $z, $block->getDelay() * self::REDSTONE_TICK, $currentTick);
			}
		}elseif($block instanceof RedstoneComparator){
			if($this->comparatorOutput($block) !== $block->getOutputSignalStrength()){
				$this->schedule($x, $y, $z, self::REDSTONE_TICK, $currentTick);
			}
		}elseif($block instanceof RedstoneLamp){
			$powered = $this->receivedPower($x, $y, $z) > 0;
			if($powered && !$block->isLit()){
				$this->world->setBlockAt($x, $y, $z, $block->setLit(true));
			}elseif(!$powered && $block->isLit()){
				$this->schedule($x, $y, $z, 2 * self::REDSTONE_TICK, $currentTick);
			}
		}elseif($block instanceof CopperBulb){
			$powered = $this->receivedPower($x, $y, $z) > 0;
			if($powered !== $block->isPowered()){
				$this->world->setBlockAt($x, $y, $z, $block->togglePowered($powered));
			}
		}elseif($block instanceof PoweredRail || $block instanceof ActivatorRail){
			$powered = $this->receivedPower($x, $y, $z) > 0;
			if($powered !== $block->isPowered()){
				$this->world->setBlockAt($x, $y, $z, $block->setPowered($powered));
			}
		}elseif($block instanceof Door){
			$bottomY = $block->isTop() ? $y - 1 : $y;
			$powered = $this->receivedPower($x, $bottomY, $z) > 0 || $this->receivedPower($x, $bottomY + 1, $z) > 0;
			$bottomHash = World::blockHash($x, $bottomY, $z);
			if($this->edge($bottomHash, $powered) && $block->isOpen() !== $powered){
				foreach([$bottomY, $bottomY + 1] as $partY){
					$part = $this->world->getBlockAt($x, $partY, $z);
					if($part instanceof Door){
						$this->world->setBlockAt($x, $partY, $z, $part->setOpen($powered));
					}
				}
			}
		}elseif($block instanceof Trapdoor || $block instanceof FenceGate){
			$powered = $this->receivedPower($x, $y, $z) > 0;
			if($this->edge($hash, $powered) && $block->isOpen() !== $powered){
				$this->world->setBlockAt($x, $y, $z, $block->setOpen($powered));
			}
		}elseif($block instanceof TNT){
			if($this->receivedPower($x, $y, $z) > 0){
				$block->ignite();
			}
		}
		//sources (levers, buttons, plates...) need nothing here: the world's neighbour updates for their change
		//already reach what they power
	}

	/** Records the power an edge-triggered consumer sees; true when it changed since last time. */
	private function edge(int $hash, bool $powered) : bool{
		$previous = $this->lastPowered[$hash] ?? null;
		if($powered){
			$this->lastPowered[$hash] = true;
		}else{
			unset($this->lastPowered[$hash]);
		}
		return $previous === null ? $powered : $previous !== $powered;
	}

	private function commitDelayed(int $x, int $y, int $z, int $currentTick) : void{
		if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
			return;
		}
		$block = $this->world->getBlockAt($x, $y, $z);
		if($block instanceof RedstoneTorch){
			$lit = $this->torchShouldBeLit($block, $currentTick);
			if($lit !== $block->isLit()){
				$this->recordTorchToggle(World::blockHash($x, $y, $z), $currentTick);
				$this->world->setBlockAt($x, $y, $z, $block->setLit($lit));
				$this->requestAround($x, $y, $z);
			}
		}elseif($block instanceof RedstoneRepeater){
			$powered = $this->diodeInput($block) > 0;
			if(!$this->isDiodeLocked($block) && $powered !== $block->isPowered()){
				$this->world->setBlockAt($x, $y, $z, $block->setPowered($powered));
				$this->requestAround($x, $y, $z);
			}
		}elseif($block instanceof RedstoneComparator){
			$output = $this->comparatorOutput($block);
			if($output !== $block->getOutputSignalStrength()){
				$this->world->setBlockAt($x, $y, $z, $block->setOutputSignalStrength($output)->setPowered($output > 0));
				$this->requestAround($x, $y, $z);
			}
		}elseif($block instanceof RedstoneLamp){
			if($block->isLit() && $this->receivedPower($x, $y, $z) === 0){
				$this->world->setBlockAt($x, $y, $z, $block->setLit(false));
			}
		}
	}

	// ---------------------------------------------------------------- torches

	private function torchShouldBeLit(RedstoneTorch $torch, int $currentTick) : bool{
		$pos = $torch->getPosition();
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		if(($this->burntOut[$hash] ?? 0) > $currentTick){
			return false;
		}
		unset($this->burntOut[$hash]);
		$attached = $pos->getSide(Facing::opposite($torch->getFacing()));
		return $this->blockPower($attached->getFloorX(), $attached->getFloorY(), $attached->getFloorZ(), true) === 0;
	}

	private function recordTorchToggle(int $hash, int $currentTick) : void{
		$recent = [];
		foreach($this->torchToggles[$hash] ?? [] as $tick){
			if($currentTick - $tick < self::TORCH_BURNOUT_WINDOW){
				$recent[] = $tick;
			}
		}
		$recent[] = $currentTick;
		if(count($recent) > self::TORCH_BURNOUT_TOGGLES){
			$this->burntOut[$hash] = $currentTick + self::TORCH_BURNOUT_TIME;
			$recent = [];
			World::getBlockXYZ($hash, $x, $y, $z);
			$this->schedule($x, $y, $z, self::TORCH_BURNOUT_TIME + 1, $currentTick);
		}
		$this->torchToggles[$hash] = $recent;
	}

	// ---------------------------------------------------------------- repeaters and comparators

	/** Power into a repeater or comparator from behind (its input side). */
	private function diodeInput(RedstoneRepeater|RedstoneComparator $diode) : int{
		$pos = $diode->getPosition();
		$back = $pos->getSide($diode->getFacing());
		return $this->powerFrom($back->getFloorX(), $back->getFloorY(), $back->getFloorZ(), Facing::opposite($diode->getFacing()));
	}

	/** Power into a diode from one side: only wire, repeaters, comparators and redstone blocks count. */
	private function diodeSideInput(RedstoneRepeater|RedstoneComparator $diode, int $side) : int{
		$sidePos = $diode->getPosition()->getSide($side);
		$block = $this->world->getBlockAt($sidePos->getFloorX(), $sidePos->getFloorY(), $sidePos->getFloorZ());
		if($block instanceof RedstoneWire || $block instanceof RedstoneRepeater || $block instanceof RedstoneComparator || $block instanceof Redstone){
			return $this->emitted($block, Facing::opposite($side), false);
		}
		return 0;
	}

	private function isDiodeLocked(RedstoneRepeater $repeater) : bool{
		foreach([Facing::rotateY($repeater->getFacing(), true), Facing::rotateY($repeater->getFacing(), false)] as $side){
			$sidePos = $repeater->getPosition()->getSide($side);
			$block = $this->world->getBlockAt($sidePos->getFloorX(), $sidePos->getFloorY(), $sidePos->getFloorZ());
			if(($block instanceof RedstoneRepeater || $block instanceof RedstoneComparator) && $this->emitted($block, Facing::opposite($side), false) > 0){
				return true;
			}
		}
		return false;
	}

	/**
	 * Containers do not cause block updates when their contents change, so comparators reading one are re-checked
	 * every redstone tick.
	 */
	private function checkWatchedContainers() : void{
		foreach($this->containerWatch as $hash => $signal){
			World::getBlockXYZ($hash, $x, $y, $z);
			if(!$this->world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			$comparator = $this->world->getBlockAt($x, $y, $z);
			if(!$comparator instanceof RedstoneComparator){
				unset($this->containerWatch[$hash]);
				continue;
			}
			$back = $comparator->getPosition()->getSide($comparator->getFacing());
			$tile = $this->world->getTileAt($back->getFloorX(), $back->getFloorY(), $back->getFloorZ());
			if(!$tile instanceof Container || self::containerSignal($tile) !== $signal){
				$this->request($x, $y, $z);
			}
		}
	}

	private function comparatorOutput(RedstoneComparator $comparator) : int{
		$rear = $this->diodeInput($comparator);
		$pos = $comparator->getPosition();
		$hash = World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$backPos = $pos->getSide($comparator->getFacing());
		$tile = $this->world->getTileAt($backPos->getFloorX(), $backPos->getFloorY(), $backPos->getFloorZ());
		if($tile instanceof Container){
			$signal = self::containerSignal($tile);
			$this->containerWatch[$hash] = $signal;
			$rear = max($rear, $signal);
		}else{
			unset($this->containerWatch[$hash]);
		}
		$side = max(
			$this->diodeSideInput($comparator, Facing::rotateY($comparator->getFacing(), true)),
			$this->diodeSideInput($comparator, Facing::rotateY($comparator->getFacing(), false))
		);
		return $comparator->isSubtractMode() ? max(0, $rear - $side) : ($rear >= $side ? $rear : 0);
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
			$fullness += $item->getCount() / min($inventory->getMaxStackSize(), $item->getMaxStackSize());
			$any = true;
		}
		return $any ? (int) floor(1 + ($fullness / $size) * 14) : 0;
	}

	// ---------------------------------------------------------------- wire

	/**
	 * Recomputes the power of every wire connected to this one: each wire has the strongest of the power it gets
	 * from non-wire sources and its connected wires' power minus one.
	 */
	private function updateWireNetwork(int $x, int $y, int $z) : void{
		$start = World::blockHash($x, $y, $z);
		/** @var array<int, array{int, int, int}> $wires */
		$wires = [$start => [$x, $y, $z]];
		$pending = [$start];
		while($pending !== [] && count($wires) < self::MAX_WIRE_NETWORK){
			$hash = array_pop($pending);
			[$wx, $wy, $wz] = $wires[$hash];
			foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
				$next = World::blockHash($nx, $ny, $nz);
				if(!isset($wires[$next])){
					$wires[$next] = [$nx, $ny, $nz];
					$pending[] = $next;
				}
			}
		}

		//max-plus propagation, strongest first
		$power = [];
		/** @var array<int, list<int>> $buckets */
		$buckets = [];
		foreach($wires as $hash => [$wx, $wy, $wz]){
			$power[$hash] = $this->wireSourcePower($wx, $wy, $wz);
			if($power[$hash] > 0){
				$buckets[$power[$hash]][] = $hash;
			}
		}
		for($level = 15; $level > 1; --$level){
			foreach($buckets[$level] ?? [] as $hash){
				if($power[$hash] !== $level){
					continue;
				}
				[$wx, $wy, $wz] = $wires[$hash];
				foreach($this->connectedWires($wx, $wy, $wz) as [$nx, $ny, $nz]){
					$next = World::blockHash($nx, $ny, $nz);
					if(isset($power[$next]) && $power[$next] < $level - 1){
						$power[$next] = $level - 1;
						$buckets[$level - 1][] = $next;
					}
				}
			}
		}

		foreach($wires as $hash => [$wx, $wy, $wz]){
			$wire = $this->world->getBlockAt($wx, $wy, $wz);
			if($wire instanceof RedstoneWire && $wire->getOutputSignalStrength() !== $power[$hash]){
				$this->world->setBlockAt($wx, $wy, $wz, $wire->setOutputSignalStrength($power[$hash]), false);
				$this->world->notifyNeighbourBlockUpdate(new Vector3($wx, $wy, $wz));
				$this->requestAround($wx, $wy, $wz);
			}
			$this->wiresDone[$hash] = true;
		}
	}

	/**
	 * Wires connected to the wire at the position: beside it, one block up (if nothing solid is above this wire)
	 * and one block down (if the block beside it is not solid).
	 *
	 * @return list<array{int, int, int}>
	 */
	private function connectedWires(int $x, int $y, int $z) : array{
		$connected = [];
		$openAbove = !self::isConductor($this->world->getBlockAt($x, $y + 1, $z));
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if($side instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y, $z + $dz];
				continue;
			}
			if($openAbove && self::isConductor($side) && $this->world->getBlockAt($x + $dx, $y + 1, $z + $dz) instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y + 1, $z + $dz];
			}
			if(!self::isConductor($side) && $this->world->getBlockAt($x + $dx, $y - 1, $z + $dz) instanceof RedstoneWire){
				$connected[] = [$x + $dx, $y - 1, $z + $dz];
			}
		}
		return $connected;
	}

	/** Power a wire gets from anything but other wire: sources beside it, and strongly powered blocks. */
	private function wireSourcePower(int $x, int $y, int $z) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			$neighbour = $this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
			if($neighbour instanceof RedstoneWire){
				continue;
			}
			$power = max($power, $this->emitted($neighbour, Facing::opposite($face), false));
			if(self::isConductor($neighbour)){
				$power = max($power, $this->blockPower($x + $dx, $y + $dy, $z + $dz, false));
			}
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/**
	 * The horizontal directions a wire powers blocks in: towards what it connects to, straight through when it only
	 * connects on one side, and every direction when it connects to nothing.
	 *
	 * @return array<int, true>
	 */
	private function wireDirections(RedstoneWire $wire) : array{
		$pos = $wire->getPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();
		$openAbove = !self::isConductor($this->world->getBlockAt($x, $y + 1, $z));
		$directions = [];
		foreach(self::HORIZONTAL as $face){
			[$dx, , $dz] = Facing::OFFSET[$face];
			$side = $this->world->getBlockAt($x + $dx, $y, $z + $dz);
			if(
				$side instanceof RedstoneWire || self::connectsToWire($side, $face) ||
				($openAbove && self::isConductor($side) && $this->world->getBlockAt($x + $dx, $y + 1, $z + $dz) instanceof RedstoneWire) ||
				(!self::isConductor($side) && $this->world->getBlockAt($x + $dx, $y - 1, $z + $dz) instanceof RedstoneWire)
			){
				$directions[$face] = true;
			}
		}
		if($directions === []){
			return [Facing::NORTH => true, Facing::SOUTH => true, Facing::WEST => true, Facing::EAST => true];
		}
		if(count($directions) === 1){
			foreach($directions as $face => $_){
				$directions[Facing::opposite($face)] = true;
			}
		}
		return $directions;
	}

	/** Components a wire visually connects to, seen from a wire in direction $face. */
	private static function connectsToWire(Block $block, int $face) : bool{
		if($block instanceof RedstoneRepeater){
			return Facing::axis($block->getFacing()) === Facing::axis($face);
		}
		return $block instanceof RedstoneComparator || $block instanceof RedstoneTorch || $block instanceof Lever ||
			$block instanceof Button || $block instanceof PressurePlate || $block instanceof Redstone || $block instanceof DaylightSensor;
	}

	// ---------------------------------------------------------------- power queries

	/**
	 * Power the block sends through its face $face (the direction from it to the receiver). With $strongOnly, only
	 * power strong enough to power a block that wire next to it can pick up.
	 */
	private function emitted(Block $block, int $face, bool $strongOnly) : int{
		if($block instanceof Lever){
			return $block->isActivated() && (!$strongOnly || $face === Facing::opposite($block->getFacing()->getFacing())) ? 15 : 0;
		}
		if($block instanceof Button){
			return $block->isPressed() && (!$strongOnly || $face === Facing::opposite($block->getFacing())) ? 15 : 0;
		}
		if($block instanceof SimplePressurePlate){
			return $block->isPressed() && (!$strongOnly || $face === Facing::DOWN) ? 15 : 0;
		}
		if($block instanceof WeightedPressurePlate){
			return !$strongOnly || $face === Facing::DOWN ? $block->getOutputSignalStrength() : 0;
		}
		if($block instanceof Redstone){
			return $strongOnly ? 0 : 15;
		}
		if($block instanceof RedstoneTorch){
			if(!$block->isLit() || $face === Facing::opposite($block->getFacing())){
				return 0;
			}
			return !$strongOnly || $face === Facing::UP ? 15 : 0;
		}
		if($block instanceof RedstoneRepeater){
			return $block->isPowered() && $face === Facing::opposite($block->getFacing()) ? 15 : 0;
		}
		if($block instanceof RedstoneComparator){
			return $face === Facing::opposite($block->getFacing()) ? $block->getOutputSignalStrength() : 0;
		}
		if($block instanceof RedstoneWire){
			if($strongOnly || $face === Facing::UP || $block->getOutputSignalStrength() === 0){
				return 0;
			}
			return $face === Facing::DOWN || isset($this->wireDirections($block)[$face]) ? $block->getOutputSignalStrength() : 0;
		}
		if($block instanceof DaylightSensor){
			return $strongOnly ? 0 : $block->getOutputSignalStrength();
		}
		return 0;
	}

	/**
	 * Power a block at the position is getting from the blocks around it (all power, or only strong power).
	 */
	private function blockPower(int $x, int $y, int $z, bool $anyPower) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			$power = max($power, $this->emitted($this->world->getBlockAt($x + $dx, $y + $dy, $z + $dz), Facing::opposite($face), !$anyPower));
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/** Power the component at the position receives: from sources and wire beside it, and through powered blocks. */
	public function receivedPower(int $x, int $y, int $z) : int{
		$power = 0;
		foreach(Facing::ALL as $face){
			[$dx, $dy, $dz] = Facing::OFFSET[$face];
			$power = max($power, $this->powerFrom($x + $dx, $y + $dy, $z + $dz, Facing::opposite($face)));
			if($power >= 15){
				break;
			}
		}
		return $power;
	}

	/** Power a component gets from the block at the position, which lies in direction opposite($face) of it. */
	private function powerFrom(int $x, int $y, int $z, int $face) : int{
		if(!$this->world->isInWorld($x, $y, $z)){
			return 0;
		}
		$block = $this->world->getBlockAt($x, $y, $z);
		$power = $this->emitted($block, $face, false);
		if($power < 15 && self::isConductor($block)){
			$power = max($power, $this->blockPower($x, $y, $z, true));
		}
		return $power;
	}
}
