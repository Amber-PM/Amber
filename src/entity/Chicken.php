<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\sound\PopSound;
use function mt_rand;

class Chicken extends Animal{

	private const TAG_EGG_LAY_TIME = "EggLayTime"; // TAG_Int

	protected float $movementSpeed = 0.25;
	private int $eggLayTime = 6000;

	public static function getNetworkTypeId() : string{ return EntityIds::CHICKEN; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.4);
	}

	public function getName() : string{
		return "Chicken";
	}

	public function initAI() : void{
		parent::initAI();
		$this->eggLayTime = mt_rand(6000, 12000);
	}

	public function isBreedingItem(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === VanillaItems::WHEAT_SEEDS()->getTypeId() ||
			$id === VanillaItems::PUMPKIN_SEEDS()->getTypeId() ||
			$id === VanillaItems::MELON_SEEDS()->getTypeId() ||
			$id === VanillaItems::BEETROOT_SEEDS()->getTypeId();
	}

	protected function createBaby(Location $location) : Animal{
		return new Chicken($location);
	}

	public function calculateFallDamage(float $fallDistance) : float{
		return 0.0; // Chickens flap wings and take zero fall damage
	}

	public function getMaxFallingSpeed() : float{
		return -0.15;
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

	public function tickAI(int $tickDiff = 1) : void{
		// Slow fall glide when airborne
		if(!$this->onGround && $this->motion->y < -0.15){
			$this->motion = new Vector3($this->motion->x, -0.15, $this->motion->z);
		}

		// Egg laying logic for adults
		if(!$this->baby){
			$this->eggLayTime -= $tickDiff;
			if($this->eggLayTime <= 0){
				$this->getWorld()->dropItem($this->location, VanillaItems::EGG());
				$this->getWorld()->addSound($this->location, new PopSound());
				$this->eggLayTime = mt_rand(6000, 12000); // 5-10 minutes in BDS
			}
		}

		parent::tickAI($tickDiff);
	}

	public function getDrops() : array{
		if($this->baby){
			return [];
		}
		return [
			VanillaItems::FEATHER()->setCount(mt_rand(0, 2)),
			VanillaItems::RAW_CHICKEN()
		];
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CHICKEN_SPAWN_EGG();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->eggLayTime = $nbt->getInt(self::TAG_EGG_LAY_TIME, mt_rand(6000, 12000));
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_EGG_LAY_TIME, $this->eggLayTime);
		return $nbt;
	}
}
