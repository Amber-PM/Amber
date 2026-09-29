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

use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLoggerAttachment;
use pocketmine\utils\TextFormat;
use function max;

/**
 * Keeps the most recent log lines for the web panel. Attached to the main logger, so it is fed from any thread.
 */
final class WebLogBuffer extends ThreadSafeLoggerAttachment{
	private const MAX_LINES = 500;

	/** @phpstan-var ThreadSafeArray<int, string> */
	private ThreadSafeArray $lines;
	private int $next = 0;

	public function __construct(){
		$this->lines = new ThreadSafeArray();
	}

	public function log(string $level, string $message) : void{
		$line = "[" . $level . "] " . TextFormat::clean($message);
		$this->synchronized(function() use ($line) : void{
			$this->lines[$this->next] = $line;
			unset($this->lines[$this->next - self::MAX_LINES]);
			++$this->next;
		});
	}

	/**
	 * @return array{list<array{int, string}>, int} the lines numbered from $after on (as many as are still kept),
	 *                                              and the number to ask for next
	 */
	public function getLinesAfter(int $after) : array{
		return $this->synchronized(function() use ($after) : array{
			$lines = [];
			for($i = max($after, $this->next - self::MAX_LINES, 0); $i < $this->next; ++$i){
				$line = $this->lines[$i] ?? null;
				if($line !== null){
					$lines[] = [$i, $line];
				}
			}
			return [$lines, $this->next];
		});
	}
}
