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

use function max;
use function min;

final class SplineCalculator{

	private function __construct(){
		// NOOP
	}

	/**
	 * Computes base terrain height using the 1.18+ continentalness spline.
	 */
	public static function computeContinentalnessBase(float $c) : float{
		$c = max(-1.0, min(1.0, $c));

		if($c < -0.45){
			// Deep ocean: interpolates from 28.0 to 40.0
			return 28.0 + ($c + 1.0) / 0.55 * 12.0;
		}
		if($c < -0.15){
			// Shallow ocean to coast: interpolates from 40.0 to 62.0
			return 40.0 + ($c + 0.45) / 0.30 * 22.0;
		}
		if($c < 0.20){
			// Lowland / Plains: interpolates from 62.0 to 76.0
			return 62.0 + ($c + 0.15) / 0.35 * 14.0;
		}
		if($c < 0.55){
			// Midlands / Hills: interpolates from 76.0 to 110.0
			return 76.0 + ($c - 0.20) / 0.35 * 34.0;
		}
		// Highlands / Mountains: interpolates from 110.0 to 185.0
		return 110.0 + ($c - 0.55) / 0.45 * 75.0;
	}

	/**
	 * Computes erosion roughness factor.
	 * Lower erosion gives higher roughness (sharp peaks); high erosion smooths out terrain.
	 */
	public static function computeErosionFactor(float $e) : float{
		$e = max(-1.0, min(1.0, $e));
		// Maps [-1.0, 1.0] to [1.0, 0.1]
		return 0.1 + (1.0 - $e) * 0.45;
	}

	/**
	 * Computes target surface elevation Y based on Continentalness, Erosion, and Peaks & Valleys.
	 */
	public static function computeSurfaceHeight(float $continentalness, float $erosion, float $peaksAndValleys) : float{
		$c = max(-1.0, min(1.0, $continentalness));
		$e = max(-1.0, min(1.0, $erosion));
		$pv = max(-1.0, min(1.0, $peaksAndValleys));

		$base = self::computeContinentalnessBase($c);
		$erosionFactor = self::computeErosionFactor($e);

		// Peaks and Valleys ridge modulation
		$ridge = $pv * 32.0 * $erosionFactor;

		// Mountain peak amplification for high continentalness and low erosion
		$mountainBoost = 0.0;
		if($c > 0.30 && $e < 0.15){
			$mountainBoost = ($c - 0.30) * (0.15 - $e) * 110.0 * max(0.0, $pv + 0.4);
		}

		$height = $base + $ridge + $mountainBoost;

		// Bound within playable overworld limits (-64 to 320)
		return max(-32.0, min(300.0, $height));
	}
}
