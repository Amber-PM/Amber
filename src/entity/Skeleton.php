<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\ai\goal\AvoidMobGoal;
use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\HurtByTargetGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\NearestAttackableTargetGoal;
use pocketmine\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\goal\RangedBowAttackGoal;
use pocketmine\entity\ai\goal\SunlightBurnGoal;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

class Skeleton extends Monster{

	protected float $movementSpeed = 0.25;
	private ?SunlightBurnGoal $sunlightBurnGoal = null;

	public static function getNetworkTypeId() : string{ return EntityIds::SKELETON; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Skeleton";
	}

	protected function registerGoals() : void{
		$this->sunlightBurnGoal = new SunlightBurnGoal($this, 1);
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal($this->sunlightBurnGoal);
		$this->goalSelector->addGoal(new AvoidMobGoal($this, [EntityIds::WOLF], maxDistance: 6.0, speedMultiplier: 1.2, priority: 3));
		$this->goalSelector->addGoal(new RangedBowAttackGoal($this, speedMultiplier: 1.0, attackInterval: 60, maxAttackDistance: 15.0, priority: 2));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 0.8, priority: 7));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 6.0, priority: 8));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 9));

		$this->targetSelector->addGoal(new HurtByTargetGoal($this, 1));
		$this->targetSelector->addGoal(new NearestAttackableTargetGoal($this, [Player::class], targetRange: 16.0, priority: 2));
	}

	public function checkDaylightBurn() : void{
		$this->sunlightBurnGoal?->checkBurn();
	}

	public function getDrops() : array{
		return [
			VanillaItems::ARROW()->setCount(mt_rand(0, 2)),
			VanillaItems::BONE()->setCount(mt_rand(0, 2))
		];
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SKELETON_SPAWN_EGG();
	}
}
