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

namespace pocketmine\addon\entity\ai;

use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;

/**
 * Runs one path search in an async worker over a snapshot of the surrounding chunks.
 */
final class AsyncPathTask extends AsyncTask{
	private const TLS_KEY_CALLBACK = "callback";

	/**
	 * @phpstan-param \Closure(list<Vector3>|null $path, int $expandedNodes) : void $onCompletion
	 */
	public function __construct(
		private string $snapshot,
		private int $height,
		private bool $canSwim,
		private bool $avoidWater,
		private int $maxFall,
		private float $fromX,
		private float $fromY,
		private float $fromZ,
		private float $toX,
		private float $toY,
		private float $toZ,
		private float $maxDistance,
		\Closure $onCompletion
	){
		$this->storeLocal(self::TLS_KEY_CALLBACK, $onCompletion);
	}

	public function onRun() : void{
		$pathfinder = new Pathfinder(PathChunkSnapshot::fromPayload($this->snapshot), $this->height, $this->canSwim, $this->avoidWater, $this->maxFall);
		$path = $pathfinder->find(new Vector3($this->fromX, $this->fromY, $this->fromZ), new Vector3($this->toX, $this->toY, $this->toZ), $this->maxDistance);
		$points = null;
		if($path !== null){
			$points = [];
			foreach($path as $point){
				$points[] = [$point->x, $point->y, $point->z];
			}
		}
		$this->setResult(igbinary_serialize([$points, $pathfinder->getExpandedNodes()]));
	}

	public function onCompletion() : void{
		/** @phpstan-var \Closure(list<Vector3>|null $path, int $expandedNodes) : void $callback */
		$callback = $this->fetchLocal(self::TLS_KEY_CALLBACK);
		$result = igbinary_unserialize($this->getResult());
		if(!is_array($result)){
			$callback(null, 0);
			return;
		}
		[$points, $expandedNodes] = $result;
		$path = null;
		if(is_array($points)){
			$path = [];
			foreach($points as [$x, $y, $z]){
				$path[] = new Vector3($x, $y, $z);
			}
		}
		$callback($path, $expandedNodes);
	}
}
