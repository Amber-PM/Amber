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

namespace pocketmine\block\dispenser;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\utils\SingletonTrait;

final class DispenseBehaviorRegistry{
	use SingletonTrait;

	/** @var array<int, DispenseBehavior> */
	private array $behaviors = [];
	private DispenseBehavior $defaultBehavior;
	private DispenseBehavior $armorBehavior;

	public function __construct(){
		$this->defaultBehavior = new DefaultDispenseBehavior();
		$this->armorBehavior = new ArmorDispenseBehavior();
		$this->registerBehaviors();
	}

	private function registerBehaviors() : void{
		$projectileBehavior = new ProjectileDispenseBehavior();
		$this->register(ItemTypeIds::ARROW, $projectileBehavior);
		$this->register(ItemTypeIds::SNOWBALL, $projectileBehavior);
		$this->register(ItemTypeIds::EGG, $projectileBehavior);
		$this->register(ItemTypeIds::SPLASH_POTION, $projectileBehavior);
		$this->register(ItemTypeIds::EXPERIENCE_BOTTLE, $projectileBehavior);
		$this->register(ItemTypeIds::ENDER_PEARL, $projectileBehavior);

		$bucketBehavior = new BucketDispenseBehavior();
		$this->register(ItemTypeIds::BUCKET, $bucketBehavior);
		$this->register(ItemTypeIds::WATER_BUCKET, $bucketBehavior);
		$this->register(ItemTypeIds::LAVA_BUCKET, $bucketBehavior);

		$this->register(VanillaBlocks::TNT()->asItem()->getTypeId(), new TNTDispenseBehavior());
		$this->register(ItemTypeIds::BONE_MEAL, new BoneMealDispenseBehavior());

		$this->register(VanillaBlocks::CARVED_PUMPKIN()->asItem()->getTypeId(), $this->armorBehavior);
		$this->register(VanillaBlocks::MOB_HEAD()->asItem()->getTypeId(), $this->armorBehavior);
	}

	public function register(int $itemTypeId, DispenseBehavior $behavior) : void{
		$this->behaviors[$itemTypeId] = $behavior;
	}

	public function get(Item $item) : DispenseBehavior{
		if(isset($this->behaviors[$item->getTypeId()])){
			return $this->behaviors[$item->getTypeId()];
		}

		if($item instanceof Armor){
			return $this->armorBehavior;
		}

		return $this->defaultBehavior;
	}

	public function getDefault() : DispenseBehavior{
		return $this->defaultBehavior;
	}

	public function getArmorBehavior() : DispenseBehavior{
		return $this->armorBehavior;
	}
}
