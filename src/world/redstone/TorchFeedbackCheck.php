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

use pocketmine\math\Vector3;

final class TorchFeedbackCheck{
	/** @var \SplQueue<array{Vector3, int}> */
	public \SplQueue $pending;
	/** @var array<int, true> */
	public array $seen = [];
	public ?bool $result = null;
	/** @var array<int, int> */
	public array $versions = [];
	/** @var array<int, array{\stdClass, int}> at most 16 watched horizontal chunks */
	public array $chunkVersions = [];
	public bool $dirty = false;
	public bool $cancelled = false;
	public bool $queued = true;
	public ?int $extinguishTick = null;
	public ?\stdClass $identity = null;
	public int $facing = 0;

	public function __construct(
		public Vector3 $position,
		public Vector3 $target,
		public int $stateId
	){
		$this->pending = new \SplQueue();
	}
}
