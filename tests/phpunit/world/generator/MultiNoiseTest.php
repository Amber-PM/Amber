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

namespace pocketmine\world\generator;

use PHPUnit\Framework\TestCase;
use pocketmine\world\generator\noise\MultiNoise;

final class MultiNoiseTest extends TestCase{

	public function testKnownSeedCoordinatesProduceDeterministicBiome() : void{
		$noise = new MultiNoise(seed: 12345678);
		$sample = $noise->sample(x: 100, y: 64, z: -250);

		self::assertGreaterThanOrEqual(-1.0, $sample->continentalness);
		self::assertLessThanOrEqual(1.0, $sample->continentalness);
		self::assertEqualsWithDelta(-0.245, $sample->continentalness, 0.05);
	}

	public function testSamplingIsDeterministicAcrossCalls() : void{
		$noise1 = new MultiNoise(seed: 987654321);
		$noise2 = new MultiNoise(seed: 987654321);

		$sample1 = $noise1->sample(250, 48, -120);
		$sample2 = $noise2->sample(250, 48, -120);

		self::assertSame($sample1->continentalness, $sample2->continentalness);
		self::assertSame($sample1->erosion, $sample2->erosion);
		self::assertSame($sample1->peaksAndValleys, $sample2->peaksAndValleys);
		self::assertSame($sample1->temperature, $sample2->temperature);
		self::assertSame($sample1->humidity, $sample2->humidity);
		self::assertSame($sample1->weirdness, $sample2->weirdness);
	}

	public function testBiomeSelectionOutputsValidBedrockBiomeId() : void{
		$noise = new MultiNoise(seed: 42);
		$sample = $noise->sample(0, 64, 0);
		$biomeId = $noise->pickBiome($sample);

		self::assertGreaterThanOrEqual(0, $biomeId);
		self::assertLessThan(256, $biomeId);
	}
}
