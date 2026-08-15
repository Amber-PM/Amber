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

namespace pocketmine\world\shape\shapes;

use pocketmine\color\Color;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\shape\PacketShapeData;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeConePayload;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeType;
use pocketmine\world\shape\Shape;

final class ConeShape implements Shape{

	public function __construct(
		private readonly Vector3 $base,
		private readonly Vector2 $radii,
		private readonly float $height,
		private readonly int $segments,
		private readonly ?Color $color = null,
		private readonly ?int $dimensionId = null,
		private readonly ?int $attachedEntityId = null,
		private readonly ?Vector3 $rotation = null
	){}

	public function toShapeData(int $networkId) : PacketShapeData{
		return new PacketShapeData(
			$networkId,
			PrimitiveShapeType::CONE,
			$this->base,
			null,
			$this->rotation,
			null,
			null,
			$this->color,
			$this->dimensionId,
			$this->attachedEntityId,
			new PrimitiveShapeConePayload($this->radii, $this->height, $this->segments)
		);
	}
}
