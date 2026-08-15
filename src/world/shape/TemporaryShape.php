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

namespace pocketmine\world\shape;

use pocketmine\network\mcpe\protocol\types\shape\PacketShapeData;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeType;

final class TemporaryShape implements Shape{

	public function __construct(
		private readonly Shape $inner,
		private readonly float $totalTimeSeconds
	){}

	public function toShapeData(int $networkId) : PacketShapeData{
		$d = $this->inner->toShapeData($networkId);
		return new PacketShapeData(
			$d->getNetworkId(),
			$d->getType() ?? PrimitiveShapeType::LINE,
			$d->getLocation(),
			$d->getScale(),
			$d->getRotation(),
			$this->totalTimeSeconds,
			$d->getMaximumRenderDistance(),
			$d->getColor(),
			$d->getDimensionId(),
			$d->getAttachedToEntityId(),
			$d->getPayload()
		);
	}
}
