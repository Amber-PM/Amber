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

namespace pocketmine\addon\script;

use pocketmine\addon\AddonManager;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\world\AddonWorldRules;
use pocketmine\camera\options\CameraEaseType;
use pocketmine\camera\options\CameraFadeOptions;
use pocketmine\camera\options\CameraSetOptions;
use pocketmine\color\Color;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\CameraShakePacket;
use pocketmine\network\mcpe\protocol\PlayerFogPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\SetHudPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\network\mcpe\protocol\types\hud\HudElement;
use pocketmine\network\mcpe\protocol\types\hud\HudVisibility;
use pocketmine\network\mcpe\protocol\UpdateClientInputLocksPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use function array_filter;
use function array_map;
use function array_reverse;
use function array_shift;
use function array_slice;
use function array_splice;
use function array_values;
use function cos;
use function count;
use function deg2rad;
use function explode;
use function file;
use function floor;
use function implode;
use function in_array;
use function intdiv;
use function is_bool;
use function is_file;
use function is_numeric;
use function json_decode;
use function ltrim;
use function max;
use function min;
use function mt_rand;
use function preg_match;
use function preg_match_all;
use function range;
use function shuffle;
use function sin;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function usort;
use const PREG_SET_ORDER;

/**
 * Runs Bedrock commands for add-ons (event queue_command, scripts' runCommand, mcfunction files).
 *
 * The commands add-ons rely on that PocketMine does not have, or has only for players, are implemented here
 * with full selector support (@s @p @a @r @e and their arguments, execute as/at/positioned/if/unless/run).
 * Everything else goes to the server's command map with selectors expanded to player names - so plugin
 * commands work from add-ons, which is how packs and plugins share one command space.
 */
final class CommandBridge{
	private const INPUT_LOCKS = ["camera" => 2, "movement" => 4, "lateral_movement" => 16, "sneak" => 32, "jump" => 64, "mount" => 128, "dismount" => 256, "move_forward" => 512, "move_backward" => 1024, "move_left" => 2048, "move_right" => 4096];
	/** Commands accepted and ignored, because there is nothing for them to do on this server. */
	private const NOOP = ["music", "mobevent", "reload", "dialogue", "ride", "aimassist", "controlscheme"];

	/** Vanilla commands this bridge provides to players and operators (where no plugin has the name). */
	public const PROVIDED = [
		"camera" => "Controls a player's camera", "camerashake" => "Shakes a player's camera", "damage" => "Damages entities",
		"event" => "Runs an add-on entity event", "execute" => "Runs a command as or at entities", "fill" => "Fills a region with a block",
		"fog" => "Adds or removes fog settings", "hud" => "Hides or shows HUD elements", "function" => "Runs a function file", "gamerule" => "Shows or changes game rules",
		"inputpermission" => "Locks player input", "particle" => "Spawns a particle", "playanimation" => "Plays an entity animation",
		"playsound" => "Plays a sound", "replaceitem" => "Replaces items in entity slots", "scoreboard" => "Manages scoreboards",
		"setblock" => "Changes a block", "stopsound" => "Stops sounds", "structure" => "Loads structures from add-ons", "summon" => "Summons an entity",
		"tag" => "Manages entity tags", "tellraw" => "Sends a JSON message", "testfor" => "Counts entities matching a selector",
		"tickingarea" => "Keeps areas of the world loaded", "weather" => "Sets the weather",
	];

	/** @var \WeakMap<Player, list<array{string, string}>> active fog layers: [user id, fog id] */
	private \WeakMap $fogs;

	/** @var \WeakMap<Player, int> */
	private \WeakMap $inputLocks;
	/** @var array<string, true> */
	private array $reported = [];
	private int $depth = 0;

	public function __construct(private Server $server, private AddonManager $manager){
		$this->inputLocks = new \WeakMap();
		$this->fogs = new \WeakMap();
	}

	/**
	 * Runs a command as an entity (or the server when null), at a position in a world.
	 *
	 * @return int the success count, as the game reports it
	 */
	public function run(string $command, ?Entity $executor, ?World $world = null, ?Vector3 $origin = null, ?float $yaw = null, ?float $pitch = null, ?CommandOutput $output = null) : int{
		$command = trim($command);
		if($command === "" || str_starts_with($command, "#")){
			return 0;
		}
		if($command[0] === "/"){
			$command = substr($command, 1);
		}
		if($this->depth > 16){
			return 0;
		}
		$world ??= $executor?->getWorld() ?? $this->server->getWorldManager()->getDefaultWorld();
		if($world === null){
			return 0;
		}
		$origin ??= $executor?->getPosition()->asVector3() ?? $world->getSpawnLocation()->asVector3();
		$yaw ??= $executor?->getLocation()->yaw ?? 0.0;
		$pitch ??= $executor?->getLocation()->pitch ?? 0.0;
		$context = new CommandContext($executor, $world, $origin, $yaw, $pitch, $output);

		$this->depth++;
		try{
			return $this->dispatch($command, $context);
		}catch(\Throwable $e){
			$this->server->getLogger()->debug("[Addons] command \"$command\" failed: " . $e->getMessage());
			return 0;
		}finally{
			$this->depth--;
		}
	}

	private function dispatch(string $command, CommandContext $ctx) : int{
		$args = self::tokenize($command);
		$name = strtolower((string) array_shift($args));
		switch($name){
			case "execute": return $this->execute($args, $ctx);
			case "function": return $this->runFunction($args[0] ?? "", $ctx);
			case "tag": return $this->tag($args, $ctx);
			case "kill": return $this->each($args[0] ?? "@s", $ctx, static function(Entity $e) : bool{ $e->kill(); return true; });
			case "tp":
			case "teleport": return $this->teleport($args, $ctx);
			case "effect": return $this->effect($args, $ctx);
			case "damage": return $this->damage($args, $ctx);
			case "event": return $this->event($args, $ctx);
			case "summon": return $this->summon($args, $ctx);
			case "setblock": return $this->setblock($args, $ctx);
			case "fill": return $this->fill($args, $ctx);
			case "particle": return $this->particle($args, $ctx);
			case "playsound": return $this->playsound($args, $ctx);
			case "stopsound": return $this->each($args[0] ?? "@s", $ctx, static function(Entity $e) use ($args) : bool{
				if($e instanceof Player){
					$e->getNetworkSession()->sendDataPacket(StopSoundPacket::create($args[1] ?? "", !isset($args[1]), false));
				}
				return $e instanceof Player;
			});
			case "camerashake": return $this->camerashake($args, $ctx);
			case "inputpermission": return $this->inputpermission($args, $ctx);
			case "playanimation": return $this->playanimation($args, $ctx);
			case "tellraw": return $this->tellraw($args, $ctx);
			case "testfor": return count($this->select($args[0] ?? "@s", $ctx));
			case "replaceitem": return $this->replaceitem($args, $ctx);
			case "camera": return $this->camera($args, $ctx);
			case "fog": return $this->fog($args, $ctx);
			case "weather": return $this->weather($args, $ctx);
			case "gamerule": return $this->gamerule($args, $ctx);
			case "tickingarea": return $this->tickingarea($args, $ctx);
			case "scoreboard": return $this->scoreboard($args, $ctx);
			case "structure": return $this->structure($args, $ctx);
			case "hud": return $this->hud($args, $ctx);
		}
		if(in_array($name, self::NOOP, true)){
			$this->reportOnce($name, "/$name is not supported by this server and does nothing");
			return 1;
		}
		return $this->passThrough($name, $args, $ctx);
	}

	/**
	 * Any other command: through the command map, once per combination of selected players.
	 *
	 * @param list<string> $args
	 */
	private function passThrough(string $name, array $args, CommandContext $ctx) : int{
		$lines = [[]];
		foreach($args as $arg){
			if(str_starts_with($arg, "@")){
				$names = array_map(static fn(Player $p) : string => self::quote($p->getName()), array_values(array_filter($this->select($arg, $ctx), static fn(Entity $e) : bool => $e instanceof Player)));
				if($names === []){
					return 0;
				}
				$expanded = [];
				foreach($lines as $line){
					foreach($names as $n){
						$expanded[] = [...$line, $n];
					}
				}
				$lines = $expanded;
			}else{
				$lines = array_map(static fn(array $line) : array => [...$line, $arg], $lines);
			}
		}
		$sender = new ConsoleCommandSender($this->server, $this->server->getLanguage());
		$success = 0;
		foreach($lines as $line){
			if($this->server->getCommandMap()->getCommand($name) === null){
				$this->reportOnce($name, "/$name is not a command on this server");
				return 0;
			}
			if($this->server->dispatchCommand($sender, trim($name . " " . implode(" ", $line)))){
				$success++;
			}
		}
		return $success;
	}

	// ---------------------------------------------------------------- execute and functions

	/** @param list<string> $args */
	private function execute(array $args, CommandContext $ctx) : int{
		if(isset($args[0]) && str_starts_with($args[0], "@") && isset($args[1]) && self::isCoord($args[1])){
			//legacy syntax: execute <target> <x> <y> <z> <command>
			$selector = array_shift($args);
			$pos = array_splice($args, 0, 3);
			$success = 0;
			foreach($this->select($selector, $ctx) as $entity){
				$at = $ctx->as($entity)->at($entity);
				$success += $this->dispatch(implode(" ", $args), $at->positioned($this->position($pos, $at)));
			}
			return $success;
		}
		$contexts = [$ctx];
		while($args !== []){
			$sub = strtolower(array_shift($args));
			switch($sub){
				case "as":
					$selector = (string) array_shift($args);
					$next = [];
					foreach($contexts as $c){
						foreach($this->select($selector, $c) as $e){
							$next[] = $c->as($e);
						}
					}
					$contexts = $next;
					break;
				case "at":
					$selector = (string) array_shift($args);
					$next = [];
					foreach($contexts as $c){
						foreach($this->select($selector, $c) as $e){
							$next[] = $c->at($e);
						}
					}
					$contexts = $next;
					break;
				case "positioned":
					if(($args[0] ?? "") === "as"){
						array_shift($args);
						$selector = (string) array_shift($args);
						$next = [];
						foreach($contexts as $c){
							foreach($this->select($selector, $c) as $e){
								$next[] = $c->positioned($e->getPosition()->asVector3());
							}
						}
						$contexts = $next;
					}else{
						$pos = array_splice($args, 0, 3);
						$contexts = array_map(fn(CommandContext $c) : CommandContext => $c->positioned($this->position($pos, $c)), $contexts);
					}
					break;
				case "rotated":
					$yaw = (string) array_shift($args);
					$pitch = (string) array_shift($args);
					$contexts = array_map(static fn(CommandContext $c) : CommandContext => $c->rotated(self::relative($yaw, $c->yaw), self::relative($pitch, $c->pitch)), $contexts);
					break;
				case "in":
					$dimension = (string) array_shift($args);
					$world = $this->manager->getScriptHost()?->worldFor($dimension);
					if($world !== null){
						$contexts = array_map(static fn(CommandContext $c) : CommandContext => $c->in($world), $contexts);
					}
					break;
				case "align":
				case "anchored":
				case "facing":
					array_shift($args);
					if($sub === "facing" && ($args[0] ?? "") !== "" && self::isCoord((string) $args[0])){
						array_splice($args, 0, 2);
					}
					break;
				case "if":
				case "unless":
					$kind = strtolower((string) array_shift($args));
					$next = [];
					foreach($contexts as $c){
						$ok = match($kind){
							"entity" => $this->select((string) ($args[0] ?? "@s"), $c) !== [],
							"block" => $this->blockIs(array_slice($args, 0, 4), $c),
							default => true,
						};
						if($ok === ($sub === "if")){
							$next[] = $c;
						}
					}
					array_splice($args, 0, $kind === "block" ? 4 : 1);
					$contexts = $next;
					break;
				case "run":
					$rest = implode(" ", $args);
					$success = 0;
					foreach($contexts as $c){
						$success += $this->dispatch($rest, $c);
					}
					return $success;
				default:
					return 0;
			}
		}
		return count($contexts);
	}

	/** @param list<string> $args x y z block */
	private function blockIs(array $args, CommandContext $ctx) : bool{
		if(count($args) < 4){
			return false;
		}
		$pos = $this->position(array_slice($args, 0, 3), $ctx);
		$block = $ctx->world->getBlockAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z));
		return ScriptHost::blockTypeId($block) === self::ns($args[3]);
	}

	private function runFunction(string $name, CommandContext $ctx) : int{
		$file = $this->manager->findFunction($name);
		if($file === null || !is_file($file)){
			$this->reportOnce("function:$name", "function $name does not exist");
			return 0;
		}
		$success = 0;
		foreach(file($file) ?: [] as $line){
			$line = trim($line);
			if($line !== "" && !str_starts_with($line, "#")){
				$success += $this->dispatch($line, $ctx) > 0 ? 1 : 0;
			}
		}
		return $success;
	}

	// ---------------------------------------------------------------- emulated commands

	/** @param list<string> $args */
	private function tag(array $args, CommandContext $ctx) : int{
		$action = strtolower($args[1] ?? "");
		$tag = $args[2] ?? "";
		$host = $this->manager->getScriptHost();
		return $this->each($args[0] ?? "@s", $ctx, function(Entity $e) use ($action, $tag, $host) : bool{
			return match($action){
				"add" => ScriptHost::addTag($e, $tag, $host),
				"remove" => ScriptHost::removeTag($e, $tag, $host),
				default => true,
			};
		});
	}

	/** @param list<string> $args */
	private function teleport(array $args, CommandContext $ctx) : int{
		if($args === []){
			return 0;
		}
		//tp <destination> | tp <x y z> | tp <targets> <destination> | tp <targets> <x y z> [yaw pitch | facing ...]
		if(self::isCoord($args[0])){
			$targets = $ctx->executor === null ? [] : [$ctx->executor];
			$rest = $args;
		}elseif(count($args) === 1){
			$targets = $ctx->executor === null ? [] : [$ctx->executor];
			$rest = [$args[0]];
		}else{
			$targets = $this->select($args[0], $ctx);
			$rest = array_slice($args, 1);
		}
		$success = 0;
		foreach($targets as $target){
			if(isset($rest[0]) && str_starts_with($rest[0], "@")){
				$destination = $this->select($rest[0], $ctx)[0] ?? null;
				if($destination !== null && $target->teleport($destination->getLocation())){
					$success++;
				}
				continue;
			}
			$at = $ctx->as($target);
			$pos = $this->position(array_slice($rest, 0, 3), $ctx);
			$yaw = isset($rest[3]) && ($rest[3] ?? "") !== "facing" ? self::relative($rest[3], $target->getLocation()->yaw) : null;
			$pitch = isset($rest[4]) && $yaw !== null ? self::relative($rest[4], $target->getLocation()->pitch) : null;
			if($target->teleport(Location::fromObject($pos, $at->world, $yaw ?? $target->getLocation()->yaw, $pitch ?? $target->getLocation()->pitch))){
				$success++;
			}
		}
		return $success;
	}

	/** @param list<string> $args */
	private function effect(array $args, CommandContext $ctx) : int{
		$targets = $args[0] ?? "@s";
		if(strtolower($args[1] ?? "") === "clear"){
			return $this->each($targets, $ctx, static function(Entity $e) use ($args) : bool{
				if(!$e instanceof Living){
					return false;
				}
				if(isset($args[2]) && ($type = StringToEffectParser::getInstance()->parse(self::bare($args[2]))) !== null){
					$e->getEffects()->remove($type);
				}else{
					$e->getEffects()->clear();
				}
				return true;
			});
		}
		$type = StringToEffectParser::getInstance()->parse(self::bare($args[1] ?? ""));
		if($type === null){
			return 0;
		}
		$seconds = strtolower($args[2] ?? "30") === "infinite" ? -1 : (int) ($args[2] ?? 30);
		$amplifier = (int) ($args[3] ?? 0);
		$visible = strtolower($args[4] ?? "false") !== "true";
		return $this->each($targets, $ctx, static function(Entity $e) use ($type, $seconds, $amplifier, $visible) : bool{
			if(!$e instanceof Living){
				return false;
			}
			if($seconds === 0){
				$e->getEffects()->remove($type);
				return true;
			}
			return $e->getEffects()->add(new EffectInstance($type, $seconds < 0 ? 2147483647 : $seconds * 20, max(0, min(255, $amplifier)), $visible));
		});
	}

	/** @param list<string> $args */
	private function damage(array $args, CommandContext $ctx) : int{
		$amount = (float) ($args[1] ?? 1);
		$cause = EntityFilter::DAMAGE_CAUSES[strtolower($args[2] ?? "override")] ?? EntityDamageEvent::CAUSE_CUSTOM;
		return $this->each($args[0] ?? "@s", $ctx, static function(Entity $e) use ($amount, $cause) : bool{
			$event = new EntityDamageEvent($e, $cause < 0 ? EntityDamageEvent::CAUSE_CUSTOM : $cause, $amount);
			$e->attack($event);
			return !$event->isCancelled();
		});
	}

	/** @param list<string> $args event entity <targets> <event> */
	private function event(array $args, CommandContext $ctx) : int{
		if(strtolower($args[0] ?? "") !== "entity"){
			return 0;
		}
		$name = $args[2] ?? "";
		return $this->each($args[1] ?? "@s", $ctx, static fn(Entity $e) : bool => $e instanceof AddonEntity && $e->triggerEvent($name));
	}

	/** @param list<string> $args summon <type> [x y z] [yaw pitch] [event] [name] */
	private function summon(array $args, CommandContext $ctx) : int{
		$type = self::ns($args[0] ?? "");
		$pos = isset($args[1]) && self::isCoord($args[1]) ? $this->position(array_slice($args, 1, 3), $ctx) : $ctx->origin;
		$rest = isset($args[1]) && self::isCoord($args[1]) ? array_slice($args, 4) : array_slice($args, 1);
		$yaw = isset($rest[0]) && self::isCoord($rest[0]) ? self::relative($rest[0], $ctx->yaw) : $ctx->yaw;
		if(isset($rest[0]) && self::isCoord($rest[0])){
			$rest = array_slice($rest, 2);
		}
		$entity = $this->manager->getScriptHost()?->spawnEntity($type, Location::fromObject($pos, $ctx->world, $yaw, 0.0), $rest[0] ?? null);
		if($entity === null){
			return 0;
		}
		if(isset($rest[1])){
			$entity->setNameTag($rest[1]);
		}
		return 1;
	}

	/** @param list<string> $args setblock x y z block [states] [mode] */
	private function setblock(array $args, CommandContext $ctx) : int{
		$pos = $this->position(array_slice($args, 0, 3), $ctx);
		[$type, $states] = self::blockSpec(array_slice($args, 3));
		$host = $this->manager->getScriptHost();
		if($host === null){
			return 0;
		}
		$mode = strtolower($args[count($args) - 1] ?? "");
		$x = (int) floor($pos->x);
		$y = (int) floor($pos->y);
		$z = (int) floor($pos->z);
		if($mode === "keep" && ScriptHost::blockTypeId($ctx->world->getBlockAt($x, $y, $z)) !== "minecraft:air"){
			return 0;
		}
		if($mode === "destroy"){
			$ctx->world->useBreakOn(new Vector3($x, $y, $z));
		}
		return $host->setBlock($ctx->world, $x, $y, $z, $type, $states) ? 1 : 0;
	}

	/** @param list<string> $args fill x1 y1 z1 x2 y2 z2 block [states] [mode] [replaceBlock] */
	private function fill(array $args, CommandContext $ctx) : int{
		$a = $this->position(array_slice($args, 0, 3), $ctx);
		$b = $this->position(array_slice($args, 3, 3), $ctx);
		[$type, $states] = self::blockSpec(array_slice($args, 6));
		$host = $this->manager->getScriptHost();
		return $host?->fill($ctx->world, $a, $b, $type, $states) ?? 0;
	}

	/**
	 * @param list<string> $args block [states-json] ...
	 * @return array{string, array<string, mixed>}
	 */
	private static function blockSpec(array $args) : array{
		$type = self::ns($args[0] ?? "air");
		$states = [];
		$raw = $args[1] ?? "";
		if(str_starts_with($raw, "[")){
			//["facing_direction"=1, "open_bit"=true] or ["x":1]
			preg_match_all('/"([^"]+)"\s*[=:]\s*("([^"]*)"|true|false|-?\d+)/', $raw, $m, PREG_SET_ORDER);
			foreach($m as $match){
				$value = $match[2];
				$states[$match[1]] = match(true){
					str_starts_with($value, "\"") => $match[3] ?? "",
					$value === "true" => true,
					$value === "false" => false,
					default => (int) $value,
				};
			}
		}
		return [$type, $states];
	}

	/** @param list<string> $args particle <name> [x y z] */
	private function particle(array $args, CommandContext $ctx) : int{
		$pos = isset($args[1]) ? $this->position(array_slice($args, 1, 3), $ctx) : $ctx->origin;
		$this->manager->getScriptHost()?->spawnParticle($ctx->world, (string) ($args[0] ?? ""), $pos, null, null);
		return 1;
	}

	/** @param list<string> $args playsound <sound> [targets] [x y z] [volume] [pitch] */
	private function playsound(array $args, CommandContext $ctx) : int{
		$sound = $args[0] ?? "";
		$targets = isset($args[1]) ? $this->select($args[1], $ctx) : $ctx->world->getPlayers();
		$pos = isset($args[2]) ? $this->position(array_slice($args, 2, 3), $ctx) : null;
		$volume = (float) ($args[5] ?? 1);
		$pitch = (float) ($args[6] ?? 1);
		$success = 0;
		foreach($targets as $player){
			if($player instanceof Player){
				$at = $pos ?? $player->getPosition();
				$player->getNetworkSession()->sendDataPacket(PlaySoundPacket::create($sound, $at->x, $at->y, $at->z, $volume, $pitch, 0, null));
				$success++;
			}
		}
		return $success;
	}

	/** @param list<string> $args camerashake add <targets> [intensity] [seconds] [type] | camerashake stop [targets] */
	private function camerashake(array $args, CommandContext $ctx) : int{
		$action = strtolower($args[0] ?? "add");
		$intensity = (float) ($args[2] ?? 0.5);
		$seconds = (float) ($args[3] ?? 1);
		$type = strtolower($args[4] ?? "positional") === "rotational" ? CameraShakePacket::TYPE_ROTATIONAL : CameraShakePacket::TYPE_POSITIONAL;
		$packet = $action === "stop"
			? CameraShakePacket::create(0, 0, CameraShakePacket::TYPE_POSITIONAL, CameraShakePacket::ACTION_STOP)
			: CameraShakePacket::create(max(0.0, min(4.0, $intensity)), $seconds, $type, CameraShakePacket::ACTION_ADD);
		return $this->each($args[1] ?? "@s", $ctx, static function(Entity $e) use ($packet) : bool{
			if($e instanceof Player){
				$e->getNetworkSession()->sendDataPacket($packet);
				return true;
			}
			return false;
		});
	}

	/** @param list<string> $args inputpermission set <targets> <permission> enabled|disabled */
	private function inputpermission(array $args, CommandContext $ctx) : int{
		if(strtolower($args[0] ?? "") !== "set"){
			return 1;
		}
		$permission = strtolower($args[2] ?? "");
		$enabled = strtolower($args[3] ?? "enabled") === "enabled";
		return $this->each($args[1] ?? "@s", $ctx, fn(Entity $e) : bool => $e instanceof Player && $this->setInputPermission($e, $permission, $enabled));
	}

	public function setInputPermission(Player $player, string $permission, bool $enabled) : bool{
		$flag = self::INPUT_LOCKS[strtolower($permission)] ?? (is_numeric($permission) ? 1 << (int) $permission : null);
		if($flag === null){
			return false;
		}
		$locks = $this->inputLocks[$player] ?? 0;
		$locks = $enabled ? $locks & ~$flag : $locks | $flag;
		$this->inputLocks[$player] = $locks;
		$player->getNetworkSession()->sendDataPacket(UpdateClientInputLocksPacket::create($locks, $player->getPosition()));
		if($permission === "movement"){
			$player->setNoClientPredictions(!$enabled);
		}
		return true;
	}

	/** @param list<string> $args playanimation <targets> <animation> [next_state] [blend_out] [stop_expression] [controller] */
	private function playanimation(array $args, CommandContext $ctx) : int{
		$targets = $this->select($args[0] ?? "@s", $ctx);
		if($targets === []){
			return 0;
		}
		$packet = AnimateEntityPacket::create($args[1] ?? "", $args[2] ?? "", $args[4] ?? "query.any_animation_finished", 0, $args[5] ?? "", (float) ($args[3] ?? 0), array_map(static fn(Entity $e) : int => $e->getId(), $targets));
		foreach($targets[0]->getWorld()->getPlayers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket($packet);
		}
		return count($targets);
	}

	/** @param list<string> $args tellraw <targets> <json> */
	private function tellraw(array $args, CommandContext $ctx) : int{
		$json = json_decode(implode(" ", array_slice($args, 1)), true);
		$text = ScriptHost::rawText($json);
		return $this->each($args[0] ?? "@a", $ctx, static function(Entity $e) use ($text) : bool{
			if($e instanceof Player){
				$e->sendMessage($text);
				return true;
			}
			return false;
		});
	}

	/** @param list<string> $args replaceitem entity <target> <slot> <index> <item> [amount] */
	private function replaceitem(array $args, CommandContext $ctx) : int{
		if(strtolower($args[0] ?? "") !== "entity"){
			return 0;
		}
		$slot = strtolower($args[2] ?? "");
		$index = (int) ($args[3] ?? 0);
		$item = StringToItemParser::getInstance()->parse(self::bare($args[4] ?? "air")) ?? $this->manager->getItem(self::ns($args[4] ?? ""));
		if($item === null){
			return 0;
		}
		$item->setCount(max(1, (int) ($args[5] ?? 1)));
		return $this->each($args[1] ?? "@s", $ctx, static function(Entity $e) use ($slot, $index, $item) : bool{
			if(!$e instanceof Living){
				return false;
			}
			$armor = $e->getArmorInventory();
			switch($slot){
				case "slot.armor.head": $armor->setHelmet(clone $item); return true;
				case "slot.armor.chest": $armor->setChestplate(clone $item); return true;
				case "slot.armor.legs": $armor->setLeggings(clone $item); return true;
				case "slot.armor.feet": $armor->setBoots(clone $item); return true;
			}
			if(!$e instanceof Player){
				return false;
			}
			return match($slot){
				"slot.weapon.mainhand" => (static function() use ($e, $item) : bool{ $e->getInventory()->setItemInHand(clone $item); return true; })(),
				"slot.weapon.offhand" => (static function() use ($e, $item) : bool{ $e->getOffHandInventory()->setItem(0, clone $item); return true; })(),
				"slot.hotbar" => (static function() use ($e, $item, $index) : bool{ $e->getInventory()->setItem(max(0, min(8, $index)), clone $item); return true; })(),
				"slot.inventory" => (static function() use ($e, $item, $index) : bool{ $e->getInventory()->setItem(max(0, min(35, $index + 9)), clone $item); return true; })(),
				"slot.enderchest" => (static function() use ($e, $item, $index) : bool{ $e->getEnderInventory()->setItem(max(0, min(26, $index)), clone $item); return true; })(),
				default => false,
			};
		});
	}

	// ---------------------------------------------------------------- camera, fog, weather, game rules, ticking areas

	/** @param list<string> $args camera <players> clear | fade [time <in> <hold> <out>] [color <r> <g> <b>] | set <preset> [ease <seconds> <type>] [pos <x y z>] [rot <x> <y>] [facing <target>|<x y z>] [default] */
	private function camera(array $args, CommandContext $ctx) : int{
		$targets = array_values(array_filter($this->select($args[0] ?? "@s", $ctx), static fn(Entity $e) : bool => $e instanceof Player));
		$action = strtolower($args[1] ?? "clear");
		$rest = array_slice($args, 2);
		$success = 0;
		foreach($targets as $player){
			/** @var Player $player */
			$camera = $player->getCamera();
			try{
				$ok = match($action){
					"clear" => $camera->clear(),
					"fade" => $camera->fade(self::fadeOptions($rest)),
					"set" => $camera->set(self::ns($rest[0] ?? "minecraft:free"), $this->cameraSetOptions(array_slice($rest, 1), $ctx->as($player))),
					default => false,
				};
			}catch(\InvalidArgumentException $e){
				$ctx->say(TextFormat::RED . $e->getMessage());
				$ok = false;
			}
			$success += $ok ? 1 : 0;
		}
		return $success;
	}

	/** @param list<string> $args */
	private static function fadeOptions(array $args) : CameraFadeOptions{
		$in = 0.5;
		$hold = 1.0;
		$out = 0.5;
		$color = new Color(0, 0, 0);
		for($i = 0; $i < count($args); ++$i){
			if(strtolower($args[$i]) === "time"){
				[$in, $hold, $out] = [(float) ($args[$i + 1] ?? $in), (float) ($args[$i + 2] ?? $hold), (float) ($args[$i + 3] ?? $out)];
				$i += 3;
			}elseif(strtolower($args[$i]) === "color"){
				$color = new Color((int) ($args[$i + 1] ?? 0), (int) ($args[$i + 2] ?? 0), (int) ($args[$i + 3] ?? 0));
				$i += 3;
			}
		}
		return new CameraFadeOptions($color, $in, $hold, $out);
	}

	/** @param list<string> $args */
	private function cameraSetOptions(array $args, CommandContext $ctx) : CameraSetOptions{
		$options = CameraSetOptions::create();
		for($i = 0; $i < count($args); ++$i){
			switch(strtolower($args[$i])){
				case "ease":
					$options->setEase(CameraEaseType::tryFrom(strtolower($args[$i + 2] ?? "linear")) ?? CameraEaseType::LINEAR, (float) ($args[$i + 1] ?? 1));
					$i += 2;
					break;
				case "pos":
					$options->setPosition($this->position(array_slice($args, $i + 1, 3), $ctx));
					$i += 3;
					break;
				case "rot":
					$options->setRotation((float) ($args[$i + 1] ?? 0), (float) ($args[$i + 2] ?? 0));
					$i += 2;
					break;
				case "facing":
					if(str_starts_with($args[$i + 1] ?? "", "@")){
						$target = $this->select($args[$i + 1], $ctx)[0] ?? null;
						$options->setFacingPosition($target?->getEyePos());
						$i += 1;
					}else{
						$options->setFacingPosition($this->position(array_slice($args, $i + 1, 3), $ctx));
						$i += 3;
					}
					break;
				case "default":
					$options->setDefault(true);
					break;
			}
		}
		return $options;
	}

	/** @param list<string> $args fog <players> push <fog id> <user id> | pop <user id> | remove <user id> */
	private function fog(array $args, CommandContext $ctx) : int{
		$mode = strtolower($args[1] ?? "");
		return $this->each($args[0] ?? "@s", $ctx, function(Entity $e) use ($mode, $args) : bool{
			if(!$e instanceof Player){
				return false;
			}
			$layers = $this->fogs[$e] ?? [];
			if($mode === "push"){
				$layers[] = [(string) ($args[3] ?? ""), self::ns($args[2] ?? "")];
			}elseif($mode === "pop"){
				for($i = count($layers) - 1; $i >= 0; --$i){
					if($layers[$i][0] === ($args[2] ?? "")){
						array_splice($layers, $i, 1);
						break;
					}
				}
			}elseif($mode === "remove"){
				$layers = array_values(array_filter($layers, static fn(array $l) : bool => $l[0] !== ($args[2] ?? "")));
			}else{
				return false;
			}
			$this->fogs[$e] = $layers;
			$e->getNetworkSession()->sendDataPacket(PlayerFogPacket::create(array_map(static fn(array $l) : string => $l[1], $layers)));
			return true;
		});
	}

	/** @param list<string> $args weather clear|rain|thunder [seconds] | weather query */
	private function weather(array $args, CommandContext $ctx) : int{
		$rules = $this->manager->getWorldRules();
		$type = strtolower($args[0] ?? "query");
		if($type === "query"){
			$ctx->say("Weather in " . $ctx->world->getFolderName() . ": " . $rules->getWeather($ctx->world));
			return 1;
		}
		if(!in_array($type, [AddonWorldRules::CLEAR, AddonWorldRules::RAIN, AddonWorldRules::THUNDER], true)){
			return 0;
		}
		$rules->setWeather($ctx->world, $type, isset($args[1]) ? (int) $args[1] * 20 : null);
		$ctx->say("Weather set to $type");
		return 1;
	}

	/** @param list<string> $args gamerule [rule [value]] */
	private function gamerule(array $args, CommandContext $ctx) : int{
		$rules = $this->manager->getWorldRules();
		if(!isset($args[0])){
			$list = [];
			foreach($rules->getRules() as $rule => $value){
				$list[] = "$rule = " . (is_bool($value) ? ($value ? "true" : "false") : $value);
			}
			$ctx->say(implode(", ", $list));
			return 1;
		}
		$rule = strtolower($args[0]);
		if(!isset($args[1])){
			$value = $rules->getRule($rule);
			if($value === null){
				$ctx->say(TextFormat::RED . "Unknown game rule $rule");
				return 0;
			}
			$ctx->say("$rule = " . (is_bool($value) ? ($value ? "true" : "false") : $value));
			return 1;
		}
		if(!$rules->setRule($rule, $args[1])){
			$ctx->say(TextFormat::RED . "Unknown game rule $rule");
			return 0;
		}
		$ctx->say("Game rule $rule is now " . $args[1]);
		return 1;
	}

	/** @param list<string> $args tickingarea add <from> <to> [name] | add circle <center> <radius> [name] | remove <name>|<x y z> | remove_all | list */
	private function tickingarea(array $args, CommandContext $ctx) : int{
		$areas = $this->manager->getTickingAreas();
		switch(strtolower($args[0] ?? "list")){
			case "add":
				if(strtolower($args[1] ?? "") === "circle"){
					$center = $this->position(array_slice($args, 2, 3), $ctx);
					$radius = max(1, min(4, (int) ($args[5] ?? 1))) * 16;
					$name = $args[6] ?? ("area" . count($areas->getAreas()));
					$error = $areas->add($ctx->world, $name, (int) floor($center->x) - $radius, (int) floor($center->z) - $radius, (int) floor($center->x) + $radius, (int) floor($center->z) + $radius);
				}else{
					$a = $this->position(array_slice($args, 1, 3), $ctx);
					$b = $this->position(array_slice($args, 4, 3), $ctx);
					$name = $args[7] ?? ("area" . count($areas->getAreas()));
					$error = $areas->add($ctx->world, $name, (int) floor($a->x), (int) floor($a->z), (int) floor($b->x), (int) floor($b->z));
				}
				$ctx->say($error === null ? "Added ticking area $name" : TextFormat::RED . $error);
				return $error === null ? 1 : 0;
			case "remove":
				$name = $args[1] ?? "";
				if(self::isCoord($name)){
					$pos = $this->position(array_slice($args, 1, 3), $ctx);
					foreach($areas->getAreas() as $areaName => $area){
						if($area["world"] === $ctx->world->getFolderName() && ((int) floor($pos->x) >> 4) >= $area["x1"] && ((int) floor($pos->x) >> 4) <= $area["x2"] && ((int) floor($pos->z) >> 4) >= $area["z1"] && ((int) floor($pos->z) >> 4) <= $area["z2"]){
							$name = $areaName;
						}
					}
				}
				$removed = $areas->remove($name);
				$ctx->say($removed ? "Removed ticking area $name" : TextFormat::RED . "No ticking area $name");
				return $removed ? 1 : 0;
			case "remove_all":
				$ctx->say("Removed " . $areas->removeAll() . " ticking area(s)");
				return 1;
			default:
				foreach($areas->getAreas() as $name => $area){
					$ctx->say("$name: " . $area["world"] . " chunks " . $area["x1"] . "," . $area["z1"] . " to " . $area["x2"] . "," . $area["z2"]);
				}
				if($areas->getAreas() === []){
					$ctx->say("No ticking areas");
				}
				return 1;
		}
	}

	/** @param list<string> $args hud <players> hide|reset [element] */
	private function hud(array $args, CommandContext $ctx) : int{
		$hide = strtolower($args[1] ?? "reset") === "hide";
		$names = ["paperdoll" => 0, "armor" => 1, "tooltips" => 2, "touch_controls" => 3, "crosshair" => 4, "hotbar" => 5, "health" => 6, "progress_bar" => 7, "hunger" => 8, "air_bubbles" => 9, "horse_health" => 10, "status_effects" => 11, "item_text" => 12];
		$element = strtolower($args[2] ?? "all");
		$elements = $element === "all" ? range(0, 12) : (isset($names[$element]) ? [$names[$element]] : []);
		return $this->each($args[0] ?? "@s", $ctx, static function(Entity $e) use ($hide, $elements) : bool{
			if(!$e instanceof Player){
				return false;
			}
			$cases = [];
			foreach($elements as $id){
				$case = HudElement::tryFrom($id);
				if($case !== null){
					$cases[] = $case;
				}
			}
			$e->getNetworkSession()->sendDataPacket(SetHudPacket::create($cases, $hide ? HudVisibility::HIDE : HudVisibility::RESET));
			return true;
		});
	}

	/** @param list<string> $args structure load <name> <x y z> */
	private function structure(array $args, CommandContext $ctx) : int{
		if(strtolower($args[0] ?? "") !== "load"){
			$ctx->say(TextFormat::RED . "Only /structure load is supported");
			return 0;
		}
		$structure = $this->manager->getStructures()->get($args[1] ?? "");
		if($structure === null){
			$ctx->say(TextFormat::RED . "Structure " . ($args[1] ?? "") . " not found");
			return 0;
		}
		$pos = isset($args[2]) ? $this->position(array_slice($args, 2, 3), $ctx) : $ctx->origin;
		$placed = $structure->place($ctx->world, (int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z), $this->manager);
		$ctx->say("Placed structure " . $args[1] . " ($placed blocks)");
		return 1;
	}

	// ---------------------------------------------------------------- scoreboard

	/** @param list<string> $args */
	private function scoreboard(array $args, CommandContext $ctx) : int{
		$board = $this->manager->getScoreboard();
		$section = strtolower($args[0] ?? "");
		$action = strtolower($args[1] ?? "");
		if($section === "objectives"){
			switch($action){
				case "add":
					$ok = $board->addObjective($args[2] ?? "", implode(" ", array_slice($args, 4)));
					$ctx->say($ok ? "Added objective " . ($args[2] ?? "") : TextFormat::RED . "Objective " . ($args[2] ?? "") . " already exists");
					return $ok ? 1 : 0;
				case "remove":
					return $board->removeObjective($args[2] ?? "") ? 1 : 0;
				case "setdisplay":
					$slot = strtolower($args[2] ?? "sidebar");
					$order = strtolower($args[4] ?? "descending") === "ascending" ? 0 : 1;
					return $board->setDisplay($slot, $args[3] ?? null, $order) ? 1 : 0;
				default:
					foreach($board->getObjectives() as $id => $name){
						$ctx->say("$id: $name");
					}
					return 1;
			}
		}
		if($section !== "players"){
			return 0;
		}
		$objective = $args[3] ?? "";
		switch($action){
			case "set":
			case "add":
			case "remove":
				$amount = (int) ($args[4] ?? 0);
				$count = 0;
				foreach($this->participants($args[2] ?? "", $ctx) as $participant){
					if($action === "set"){
						$count += $board->setScore($objective, $participant, $amount) ? 1 : 0;
					}else{
						$count += $board->addScore($objective, $participant, $action === "add" ? $amount : -$amount) !== null ? 1 : 0;
					}
				}
				return $count;
			case "random":
				$count = 0;
				foreach($this->participants($args[2] ?? "", $ctx) as $participant){
					$count += $board->setScore($objective, $participant, mt_rand((int) ($args[4] ?? 0), (int) ($args[5] ?? 0))) ? 1 : 0;
				}
				return $count;
			case "reset":
				$count = 0;
				foreach($this->participants($args[2] ?? "*", $ctx) as $participant){
					$count += $board->resetScore($args[3] ?? null, $participant) ? 1 : 0;
				}
				return $count;
			case "test":
				$min = ($args[4] ?? "*") === "*" ? \PHP_INT_MIN : (int) $args[4];
				$max = ($args[5] ?? "*") === "*" ? \PHP_INT_MAX : (int) $args[5];
				$count = 0;
				foreach($this->participants($args[2] ?? "", $ctx) as $participant){
					$score = $board->getScore($objective, $participant);
					if($score !== null && $score >= $min && $score <= $max){
						$count++;
					}
				}
				return $count;
			case "operation":
				//operation <targets> <objective> <op> <sources> <objective>
				$op = $args[4] ?? "=";
				$sources = $this->participants($args[5] ?? "", $ctx);
				$sourceObjective = $args[6] ?? $objective;
				$count = 0;
				foreach($this->participants($args[2] ?? "", $ctx) as $target){
					foreach($sources as $source){
						$a = $board->getScore($objective, $target) ?? 0;
						$b = $board->getScore($sourceObjective, $source) ?? 0;
						$result = match($op){
							"+=" => $a + $b, "-=" => $a - $b, "*=" => $a * $b, "/=" => $b === 0 ? $a : intdiv($a, $b), "%=" => $b === 0 ? $a : $a % $b,
							"<" => min($a, $b), ">" => max($a, $b), "><" => $b, default => $b,
						};
						if($op === "><"){
							$board->setScore($sourceObjective, $source, $a);
						}
						$count += $board->setScore($objective, $target, $result) ? 1 : 0;
					}
				}
				return $count;
			default:
				$who = isset($args[2]) ? $this->participants($args[2], $ctx) : $board->getParticipants();
				foreach($who as $participant){
					$line = [];
					foreach($board->getObjectives() as $id => $_){
						$score = $board->getScore($id, $participant);
						if($score !== null){
							$line[] = "$id: $score";
						}
					}
					if($line !== []){
						$ctx->say("$participant - " . implode(", ", $line));
					}
				}
				return 1;
		}
	}

	/**
	 * Scoreboard participants for a target: player names, "entity:<id>" for other entities, "*" for everyone
	 * already on the board, or the literal name (fake players).
	 *
	 * @return list<string>
	 */
	private function participants(string $target, CommandContext $ctx) : array{
		if($target === "*"){
			return $this->manager->getScoreboard()->getParticipants();
		}
		if(!str_starts_with($target, "@")){
			return [trim($target, "\"")];
		}
		return array_map(static fn(Entity $e) : string => $e instanceof Player ? $e->getName() : "entity:" . $e->getId(), $this->select($target, $ctx));
	}

	/**
	 * @param \Closure(Entity) : bool $action
	 */
	private function each(string $selector, CommandContext $ctx, \Closure $action) : int{
		$success = 0;
		foreach($this->select($selector, $ctx) as $entity){
			if($action($entity)){
				$success++;
			}
		}
		return $success;
	}

	// ---------------------------------------------------------------- selectors and positions

	/**
	 * Resolves a target: a selector (@s @p @a @r @e @initiator with arguments) or a player name.
	 *
	 * @return list<Entity>
	 */
	public function select(string $selector, CommandContext $ctx) : array{
		if(!str_starts_with($selector, "@")){
			$player = $this->server->getPlayerExact(trim($selector, "\""));
			return $player === null ? [] : [$player];
		}
		$kind = strtolower(substr($selector, 1, 1));
		$args = [];
		if(preg_match('/^@\w+\[(.*)\]$/s', $selector, $m) === 1){
			foreach(self::splitArgs($m[1]) as [$key, $value]){
				$args[strtolower($key)][] = $value;
			}
		}
		if($kind === "s"){
			$pool = $ctx->executor === null ? [] : [$ctx->executor];
		}elseif($kind === "e"){
			$pool = isset($args["r"]) || isset($args["dx"]) || isset($args["x"]) ? $ctx->world->getEntities() : $this->allEntities($ctx->world);
		}else{
			$pool = [];
			foreach($this->server->getOnlinePlayers() as $p){
				$pool[] = $p;
			}
		}
		$center = $ctx->origin;
		if(isset($args["x"]) || isset($args["y"]) || isset($args["z"])){
			$center = new Vector3(
				self::relative($args["x"][0] ?? "~", $ctx->origin->x),
				self::relative($args["y"][0] ?? "~", $ctx->origin->y),
				self::relative($args["z"][0] ?? "~", $ctx->origin->z)
			);
		}
		$out = [];
		foreach($pool as $entity){
			if($entity->isClosed() || (!$entity->isAlive() && $kind !== "s")){
				continue;
			}
			if($this->matches($entity, $args, $ctx, $center)){
				$out[] = $entity;
			}
		}
		if($kind === "p" || $kind === "r" || isset($args["c"])){
			usort($out, static fn(Entity $a, Entity $b) : int => $a->getPosition()->distanceSquared($center) <=> $b->getPosition()->distanceSquared($center));
			$limit = isset($args["c"]) ? (int) $args["c"][0] : 1;
			if($kind === "r"){
				shuffle($out);
			}
			if($limit < 0){
				$out = array_reverse($out);
				$limit = -$limit;
			}
			$out = array_slice($out, 0, $limit);
		}
		return $out;
	}

	/** @return list<Entity> */
	private function allEntities(World $world) : array{
		return array_values($world->getEntities());
	}

	/** @param array<string, list<string>> $args */
	private function matches(Entity $e, array $args, CommandContext $ctx, Vector3 $center) : bool{
		if((isset($args["r"]) || isset($args["rm"]) || isset($args["x"])) && $e->getWorld() !== $ctx->world){
			return false;
		}
		$d2 = $e->getPosition()->distanceSquared($center);
		if(isset($args["r"]) && $d2 > ((float) $args["r"][0]) ** 2){
			return false;
		}
		if(isset($args["rm"]) && $d2 < ((float) $args["rm"][0]) ** 2){
			return false;
		}
		if(isset($args["dx"]) || isset($args["dy"]) || isset($args["dz"])){
			$p = $e->getPosition();
			$dx = (float) ($args["dx"][0] ?? 0);
			$dy = (float) ($args["dy"][0] ?? 0);
			$dz = (float) ($args["dz"][0] ?? 0);
			if($p->x < min($center->x, $center->x + $dx) || $p->x > max($center->x, $center->x + $dx) + 1
				|| $p->y < min($center->y, $center->y + $dy) || $p->y > max($center->y, $center->y + $dy) + 1
				|| $p->z < min($center->z, $center->z + $dz) || $p->z > max($center->z, $center->z + $dz) + 1){
				return false;
			}
		}
		$type = ScriptHost::typeId($e);
		foreach($args["type"] ?? [] as $want){
			$not = str_starts_with($want, "!");
			if(($type === self::ns(ltrim($want, "!"))) === $not){
				return false;
			}
		}
		$families = EntityFilter::familiesOf($e);
		foreach($args["family"] ?? [] as $want){
			$not = str_starts_with($want, "!");
			if(in_array(ltrim($want, "!"), $families, true) === $not){
				return false;
			}
		}
		foreach($args["tag"] ?? [] as $want){
			$not = str_starts_with($want, "!");
			$tag = ltrim($want, "!");
			$has = $tag === "" ? ScriptHost::tagsOf($e, $this->manager->getScriptHost()) !== [] : in_array($tag, ScriptHost::tagsOf($e, $this->manager->getScriptHost()), true);
			if($has === $not){
				return false;
			}
		}
		foreach($args["name"] ?? [] as $want){
			$not = str_starts_with($want, "!");
			$name = trim(ltrim($want, "!"), "\"");
			$actual = $e instanceof Player ? $e->getName() : $e->getNameTag();
			if(($actual === $name) === $not){
				return false;
			}
		}
		foreach($args["m"] ?? [] as $want){
			if(!$e instanceof Player){
				return false;
			}
			$not = str_starts_with($want, "!");
			$mode = ScriptHost::gameModeName($e->getGamemode());
			$wantMode = match(strtolower(ltrim($want, "!"))){ "0", "s" => "survival", "1", "c" => "creative", "2", "a" => "adventure", "spectator", "6" => "spectator", default => strtolower(ltrim($want, "!")) };
			if(($mode === $wantMode) === $not){
				return false;
			}
		}
		return true;
	}

	/** @return list<array{string, string}> */
	private static function splitArgs(string $raw) : array{
		$out = [];
		$depth = 0;
		$current = "";
		$quoted = false;
		for($i = 0, $n = strlen($raw); $i < $n; ++$i){
			$c = $raw[$i];
			if($c === "\""){
				$quoted = !$quoted;
			}elseif(!$quoted && ($c === "{" || $c === "[")){
				$depth++;
			}elseif(!$quoted && ($c === "}" || $c === "]")){
				$depth--;
			}
			if($c === "," && $depth === 0 && !$quoted){
				$out[] = $current;
				$current = "";
				continue;
			}
			$current .= $c;
		}
		if(trim($current) !== ""){
			$out[] = $current;
		}
		$pairs = [];
		foreach($out as $part){
			$kv = explode("=", $part, 2);
			if(count($kv) === 2){
				$pairs[] = [trim($kv[0]), trim($kv[1])];
			}
		}
		return $pairs;
	}

	/** @param list<string> $coords */
	public function position(array $coords, CommandContext $ctx) : Vector3{
		$x = $coords[0] ?? "~";
		$y = $coords[1] ?? "~";
		$z = $coords[2] ?? "~";
		if(str_starts_with($x, "^")){
			//local coordinates: left, up, forward relative to the executor's rotation
			$left = (float) substr($x, 1);
			$up = (float) substr($y, 1);
			$forward = (float) substr($z, 1);
			$yaw = deg2rad($ctx->yaw);
			$pitch = deg2rad($ctx->pitch);
			$fx = -sin($yaw) * cos($pitch);
			$fy = -sin($pitch);
			$fz = cos($yaw) * cos($pitch);
			$lx = cos($yaw);
			$lz = sin($yaw);
			return new Vector3(
				$ctx->origin->x + $fx * $forward + $lx * $left,
				$ctx->origin->y + $fy * $forward + $up,
				$ctx->origin->z + $fz * $forward + $lz * $left
			);
		}
		return new Vector3(self::relative($x, $ctx->origin->x), self::relative($y, $ctx->origin->y), self::relative($z, $ctx->origin->z));
	}

	private static function relative(string $value, float $base) : float{
		if(str_starts_with($value, "~")){
			return $base + (float) (substr($value, 1) === "" ? 0 : substr($value, 1));
		}
		return (float) $value;
	}

	private static function isCoord(string $value) : bool{
		return $value !== "" && (is_numeric($value) || $value[0] === "~" || $value[0] === "^");
	}

	/**
	 * Splits a command line into arguments, keeping quoted strings, selectors with [...] and JSON {...} whole.
	 *
	 * @return list<string>
	 */
	public static function tokenize(string $command) : array{
		$out = [];
		$current = "";
		$depth = 0;
		$quoted = false;
		for($i = 0, $n = strlen($command); $i < $n; ++$i){
			$c = $command[$i];
			if($c === "\"" && ($i === 0 || $command[$i - 1] !== "\\")){
				$quoted = !$quoted;
				if($depth === 0){
					continue;
				}
			}elseif(!$quoted && ($c === "[" || $c === "{")){
				$depth++;
			}elseif(!$quoted && ($c === "]" || $c === "}")){
				$depth = max(0, $depth - 1);
			}
			if(($c === " " || $c === "\t") && !$quoted && $depth === 0){
				if($current !== ""){
					$out[] = $current;
					$current = "";
				}
				continue;
			}
			$current .= $c;
		}
		if($current !== ""){
			$out[] = $current;
		}
		return $out;
	}

	private static function ns(string $id) : string{
		$id = strtolower(trim($id, "\""));
		return str_contains($id, ":") ? $id : "minecraft:$id";
	}

	private static function bare(string $id) : string{
		return strtolower(str_replace("minecraft:", "", $id));
	}

	private static function quote(string $name) : string{
		return str_contains($name, " ") ? "\"$name\"" : $name;
	}

	private function reportOnce(string $key, string $message) : void{
		if(!isset($this->reported[$key])){
			$this->reported[$key] = true;
			$this->server->getLogger()->notice("[Addons] $message");
		}
	}
}
