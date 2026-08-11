<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\___|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketmineMV Team
 * @link https://github.com/vapebw/PocketmineMV
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\shape;

use pocketmine\network\mcpe\protocol\types\shape\PacketShapeData;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeType;

final class EntityAttachedShape implements Shape{

	public function __construct(
		private readonly Shape $inner,
		private readonly int $entityId
	){}

	public function toShapeData(int $networkId) : PacketShapeData{
		$d = $this->inner->toShapeData($networkId);
		return new PacketShapeData(
			$d->getNetworkId(),
			$d->getType() ?? PrimitiveShapeType::LINE,
			$d->getLocation(),
			$d->getScale(),
			$d->getRotation(),
			$d->getTotalTimeLeft(),
			$d->getMaximumRenderDistance(),
			$d->getColor(),
			$d->getDimensionId(),
			$this->entityId,
			$d->getPayload()
		);
	}
}
