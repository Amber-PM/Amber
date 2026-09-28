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

namespace pocketmine\addon\event;

use pocketmine\addon\entity\AddonEntity;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\entity\EntityEvent;

/**
 * Called before an add-on entity runs one of its behavior pack events (for example "minecraft:entity_spawned",
 * a timer's event, or one a script triggered). Cancel it to stop the event from running.
 *
 * This is where plugins and add-ons meet: a plugin can react to what the pack does (the pack's own event names
 * are stable identifiers), and trigger pack events itself with AddonEntity::triggerEvent().
 *
 * @phpstan-extends EntityEvent<AddonEntity>
 */
class AddonEntityTriggerEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(AddonEntity $entity, private string $triggerName, private ?Entity $other){
		$this->entity = $entity;
	}

	/** The behavior pack event, e.g. "minecraft:entity_spawned" or "example:become_angry". */
	public function getTriggerName() : string{ return $this->triggerName; }

	/** The other entity involved (the player that interacted, the damager...), if any. */
	public function getOther() : ?Entity{ return $this->other; }
}
