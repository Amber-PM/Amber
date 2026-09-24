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

namespace pocketmine\addon\script;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * Who runs a command, and where: what "@s", "~" and "^" mean. /execute derives new contexts from it.
 */
final class CommandContext{
	public function __construct(
		public readonly ?Entity $executor,
		public readonly World $world,
		public readonly Vector3 $origin,
		public readonly float $yaw,
		public readonly float $pitch,
		public readonly ?CommandOutput $output = null
	){}

	/** Adds a line to the command's output, when someone reads it. */
	public function say(string $line) : void{
		$this->output?->add($line);
	}

	public function as(Entity $entity) : self{
		return new self($entity, $this->world, $this->origin, $this->yaw, $this->pitch, $this->output);
	}

	public function at(Entity $entity) : self{
		$location = $entity->getLocation();
		return new self($this->executor, $entity->getWorld(), $location->asVector3(), $location->yaw, $location->pitch, $this->output);
	}

	public function positioned(Vector3 $position) : self{
		return new self($this->executor, $this->world, $position, $this->yaw, $this->pitch, $this->output);
	}

	public function rotated(float $yaw, float $pitch) : self{
		return new self($this->executor, $this->world, $this->origin, $yaw, $pitch, $this->output);
	}

	public function in(World $world) : self{
		return new self($this->executor, $world, $this->origin, $this->yaw, $this->pitch, $this->output);
	}
}
