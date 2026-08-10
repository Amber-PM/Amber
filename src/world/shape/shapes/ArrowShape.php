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
 * @author PocketmineMV Team
 * @link https://github.com/vapebw/PocketmineMV
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\shape\shapes;

use pocketmine\color\Color;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\shape\PacketShapeData;
use pocketmine\world\shape\Shape;

final class ArrowShape implements Shape{

	public function __construct(
		private readonly Vector3 $tail,
		private readonly Vector3 $head,
		private readonly ?float $scale = null,
		private readonly ?Color $color = null,
		private readonly ?float $arrowHeadLength = null,
		private readonly ?float $arrowHeadRadius = null,
		private readonly ?int $segments = null,
		private readonly ?int $dimensionId = null,
		private readonly ?int $attachedEntityId = null
	){}

	// tail = start, head = pointy end
	public function toShapeData(int $networkId) : PacketShapeData{
		return PacketShapeData::arrow(
			$networkId,
			$this->tail,
			$this->head,
			$this->scale,
			$this->color,
			$this->arrowHeadLength,
			$this->arrowHeadRadius,
			$this->segments,
			$this->dimensionId,
			$this->attachedEntityId
		);
	}
}
