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

namespace pocketmine\world\redstone\wire;

final class WireContinuation{
	public WirePhase $phase = WirePhase::DISCOVER;
	public ?int $aliasPending = null;
	public int $cleanupMode = 0;

	public array $wires = [];

	public array $edges = [];

	public array $pending = [];

	public array $sourceQueue = [];

	public array $power = [];

	public array $buckets = [];
	public int $propagateLevel = 15;

	public array $applyQueue = [];
	public bool $mutated = false;

	public array $mergingSources = [];

	public function __construct(int $hash, int $x, int $y, int $z){
		$this->wires[$hash] = [$x, $y, $z];
		$this->pending[] = $hash;
		$this->sourceQueue[] = $hash;
		$this->applyQueue[] = $hash;
	}
}
