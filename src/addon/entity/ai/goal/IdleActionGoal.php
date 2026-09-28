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

namespace pocketmine\addon\entity\ai\goal;

use pocketmine\addon\AddonManager;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\Goal;
use pocketmine\addon\script\ScriptHost;
use function array_is_list;
use function floor;
use function is_array;
use function is_string;
use function str_contains;

/**
 * Things a mob does standing still:
 * - eat_block: eat the block it stands in or on (sheep and grass), replace it, fire on_eat;
 * - random_sitting: sit down now and then, and get up again;
 * - swell: hold still (swelling) while its target is within stop_distance and it is about to explode.
 */
final class IdleActionGoal extends Goal{
	public const EAT_BLOCK = "eat_block";
	public const RANDOM_SITTING = "random_sitting";
	public const SWELL = "swell";

	private int $until = 0;
	private int $readyAt = 0;
	/** @var array{int, int, int, string}|null the block being eaten and what replaces it */
	private ?array $eating = null;

	/** @param mixed[] $data */
	public function __construct(AddonEntity $mob, array $data, int $priority, private string $mode){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_MOVE | ($this->mode === self::RANDOM_SITTING ? 0 : self::FLAG_LOOK); }

	public function canStart() : bool{
		return match($this->mode){
			self::EAT_BLOCK => $this->findFood(),
			self::RANDOM_SITTING => !$this->mob->isSitting() && $this->now() >= $this->readyAt && $this->chance($this->float("start_chance", 0.1) / 20),
			default => $this->swelling($this->float("start_distance", 2.5)),
		};
	}

	private function swelling(float $distance) : bool{
		$target = $this->mob->getTargetEntity();
		return $target !== null && self::isValidTarget($target) && $target->getPosition()->distance($this->mob->getPosition()) <= $distance;
	}

	private function findFood() : bool{
		//success_chance is a chance per tick, as in the game
		if($this->mob->isSitting() || !$this->chance($this->float("success_chance", 0.02))){
			return false;
		}
		$pos = $this->mob->getPosition();
		$world = $this->mob->getWorld();
		$pairs = $this->data["eat_and_replace_block_pairs"] ?? [];
		foreach([0, -1] as $dy){
			$x = (int) floor($pos->x);
			$y = (int) floor($pos->y) + $dy;
			$z = (int) floor($pos->z);
			$type = ScriptHost::blockTypeId($world->getBlockAt($x, $y, $z));
			foreach(is_array($pairs) ? (array_is_list($pairs) ? $pairs : [$pairs]) : [] as $pair){
				$eat = is_array($pair) ? ($pair["eat_block"] ?? null) : null;
				if(is_string($eat) && (str_contains($eat, ":") ? $eat : "minecraft:$eat") === $type){
					$replace = is_string($pair["replace_block"] ?? null) ? $pair["replace_block"] : "minecraft:air";
					$this->eating = [$x, $y, $z, str_contains($replace, ":") ? $replace : "minecraft:$replace"];
					return true;
				}
			}
		}
		return false;
	}

	public function start() : void{
		$this->navigator()->stop();
		$this->until = $this->now() + (int) (20 * match($this->mode){
			self::EAT_BLOCK => $this->float("time_until_eat", 1.8),
			self::RANDOM_SITTING => $this->float("min_sit_time", 10),
			default => 0,
		});
		if($this->mode === self::RANDOM_SITTING){
			$this->mob->setSitting(true);
		}
	}

	public function canContinue() : bool{
		return match($this->mode){
			self::EAT_BLOCK => $this->eating !== null,
			self::RANDOM_SITTING => $this->mob->isSitting() && ($this->now() < $this->until || !$this->chance($this->float("stop_chance", 0.3) / 20)),
			default => $this->swelling($this->float("stop_distance", 6)),
		};
	}

	public function tick() : void{
		if($this->mode === self::SWELL){
			$this->navigator()->stop();
			$target = $this->mob->getTargetEntity();
			if($target !== null){
				$this->mob->lookAt($target->getEyePos());
			}
			return;
		}
		if($this->mode === self::EAT_BLOCK && $this->eating !== null && $this->now() >= $this->until){
			[$x, $y, $z, $replace] = $this->eating;
			$this->eating = null;
			AddonManager::getInstance()?->getScriptHost()?->setBlock($this->mob->getWorld(), $x, $y, $z, $replace, []);
			$this->mob->triggerEventDefinition($this->data["on_eat"] ?? null);
		}
	}

	public function stop() : void{
		$this->eating = null;
		if($this->mode === self::RANDOM_SITTING){
			$this->mob->setSitting(false);
			$this->readyAt = $this->now() + (int) ($this->float("cooldown", 0) * 20);
		}
	}
}
