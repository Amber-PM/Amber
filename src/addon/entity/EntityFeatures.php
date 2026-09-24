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

namespace pocketmine\addon\entity;

use pocketmine\addon\AddonManager;
use pocketmine\addon\AddonMath;
use pocketmine\addon\script\ScriptHost;
use pocketmine\block\Liquid;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\player\Player;
use pocketmine\world\sound\EndermanTeleportSound;
use function array_intersect;
use function array_is_list;
use function floor;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function min;
use function mt_rand;
use function sqrt;
use function str_replace;
use function str_contains;
use function strtolower;

/**
 * The entity components that run on their own rather than through the mob's AI: sounds, entity and target
 * sensors, anger, spawning entities and items (egg laying), time-of-day schedules, block notifications, damage
 * over time, attack cooldowns, buoyancy, trails, random teleporting, pushing, following range and home.
 * One instance per add-on entity, ticked by it.
 */
final class EntityFeatures{
	private const SAVE_HOME = "AddonHome";

	private int $nextAmbient = 0;
	/** @var array<int, int> subsensor => tick it may sense again */
	private array $sensorReady = [];
	private ?bool $targetInside = null;
	private ?int $angryUntil = null;
	/** @var array<int, int> spawn_entity entry => tick of its next spawn (-1: used up) */
	private array $spawnAt = [];
	private int $nextSchedule = 0;
	/** @var array<int, bool> inside_block_notifier entry => the entity is inside that block */
	private array $insideBlocks = [];
	private int $nextDamageOverTime = 0;
	private ?int $attackCooldownUntil = null;
	private int $nextTeleport = 0;
	private ?Vector3 $home = null;

	public function __construct(private AddonEntity $mob){}

	private function now() : int{
		return $this->mob->getWorld()->getServer()->getTick();
	}

	/** @param mixed $component a number or a [min, max] pair, in seconds */
	private static function seconds(mixed $component, float $min, float $max) : float{
		if(is_numeric($component)){
			return (float) $component;
		}
		if(is_array($component)){
			$a = is_numeric($component[0] ?? $component["range_min"] ?? null) ? (float) ($component[0] ?? $component["range_min"]) : $min;
			$b = is_numeric($component[1] ?? $component["range_max"] ?? null) ? (float) ($component[1] ?? $component["range_max"]) : $a;
			return $a + AddonMath::randomFloat() * max(0.0, $b - $a);
		}
		return $min + AddonMath::randomFloat() * max(0.0, $max - $min);
	}

	/** @return list<mixed[]> */
	private static function entries(mixed $component, string $key) : array{
		$list = is_array($component) ? ($component[$key] ?? $component) : [];
		if(!is_array($list)){
			return [];
		}
		$out = [];
		foreach(array_is_list($list) ? $list : [$list] as $entry){
			if(is_array($entry)){
				$out[] = $entry;
			}
		}
		return $out;
	}

	private static function key(mixed $component) : string{
		return (string) json_encode($component);
	}

	// ---------------------------------------------------------------- lifecycle

	public function load(CompoundTag $nbt) : void{
		$home = $nbt->getCompoundTag(self::SAVE_HOME);
		if($home !== null){
			$this->home = new Vector3($home->getFloat("x"), $home->getFloat("y"), $home->getFloat("z"));
		}
	}

	public function save(CompoundTag $nbt) : void{
		if($this->home !== null){
			$nbt->setTag(self::SAVE_HOME, CompoundTag::create()->setFloat("x", (float) $this->home->x)->setFloat("y", (float) $this->home->y)->setFloat("z", (float) $this->home->z));
		}
	}

	/**
	 * The active components changed (a component group was added or removed): restart the timers of the
	 * components that changed.
	 *
	 * @param mixed[] $old
	 * @param mixed[] $new
	 */
	public function componentsChanged(array $old, array $new) : void{
		$now = $this->now();
		$changed = static fn(string $name) : bool => self::key($old[$name] ?? null) !== self::key($new[$name] ?? null);
		if($changed("minecraft:ambient_sound_interval")){
			$this->nextAmbient = $now + $this->ambientDelay($new["minecraft:ambient_sound_interval"] ?? null);
		}
		if($changed("minecraft:angry")){
			$this->angryUntil = null;
			$angry = $new["minecraft:angry"] ?? null;
			if(is_array($angry) || isset($new["minecraft:angry"])){
				$this->becomeAngry(is_array($angry) ? $angry : []);
			}
		}
		if($changed("minecraft:spawn_entity")){
			$this->spawnAt = [];
		}
		if($changed("minecraft:scheduler")){
			$scheduler = $new["minecraft:scheduler"] ?? null;
			$this->nextSchedule = $now + (int) (20 * max(1.0, self::seconds(null, (float) (is_array($scheduler) ? ($scheduler["min_delay_secs"] ?? 0) : 0), (float) (is_array($scheduler) ? ($scheduler["max_delay_secs"] ?? 0) : 0))));
		}
		if($changed("minecraft:inside_block_notifier")){
			$this->insideBlocks = [];
		}
		if($changed("minecraft:entity_sensor")){
			$this->sensorReady = [];
		}
		if($changed("minecraft:target_nearby_sensor")){
			$this->targetInside = null;
		}
		if(isset($new["minecraft:home"]) && $this->home === null){
			$this->home = $this->mob->getPosition()->asVector3();
		}
	}

	// ---------------------------------------------------------------- sounds

	/** Plays one of the entity's own sounds (its resource pack's entity_sounds): "ambient", "hurt", "death"... */
	public function playSound(string $event) : void{
		$pos = $this->mob->getPosition();
		$this->mob->getWorld()->broadcastPacketToViewers($pos, LevelSoundEventPacket::create($event, $pos->add(0, $this->mob->getEyeHeight(), 0), -1, $this->mob->getAddonIdentifier(), $this->mob->isBaby(), false, $this->mob->getId(), null));
	}

	private function ambientDelay(mixed $component) : int{
		$value = is_array($component) && is_numeric($component["value"] ?? null) ? (float) $component["value"] : 8.0;
		$range = is_array($component) && is_numeric($component["range"] ?? null) ? (float) $component["range"] : 16.0;
		return max(20, (int) (($value + AddonMath::randomFloat() * $range) * 20));
	}

	private function ambient(mixed $component, int $now) : void{
		if($now < $this->nextAmbient){
			return;
		}
		$this->nextAmbient = $now + $this->ambientDelay($component);
		$event = is_array($component) && is_string($component["event_name"] ?? null) ? $component["event_name"] : "ambient";
		//event_names chooses by Molang condition; the one without a condition is the default
		foreach(is_array($component) && is_array($component["event_names"] ?? null) ? $component["event_names"] : [] as $choice){
			if(is_array($choice) && is_string($choice["event_name"] ?? null) && !isset($choice["condition"])){
				$event = $choice["event_name"];
			}
		}
		$this->playSound($event);
	}

	// ---------------------------------------------------------------- sensors

	/** @param mixed[] $sensor minecraft:entity_sensor, with subsensors or in the older single-sensor form */
	private function entitySensor(array $sensor, int $now) : void{
		$subsensors = is_array($sensor["subsensors"] ?? null) ? $sensor["subsensors"] : [$sensor];
		$relative = (bool) ($sensor["relative_range"] ?? true);
		$playersOnly = (bool) ($sensor["find_players_only"] ?? false);
		$size = $this->mob->getSize();
		$world = $this->mob->getWorld();
		$pos = $this->mob->getPosition();
		foreach($subsensors as $i => $sub){
			if(!is_array($sub) || ($this->sensorReady[$i] ?? 0) > $now){
				continue;
			}
			$range = $sub["range"] ?? $sub["sensor_range"] ?? $sensor["sensor_range"] ?? 10;
			$horizontal = is_array($range) ? (float) ($range[0] ?? 10) : (float) $range;
			$vertical = is_array($range) ? (float) ($range[1] ?? $range[0] ?? 10) : (float) $range;
			if($relative){
				$horizontal += $size->getWidth() / 2;
				$vertical += $size->getHeight() / 2;
			}
			$min = (int) ($sub["minimum_count"] ?? $sensor["minimum_count"] ?? 1);
			$max = (int) ($sub["maximum_count"] ?? $sensor["maximum_count"] ?? -1);
			$requireAll = (bool) ($sub["require_all"] ?? $sensor["require_all"] ?? false);
			$filters = $sub["event_filters"] ?? $sensor["event_filters"] ?? null;
			$matching = 0;
			$seen = 0;
			$center = $pos->add(0, (float) ($sub["y_offset"] ?? 0), 0);
			foreach($world->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($horizontal, $vertical, $horizontal), $this->mob) as $entity){
				if(!$entity->isAlive() || ($playersOnly && !$entity instanceof Player) || ($entity instanceof Player && $entity->isSpectator())){
					continue;
				}
				$ep = $entity->getPosition();
				if(($ep->x - $center->x) ** 2 + ($ep->z - $center->z) ** 2 > $horizontal ** 2){
					continue;
				}
				$seen++;
				if(EntityFilter::test($filters, new FilterContext($this->mob, $entity), $this->mob->reporter())){
					$matching++;
				}
			}
			if(($requireAll && $matching !== $seen) || $matching < $min || ($max >= 0 && $matching > $max)){
				continue;
			}
			$this->mob->triggerEventDefinition($sub["event"] ?? $sensor["event"] ?? null);
			$cooldown = (float) ($sub["cooldown"] ?? -1);
			if($cooldown > 0){
				$this->sensorReady[$i] = $now + (int) ($cooldown * 20);
			}
		}
	}

	/** @param mixed[] $sensor minecraft:target_nearby_sensor: events as the target crosses the inside and outside ranges */
	private function targetSensor(array $sensor) : void{
		$target = $this->mob->getTargetEntity();
		if($target === null || !$target->isAlive() || $target->getWorld() !== $this->mob->getWorld()){
			$this->targetInside = null;
			return;
		}
		$distance = $target->getPosition()->distance($this->mob->getPosition());
		$inside = (float) ($sensor["inside_range"] ?? 1);
		$outside = (float) ($sensor["outside_range"] ?? 5);
		if($distance <= $inside){
			if((bool) ($sensor["must_see"] ?? false) && !$this->canSee($target)){
				$this->mob->triggerEventDefinition($sensor["on_vision_lost_inside_range"] ?? null, $target);
				return;
			}
			if($this->targetInside !== true){
				$this->targetInside = true;
				$this->mob->triggerEventDefinition($sensor["on_inside_range"] ?? null, $target);
			}
		}elseif($distance > $outside && $this->targetInside !== false){
			$this->targetInside = false;
			$this->mob->triggerEventDefinition($sensor["on_outside_range"] ?? null, $target);
		}
	}

	private function canSee(Entity $target) : bool{
		$world = $this->mob->getWorld();
		foreach(VoxelRayTrace::betweenPoints($this->mob->getEyePos(), $target->getEyePos()) as $v){
			$block = $world->getBlockAt((int) $v->x, (int) $v->y, (int) $v->z);
			if($block->isSolid() && !$block->isTransparent()){
				return false;
			}
		}
		return true;
	}

	// ---------------------------------------------------------------- anger

	/** @param mixed[] $angry */
	private function becomeAngry(array $angry) : void{
		$duration = (float) ($angry["duration"] ?? 25) + (float) ($angry["duration_delta"] ?? 0) * (AddonMath::randomFloat() * 2 - 1);
		$this->angryUntil = $duration < 0 ? null : $this->now() + (int) ($duration * 20);
		$target = $this->mob->getTargetEntity();
		if(!(bool) ($angry["broadcast_anger"] ?? false) || $target === null){
			return;
		}
		$range = (float) ($angry["broadcast_range"] ?? 20);
		$families = is_array($angry["broadcast_targets"] ?? null) ? $angry["broadcast_targets"] : null;
		foreach($this->mob->getWorld()->getNearbyEntities($this->mob->getBoundingBox()->expandedCopy($range, $range / 2, $range), $this->mob) as $other){
			if(!$other instanceof AddonEntity || $other === $target){
				continue;
			}
			$sameKind = $families === null ? $other->getAddonIdentifier() === $this->mob->getAddonIdentifier() : array_intersect($families, $other->getFamilies()) !== [];
			if($sameKind && EntityFilter::test($angry["broadcast_filters"] ?? null, new FilterContext($other, $target), $other->reporter())){
				$other->setTargetEntity($target);
				$other->triggerEventDefinition($other->getComponent("minecraft:on_friendly_anger") ?? null, $this->mob);
			}
		}
	}

	private function calmDown(mixed $angry) : void{
		$this->angryUntil = null;
		$this->mob->triggerEventDefinition(is_array($angry) ? ($angry["calm_event"] ?? null) : null);
	}

	// ---------------------------------------------------------------- spawning, schedules, blocks

	private function spawnEntities(mixed $component, int $now) : void{
		foreach(self::entries($component, "entities") as $i => $entry){
			$at = $this->spawnAt[$i] ?? null;
			if($at === -1){
				continue;
			}
			$wait = static fn() : int => (int) (20 * self::seconds(null, (float) ($entry["min_wait_time"] ?? 300), (float) ($entry["max_wait_time"] ?? 600)));
			if($at === null){
				$this->spawnAt[$i] = $now + max(1, $wait());
				continue;
			}
			if($now < $at){
				continue;
			}
			$this->spawnAt[$i] = (bool) ($entry["single_use"] ?? false) ? -1 : $now + max(1, $wait());
			if(!EntityFilter::test($entry["filters"] ?? null, new FilterContext($this->mob), $this->mob->reporter())){
				continue;
			}
			$count = max(1, (int) ($entry["num_to_spawn"] ?? 1));
			$location = $this->mob->getLocation();
			for($n = 0; $n < $count; $n++){
				if(is_string($entry["spawn_item"] ?? null)){
					$name = $entry["spawn_item"];
					$item = StringToItemParser::getInstance()->parse(str_contains($name, ":") ? $name : "minecraft:$name") ?? AddonManager::getInstance()?->getItem(str_contains($name, ":") ? $name : "minecraft:$name");
					if($item !== null){
						$this->mob->getWorld()->dropItem($location, $item);
					}
				}elseif(is_string($entry["spawn_entity"] ?? null)){
					$child = AddonManager::getInstance()?->getScriptHost()?->spawnEntity(strtolower($entry["spawn_entity"]), Location::fromObject($location, $location->getWorld()), is_string($entry["spawn_event"] ?? null) ? $entry["spawn_event"] : null);
					if($child instanceof AddonEntity && ($entry["spawn_method"] ?? "") === "born"){
						$child->triggerEvent("minecraft:entity_born", $this->mob);
					}
				}
			}
			$this->playSound(is_string($entry["spawn_sound"] ?? null) ? $entry["spawn_sound"] : "plop");
		}
	}

	private function schedule(mixed $scheduler, int $now) : void{
		if($now < $this->nextSchedule || !is_array($scheduler)){
			return;
		}
		$this->nextSchedule = $now + (int) (20 * max(1.0, self::seconds(null, (float) ($scheduler["min_delay_secs"] ?? 0), (float) ($scheduler["max_delay_secs"] ?? 0))));
		foreach(self::entries($scheduler["scheduled_events"] ?? [], "scheduled_events") as $entry){
			if(EntityFilter::test($entry["filters"] ?? null, new FilterContext($this->mob), $this->mob->reporter())){
				$this->mob->triggerEventDefinition($entry["event"] ?? null);
				return;
			}
		}
	}

	private function insideBlocks(mixed $component) : void{
		$box = $this->mob->getBoundingBox();
		$world = $this->mob->getWorld();
		$blocks = [];
		for($x = (int) floor($box->minX); $x <= (int) floor($box->maxX - 0.0001); $x++){
			for($y = (int) floor($box->minY); $y <= (int) floor($box->maxY - 0.0001); $y++){
				for($z = (int) floor($box->minZ); $z <= (int) floor($box->maxZ - 0.0001); $z++){
					$blocks[] = ScriptHost::blockWire($world->getBlockAt($x, $y, $z));
				}
			}
		}
		foreach(self::entries($component, "block_list") as $i => $entry){
			$want = $entry["block"] ?? null;
			$name = is_array($want) ? ($want["name"] ?? null) : $want;
			if(!is_string($name)){
				continue;
			}
			//still and flowing liquids are one block to packs ("water" matches flowing water too)
			$name = str_replace("minecraft:flowing_", "minecraft:", str_contains($name, ":") ? $name : "minecraft:$name");
			$states = is_array($want) && is_array($want["states"] ?? null) ? $want["states"] : [];
			$inside = false;
			foreach($blocks as $wire){
				if(str_replace("minecraft:flowing_", "minecraft:", $wire["type"]) !== $name){
					continue;
				}
				foreach($states as $state => $value){
					if(($wire["states"][$state] ?? null) !== $value){
						continue 2;
					}
				}
				$inside = true;
				break;
			}
			if($inside !== ($this->insideBlocks[$i] ?? false)){
				$this->insideBlocks[$i] = $inside;
				$this->mob->triggerEventDefinition($entry[$inside ? "entered_block_event" : "exited_block_event"] ?? null);
			}
		}
	}

	/** minecraft:block_sensor: called when a block breaks near the entity (by AddonRuntime). */
	public function blockBroken(string $type, Vector3 $at, ?Entity $breaker) : void{
		$sensor = $this->mob->getComponent("minecraft:block_sensor");
		if(!is_array($sensor) || $at->distanceSquared($this->mob->getPosition()) > ((float) ($sensor["sensor_radius"] ?? 16)) ** 2){
			return;
		}
		if(isset($sensor["sources"]) && ($breaker === null || !EntityFilter::test($sensor["sources"], new FilterContext($this->mob, $breaker), $this->mob->reporter()))){
			return;
		}
		foreach(self::entries($sensor["on_break"] ?? [], "on_break") as $entry){
			$list = is_array($entry["block_list"] ?? null) ? $entry["block_list"] : [];
			foreach($list as $name){
				if(is_string($name) && (str_contains($name, ":") ? $name : "minecraft:$name") === $type){
					$this->mob->triggerEventDefinition($entry["on_block_broken"] ?? null, $breaker);
					break;
				}
			}
		}
	}

	// ---------------------------------------------------------------- movement and body

	private function buoyant(mixed $component) : void{
		$world = $this->mob->getWorld();
		$pos = $this->mob->getPosition();
		$liquids = is_array($component) && is_array($component["liquid_blocks"] ?? null) ? $component["liquid_blocks"] : ["minecraft:water", "minecraft:flowing_water"];
		$block = $world->getBlockAt((int) floor($pos->x), (int) floor($pos->y + 0.3), (int) floor($pos->z));
		if(!$block instanceof Liquid || !in_array(ScriptHost::blockTypeId($block), $liquids, true) && !in_array(str_replace("flowing_", "", ScriptHost::blockTypeId($block)), $liquids, true)){
			return;
		}
		$lift = 0.04 * (is_array($component) && is_numeric($component["base_buoyancy"] ?? null) ? (float) $component["base_buoyancy"] : 1.0);
		$motion = $this->mob->getMotion();
		$this->mob->setMotion(new Vector3($motion->x, min(0.2, $motion->y + $lift + 0.08), $motion->z));
	}

	private function trail(mixed $component) : void{
		if(!is_array($component) || !EntityFilter::test($component["spawn_filter"] ?? null, new FilterContext($this->mob), $this->mob->reporter())){
			return;
		}
		$offset = is_array($component["spawn_offset"] ?? null) ? $component["spawn_offset"] : [0, 0, 0];
		$pos = $this->mob->getPosition();
		$x = (int) floor($pos->x + (float) ($offset[0] ?? 0));
		$y = (int) floor($pos->y + (float) ($offset[1] ?? 0));
		$z = (int) floor($pos->z + (float) ($offset[2] ?? 0));
		$world = $this->mob->getWorld();
		if(ScriptHost::blockTypeId($world->getBlockAt($x, $y, $z)) === "minecraft:air" && $world->getBlockAt($x, $y - 1, $z)->isSolid()){
			$type = (string) ($component["block_type"] ?? "minecraft:air");
			AddonManager::getInstance()?->getScriptHost()?->setBlock($world, $x, $y, $z, str_contains($type, ":") ? $type : "minecraft:$type", []);
		}
	}

	/** minecraft:teleport: random teleports (more likely in the dark or in daylight, as set) and teleports towards a far target. */
	private function teleport(mixed $component, int $now) : void{
		if(!is_array($component) || $now < $this->nextTeleport){
			return;
		}
		$this->nextTeleport = $now + 20;
		$target = $this->mob->getTargetEntity();
		if($target !== null && $target->getWorld() === $this->mob->getWorld() && $target->getPosition()->distance($this->mob->getPosition()) > (float) ($component["target_distance"] ?? 16)){
			if(EntityFilter::chance((float) ($component["target_teleport_chance"] ?? 1))){
				$this->teleportNear($target->getPosition(), 4);
			}
			return;
		}
		if(!(bool) ($component["random_teleports"] ?? true)){
			return;
		}
		$this->nextTeleport = $now + max(20, (int) (20 * self::seconds(null, (float) ($component["min_random_teleport_time"] ?? 0), (float) ($component["max_random_teleport_time"] ?? 20))));
		$light = $this->mob->getWorld()->getFullLight($this->mob->getPosition()->floor());
		if(EntityFilter::chance((float) ($component[$light >= 8 ? "light_teleport_chance" : "dark_teleport_chance"] ?? 0.01))){
			$cube = is_array($component["random_teleport_cube"] ?? null) ? $component["random_teleport_cube"] : [32, 32, 32];
			$this->teleportNear($this->mob->getPosition(), (int) ((float) ($cube[0] ?? 32) / 2), (int) ((float) ($cube[1] ?? 32) / 2));
		}
	}

	/** Teleports to a safe spot (ground below, room for the body) near a point. */
	public function teleportNear(Vector3 $around, int $xz, int $y = 8) : bool{
		$world = $this->mob->getWorld();
		$height = (int) max(1, (int) floor($this->mob->getSize()->getHeight()) + 1);
		for($attempt = 0; $attempt < 16; $attempt++){
			$x = (int) floor($around->x) + mt_rand(-$xz, $xz);
			$z = (int) floor($around->z) + mt_rand(-$xz, $xz);
			if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			for($ty = (int) floor($around->y) + $y; $ty >= (int) floor($around->y) - $y; $ty--){
				if(!$world->isInWorld($x, $ty, $z) || !$world->getBlockAt($x, $ty - 1, $z)->isSolid()){
					continue;
				}
				for($h = 0; $h < $height; $h++){
					$block = $world->getBlockAt($x, $ty + $h, $z);
					if($block->isSolid() || $block instanceof Liquid){
						continue 2;
					}
				}
				$from = $this->mob->getPosition();
				$this->mob->teleport(new Vector3($x + 0.5, $ty, $z + 0.5));
				$world->addSound($from, new EndermanTeleportSound());
				$world->addSound($this->mob->getPosition(), new EndermanTeleportSound());
				return true;
			}
		}
		return false;
	}

	private function push() : void{
		$box = $this->mob->getBoundingBox();
		$pos = $this->mob->getPosition();
		$pushX = 0.0;
		$pushZ = 0.0;
		foreach($this->mob->getWorld()->getNearbyEntities($box, $this->mob) as $other){
			if(!$other instanceof Living || !$other->isAlive() || AddonEntity::getVehicleOf($other) === $this->mob || AddonEntity::getVehicleOf($this->mob) === $other){
				continue;
			}
			$dx = $pos->x - $other->getPosition()->x;
			$dz = $pos->z - $other->getPosition()->z;
			$length = sqrt($dx * $dx + $dz * $dz);
			if($length < 0.01){
				$dx = AddonMath::randomFloat() - 0.5;
				$dz = AddonMath::randomFloat() - 0.5;
				$length = sqrt($dx * $dx + $dz * $dz);
			}
			$pushX += $dx / $length * 0.05;
			$pushZ += $dz / $length * 0.05;
		}
		if($pushX !== 0.0 || $pushZ !== 0.0){
			$motion = $this->mob->getMotion();
			$this->mob->setMotion(new Vector3($motion->x + $pushX, $motion->y, $motion->z + $pushZ));
		}
	}

	// ---------------------------------------------------------------- hooks from the entity

	/** Called after the entity lands a melee hit (minecraft:attack_cooldown). */
	public function attacked() : void{
		$cooldown = $this->mob->getComponent("minecraft:attack_cooldown");
		if(is_array($cooldown)){
			$this->attackCooldownUntil = $this->now() + (int) (20 * self::seconds($cooldown["attack_cooldown_time"] ?? 1, 1, 1));
		}
	}

	/** minecraft:mob_effect_immunity */
	public function isImmuneTo(Effect $effect) : bool{
		$immunity = $this->mob->getComponent("minecraft:mob_effect_immunity");
		foreach(is_array($immunity) && is_array($immunity["mob_effects"] ?? null) ? $immunity["mob_effects"] : [] as $name){
			if(is_string($name) && StringToEffectParser::getInstance()->parse($name) === $effect){
				return true;
			}
		}
		return false;
	}

	/** Where minecraft:home put the entity's home, if it has one. */
	public function getHome() : ?Vector3{ return $this->home; }

	/** The home restriction radius for random movement, when minecraft:home sets one. */
	public function homeRestriction() : ?float{
		$home = $this->mob->getComponent("minecraft:home");
		if($this->home === null || !is_array($home) || (float) ($home["restriction_radius"] ?? -1) <= 0 || ($home["restriction_type"] ?? "none") === "none"){
			return null;
		}
		return (float) $home["restriction_radius"];
	}

	// ---------------------------------------------------------------- tick

	public function tick(int $now) : void{
		$c = $this->mob->getComponents();
		$slot = $this->mob->getId();
		if(isset($c["minecraft:ambient_sound_interval"])){
			$this->ambient($c["minecraft:ambient_sound_interval"], $now);
		}
		if($now % 4 === $slot % 4){
			if(is_array($c["minecraft:entity_sensor"] ?? null)){
				$this->entitySensor($c["minecraft:entity_sensor"], $now);
			}
			if(is_array($c["minecraft:target_nearby_sensor"] ?? null)){
				$this->targetSensor($c["minecraft:target_nearby_sensor"]);
			}
		}
		if($this->angryUntil !== null && $now >= $this->angryUntil){
			$this->calmDown($c["minecraft:angry"] ?? null);
		}
		if(isset($c["minecraft:spawn_entity"])){
			$this->spawnEntities($c["minecraft:spawn_entity"], $now);
		}
		if(isset($c["minecraft:scheduler"])){
			$this->schedule($c["minecraft:scheduler"], $now);
		}
		if($now % 2 === $slot % 2){
			if(isset($c["minecraft:inside_block_notifier"])){
				$this->insideBlocks($c["minecraft:inside_block_notifier"]);
			}
			if(isset($c["minecraft:trail"])){
				$this->trail($c["minecraft:trail"]);
			}
			$pushable = $c["minecraft:pushable"] ?? null;
			if((is_array($pushable) && (bool) ($pushable["is_pushable"] ?? true)) || isset($c["minecraft:pushable_by_entity"])){
				$this->push();
			}
		}
		$dot = $c["minecraft:damage_over_time"] ?? null;
		if(is_array($dot) && $now >= $this->nextDamageOverTime){
			$this->nextDamageOverTime = $now + max(1, (int) ((float) ($dot["time_between_hurt"] ?? 0) * 20));
			$this->mob->attack(new EntityDamageEvent($this->mob, EntityDamageEvent::CAUSE_CUSTOM, (float) ($dot["damage_per_hurt"] ?? 1)));
		}
		if($this->attackCooldownUntil !== null && $now >= $this->attackCooldownUntil){
			$this->attackCooldownUntil = null;
			$cooldown = $c["minecraft:attack_cooldown"] ?? null;
			$this->mob->triggerEventDefinition(is_array($cooldown) ? ($cooldown["attack_cooldown_complete_event"] ?? null) : null);
		}
		if(isset($c["minecraft:buoyant"])){
			$this->buoyant($c["minecraft:buoyant"]);
		}
		if(isset($c["minecraft:teleport"])){
			$this->teleport($c["minecraft:teleport"], $now);
		}
		//minecraft:follow_range: a target further away than this is given up
		if($now % 20 === $slot % 20 && isset($c["minecraft:follow_range"])){
			$range = is_array($c["minecraft:follow_range"]) ? (float) ($c["minecraft:follow_range"]["value"] ?? 16) : (float) $c["minecraft:follow_range"];
			$target = $this->mob->getTargetEntity();
			if($target !== null && ($target->getWorld() !== $this->mob->getWorld() || $target->getPosition()->distanceSquared($this->mob->getPosition()) > $range * $range)){
				$this->mob->setTargetEntity(null);
			}
		}
	}
}
