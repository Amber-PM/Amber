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
		if(empty($this->pieces)){
			return null;
		}

		$totalWeight = 0;
		foreach($this->pieces as $p){
			$totalWeight += $p->getWeight();
		}

		if($totalWeight <= 0){
			return $this->pieces[0];
		}

		$roll = $random->nextRange(0, $totalWeight - 1);
		$accum = 0;
		foreach($this->pieces as $p){
			$accum += $p->getWeight();
			if($roll < $accum){
				return $p;
			}
		}

		return $this->pieces[0];
	}
}
