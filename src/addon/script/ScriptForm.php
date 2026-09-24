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

namespace pocketmine\addon\script;

use pocketmine\form\Form;
use pocketmine\player\Player;

/**
 * A form built by a script (@minecraft/server-ui), sent as a normal server form. The answer goes back to the
 * script, which resolves its show() promise with it.
 */
final class ScriptForm implements Form{
	/** @param mixed[] $data the form JSON as the client expects it */
	public function __construct(private array $data, private int $formId, private ScriptHost $host){}

	public function jsonSerialize() : array{
		return $this->data;
	}

	public function handleResponse(Player $player, mixed $data) : void{
		$this->host->formResponse($this->formId, $data);
	}
}
