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

use pocketmine\utils\TextFormat;

/**
 * @internal
 */
final class CustomFormElement{
	/**
	 * @param array<string, mixed> $wireData
	 * @phpstan-param (\Closure(mixed) : mixed)|null $validator
	 */
	public function __construct(
		public readonly ?string $id,
		public readonly array $wireData,
		public readonly ?\Closure $validator = null
	){}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeFor(int $protocolId) : array{
		$data = $this->wireData;
		if($protocolId < CustomForm::ELEMENTS_PROTOCOL){
			if($data["type"] === "header"){
				return ["type" => "label", "text" => TextFormat::BOLD . $data["text"] . TextFormat::RESET];
			}
			if($data["type"] === "divider"){
				return ["type" => "label", "text" => ""];
			}
		}
		return $data;
	}

	public function validate(mixed $value) : mixed{
		if($this->validator === null){
			return null;
		}
		return ($this->validator)($value);
	}
}
