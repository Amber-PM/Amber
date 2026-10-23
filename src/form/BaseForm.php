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

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;

/**
 * Shared parts of the built-in form builders (MenuForm, ConfirmForm, CustomForm).
 */
abstract class BaseForm implements ProtocolAwareForm, ClosableForm{
	/** First version whose clients show headers, labels and dividers in forms. */
	public const ELEMENTS_PROTOCOL = ProtocolInfo::PROTOCOL_1_21_70;

	/** @phpstan-var (\Closure(Player, FormCloseReason) : void)|null */
	private ?\Closure $onClose = null;

	public function __construct(
		protected string $title
	){}

	/**
	 * Called when the player closes the form without answering.
	 *
	 * @phpstan-param \Closure(Player, FormCloseReason) : void $onClose
	 * @return $this
	 */
	public function onClose(\Closure $onClose) : static{
		$this->onClose = $onClose;
		return $this;
	}

	public function handleClose(Player $player, FormCloseReason $reason) : void{
		if($this->onClose !== null){
			($this->onClose)($player, $reason);
		}
	}

	/**
	 * @param mixed $data
	 */
	public function handleResponse(Player $player, $data) : void{
		if($data === null){
			$this->handleClose($player, FormCloseReason::CLOSED);
			return;
		}
		$this->handleAnswer($player, $data);
	}

	/**
	 * @param mixed $data not null
	 * @throws FormValidationException
	 */
	abstract protected function handleAnswer(Player $player, mixed $data) : void;

	/**
	 * @return mixed[]
	 */
	public function jsonSerialize() : array{
		return $this->serializeFor(ProtocolInfo::CURRENT_PROTOCOL);
	}
}
