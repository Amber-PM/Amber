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
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\entity\FilterContext;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use function array_is_list;
use function array_slice;
use function array_values;
use function atan2;
use function cos;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function mt_getrandmax;
use function mt_rand;
use function sin;
use function strtolower;
use function usort;
use const M_PI;

/**
 * minecraft:behavior.summon_entity and minecraft:behavior.send_event: the "spell" behaviors. When the target is
 * in a choice's activation range and the cooldown has passed, the mob casts a weighted random choice: a timed
 * sequence of summons (in a line towards the target or in circles) or of events sent to the target.
 */
final class SequenceGoal extends Goal{
	/** @var list<array{int, \Closure() : void}> steps still to run: [tick, action] */
	private array $steps = [];
	private int $endAt = 0;
	private int $readyAt = 0;
	/** @var array<int, int> choice => tick it may be cast again */
	private array $choiceReady = [];
	/** @var list<array{\WeakReference<Entity>, int}> summoned entities with a lifespan: [entity, tick to remove it] */
	private array $lifespans = [];

	public function __construct(AddonEntity $mob, array $data, int $priority, private bool $summon){
		parent::__construct($mob, $data, $priority);
	}

	public function getFlags() : int{ return self::FLAG_MOVE | self::FLAG_LOOK; }

	/** @return list<mixed[]> */
	private function choices() : array{
		$choices = $this->data[$this->summon ? "summon_choices" : "event_choices"] ?? [];
		$out = [];
		foreach(is_array($choices) ? (array_is_list($choices) ? $choices : [$choices]) : [] as $choice){
			if(is_array($choice)){
				$out[] = $choice;
			}
		}
		return $out;
	}

	public function canStart() : bool{
		$this->expireSummons();
		$target = $this->mob->getTargetEntity();
		if($this->now() < $this->readyAt || !self::isValidTarget($target) || $target === null){
			return false;
		}
		$distance = $target->getPosition()->distance($this->mob->getPosition());
		$candidates = [];
		$weights = 0.0;
		foreach($this->choices() as $i => $choice){
			$min = (float) ($choice["min_activation_range"] ?? ($this->summon ? 1 : 0));
			$max = (float) ($choice["max_activation_range"] ?? ($this->summon ? 32 : 16));
			if($distance < $min || $distance > $max || ($this->choiceReady[$i] ?? 0) > $this->now()){
				continue;
			}
			if(!EntityFilter::test($choice["filters"] ?? null, new FilterContext($this->mob, $target), $this->mob->reporter())){
				continue;
			}
			$weight = max(0.0, (float) ($choice["weight"] ?? 1));
			$candidates[] = [$i, $choice, $weight];
			$weights += $weight;
		}
		if($candidates === []){
			return false;
		}
		$roll = mt_rand() / mt_getrandmax() * $weights;
		foreach($candidates as [$i, $choice, $weight]){
			$roll -= $weight;
			if($roll <= 0){
				$this->plan($i, $choice, $target);
				return true;
			}
		}
		[$i, $choice] = $candidates[count($candidates) - 1];
		$this->plan($i, $choice, $target);
		return true;
	}

	/** @param mixed[] $choice */
	private function plan(int $index, array $choice, Entity $target) : void{
		$now = $this->now();
		$this->steps = [];
		$this->choiceReady[$index] = $now + (int) ((float) ($choice["cooldown_time"] ?? 0) * 20);
		$this->readyAt = $now + 20;
		if(is_string($choice["start_sound_event"] ?? null)){
			$this->mob->getFeatures()->playSound($choice["start_sound_event"]);
		}
		$end = $now + (int) ((float) ($choice["cast_duration"] ?? 0) * 20);
		$sequence = $choice["sequence"] ?? [];
		foreach(is_array($sequence) ? (array_is_list($sequence) ? $sequence : [$sequence]) : [] as $step){
			if(!is_array($step)){
				continue;
			}
			$at = $now + (int) ((float) ($step["base_delay"] ?? 0) * 20);
			if($this->summon){
				$count = max(1, (int) ($step["num_entities_spawned"] ?? 1));
				$delay = (float) ($step["delay_per_summon"] ?? 0) * 20;
				for($n = 0; $n < $count; $n++){
					$tick = $at + (int) ($n * $delay);
					$this->steps[] = [$tick, fn() => $this->summonOne($step, $n, $count)];
					$end = max($end, $tick);
				}
			}else{
				$this->steps[] = [$at, function() use ($step) : void{
					$target = $this->mob->getTargetEntity();
					if($target instanceof AddonEntity && is_string($step["event"] ?? null)){
						$target->triggerEvent($step["event"], $this->mob);
					}
					if(is_string($step["sound_event"] ?? null)){
						$this->mob->getFeatures()->playSound($step["sound_event"]);
					}
				}];
				$end = max($end, $at);
			}
		}
		usort($this->steps, static fn(array $a, array $b) : int => $a[0] <=> $b[0]);
		$this->endAt = $end;
	}

	/** @param mixed[] $step */
	private function summonOne(array $step, int $index, int $count) : void{
		$type = is_string($step["entity_type"] ?? null) ? strtolower($step["entity_type"]) : null;
		$host = AddonManager::getInstance()?->getScriptHost();
		if($type === null || $host === null){
			return;
		}
		$target = $this->mob->getTargetEntity();
		$origin = ($step["target"] ?? "self") === "target" && $target !== null ? $target->getPosition() : $this->mob->getPosition();
		$cap = (int) ($step["summon_cap"] ?? 0);
		if($cap > 0){
			$radius = (float) ($step["summon_cap_radius"] ?? 0);
			$existing = 0;
			foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $entity){
				if($entity instanceof AddonEntity && $entity->getAddonIdentifier() === $type){
					$existing++;
				}
			}
			if($existing >= $cap){
				return;
			}
		}
		$size = is_numeric($step["size"] ?? null) ? (float) $step["size"] : 1.0;
		$mob = $this->mob->getPosition();
		if(($step["shape"] ?? "line") === "circle"){
			$angle = 2 * M_PI * $index / $count;
			$at = new Vector3($origin->x + cos($angle) * $size, $origin->y, $origin->z + sin($angle) * $size);
		}else{
			$toward = $target !== null ? atan2($target->getPosition()->z - $mob->z, $target->getPosition()->x - $mob->x) : 0.0;
			$at = new Vector3($mob->x + cos($toward) * $size * ($index + 1), $mob->y, $mob->z + sin($toward) * $size * ($index + 1));
		}
		$entity = $host->spawnEntity($type, new Location($at->x, $at->y, $at->z, $this->mob->getWorld(), 0.0, 0.0), is_string($step["summon_event"] ?? null) ? $step["summon_event"] : null);
		if($entity === null){
			return;
		}
		if($entity instanceof AddonEntity && $target !== null){
			$entity->setTargetEntity($target);
		}
		$lifespan = (float) ($step["entity_lifespan"] ?? -1);
		if($lifespan > 0){
			$this->lifespans[] = [\WeakReference::create($entity), $this->now() + (int) ($lifespan * 20)];
		}
		if(is_string($step["sound_event"] ?? null)){
			$this->mob->getFeatures()->playSound($step["sound_event"]);
		}
	}

	private function expireSummons() : void{
		foreach($this->lifespans as $i => [$ref, $until]){
			$entity = $ref->get();
			if($entity === null || $entity->isClosed()){
				unset($this->lifespans[$i]);
			}elseif($this->now() >= $until){
				$entity->flagForDespawn();
				unset($this->lifespans[$i]);
			}
		}
	}

	public function start() : void{
		$this->navigator()->stop();
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if($target !== null){
			$this->mob->lookAt($target->getEyePos());
		}
		$now = $this->now();
		while($this->steps !== [] && $this->steps[0][0] <= $now){
			[, $action] = $this->steps[0];
			$this->steps = array_values(array_slice($this->steps, 1));
			$action();
		}
		$this->expireSummons();
	}

	public function canContinue() : bool{
		return $this->steps !== [] || $this->now() < $this->endAt;
	}

	public function stop() : void{
		$this->steps = [];
	}
}
