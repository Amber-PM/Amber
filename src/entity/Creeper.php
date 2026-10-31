<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\ai\goal\AvoidMobGoal;
use pocketmine\entity\ai\goal\CreeperSwellGoal;
use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\NearestAttackableTargetGoal;
use pocketmine\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\sound\IgniteSound;
use function max;
use function min;
use function mt_rand;

class Creeper extends Monster{

	private const TAG_POWERED = "powered"; // TAG_Byte
	private const TAG_FUSE = "Fuse"; // TAG_Short
	private const TAG_IGNITED = "ignited"; // TAG_Byte

	public const MAX_FUSE_TICKS = 30; // 1.5 seconds in BDS

	protected float $movementSpeed = 0.2;
	private bool $powered = false;
	private bool $ignited = false;
	private int $fuseTicks = 0;

	/** @var \Closure|null */
	public ?\Closure $onExplodeCallback = null;

	public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6);
	}

	public function getName() : string{
		return "Creeper";
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	public function setPowered(bool $powered = true) : void{
		$this->powered = $powered;
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::POWERED, $powered);
	}

	public function isIgnited() : bool{
		return $this->ignited;
	}

	public function setIgnited(bool $ignited = true) : void{
		if($this->ignited !== $ignited){
			$this->ignited = $ignited;
			$this->networkProperties->setGenericFlag(EntityMetadataFlags::IGNITED, $ignited);
			if($ignited){
				$this->getWorld()->addSound($this->location, new IgniteSound());
			}
		}
	}

	public function getFuseTicks() : int{
		return $this->fuseTicks;
	}

	public function setFuseTicks(int $ticks) : void{
		$this->fuseTicks = max(0, min(self::MAX_FUSE_TICKS, $ticks));
		$this->networkProperties->setInt(EntityMetadataProperties::FUSE_LENGTH, self::MAX_FUSE_TICKS - $this->fuseTicks);
	}

	public function getExplosionRadius() : float{
		return $this->powered ? 6.0 : 3.0;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal(new CreeperSwellGoal($this, priority: 2));
		$this->goalSelector->addGoal(new AvoidMobGoal($this, [EntityIds::CAT, EntityIds::OCELOT], maxDistance: 6.0, speedMultiplier: 1.2, priority: 3));
		$this->goalSelector->addGoal(new MeleeAttackGoal($this, speedMultiplier: 1.25, priority: 4));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 1.0, priority: 5));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 8.0, priority: 6));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 7));

		$this->targetSelector->addGoal(new NearestAttackableTargetGoal($this, [Player::class], targetRange: 16.0, priority: 1));
	}

	public function tickFuse() : void{
		if($this->ignited){
			$this->fuseTicks++;
			$this->networkProperties->setInt(EntityMetadataProperties::FUSE_LENGTH, self::MAX_FUSE_TICKS - $this->fuseTicks);

			if($this->fuseTicks >= self::MAX_FUSE_TICKS){
				$this->explode();
			}
		}else{
			if($this->fuseTicks > 0){
				$this->fuseTicks--;
				$this->networkProperties->setInt(EntityMetadataProperties::FUSE_LENGTH, self::MAX_FUSE_TICKS - $this->fuseTicks);
			}
		}
	}

	public function explode() : void{
		if($this->isClosed() || !$this->isAlive()){
			return;
		}

		if($this->onExplodeCallback !== null){
			($this->onExplodeCallback)();
		}

		$ev = new EntityPreExplodeEvent($this, $this->getExplosionRadius());
		$ev->call();
		if(!$ev->isCancelled()){
			$explosion = new Explosion($this->location, $ev->getRadius(), $this);
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}

		$this->flagForDespawn();
	}

	public function onStruckByLightning() : void{
		$this->setPowered(true);
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))
		];

		// If killed by a skeleton, drop a random music disc
		$lastDamage = $this->getLastDamageCause();
		if($lastDamage instanceof EntityDamageByEntityEvent && $lastDamage->getDamager() instanceof Skeleton){
			$discs = [
				VanillaItems::RECORD_13(),
				VanillaItems::RECORD_CAT(),
				VanillaItems::RECORD_BLOCKS(),
				VanillaItems::RECORD_CHIRP(),
				VanillaItems::RECORD_FAR(),
				VanillaItems::RECORD_MALL(),
				VanillaItems::RECORD_MELLOHI(),
				VanillaItems::RECORD_STAL(),
				VanillaItems::RECORD_STRAD(),
				VanillaItems::RECORD_WARD(),
				VanillaItems::RECORD_11(),
				VanillaItems::RECORD_WAIT()
			];
			$drops[] = $discs[array_rand($discs)];
		}

		return $drops;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CREEPER_SPAWN_EGG();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setPowered($nbt->getByte(self::TAG_POWERED, 0) !== 0);
		$this->setIgnited($nbt->getByte(self::TAG_IGNITED, 0) !== 0);
		$this->setFuseTicks($nbt->getShort(self::TAG_FUSE, 0));
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_POWERED, $this->powered ? 1 : 0);
		$nbt->setByte(self::TAG_IGNITED, $this->ignited ? 1 : 0);
		$nbt->setShort(self::TAG_FUSE, $this->fuseTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::POWERED, $this->powered);
		$properties->setGenericFlag(EntityMetadataFlags::IGNITED, $this->ignited);
		$properties->setInt(EntityMetadataProperties::FUSE_LENGTH, self::MAX_FUSE_TICKS - $this->fuseTicks);
	}
}
