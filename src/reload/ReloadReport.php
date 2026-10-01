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

namespace pocketmine\reload;

/**
 * What a live reload did: what it applied, what needs a full restart, and what failed.
 */
final class ReloadReport{
	/** @var list<string> */
	private array $applied = [];
	/** @var list<string> */
	private array $needsRestart = [];
	/** @var list<string> */
	private array $errors = [];

	public function applied(string $line) : void{ $this->applied[] = $line; }

	public function needsRestart(string $line) : void{ $this->needsRestart[] = $line; }

	public function error(string $line) : void{ $this->errors[] = $line; }

	/** @return list<string> */
	public function getApplied() : array{ return $this->applied; }

	/** @return list<string> */
	public function getNeedsRestart() : array{ return $this->needsRestart; }

	/** @return list<string> */
	public function getErrors() : array{ return $this->errors; }

	public function merge(ReloadReport $other) : void{
		$this->applied = [...$this->applied, ...$other->applied];
		$this->needsRestart = [...$this->needsRestart, ...$other->needsRestart];
		$this->errors = [...$this->errors, ...$other->errors];
	}
}
