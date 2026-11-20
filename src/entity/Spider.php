<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\HurtByTargetGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\NearestAttackableTargetGoal;
use pocketmine\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

class Spider extends Monster{

	protected float $movementSpeed = 0.3;
	protected float $attackDamage = 2.0;

	public static function getNetworkTypeId() : string{ return EntityIds::SPIDER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 1.4);
	}

	public function getName() : string{
		return "Spider";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->canClimbWalls = true;
	}

	public function initAI() : void{
		parent::initAI();
		$this->canClimbWalls = true;
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(16);
	}

	public function isHostileInLightLevel(int $lightLevel) : bool{
		return $lightLevel < 8;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal(new MeleeAttackGoal($this, speedMultiplier: 1.0, priority: 2));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 0.8, priority: 7));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 6.0, priority: 8));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 9));

		$this->targetSelector->addGoal(new HurtByTargetGoal($this, 1));
		$this->targetSelector->addGoal(new NearestAttackableTargetGoal($this, [Player::class], targetRange: 16.0, priority: 2));
	}

	public function leapAtTarget(Living $target) : void{
		$dx = $target->getLocation()->x - $this->location->x;
		$dz = $target->getLocation()->z - $this->location->z;
		$dist = sqrt($dx * $dx + $dz * $dz);
		if($dist > 0.0001){
			$this->setMotion(new Vector3(($dx / $dist) * 0.5, 0.4, ($dz / $dist) * 0.5));
		}
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::STRING()->setCount(mt_rand(0, 2))
		];
		if(mt_rand(0, 99) < 33){
			$drops[] = VanillaItems::SPIDER_EYE();
		}
		return $drops;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SPIDER_SPAWN_EGG();
	}
}
