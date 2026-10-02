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

namespace pocketmine\web;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\lang\Translatable;
use pocketmine\utils\TextFormat;
use function explode;
use function trim;
use const PHP_INT_MAX;

/**
 * Runs commands for the web panel with console permissions, keeping their output for the response.
 */
final class WebCommandSender extends ConsoleCommandSender{
	/** @var list<string> */
	private array $output = [];

	public function sendMessage(Translatable|string $message) : void{
		if($message instanceof Translatable){
			$message = $this->getLanguage()->translate($message);
		}
		foreach(explode("\n", trim($message), limit: PHP_INT_MAX) as $line){
			$this->output[] = TextFormat::clean($line);
		}
	}

	public function getName() : string{
		return "Web panel";
	}

	/** @return list<string> */
	public function getOutput() : array{
		return $this->output;
	}
}
