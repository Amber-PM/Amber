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
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\shape\PacketShapeData;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeCircleOrSpherePayload;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeType;
use pocketmine\world\shape\Shape;

final class CircleShape implements Shape{

	public function __construct(
		private readonly Vector3 $center,
		private readonly int $segments,
		private readonly ?float $scale = null,
		private readonly ?Color $color = null,
		private readonly ?int $dimensionId = null,
		private readonly ?int $attachedEntityId = null,
		private readonly ?Vector3 $rotation = null
	){}

	// flat circle, use SphereShape if you want 3D
	public function toShapeData(int $networkId) : PacketShapeData{
		return new PacketShapeData(
			$networkId,
			PrimitiveShapeType::CIRCLE,
			$this->center,
			$this->scale,
			$this->rotation,
			null,
			null,
			$this->color,
			$this->dimensionId,
			$this->attachedEntityId,
			new PrimitiveShapeCircleOrSpherePayload($this->segments)
		);
	}
}
