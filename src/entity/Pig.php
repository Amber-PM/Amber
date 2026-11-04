<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

class Pig extends Animal{

	protected float $movementSpeed = 0.25;

	public static function getNetworkTypeId() : string{ return EntityIds::PIG; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 0.9);
	}

	public function getName() : string{
		return "Pig";
	}

	public function isBreedingItem(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === VanillaItems::CARROT()->getTypeId() ||
			$id === VanillaItems::POTATO()->getTypeId() ||
			$id === VanillaItems::BEETROOT()->getTypeId();
	}

	protected function createBaby(Location $location) : Animal{
		return new Pig($location);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if(!$this->baby && $this->isBreedingItem($item)){
			if($this->feed($item)){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				return true;
			}
		}

		return parent::onInteract($player, $clickPos);
	}

	public function getDrops() : array{
		if($this->baby){
			return [];
		}
		return [
			VanillaItems::RAW_PORKCHOP()->setCount(mt_rand(1, 3))
		];
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PIG_SPAWN_EGG();
	}
}
