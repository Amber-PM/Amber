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
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeTextPayload;
use pocketmine\network\mcpe\protocol\types\shape\PrimitiveShapeType;
use pocketmine\world\shape\Shape;

final class TextShape implements Shape{

	public function __construct(
		private readonly Vector3 $position,
		private readonly string $text,
		private readonly bool $useRotation = false,
		private readonly ?Color $backgroundColor = null,
		private readonly bool $depthTest = true,
		private readonly bool $showBackface = true,
		private readonly bool $showTextBackface = true,
		private readonly ?Color $color = null,
		private readonly ?int $dimensionId = null,
		private readonly ?int $attachedEntityId = null,
		private readonly ?Vector3 $rotation = null
	){}

	public function toShapeData(int $networkId) : PacketShapeData{
		return new PacketShapeData(
			$networkId,
			PrimitiveShapeType::TEXT,
			$this->position,
			null,
			$this->rotation,
			null,
			null,
			$this->color,
			$this->dimensionId,
			$this->attachedEntityId,
			new PrimitiveShapeTextPayload($this->text, $this->useRotation, $this->backgroundColor, $this->depthTest, $this->showBackface, $this->showTextBackface)
		);
	}
}
