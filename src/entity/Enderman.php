<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\HurtByTargetGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\MeleeAttackGoal;
use pocketmine\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\entity\ai\goal\RandomStrollGoal;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\EndermanTeleportSound;
use function cos;
use function deg2rad;
use function floor;
use function mt_rand;
use function sin;

class Enderman extends Monster{

	protected float $movementSpeed = 0.3;
	protected float $attackDamage = 7.0;
	private bool $angry = false;
	private int $angerTicks = 0;

	/** @var \Closure|null */
	public ?\Closure $onTeleportCallback = null;

	public static function getNetworkTypeId() : string{ return EntityIds::ENDERMAN; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 0.6);
	}

	public function getName() : string{
		return "Enderman";
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(40);
	}

	public function isAngry() : bool{
		return $this->angry;
	}

	public function setAngry(bool $angry = true) : void{
		$this->angry = $angry;
		$this->setMovementSpeed($angry ? 0.45 : 0.3);
		$this->networkProperties->setGenericFlag(EntityMetadataFlags::ANGRY, $angry);
		if($angry){
			$this->angerTicks = 500; // 25 seconds in BDS
		}
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(new FloatGoal($this, 0));
		$this->goalSelector->addGoal(new MeleeAttackGoal($this, speedMultiplier: 1.0, priority: 2));
		$this->goalSelector->addGoal(new RandomStrollGoal($this, speedMultiplier: 0.8, priority: 7));
		$this->goalSelector->addGoal(new LookAtPlayerGoal($this, lookDistance: 8.0, priority: 8));
		$this->goalSelector->addGoal(new RandomLookAroundGoal($this, priority: 9));

		$this->targetSelector->addGoal(new HurtByTargetGoal($this, 1));
	}

	public function checkStareAngle(Location $playerLoc, Vector3 $playerLookVector) : bool{
		$headPos = $this->location->add(0, 2.6, 0);
		$playerEyePos = $playerLoc->add(0, 1.62, 0);

		$toHead = $headPos->subtractVector($playerEyePos)->normalize();
		$dot = $toHead->dot($playerLookVector->normalize());

		// Cosine of 18 degrees is ~0.95
		return $dot > 0.95;
	}

	public function teleportRandomly(int $radius = 32) : bool{
		$world = $this->getWorld();
		$loc = $this->location;

		for($attempt = 0; $attempt < 16; ++$attempt){
			$rx = (int) floor($loc->x) + mt_rand(-$radius, $radius);
			$rz = (int) floor($loc->z) + mt_rand(-$radius, $radius);
			$targetY = (int) floor($loc->y) + mt_rand(-8, 8);

			$ry = $targetY;
			while($ry > $world->getMinY() && !$world->getBlockAt($rx, $ry - 1, $rz)->isSolid()){
				--$ry;
				if($targetY - $ry > 8){
					break;
				}
			}

			$ground = $world->getBlockAt($rx, $ry - 1, $rz);
			$feet = $world->getBlockAt($rx, $ry, $rz);
			$body = $world->getBlockAt($rx, $ry + 1, $rz);
			$head = $world->getBlockAt($rx, $ry + 2, $rz);

			if($ground->isSolid() && !$feet->isSolid() && !$body->isSolid() && !$head->isSolid()){
				if(!$feet instanceof Water && !$body instanceof Water){
					$this->teleport(new Vector3($rx + 0.5, $ry, $rz + 0.5));
					$world->addSound($this->location, new EndermanTeleportSound());

					if($this->onTeleportCallback !== null){
						($this->onTeleportCallback)();
					}
					return true;
				}
			}
		}

		return false;
	}

	public function attack(EntityDamageEvent $source) : void{
		// Cancel all projectile attacks and teleport away (BDS teleports_on_projectile_hit)
		if($source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
			$source->cancel();
			$this->teleportRandomly();
			return;
		}

		parent::attack($source);

		if(!$source->isCancelled()){
			if($source instanceof EntityDamageByEntityEvent){
				$damager = $source->getDamager();
				if($damager instanceof Living){
					$this->setAngry(true);
					$this->setTargetEntity($damager);
				}
			}
			// Chance to teleport when taking damage
			if(mt_rand(1, 4) === 1){
				$this->teleportRandomly();
			}
		}
	}

	public function tickAI(int $tickDiff = 1) : void{
		// Check damage in water or rain
		$inRain = method_exists($this->getWorld(), "isRaining") && $this->getWorld()->isRaining();
		if($this->isUnderwater() || $inRain){
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 1.0));
			$this->teleportRandomly();
		}

		// Check player staring
		if(!$this->angry){
			foreach($this->getWorld()->getPlayers() as $player){
				if($player->isSpectator() || $player->isCreative()){
					continue;
				}
				if($this->location->distanceSquared($player->getLocation()) <= 4096.0){ // 64 blocks in BDS
					// Wearing carved pumpkin prevents aggro
					$helmet = $player->getArmorInventory()->getHelmet();
					if($helmet->getTypeId() === VanillaBlocks::CARVED_PUMPKIN()->asItem()->getTypeId()){
						continue;
					}

					// Calculate player look vector
					$pitch = deg2rad($player->getLocation()->pitch);
					$yaw = deg2rad($player->getLocation()->yaw);
					$look = new Vector3(-sin($yaw) * cos($pitch), -sin($pitch), cos($yaw) * cos($pitch));

					if($this->checkStareAngle($player->getLocation(), $look)){
						$this->setAngry(true);
						$this->setTargetEntity($player);
						break;
					}
				}
			}
		}else{
			if(--$this->angerTicks <= 0){
				$this->setAngry(false);
				$this->setTargetEntity(null);
			}
		}

		parent::tickAI($tickDiff);
	}

	public function getDrops() : array{
		return [
			VanillaItems::ENDER_PEARL()->setCount(mt_rand(0, 1))
		];
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ENDERMAN_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->angry);
	}
}
