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

namespace pocketmine\thread;

final class ThreadCrashException extends ThreadException{

	private ThreadCrashInfo $crashInfo;

	public function __construct(string $message, ThreadCrashInfo $crashInfo){
		parent::__construct($message);
		$this->crashInfo = $crashInfo;
	}

	public function getCrashInfo() : ThreadCrashInfo{
		return $this->crashInfo;
	}
}
