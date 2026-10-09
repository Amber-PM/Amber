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

use function count;
use function max;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../support/redstone/RedstoneTestEnvironment.php';

class StressHarness {
	private \ReflectionProperty $propContinuations;
	private \ReflectionProperty $propOwner;
	private \ReflectionProperty $propAliases;
	private \ReflectionProperty $propAliasSources;

	public int $peakContinuations = 0;
	public int $peakOwners = 0;
	public array $violations = [];

	public function __construct(private WireNetwork $network) {
		$this->propContinuations = new \ReflectionProperty(WireNetwork::class, 'continuations');
		$this->propOwner = new \ReflectionProperty(WireNetwork::class, 'continuationOwner');
		$this->propAliases = new \ReflectionProperty(WireNetwork::class, 'continuationAliases');
		$this->propAliasSources = new \ReflectionProperty(WireNetwork::class, 'continuationAliasSources');
	}

	public function getContinuations() : array {
		return $this->propContinuations->getValue($this->network);
	}

	public function getOwners() : array {
		return $this->propOwner->getValue($this->network);
	}

	public function getAliases() : array {
		return $this->propAliases->getValue($this->network);
	}

	public function getAliasSources() : array {
		return $this->propAliasSources->getValue($this->network);
	}

	public function resolveId(int $id, array $aliases) : int {
		$visited = [];
		while (isset($aliases[$id])) {
			if (isset($visited[$id])) {
				$this->violations[] = "Alias cycle detected at continuation ID $id";
				return $id;
			}
			$visited[$id] = true;
			$id = $aliases[$id];
		}
		return $id;
	}

	public function checkInvariants() : void {
		$continuations = $this->getContinuations();
		$owners = $this->getOwners();
		$aliases = $this->getAliases();

		$curContCount = $this->network->getContinuationCount();
		$rawContCount = count($continuations);
		foreach ($continuations as $c) {
			$rawContCount += count($c->mergingSources ?? []);
		}
		$this->peakContinuations = max($this->peakContinuations, $curContCount, $rawContCount);
		$this->peakOwners = max($this->peakOwners, count($owners));

		// Check for alias cycles
		foreach ($aliases as $src => $tgt) {
			$this->resolveId($src, $aliases);
		}

		// Check duplicate live ownership
		$claimedLiveWires = [];
		foreach ($continuations as $cId => $c) {
			if ($c->phase->value === WireNetwork::PHASE_CLEANUP && ($c->cleanupMode ?? -1) === WireNetwork::CLEANUP_INVALIDATED) {
				continue;
			}
			foreach ($c->wires as $hash => $coords) {
				if (isset($claimedLiveWires[$hash])) {
					$this->violations[] = "Wire hash $hash claimed by multiple live continuations: {$claimedLiveWires[$hash]} and $cId";
				}
				$claimedLiveWires[$hash] = $cId;
			}
			foreach ($c->mergingSources ?? [] as $srcId => $src) {
				foreach ($src->wires ?? [] as $hash => $coords) {
					if (isset($claimedLiveWires[$hash])) {
						$this->violations[] = "Wire hash $hash claimed by multiple live continuations (in merge): {$claimedLiveWires[$hash]} and $srcId";
					}
					$claimedLiveWires[$hash] = $cId;
				}
			}
		}

		// Verify owner map targets resolve to existing continuations
		foreach ($owners as $hash => $ownerId) {
			$resolvedId = $this->resolveId($ownerId, $aliases);
			if (!isset($continuations[$resolvedId])) {
				$this->violations[] = "Owner entry for hash $hash resolves to nonexistent continuation ID $resolvedId (raw $ownerId)";
			}
		}
	}

	public function verifyFinalState() : void {
		$continuations = $this->getContinuations();
		$owners = $this->getOwners();
		$aliases = $this->getAliases();
		$aliasSources = $this->getAliasSources();

		if ($this->network->hasDeferred()) {
			$this->violations[] = "Network reports hasDeferred = true after settlement loop finished";
		}
		if (count($continuations) !== 0) {
			$this->violations[] = "Leaked continuations after settlement: " . count($continuations) . " remaining";
		}
		if (count($owners) !== 0) {
			$this->violations[] = "Leaked continuationOwner entries after settlement: " . count($owners) . " remaining";
		}
		if (count($aliases) !== 0) {
			$this->violations[] = "Leaked continuationAliases after settlement: " . count($aliases) . " remaining";
		}
		if (count($aliasSources) !== 0) {
			$this->violations[] = "Leaked continuationAliasSources after settlement: " . count($aliasSources) . " remaining";
		}
	}
}

function createEnvironment(int $budget) : array {
	return RedstoneTestEnvironment::create($budget);
}
