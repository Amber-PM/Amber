<?php

/*
 *
 *     _             _               
 *    / \\   _ __ ___ | |__   ___ _ __ 
 *   / _ \\ | '_ ` _ \\| '_ \\ / _ \\ '__|
 *  / ___ \\| | | | | | |_) |  __/ |   
 * /_/   \\_\\_| |_| |_|_.__/ \\___|_|   
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

namespace pocketmine\world\structure\jigsaw;

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;
use pocketmine\world\structure\StructurePlacementSettings;
use pocketmine\world\structure\StructureRotation;

final class PlacedStructurePiece{

	public function __construct(
		public readonly JigsawPiece $piece,
		public readonly Vector3 $worldPos,
		public readonly StructureRotation $rotation,
		public readonly AxisAlignedBB $boundingBox,
		public readonly int $depth
	){}

	public function place(ChunkManager $world) : void{
		$settings = new StructurePlacementSettings(
			rotation: $this->rotation
		);
		$this->piece->place($world, (int) $this->worldPos->x, (int) $this->worldPos->y, (int) $this->worldPos->z, $settings);
	}
}
