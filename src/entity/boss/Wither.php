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

namespace pocketmine\entity\boss;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

class Wither extends Living{

	public static function getNetworkTypeId() : string{ return EntityIds::WITHER; }

	protected int $invulnerableTicks = 220;

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
		$this->setMaxHealth(300);
		$this->setHealth(300);
	}

	public function getName() : string{
		return "Wither";
	}

	public function getMaxHealth() : int{
		return 300;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(3.5, 0.9);
	}

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	public function isArmored() : bool{
		return $this->getHealth() <= ($this->getMaxHealth() * 0.5);
	}

	public function getInvulnerableTicks() : int{
		return $this->invulnerableTicks;
	}

	public function setInvulnerableTicks(int $ticks) : void{
		$this->invulnerableTicks = $ticks;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($this->invulnerableTicks > 0 && $source->getCause() !== EntityDamageEvent::CAUSE_VOID){
			$source->cancel();
			return;
		}

		if($this->isArmored() && $source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
			$source->cancel();
			return;
		}

		parent::attack($source);
	}

	public function syncBossBarTo(Player $player) : void{
		$pk = BossEventPacket::show(
			$this->getId(),
			$this->getName(),
			$this->getHealth() / $this->getMaxHealth(),
			false,
			BossBarColor::PURPLE
		);
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function hideBossBarFrom(Player $player) : void{
		$pk = BossEventPacket::hide($this->getId());
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->invulnerableTicks > 0){
			$this->invulnerableTicks = max(0, $this->invulnerableTicks - $tickDiff);
		}

		return $hasUpdate || $this->invulnerableTicks > 0;
	}
}
