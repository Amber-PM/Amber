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
use function count;
use function implode;
use function is_int;
use function str_starts_with;

/**
 * A list of buttons, optionally split up by headers, labels and dividers (the game's action form).
 *
 * Clients older than 1.21.70 cannot show headers, labels or dividers between buttons: their texts are added to the
 * form's body instead, and dividers are left out.
 *
 * ```php
 * $player->sendForm((new MenuForm("Games", "Pick a game"))
 *     ->header("Solo")
 *     ->button("Parkour", "textures/items/feather", fn(Player $p) => $p->sendMessage("Parkour!"))
 *     ->divider()
 *     ->button("Bed Wars", "https://example.com/bedwars.png")
 *     ->onSubmit(fn(Player $p, int $button) => $p->sendMessage("You picked button $button")));
 * ```
 */
final class MenuForm extends BaseForm{
	private const ELEMENT_BUTTON = "button";
	private const ELEMENT_HEADER = "header";
	private const ELEMENT_LABEL = "label";
	private const ELEMENT_DIVIDER = "divider";

	/** @phpstan-var list<array{string, string, ?string}> type, text, image */
	private array $elements = [];
	/** @phpstan-var list<(\Closure(Player) : void)|null> */
	private array $buttonHandlers = [];
	/** @phpstan-var (\Closure(Player, int) : void)|null */
	private ?\Closure $onSubmit = null;

	public function __construct(string $title, private string $body = ""){
		parent::__construct($title);
	}

	/**
	 * @param string|null $image a texture path in a resource pack ("textures/items/apple"), or an http(s) URL
	 * @phpstan-param (\Closure(Player) : void)|null $onClick
	 * @return $this
	 */
	public function button(string $text, ?string $image = null, ?\Closure $onClick = null) : self{
		$this->elements[] = [self::ELEMENT_BUTTON, $text, $image];
		$this->buttonHandlers[] = $onClick;
		return $this;
	}

	/** @return $this */
	public function header(string $text) : self{
		$this->elements[] = [self::ELEMENT_HEADER, $text, null];
		return $this;
	}

	/** @return $this */
	public function label(string $text) : self{
		$this->elements[] = [self::ELEMENT_LABEL, $text, null];
		return $this;
	}

	/** @return $this */
	public function divider() : self{
		$this->elements[] = [self::ELEMENT_DIVIDER, "", null];
		return $this;
	}

	/**
	 * Called with the index of the chosen button (counting buttons only), after the button's own handler.
	 *
	 * @phpstan-param \Closure(Player, int) : void $onSubmit
	 * @return $this
	 */
	public function onSubmit(\Closure $onSubmit) : self{
		$this->onSubmit = $onSubmit;
		return $this;
	}

	public function getButtonCount() : int{
		return count($this->buttonHandlers);
	}

	protected function handleAnswer(Player $player, mixed $data) : void{
		if(!is_int($data) || $data < 0 || $data >= count($this->buttonHandlers)){
			throw new FormValidationException("Expected a button index between 0 and " . (count($this->buttonHandlers) - 1));
		}
		$handler = $this->buttonHandlers[$data];
		if($handler !== null){
			$handler($player);
		}
		if($this->onSubmit !== null){
			($this->onSubmit)($player, $data);
		}
	}

	public function serializeFor(int $protocolId) : array{
		if($protocolId >= self::ELEMENTS_PROTOCOL){
			$elements = [];
			foreach($this->elements as [$type, $text, $image]){
				$elements[] = $type === self::ELEMENT_BUTTON ? ["type" => self::ELEMENT_BUTTON] + self::buttonData($text, $image) : ["type" => $type, "text" => $text];
			}
			return ["type" => "form", "title" => $this->title, "content" => $this->body, "elements" => $elements];
		}

		$body = $this->body === "" ? [] : [$this->body];
		$buttons = [];
		foreach($this->elements as [$type, $text, $image]){
			if($type === self::ELEMENT_BUTTON){
				$buttons[] = self::buttonData($text, $image);
			}elseif($type !== self::ELEMENT_DIVIDER){
				$body[] = $text;
			}
		}
		return ["type" => "form", "title" => $this->title, "content" => implode("\n", $body), "buttons" => $buttons];
	}

	/**
	 * @return mixed[]
	 */
	private static function buttonData(string $text, ?string $image) : array{
		$button = ["text" => $text];
		if($image !== null && $image !== ""){
			$button["image"] = [
				"type" => str_starts_with($image, "http://") || str_starts_with($image, "https://") ? "url" : "path",
				"data" => $image
			];
		}
		return $button;
	}
}
