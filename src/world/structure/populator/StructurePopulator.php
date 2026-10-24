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

namespace pocketmine\world\structure\populator;

use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\structure\jigsaw\JigsawAssembler;
use pocketmine\world\structure\jigsaw\JigsawPoolRegistry;
use function abs;
use function floor;

class StructurePopulator implements Populator{

	public function __construct(
		private string $structurePoolId,
		private int $spacing = 32,
		private int $separation = 8,
		private int $salt = 10387312,
		private int $baseElevation = 64
	){}

	public function shouldGenerateInChunk(int $chunkX, int $chunkZ, int $worldSeed) : bool{
		// Grid-based feature placement matching Bedrock chunk grid spacing
		$gridX = (int) floor($chunkX / $this->spacing);
		$gridZ = (int) floor($chunkZ / $this->spacing);

		$gridSeed = $worldSeed ^ ($gridX * 341873128712) ^ ($gridZ * 132897987541) ^ $this->salt;
		$rand = new Random(0);
		$rand->setSeed($gridSeed);

		$candidateChunkX = $gridX * $this->spacing + $rand->nextRange(0, $this->spacing - $this->separation);
		$candidateChunkZ = $gridZ * $this->spacing + $rand->nextRange(0, $this->spacing - $this->separation);

		return $chunkX === $candidateChunkX && $chunkZ === $candidateChunkZ;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}

		$startWorldX = ($chunkX << 4) + 8;
		$startWorldZ = ($chunkZ << 4) + 8;
		$surfaceY = $chunk->getHighestBlockAt(8, 8) ?? $this->baseElevation;

		$startPos = new Vector3($startWorldX, $surfaceY, $startWorldZ);

		$pieces = JigsawAssembler::assemble(
			startPoolId: $this->structurePoolId,
			startPos: $startPos,
			maxDepth: 5,
			seed: $random->nextSignedInt()
		);

		JigsawAssembler::placeAll($world, $pieces);
	}
}
