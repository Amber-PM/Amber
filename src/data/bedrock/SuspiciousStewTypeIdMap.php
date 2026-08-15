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

use pocketmine\item\SuspiciousStewType;
use pocketmine\utils\SingletonTrait;

final class SuspiciousStewTypeIdMap{
	use SingletonTrait;
	/** @phpstan-use IntSaveIdMapTrait<SuspiciousStewType> */
	use IntSaveIdMapTrait;

	private function __construct(){
		foreach(SuspiciousStewType::cases() as $case){
			$this->register(match($case){
				SuspiciousStewType::POPPY => SuspiciousStewTypeIds::POPPY,
				SuspiciousStewType::CORNFLOWER => SuspiciousStewTypeIds::CORNFLOWER,
				SuspiciousStewType::TULIP => SuspiciousStewTypeIds::TULIP,
				SuspiciousStewType::AZURE_BLUET => SuspiciousStewTypeIds::AZURE_BLUET,
				SuspiciousStewType::LILY_OF_THE_VALLEY => SuspiciousStewTypeIds::LILY_OF_THE_VALLEY,
				SuspiciousStewType::DANDELION => SuspiciousStewTypeIds::DANDELION,
				SuspiciousStewType::BLUE_ORCHID => SuspiciousStewTypeIds::BLUE_ORCHID,
				SuspiciousStewType::ALLIUM => SuspiciousStewTypeIds::ALLIUM,
				SuspiciousStewType::OXEYE_DAISY => SuspiciousStewTypeIds::OXEYE_DAISY,
				SuspiciousStewType::WITHER_ROSE => SuspiciousStewTypeIds::WITHER_ROSE,
			}, $case);
		}

	}
}
