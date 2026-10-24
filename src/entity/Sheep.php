<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\ai\goal\EatGrassGoal;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\Shears;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function floor;
use function mt_rand;

class Sheep extends Animal{

	private const TAG_COLOR = "Color"; // TAG_Byte
	private const TAG_SHEARED = "Sheared"; // TAG_Byte

	protected float $movementSpeed = 0.23;
	private DyeColor $color = DyeColor::WHITE;
	private bool $sheared = false;

	public static function getNetworkTypeId() : string{ return EntityIds::SHEEP; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9);
	}

	public function getName() : string{
		return "Sheep";
	}

	public function getColor() : DyeColor{
		return $this->color;
	}

	public function setColor(DyeColor $color) : void{
		$this->color = $color;
		$this->networkProperties->setByte(EntityMetadataProperties::COLOR, $color->id());
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function setSheared(bool $sheared = true) : void{
		$this->sheared = $sheared;
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::SHEARED, $sheared);
	}

	public function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === VanillaItems::WHEAT()->getTypeId();
	}

	protected function createBaby(Location $location) : Animal{
		$baby = new Sheep($location);
		$baby->setColor($this->color);
		return $baby;
	}

	protected function registerGoals() : void{
		parent::registerGoals();
		$this->goalSelector->addGoal(new EatGrassGoal($this, priority: 6));
	}

	/**
	 * @return Item[]
	 */
	public function shear() : array{
		if($this->sheared || $this->baby){
			return [];
		}

		$this->setSheared(true);
		$count = mt_rand(1, 3);
		$wool = VanillaBlocks::WOOL()->setColor($this->color)->asItem()->setCount($count);
		return [$wool];
	}

	public function canEatGrassBlock() : bool{
		$world = $this->getWorld();
		$loc = $this->location;
		$blockUnder = $world->getBlockAt((int) floor($loc->x), (int) floor($loc->y - 1), (int) floor($loc->z));
		if($blockUnder->getTypeId() === BlockTypeIds::GRASS){
			return true;
		}

		$inBlock = $world->getBlockAt((int) floor($loc->x), (int) floor($loc->y), (int) floor($loc->z));
		$inId = $inBlock->getTypeId();
		return $inId === BlockTypeIds::TALL_GRASS;
	}

	public function eatGrassBlock() : bool{
		$world = $this->getWorld();
		$loc = $this->location;
		$bx = (int) floor($loc->x);
		$by = (int) floor($loc->y);
		$bz = (int) floor($loc->z);

		$inBlock = $world->getBlockAt($bx, $by, $bz);
		if($inBlock->getTypeId() === BlockTypeIds::TALL_GRASS){
			$world->setBlock(new Vector3($bx, $by, $bz), VanillaBlocks::AIR());
			$this->setSheared(false);
			if($this->baby){
				$this->babyAge = max(0, $this->babyAge - 1200);
			}
			return true;
		}

		$ground = $world->getBlockAt($bx, $by - 1, $bz);
		if($ground->getTypeId() === BlockTypeIds::GRASS){
			$world->setBlock(new Vector3($bx, $by - 1, $bz), VanillaBlocks::DIRT());
			$this->setSheared(false);
			if($this->baby){
				$this->babyAge = max(0, $this->babyAge - 1200);
			}
			return true;
		}

		return false;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		// Shearing with shears
		if(!$this->sheared && !$this->baby && $item instanceof Shears){
			$drops = $this->shear();
			$item->applyDamage(1);
			$player->getInventory()->setItemInHand($item);
			foreach($drops as $drop){
				$player->getWorld()->dropItem($this->location, $drop);
			}
			return true;
		}

		// Dyeing wool with dye item
		if(!$this->sheared && $item instanceof Dye){
			$this->setColor($item->getColor());
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			return true;
		}

		// Breeding with wheat
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
		$drops = [];
		if(!$this->baby){
			if(!$this->sheared){
				$drops[] = VanillaBlocks::WOOL()->setColor($this->color)->asItem();
			}
			$drops[] = VanillaItems::RAW_MUTTON()->setCount(mt_rand(1, 2));
		}
		return $drops;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SHEEP_SPAWN_EGG();
	}

	public function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		$colorId = $nbt->getByte(self::TAG_COLOR, DyeColor::WHITE->id());
		$this->color = DyeColor::fromId($colorId) ?? DyeColor::WHITE;
		$this->setSheared($nbt->getByte(self::TAG_SHEARED, 0) !== 0);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		$nbt->setByte(self::TAG_COLOR, $this->color->id());
		$nbt->setByte(self::TAG_SHEARED, $this->sheared ? 1 : 0);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->color->id());
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
	}
}
