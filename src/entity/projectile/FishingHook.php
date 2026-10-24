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

namespace pocketmine\entity\projectile;

use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\item\fishing\FishingLootTable;
use pocketmine\item\FishingRod;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\WaterDripParticle;
use pocketmine\world\sound\WaterSplashSound;
use function cos;
use function max;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

class FishingHook extends Projectile{

	public static function getNetworkTypeId() : string{
		return EntityIds::FISHING_HOOK;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.15, 0.15);
	}

	protected function getInitialGravity() : float{
		return 0.04;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.02;
	}

	private bool $inWater = false;
	private ?int $biteTicks = null;
	private int $catchWindowTicks = 0;
	private bool $hookedBlock = false;

	public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
	}

	public function isInWater() : bool{
		return $this->inWater;
	}

	public function isInBiteWindow() : bool{
		return $this->catchWindowTicks > 0;
	}

	public function isHookedBlock() : bool{
		return $this->hookedBlock;
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		parent::onHitBlock($blockHit, $hitResult);
		$this->hookedBlock = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$owner = $this->getOwningEntity();
		if($owner instanceof Player){
			$properties->setLong(EntityMetadataProperties::TARGET_EID, $owner->getId());
		}
	}

	private function getLureLevel() : int{
		$owner = $this->getOwningEntity();
		if($owner instanceof Player){
			$item = $owner->getInventory()->getItemInHand();
			if($item instanceof FishingRod){
				foreach($item->getEnchantments() as $instance){
					$id = EnchantmentIdMap::getInstance()->toId($instance->getType());
					if($id === EnchantmentIds::LURE){
						return $instance->getLevel();
					}
				}
			}
		}
		return 0;
	}

	private function getLuckOfSeaLevel() : int{
		$owner = $this->getOwningEntity();
		if($owner instanceof Player){
			$item = $owner->getInventory()->getItemInHand();
			if($item instanceof FishingRod){
				foreach($item->getEnchantments() as $instance){
					$id = EnchantmentIdMap::getInstance()->toId($instance->getType());
					if($id === EnchantmentIds::LUCK_OF_THE_SEA){
						return $instance->getLevel();
					}
				}
			}
		}
		return 0;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		$owner = $this->getOwningEntity();
		if(!$owner instanceof Player || !$owner->isAlive() || !$owner->isConnected() || $owner->getWorld() !== $this->getWorld() || $this->location->distanceSquared($owner->getLocation()) > 1024){ // >32 blocks
			$this->flagForDespawn();
			return false;
		}

		$block = $this->getWorld()->getBlock($this->location);
		if($block instanceof Water){
			$this->inWater = true;
			$this->hookedBlock = false;

			// Buoyancy in water: dampen motion and float up
			$this->motion = new Vector3(
				$this->motion->x * 0.85,
				($this->motion->y * 0.2) + 0.05,
				$this->motion->z * 0.85
			);

			if($this->biteTicks === null){
				$wait = mt_rand(100, 600);
				$lure = $this->getLureLevel();
				$wait = max(20, $wait - ($lure * 100));

				// 20% faster in rain if exposed to sky
				if($this->getWorld()->isRaining()){
					$highest = $this->getWorld()->getHighestBlockAt((int) $this->location->x, (int) $this->location->z);
					if($highest === null || $highest <= $this->location->y){
						$wait = (int) ($wait * 0.8);
					}
				}
				$this->biteTicks = $wait;
			}

			if($this->biteTicks > 0){
				$this->biteTicks -= $tickDiff;
				if($this->biteTicks <= 40 && $this->biteTicks > 0){
					// Bubble particles approaching hook
					$angle = ($this->biteTicks / 40.0) * M_PI * 2;
					$dist = ($this->biteTicks / 40.0) * 1.5;
					$pPos = $this->location->add(cos($angle) * $dist, 0.1, sin($angle) * $dist);
					$this->getWorld()->addParticle($pPos, new WaterDripParticle());
				}

				if($this->biteTicks <= 0){
					$this->biteTicks = 0;
					$this->catchWindowTicks = mt_rand(20, 40);
					$this->broadcastSound(new WaterSplashSound(1.0));
					$this->motion = new Vector3(0, -0.2, 0);
				}
			}elseif($this->catchWindowTicks > 0){
				$this->catchWindowTicks -= $tickDiff;
				if($this->catchWindowTicks <= 0){
					$this->catchWindowTicks = 0;
					$this->biteTicks = null; // Fish got away, restart cycle
				}
			}

			$hasUpdate = true;
		}else{
			$this->inWater = false;
			$this->biteTicks = null;
			$this->catchWindowTicks = 0;
		}

		return $hasUpdate;
	}

	/**
	 * Reels in the hook.
	 *
	 * @return int Durability damage to apply to the fishing rod.
	 */
	public function reelIn() : int{
		$owner = $this->getOwningEntity();
		$damage = 0;

		if($owner instanceof Player && $owner->isAlive()){
			if($this->catchWindowTicks > 0){
				// Successful catch!
				$luck = $this->getLuckOfSeaLevel();
				$loot = FishingLootTable::rollLoot($luck);

				$itemEntity = $this->getWorld()->dropItem($this->location, $loot);
				if($itemEntity !== null){
					$diff = $owner->getPosition()->add(0, 0.5, 0)->subtractVector($this->location);
					$dist = $diff->length();
					$motion = $diff->multiply(0.1)->add(0, sqrt($dist) * 0.08, 0);
					$itemEntity->setMotion($motion);
				}

				$this->getWorld()->dropExperience($this->location, mt_rand(1, 6));
				$this->broadcastSound(new WaterSplashSound(0.7));
				$damage = 1;
			}elseif($this->hookedBlock){
				$damage = 2;
			}
		}

		$this->flagForDespawn();
		return $damage;
	}
}
