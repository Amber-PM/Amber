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

namespace pocketmine\entity\vehicle;

use pocketmine\block\ActivatorRail;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;

class TNTMinecart extends Minecart{

	protected int $fuse = -1;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		parent::__construct($location, MinecartType::TNT, $nbt);
		$this->maxPassengers = 0;
	}

	public static function getNetworkTypeId() : string{
		return EntityIds::TNT_MINECART;
	}

	public function prime(int $fuseTicks = 80) : void{
		if($this->fuse < 0){
			$this->fuse = $fuseTicks;
		}
	}

	public function isPrimed() : bool{
		return $this->fuse >= 0;
	}

	public function onRailTick(Block $rail) : void{
		parent::onRailTick($rail);

		if($rail instanceof ActivatorRail && $rail->isPowered()){
			$this->prime();
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->fuse > 0){
			$this->fuse -= $tickDiff;
			if($this->fuse <= 0){
				$this->explode();
				return false;
			}
		}

		return $hasUpdate;
	}

	public function explode(float $yield = 4.0) : void{
		$this->flagForDespawn();
		$explosion = new Explosion($this->location, $yield, $this);
		$explosion->explodeA();
		$explosion->explodeB();
	}
}
