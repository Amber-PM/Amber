<?php

declare(strict_types=1);

namespace OptimizationRegression;

use pocketmine\world\ChunkManager;
use pocketmine\world\generator\Generator;

final class RegressionGenerator extends Generator{
	public static int $constructions = 0;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		++self::$constructions;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{}
}
