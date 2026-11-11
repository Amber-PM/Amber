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
use pocketmine\world\structure\StructureTemplate;

class JigsawPiece{

	/**
	 * @param JigsawConnection[] $connections
	 */
	public function __construct(
		private StructureTemplate $template,
		private int $weight = 1,
		private string $projection = "rigid",
		private array $connections = []
	){}

	public function getTemplate() : StructureTemplate{
		return $this->template;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getProjection() : string{
		return $this->projection;
	}

	/**
	 * @return JigsawConnection[]
	 */
	public function getConnections() : array{
		return $this->connections;
	}

	public function getBoundingBox(Vector3 $worldPos, StructureRotation $rotation) : AxisAlignedBB{
		$size = $this->template->getSize();
		[$rotX, $rotZ] = $rotation->getRotatedSize((int) $size->x, (int) $size->z);

		return new AxisAlignedBB(
			$worldPos->x,
			$worldPos->y,
			$worldPos->z,
			$worldPos->x + $rotX,
			$worldPos->y + $size->y,
			$worldPos->z + $rotZ
		);
	}

	public function place(ChunkManager $world, int $worldX, int $worldY, int $worldZ, StructurePlacementSettings $settings) : void{
		$this->template->placeIntoWorld($world, $worldX, $worldY, $worldZ, $settings);
	}
}
