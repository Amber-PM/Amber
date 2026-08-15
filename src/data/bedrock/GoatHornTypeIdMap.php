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

namespace pocketmine\data\bedrock;

use pocketmine\item\GoatHornType;
use pocketmine\utils\SingletonTrait;

final class GoatHornTypeIdMap{
	use SingletonTrait;
	/** @phpstan-use IntSaveIdMapTrait<GoatHornType> */
	use IntSaveIdMapTrait;

	private function __construct(){
		foreach(GoatHornType::cases() as $case){
			$this->register(match($case){
				GoatHornType::PONDER => GoatHornTypeIds::PONDER,
				GoatHornType::SING => GoatHornTypeIds::SING,
				GoatHornType::SEEK => GoatHornTypeIds::SEEK,
				GoatHornType::FEEL => GoatHornTypeIds::FEEL,
				GoatHornType::ADMIRE => GoatHornTypeIds::ADMIRE,
				GoatHornType::CALL => GoatHornTypeIds::CALL,
				GoatHornType::YEARN => GoatHornTypeIds::YEARN,
				GoatHornType::DREAM => GoatHornTypeIds::DREAM
			}, $case);
		}
	}
}
