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

namespace pocketmine\world\generator\noise;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use function abs;

final class MultiNoiseSample{

	public function __construct(
		public readonly float $continentalness,
		public readonly float $erosion,
		public readonly float $peaksAndValleys,
		public readonly float $temperature,
		public readonly float $humidity,
		public readonly float $weirdness,
		public readonly float $depth = 0.0
	){}
}

class MultiNoise{

	private const SEED_OFFSET_CONTINENTALNESS = 2883;
	private const SEED_OFFSET_EROSION = 8752;
	private const SEED_OFFSET_PEAKS_VALLEYS = 14931;
	private const SEED_OFFSET_TEMPERATURE = 21543;
	private const SEED_OFFSET_HUMIDITY = 28127;
	private const SEED_OFFSET_WEIRDNESS = 35899;

	private Simplex $continentalnessNoise;
	private Simplex $erosionNoise;
	private Simplex $peaksAndValleysNoise;
	private Simplex $temperatureNoise;
	private Simplex $humidityNoise;
	private Simplex $weirdnessNoise;

	public function __construct(
		private readonly int $seed
	){
		$this->continentalnessNoise = new Simplex(self::makeRandom($this->seed ^ self::SEED_OFFSET_CONTINENTALNESS), 4, 0.5, 1 / 512);
		$this->erosionNoise = new Simplex(self::makeRandom($this->seed ^ self::SEED_OFFSET_EROSION), 3, 0.5, 1 / 256);
		$this->peaksAndValleysNoise = new Simplex(self::makeRandom($this->seed ^ self::SEED_OFFSET_PEAKS_VALLEYS), 5, 0.5, 1 / 128);
		$this->temperatureNoise = new Simplex(self::makeRandom($this->seed ^ self::SEED_OFFSET_TEMPERATURE), 4, 0.5, 1 / 512);
		$this->humidityNoise = new Simplex(self::makeRandom($this->seed ^ self::SEED_OFFSET_HUMIDITY), 4, 0.5, 1 / 512);
		$this->weirdnessNoise = new Simplex(self::makeRandom($this->seed ^ self::SEED_OFFSET_WEIRDNESS), 3, 0.5, 1 / 256);
	}

	private static function makeRandom(int $seed) : Random{
		$r = new Random(0);
		$r->setSeed($seed);
		return $r;
	}

	public function sample(int $x, int $y, int $z) : MultiNoiseSample{
		$c = $this->continentalnessNoise->noise2D($x, $z, true);
		$e = $this->erosionNoise->noise2D($x, $z, true);
		$pv = $this->peaksAndValleysNoise->noise2D($x, $z, true);
		$t = $this->temperatureNoise->noise2D($x, $z, true);
		$h = $this->humidityNoise->noise2D($x, $z, true);
		$w = $this->weirdnessNoise->noise2D($x, $z, true);

		// Depth represents depth below surface (or vertical offset in 1.18+)
		$depth = ($y - 64) / 128.0;

		return new MultiNoiseSample($c, $e, $pv, $t, $h, $w, $depth);
	}

	/**
	 * Maps 6D MultiNoiseSample to Bedrock Biome ID with vanilla 1.18+ parity.
	 */
	public function pickBiome(MultiNoiseSample $sample, int $y = 64) : int{
		$c = $sample->continentalness;
		$e = $sample->erosion;
		$pv = $sample->peaksAndValleys;
		$t = $sample->temperature;
		$h = $sample->humidity;
		$w = $sample->weirdness;

		// 1. Underground Cave Biomes (Y < 30)
		if($y < 30){
			if($y < -20 && $w < -0.45){
				return BiomeIds::DEEP_DARK;
			}
			if($h > 0.35 && $t > -0.1){
				return BiomeIds::LUSH_CAVES;
			}
			if($h < -0.25 && $e < 0.2){
				return BiomeIds::DRIPSTONE_CAVES;
			}
		}

		// 2. Oceans
		if($c < -0.19){
			// Deep ocean
			if($c < -0.45){
				if($t < -0.45){
					return BiomeIds::DEEP_FROZEN_OCEAN;
				}
				if($t < -0.15){
					return BiomeIds::DEEP_COLD_OCEAN;
				}
				if($t < 0.20){
					return BiomeIds::DEEP_OCEAN;
				}
				if($t < 0.55){
					return BiomeIds::DEEP_LUKEWARM_OCEAN;
				}
				return BiomeIds::DEEP_WARM_OCEAN;
			}

			// Shallow / Normal ocean
			if($t < -0.45){
				return BiomeIds::FROZEN_OCEAN;
			}
			if($t < -0.15){
				return BiomeIds::COLD_OCEAN;
			}
			if($t < 0.20){
				return BiomeIds::OCEAN;
			}
			if($t < 0.55){
				return BiomeIds::LUKEWARM_OCEAN;
			}
			return BiomeIds::WARM_OCEAN;
		}

		// 3. Rare Mushroom Island
		if($w > 0.82 && $c < -0.12){
			return BiomeIds::MUSHROOM_ISLAND;
		}

		// 4. Beaches and Coastlines
		if($c < -0.11){
			if($t < -0.15){
				return BiomeIds::COLD_BEACH;
			}
			if($e < -0.3){
				return BiomeIds::STONE_BEACH;
			}
			if($h < -0.35 && $t > 0.5){
				return BiomeIds::DESERT;
			}
			return BiomeIds::BEACH;
		}

		// 5. Rivers
		if(abs($pv) < 0.038 && $c > -0.11){
			return $t < -0.3 ? BiomeIds::FROZEN_RIVER : BiomeIds::RIVER;
		}

		// 6. Mountain Peaks & Slopes (high continentalness + jaggedness or high elevation)
		if($c > 0.35 && ($pv > 0.35 || $y > 140)){
			if($pv > 0.55 || $y > 170){
				if($t < -0.4){
					return BiomeIds::FROZEN_PEAKS;
				}
				if($t < 0.05){
					return BiomeIds::JAGGED_PEAKS;
				}
				return BiomeIds::STONY_PEAKS;
			}
			// Slopes
			if($t < -0.2){
				return BiomeIds::SNOWY_SLOPES;
			}
			if($t < 0.2){
				return BiomeIds::GROVE;
			}
			return BiomeIds::MEADOW;
		}

		// 7. Inland Biomes: Temperature / Humidity Matrix
		// Frozen
		if($t < -0.45){
			if($h < -0.1){
				return BiomeIds::ICE_PLAINS;
			}
			return BiomeIds::COLD_TAIGA;
		}

		// Cold
		if($t < -0.15){
			if($h < -0.2){
				return BiomeIds::PLAINS;
			}
			if($h < 0.35){
				return BiomeIds::TAIGA;
			}
			return BiomeIds::MEGA_TAIGA;
		}

		// Temperate
		if($t < 0.40){
			if($h < -0.35){
				return BiomeIds::PLAINS;
			}
			if($h < 0.10){
				return BiomeIds::FOREST;
			}
			if($h < 0.40){
				return BiomeIds::BIRCH_FOREST;
			}
			if($h < 0.65){
				return BiomeIds::ROOFED_FOREST;
			}
			return BiomeIds::SWAMPLAND;
		}

		// Warm / Hot
		if($h < -0.40){
			return $e > 0.2 ? BiomeIds::DESERT : BiomeIds::MESA;
		}
		if($h < 0.0){
			return $e < -0.2 ? BiomeIds::SAVANNA_PLATEAU : BiomeIds::SAVANNA;
		}
		if($h < 0.50){
			return BiomeIds::JUNGLE;
		}
		if($t > 0.65){
			return BiomeIds::MANGROVE_SWAMP;
		}
		return BiomeIds::BAMBOO_JUNGLE;
	}
}
