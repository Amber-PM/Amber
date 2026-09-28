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

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;

/**
 * A script event: a message with a namespaced ID, the channel add-on scripts and plugins talk over.
 *
 *  - Scripts send one with system.sendScriptEvent(id, message) (or a pack runs /scriptevent); plugins receive it
 *    here, and can cancel it so scripts listening to system.afterEvents.scriptEventReceive do not get it.
 *  - Plugins send one with AddonManager::sendScriptEvent(); scripts receive it through scriptEventReceive
 *    exactly as if another script had sent it. This event is fired for those too (fromPlugin() is true).
 */
class AddonScriptEvent extends Event implements Cancellable{
	use CancellableTrait;

	public function __construct(
		private string $id,
		private string $message,
		private string $sourcePack,
		private bool $fromPlugin
	){}

	/** Namespaced ID, e.g. "mypack:open_menu". */
	public function getId() : string{ return $this->id; }

	public function getMessage() : string{ return $this->message; }

	public function setMessage(string $message) : void{ $this->message = $message; }

	/** Name of the behavior pack whose script sent it, or "" when a plugin sent it. */
	public function getSourcePack() : string{ return $this->sourcePack; }

	public function fromPlugin() : bool{ return $this->fromPlugin; }
}
