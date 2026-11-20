<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\ai\goal\BreedGoal;
use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\FollowParentGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\PanicGoal;
use pocketmine\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\entity\ai\goal\TemptGoal;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\item\Item;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function max;
use function mt_rand;

abstract class Animal extends Mob implements Ageable{

	private const TAG_AGE = "Age"; // TAG_Int
	private const TAG_IN_LOVE = "InLove"; // TAG_Int
	private const TAG_BREEDING_COOLDOWN = "BreedingCooldown"; // TAG_Int

	protected bool $baby = false;
	protected int $babyAge = 0;
	protected int $inLoveTicks = 0;
	protected int $breedingCooldown = 0;

	public function isBaby() : bool{
		return $this->baby;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		$this->babyAge = $baby ? 24000 : 0; // 20 minutes to grow up
		$this->setScale($baby ? 0.5 : 1.0);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::BABY, $baby);
		$this->networkProperties->setFloat(EntityMetadataProperties::SCALE, $baby ? 0.5 : 1.0);
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0 && !$this->baby;
	}

	public function setInLove(int $ticks = 600) : void{
		$this->inLoveTicks = $ticks;
	}

	public function getBreedingCooldown() : int{
		return $this->breedingCooldown;
	}

	public function setBreedingCooldown(int $ticks) : void{
		$this->breedingCooldown = $ticks;
	}

	abstract public function isBreedingItem(Item $item) : bool;

	public function feed(Item $item) : bool{
		if($this->baby){
			// Accelerate growth by 10%
			$this->babyAge = max(0, $this->babyAge - 2400);
			if($this->babyAge === 0){
				$this->setBaby(false);
			}
			return true;
		}

		if($this->breedingCooldown <= 0 && $this->inLoveTicks <= 0 && $this->isBreedingItem($item)){
			$this->setInLove(600);
			return true;
		}

		return false;
	}

	public function breedWith(Animal $partner, ?\Closure $onBredCallback = null) : ?Animal{
		if(!$this->isInLove() || !$partner->isInLove()){
			return null;
		}

		$this->inLoveTicks = 0;
		$partner->inLoveTicks = 0;
		$this->breedingCooldown = 6000; // 5 minutes
		$partner->breedingCooldown = 6000;

		$baby = $this->createBaby($this->location);
		$baby->setBaby(true);
		$baby->spawnToAll();

		$xpAmount = mt_rand(1, 7);
		if($onBredCallback !== null){
			$onBredCallback($xpAmount);
		}else{
			$orb = new ExperienceOrb($this->location, $xpAmount);
			$orb->spawnToAll();
		}

		return $baby;
	}

	abstract protected function createBaby(Location $location) : Animal;

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal(new PanicGoal($this, speedMultiplier: 1.25, priority: 1));
		$this->goalSelector->addGoal(new BreedGoal($this, speedMultiplier: 1.0, priority: 3));
		$this->goalSelector->addGoal(new TemptGoal($this, speedMultiplier: 1.0, priority: 4));
		$this->goalSelector->addGoal(new FollowParentGoal($this, speedMultiplier: 1.1, priority: 6));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 0.8, priority: 7));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 6.0, priority: 8));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 9));
	}

	public function tickAI(int $tickDiff = 1) : void{
		if($this->baby && $this->babyAge > 0){
			$this->babyAge -= $tickDiff;
			if($this->babyAge <= 0){
				$this->setBaby(false);
			}
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks -= $tickDiff;
		}

		if($this->breedingCooldown > 0){
			$this->breedingCooldown -= $tickDiff;
		}

		parent::tickAI($tickDiff);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$age = $nbt->getInt(self::TAG_AGE, 0);
		if($age < 0){
			$this->baby = true;
			$this->babyAge = -$age;
			$this->setScale(0.5);
		}else{
			$this->baby = false;
			$this->babyAge = 0;
			$this->setScale(1.0);
		}

		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE, 0);
		$this->breedingCooldown = $nbt->getInt(self::TAG_BREEDING_COOLDOWN, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_AGE, $this->baby ? -$this->babyAge : 0);
		$nbt->setInt(self::TAG_IN_LOVE, $this->inLoveTicks);
		$nbt->setInt(self::TAG_BREEDING_COOLDOWN, $this->breedingCooldown);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
		$properties->setFloat(EntityMetadataProperties::SCALE, $this->baby ? 0.5 : 1.0);
	}
}
