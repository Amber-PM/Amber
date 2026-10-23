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
use pocketmine\utils\TextFormat;
use function abs;
use function array_is_list;
use function array_values;
use function count;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function round;

/**
 * A form of input fields (the game's modal form): text inputs, toggles, sliders and dropdowns, which can be split
 * up by headers, labels and dividers. Each input has an ID its answer is read back with.
 *
 * Clients older than 1.21.70 show headers as bold labels, and dividers as empty labels.
 *
 * ```php
 * $player->sendForm((new CustomForm("Settings"))
 *     ->header("Chat")
 *     ->toggle("mentions", "Mention sounds", true)
 *     ->dropdown("lang", "Language", ["English", "Español"])
 *     ->divider()
 *     ->input("nick", "Nickname", "Steve")
 *     ->slider("volume", "Volume", 0, 100, 5, 50)
 *     ->onSubmit(function(Player $p, CustomFormResponse $r) : void{
 *         $p->sendMessage("Nickname: " . $r->getString("nick") . ", volume " . $r->getFloat("volume"));
 *     }));
 * ```
 */
final class CustomForm extends BaseForm{
	/** @var CustomFormElement[] */
	private array $elements = [];
	/** @var array<string, true> */
	private array $ids = [];
	/** @phpstan-var (\Closure(Player, CustomFormResponse) : void)|null */
	private ?\Closure $onSubmit = null;

	/** @return $this */
	public function header(string $text) : self{
		return $this->add(null, ["type" => "header", "text" => $text], null);
	}

	/** @return $this */
	public function label(string $text) : self{
		return $this->add(null, ["type" => "label", "text" => $text], null);
	}

	/** @return $this */
	public function divider() : self{
		return $this->add(null, ["type" => "divider", "text" => ""], null);
	}

	/** @return $this */
	public function input(string $id, string $text, string $placeholder = "", string $default = "") : self{
		return $this->add($id, ["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default], static function(mixed $value) : string{
			if(!is_string($value)){
				throw new FormValidationException("Expected text");
			}
			return $value;
		});
	}

	/** @return $this */
	public function toggle(string $id, string $text, bool $default = false) : self{
		return $this->add($id, ["type" => "toggle", "text" => $text, "default" => $default], static function(mixed $value) : bool{
			if(!is_bool($value)){
				throw new FormValidationException("Expected a boolean");
			}
			return $value;
		});
	}

	/** @return $this */
	public function slider(string $id, string $text, float $min, float $max, float $step = 1.0, ?float $default = null) : self{
		if(!is_finite($min) || !is_finite($max) || !is_finite($step)){
			throw new \InvalidArgumentException("Slider min, max, and step must be finite numbers");
		}
		if($min > $max || $step <= 0){
			throw new \InvalidArgumentException("Slider needs min <= max and a positive step");
		}
		$default ??= $min;
		if(!is_finite($default) || $default < $min || $default > $max){
			throw new \InvalidArgumentException("Slider default must be a finite number between min and max");
		}
		return $this->add($id, ["type" => "slider", "text" => $text, "min" => $min, "max" => $max, "step" => $step, "default" => $default], static function(mixed $value) use ($min, $max, $step) : float{
			if((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < $min || $value > $max){
				throw new FormValidationException("Expected a number between $min and $max");
			}
			$floatVal = (float) $value;
			$stepsFromMin = ($floatVal - $min) / $step;
			if(abs($stepsFromMin - round($stepsFromMin)) > 1e-5){
				throw new FormValidationException("Value $floatVal does not align with slider step $step");
			}
			return $floatVal;
		});
	}

	/**
	 * A slider over named steps; the answer is the index of the chosen step.
	 *
	 * @param string[] $steps
	 * @return $this
	 */
	public function stepSlider(string $id, string $text, array $steps, int $default = 0) : self{
		return $this->add($id, ["type" => "step_slider", "text" => $text, "steps" => self::options($steps, $default), "default" => $default], self::indexValidator(count($steps)));
	}

	/**
	 * A dropdown list; the answer is the index of the chosen option.
	 *
	 * @param string[] $options
	 * @return $this
	 */
	public function dropdown(string $id, string $text, array $options, int $default = 0) : self{
		return $this->add($id, ["type" => "dropdown", "text" => $text, "options" => self::options($options, $default), "default" => $default], self::indexValidator(count($options)));
	}

	/**
	 * @phpstan-param \Closure(Player, CustomFormResponse) : void $onSubmit
	 * @return $this
	 */
	public function onSubmit(\Closure $onSubmit) : self{
		$this->onSubmit = $onSubmit;
		return $this;
	}

	/**
	 * @param string[] $options
	 * @return list<string>
	 */
	private static function options(array $options, int $default) : array{
		if($options === []){
			throw new \InvalidArgumentException("At least one option is needed");
		}
		if($default < 0 || $default >= count($options)){
			throw new \InvalidArgumentException("Default option index out of range");
		}
		return array_values($options);
	}

	/** @phpstan-return \Closure(mixed) : int */
	private static function indexValidator(int $count) : \Closure{
		return static function(mixed $value) use ($count) : int{
			if(!is_int($value) || $value < 0 || $value >= $count){
				throw new FormValidationException("Expected an option index between 0 and " . ($count - 1));
			}
			return $value;
		};
	}

	/**
	 * @param array<string, mixed> $json
	 * @phpstan-param (\Closure(mixed) : mixed)|null $validator
	 * @return $this
	 */
	private function add(?string $id, array $json, ?\Closure $validator) : self{
		if($id !== null){
			if(isset($this->ids[$id])){
				throw new \InvalidArgumentException("Duplicate form element ID \"$id\"");
			}
			$this->ids[$id] = true;
		}
		$this->elements[] = new CustomFormElement($id, $json, $validator);
		return $this;
	}

	protected function handleAnswer(Player $player, mixed $data) : void{
		if(!is_array($data) || !array_is_list($data) || count($data) !== count($this->elements)){
			throw new FormValidationException("Expected a list of " . count($this->elements) . " answers");
		}
		$values = [];
		foreach($this->elements as $i => $element){
			if($element->id !== null && $element->validator !== null){
				try{
					$values[$element->id] = $element->validate($data[$i]);
				}catch(FormValidationException $e){
					throw new FormValidationException("Answer for \"{$element->id}\": " . $e->getMessage(), 0, $e);
				}
			}
		}
		if($this->onSubmit !== null){
			($this->onSubmit)($player, new CustomFormResponse($values));
		}
	}

	public function serializeFor(int $protocolId) : array{
		$content = [];
		foreach($this->elements as $element){
			$content[] = $element->serializeFor($protocolId);
		}
		return ["type" => "custom_form", "title" => $this->title, "content" => $content];
	}
}
