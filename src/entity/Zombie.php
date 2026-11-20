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
use pocketmine\entity\ai\goal\SunlightBurnGoal;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

class Zombie extends Monster{

	protected float $movementSpeed = 0.23;
	protected float $attackDamage = 3.0;
	private ?SunlightBurnGoal $sunlightBurnGoal = null;

	public static function getNetworkTypeId() : string{ return EntityIds::ZOMBIE; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Zombie";
	}

	protected function registerGoals() : void{
		$this->sunlightBurnGoal = new SunlightBurnGoal($this, 1);
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal($this->sunlightBurnGoal);
		$this->goalSelector->addGoal(new MeleeAttackGoal($this, speedMultiplier: 1.0, priority: 2));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 0.8, priority: 7));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 6.0, priority: 8));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 9));

		$this->targetSelector->addGoal(new HurtByTargetGoal($this, 1));
		$this->targetSelector->addGoal(new NearestAttackableTargetGoal($this, [Player::class, Villager::class], targetRange: 25.0, priority: 2));
	}

	public function checkDaylightBurn() : void{
		$this->sunlightBurnGoal?->checkBurn();
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))
		];

		if(mt_rand(0, 199) < 5){
			switch(mt_rand(0, 2)){
				case 0:
					$drops[] = VanillaItems::IRON_INGOT();
					break;
				case 1:
					$drops[] = VanillaItems::CARROT();
					break;
				case 2:
					$drops[] = VanillaItems::POTATO();
					break;
			}
		}

		return $drops;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOMBIE_SPAWN_EGG();
	}
}
