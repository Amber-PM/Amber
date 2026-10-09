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

namespace pocketmine\form;

use pocketmine\player\Player;
use function is_bool;

/**
 * A question with two answers (the game's message form).
 *
 * ```php
 * $player->sendForm((new ConfirmForm("Leave", "Leave the game?", "Leave", "Stay"))
 *     ->onSubmit(fn(Player $p, bool $leave) => $leave ? $p->kick("Bye") : null));
 * ```
 */
final class ConfirmForm extends BaseForm{
	/** @phpstan-var (\Closure(Player, bool) : void)|null */
	private ?\Closure $onSubmit = null;

	public function __construct(
		string $title,
		private string $body,
		private string $yesText = "Yes",
		private string $noText = "No"
	){
		parent::__construct($title);
	}

	/**
	 * Called with true for the first button, false for the second.
	 *
	 * @phpstan-param \Closure(Player, bool) : void $onSubmit
	 * @return $this
	 */
	public function onSubmit(\Closure $onSubmit) : self{
		$this->onSubmit = $onSubmit;
		return $this;
	}

	protected function handleAnswer(Player $player, mixed $data) : void{
		if(!is_bool($data)){
			throw new FormValidationException("Expected a boolean answer");
		}
		if($this->onSubmit !== null){
			($this->onSubmit)($player, $data);
		}
	}

	public function serializeFor(int $protocolId) : array{
		return ["type" => "modal", "title" => $this->title, "content" => $this->body, "button1" => $this->yesText, "button2" => $this->noText];
	}
}
