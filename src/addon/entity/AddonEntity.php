<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon\entity;

use pocketmine\entity\Attribute;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use function array_map;

/**
 * A custom entity. One class serves every add-on entity: the identifier lives on the instance (and in its
 * saved NBT), and the spawn packet carries it instead of a class-wide network ID.
 */
class AddonEntity extends Living{
	/** Save ID shared by all add-on entities; "AddonIdentifier" in the NBT says which one it is. */
	public const SAVE_ID = "amber:addon_entity";
	public const TAG_IDENTIFIER = "AddonIdentifier";

	public static function getNetworkTypeId() : string{
		//never sent: sendSpawnPacket() uses the instance's own identifier
		return self::SAVE_ID;
	}

	public function __construct(Location $location, private AddonEntityDefinition $addonDefinition, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
	}

	public function getAddonDefinition() : AddonEntityDefinition{ return $this->addonDefinition; }

	/** The add-on identifier, e.g. "example:wisp". */
	public function getAddonIdentifier() : string{ return $this->addonDefinition->getIdentifier(); }

	public function getName() : string{
		return $this->addonDefinition->getDisplayName();
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		$size = $this->addonDefinition->getSize();
		return new EntitySizeInfo($size["height"], $size["width"]);
	}

	protected function getInitialGravity() : float{
		return $this->addonDefinition->hasGravity() ? parent::getInitialGravity() : 0.0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth($this->addonDefinition->getMaxHealth());
		if($nbt->getTag("Health") === null){
			$this->setHealth($this->getMaxHealth());
		}
		$this->getAttributeMap()->get(Attribute::KNOCKBACK_RESISTANCE)?->setValue($this->addonDefinition->getKnockbackResistance());
	}

	public function isFireProof() : bool{
		return $this->addonDefinition->isFireImmune();
	}

	public function saveNBT() : CompoundTag{
		return parent::saveNBT()->setString(self::TAG_IDENTIFIER, $this->addonDefinition->getIdentifier());
	}

	public function getDrops() : array{
		return [];
	}

	protected function sendSpawnPacket(Player $player) : void{
		$player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
			$this->getId(),
			$this->getId(),
			$this->addonDefinition->getIdentifier(),
			$this->getOffsetPosition($this->location->asVector3()),
			$this->getMotion(),
			$this->location->pitch,
			$this->location->yaw,
			$this->location->yaw,
			$this->location->yaw,
			array_map(function(Attribute $attr) : NetworkAttribute{
				return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue());
			}, $this->attributeMap->getAll()),
			$this->getAllNetworkData(),
			new PropertySyncData([], []),
			[]
		));
		$networkSession = $player->getNetworkSession();
		$networkSession->getEntityEventBroadcaster()->onMobArmorChange([$networkSession], $this);
	}
}
