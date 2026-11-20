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

use pocketmine\utils\Random;
use function array_key_first;

class JigsawPool{

	/**
	 * @param JigsawPiece[] $pieces
	 */
	public function __construct(
		private string $id,
		private string $fallbackPool = "minecraft:empty",
		private array $pieces = []
	){}

	public function getId() : string{
		return $this->id;
	}

	public function getFallbackPool() : string{
		return $this->fallbackPool;
	}

	/**
	 * @return JigsawPiece[]
	 */
	public function getPieces() : array{
		return $this->pieces;
	}

	public function addPiece(JigsawPiece $piece) : void{
		$this->pieces[] = $piece;
	}

	public function selectPiece(Random $random) : ?JigsawPiece{
		$index = self::selectIndex($this->pieces, $random);
		return $index !== null ? $this->pieces[$index] : null;
	}

	/** @return \Generator<int, JigsawPiece, void, void> */
	public function shuffledPieces(Random $random) : \Generator{
		$remaining = $this->pieces;
		while(($index = self::selectIndex($remaining, $random)) !== null){
			yield $remaining[$index];
			unset($remaining[$index]);
		}
	}

	/** @param array<int, JigsawPiece> $pieces */
	private static function selectIndex(array $pieces, Random $random) : ?int{
		$totalWeight = 0;
		foreach($pieces as $piece){
			$totalWeight += $piece->getWeight();
		}

		if($totalWeight <= 0){
			return array_key_first($pieces);
		}

		$roll = $random->nextRange(0, $totalWeight - 1);
		$accum = 0;
		foreach($pieces as $index => $piece){
			$accum += $piece->getWeight();
			if($roll < $accum){
				return $index;
			}
		}

		return array_key_first($pieces);
	}
}
