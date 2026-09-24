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
use pocketmine\addon\AddonPack;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\EntityFilter;
use pocketmine\addon\event\AddonScriptEvent;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\EffectIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\Explosion;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\Position;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use function array_filter;
use function array_keys;
use function array_map;
use function array_reverse;
use function array_shift;
use function array_slice;
use function array_values;
use function atan2;
use function count;
use function explode;
use function fclose;
use function feof;
use function file_get_contents;
use function floor;
use function fread;
use function fwrite;
use function hrtime;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_dir;
use function is_file;
use function is_float;
use function is_int;
use function is_numeric;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function min;
use function mkdir;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function spl_object_id;
use function sqrt;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function stream_get_contents;
use function stream_select;
use function stream_set_blocking;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function usort;
use function version_compare;
use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const M_PI;

/**
 * Runs behavior pack scripts (@minecraft/server, @minecraft/server-ui) in a Node.js process beside the server.
 *
 * The server drives it: every tick the host gets the tick's events and runs the packs' scheduled callbacks;
 * cancellable events (before events) and custom component callbacks run synchronously when they happen, so
 * scripts can cancel them as in the game. Scripts call back into the server synchronously over the same pipe
 * (see resources/addon_scripts). Script time counts against a per-tick budget; a host that does not finish in
 * time is left to catch up without holding the server, and one stuck for 10 seconds is restarted.
 *
 * The same channel connects scripts with plugins:
 *  - exposeFunction() makes a PHP function callable from scripts (@amber/plugins plugins.call());
 *  - callScript() calls a function a script exposed (plugins.expose());
 *  - sendScriptEvent() and AddonScriptEvent carry script events both ways;
 *  - dynamic properties are shared: getDynamicProperty()/setDynamicProperty() see what scripts store.
 */
final class ScriptHost{
	private const SCRIPT_FILES = ["host.mjs", "ipc.mjs", "loader.mjs", "state.mjs", "server.mjs", "server-ui.mjs", "common.mjs", "math.mjs", "vanilla-data.mjs", "admin.mjs", "unsupported.mjs", "bridge.mjs"];
	private const DEFAULT_SCOPE = "world";

	/** @var resource|null */
	private $process = null;
	/** @var resource|null */
	private $stdin = null;
	/** @var resource|null */
	private $stdout = null;
	/** @var resource|null */
	private $stderr = null;
	private string $buffer = "";
	private string $errBuffer = "";

	/** @var list<array{string, mixed}> events for the next tick */
	private array $queue = [];
	/** @var array<string, true> "after:name" / "before:name" the scripts listen to */
	private array $subscriptions = [];
	/** @var array<string, true> item/block custom component names registered by scripts */
	private array $customComponents = [];
	/** @var array<string, \Closure> */
	private array $pluginFunctions = [];
	/** @var array<string, true> */
	private array $scriptFunctions = [];
	/** @var array<string, array{string, int}> custom slash commands registered by scripts: name => [description, permission] */
	private array $customCommands = [];

	private bool $busy = false;
	private int $busySince = 0;
	private bool $servicing = false;
	private int $restarts = 0;
	private bool $running = false;
	private int $lastTick = 0;

	/** @var array<string, array<string, mixed>> scope => key => value */
	private array $dynamic = [];
	private bool $dynamicDirty = false;
	/** @var \WeakMap<Entity, array<string, true>> tags of non-add-on entities */
	private \WeakMap $entityTags;
	/** @var \WeakMap<Entity, array<string, mixed>> dynamic properties of non-player, non-add-on entities */
	private \WeakMap $entityDynamic;
	/** @var array<string, int> world folder => dimension slot, built lazily */
	private array $dimensionIds = [];

	private CommandBridge $commands;

	/**
	 * @param list<AddonPack> $packs behavior packs with a script entry
	 */
	public function __construct(
		private Server $server,
		private AddonManager $manager,
		private array $packs,
		private string $runtimeDir,
		private string $nodeBinary,
		private int $tickBudgetMs,
		private int $memoryMb,
		private \Logger $logger
	){
		$this->entityTags = new \WeakMap();
		$this->entityDynamic = new \WeakMap();
		$this->commands = $manager->getCommandBridge();
		$this->loadDynamic();
	}

	public function getCommandBridge() : CommandBridge{ return $this->commands; }

	public function isRunning() : bool{ return $this->running; }

	// ---------------------------------------------------------------- process

	/** Starts the Node.js host and runs the packs' startup code. Returns false when scripts cannot run. */
	public function start() : bool{
		if($this->packs === [] || $this->running){
			return $this->running;
		}
		$version = $this->nodeVersion();
		if($version === null){
			$this->logger->warning("Add-on scripts are not run: Node.js was not found (install Node.js 22.15 or newer, or set scripting.node in addons/config.yml)");
			return false;
		}
		if(!self::versionAtLeast($version, "22.15.0")){
			$this->logger->warning("Add-on scripts are not run: Node.js $version is too old, 22.15 or newer is needed");
			return false;
		}
		$hostDir = Path::join($this->runtimeDir, "scripts");
		if(!$this->installHostFiles($hostDir)){
			return false;
		}

		$command = [$this->nodeBinary, "--permission", "--allow-fs-read=" . $hostDir . "/*"];
		foreach($this->packs as $pack){
			$command[] = "--allow-fs-read=" . Path::canonicalize($pack->getPath()) . "/*";
		}
		$command = [...$command, "--max-old-space-size=" . $this->memoryMb, "--disable-proto=delete", "--no-warnings", "--stack-size=4096", Path::join($hostDir, "host.mjs")];
		$pipes = [];
		$process = proc_open($command, [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, $hostDir);
		if(!is_resource($process)){
			$this->logger->error("Add-on scripts: could not start Node.js");
			return false;
		}
		$this->process = $process;
		[$this->stdin, $this->stdout, $this->stderr] = [$pipes[0], $pipes[1], $pipes[2]];
		stream_set_blocking($this->stdout, false);
		stream_set_blocking($this->stderr, false);
		$this->buffer = "";
		$this->running = true;
		$this->busy = false;

		$packs = [];
		foreach($this->packs as $pack){
			$entry = $pack->getScriptEntry();
			if($entry === null){
				continue;
			}
			$packs[] = ["name" => $pack->getName(), "root" => Path::canonicalize($pack->getPath()), "entry" => Path::canonicalize(Path::join($pack->getPath(), $entry)), "api" => $pack->getScriptApiMajor()];
		}
		$this->write(["t" => "init", "packs" => $packs, "dims" => $this->dimensionList(), "tick" => $this->server->getTick()]);
		//pack startup code may take a while (big packs import hundreds of modules)
		$done = $this->pumpUntil(["done"], 15000);
		$this->drainStderr();
		if($done === null){
			$this->logger->error("Add-on scripts: the script host did not finish starting within 15 seconds");
			$this->stop();
			return false;
		}
		$this->logger->info("Add-on scripts running for " . count($packs) . " pack(s) on Node.js $version");
		return true;
	}

	public function stop() : void{
		if($this->process === null){
			$this->saveDynamic();
			return;
		}
		try{
			if($this->running){
				$this->write(["t" => "stop"]);
			}
		}catch(\Throwable){
		}
		foreach([$this->stdin, $this->stdout, $this->stderr] as $pipe){
			if(is_resource($pipe)){
				fclose($pipe);
			}
		}
		$status = proc_get_status($this->process);
		if($status["running"]){
			proc_terminate($this->process);
		}
		proc_close($this->process);
		$this->process = $this->stdin = $this->stdout = $this->stderr = null;
		$this->running = false;
		$this->saveDynamic();
	}

	private function crashed(string $why) : void{
		$this->drainStderr();
		$this->logger->error("Add-on scripts: the script host stopped ($why)");
		$this->stop();
		if(++$this->restarts <= 3){
			$this->logger->info("Add-on scripts: restarting the script host (attempt {$this->restarts} of 3)");
			$this->subscriptions = [];
			$this->start();
		}else{
			$this->logger->error("Add-on scripts: giving up after 3 restarts; scripts are off until the server restarts");
		}
	}

	private function nodeVersion() : ?string{
		$pipes = [];
		$process = @proc_open([$this->nodeBinary, "--version"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
		if(!is_resource($process)){
			return null;
		}
		$out = trim((string) @stream_get_contents($pipes[1]));
		fclose($pipes[1]);
		fclose($pipes[2]);
		$code = proc_close($process);
		return $code === 0 && preg_match('/^v?(\d+\.\d+\.\d+)/', $out, $m) === 1 ? $m[1] : null;
	}

	private static function versionAtLeast(string $version, string $min) : bool{
		return version_compare($version, $min, ">=");
	}

	/** Copies the host's JavaScript out of the server (which may be a phar) where Node can read it. */
	private function installHostFiles(string $dir) : bool{
		if(!is_dir($dir) && !@mkdir($dir, 0777, true)){
			$this->logger->error("Add-on scripts: cannot create $dir");
			return false;
		}
		foreach(self::SCRIPT_FILES as $file){
			$source = Path::join(\pocketmine\RESOURCE_PATH, "addon_scripts", $file);
			$contents = @file_get_contents($source);
			if($contents === false){
				$this->logger->error("Add-on scripts: missing server resource addon_scripts/$file");
				return false;
			}
			$target = Path::join($dir, $file);
			if(@file_get_contents($target) !== $contents){
				Filesystem::safeFilePutContents($target, $contents);
			}
		}
		//a package.json so Node treats the host files as ES modules
		$package = Path::join($dir, "package.json");
		if(!is_file($package)){
			Filesystem::safeFilePutContents($package, "{\"type\":\"module\",\"private\":true}\n");
		}
		return true;
	}

	// ---------------------------------------------------------------- pipe

	/** @param mixed[] $message */
	private function write(array $message) : void{
		if($this->stdin === null){
			return;
		}
		$line = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
		$written = 0;
		$length = strlen($line);
		while($written < $length){
			$n = @fwrite($this->stdin, substr($line, $written));
			if($n === false || $n === 0){
				throw new \RuntimeException("script host pipe closed");
			}
			$written += $n;
		}
	}

	/**
	 * Reads messages until one of the given types arrives, serving the host's calls meanwhile.
	 *
	 * @param list<string> $types
	 * @return mixed[]|null the message, or null on timeout
	 */
	private function pumpUntil(array $types, int $timeoutMs) : ?array{
		$deadline = hrtime(true) + $timeoutMs * 1_000_000;
		while(true){
			$message = $this->readMessage($deadline);
			if($message === null){
				return null;
			}
			$type = $message["t"] ?? "";
			if($type === "c" || $type === "p"){
				$op = (string) ($message["op"] ?? "");
				$args = is_array($message["a"] ?? null) ? $message["a"] : [];
				$wasServicing = $this->servicing;
				$this->servicing = true;
				try{
					$value = $this->handle($op, $args);
					if($type === "c"){
						$this->write(["t" => "r", "v" => $value]);
					}
				}catch(\Throwable $e){
					if($type === "c"){
						$this->write(["t" => "r", "e" => $e->getMessage()]);
					}else{
						$this->logger->debug("[Scripts] $op failed: " . $e->getMessage());
					}
				}finally{
					$this->servicing = $wasServicing;
				}
				continue;
			}
			if($type === "done"){
				$this->busy = false;
			}
			if(in_array($type, $types, true)){
				return $message;
			}
		}
	}

	/** @return mixed[]|null */
	private function readMessage(int $deadline) : ?array{
		while(true){
			$nl = strpos($this->buffer, "\n");
			if($nl !== false){
				$line = substr($this->buffer, 0, $nl);
				$this->buffer = substr($this->buffer, $nl + 1);
				if($line === ""){
					continue;
				}
				$decoded = json_decode($line, true);
				if(!is_array($decoded)){
					$this->logger->debug("[Scripts] bad message: " . substr($line, 0, 200));
					continue;
				}
				return $decoded;
			}
			if($this->stdout === null || feof($this->stdout)){
				throw new \RuntimeException("script host exited");
			}
			$remaining = $deadline - hrtime(true);
			if($remaining <= 0){
				return null;
			}
			$read = [$this->stdout];
			$write = $except = null;
			$ready = @stream_select($read, $write, $except, 0, (int) min(1_000_000, max(0, $remaining / 1000)));
			if($ready === false){
				throw new \RuntimeException("script host pipe failed");
			}
			if($ready > 0){
				$chunk = fread($this->stdout, 1 << 16);
				if($chunk === false || ($chunk === "" && feof($this->stdout))){
					throw new \RuntimeException("script host exited");
				}
				$this->buffer .= $chunk;
			}
		}
	}

	private function drainStderr() : void{
		if($this->stderr === null){
			return;
		}
		$chunk = @fread($this->stderr, 1 << 16);
		if(is_string($chunk) && $chunk !== ""){
			$this->errBuffer .= $chunk;
			while(($nl = strpos($this->errBuffer, "\n")) !== false){
				$line = trim(substr($this->errBuffer, 0, $nl));
				$this->errBuffer = substr($this->errBuffer, $nl + 1);
				if($line !== ""){
					$this->logger->warning("[Scripts] $line");
				}
			}
		}
	}

	// ---------------------------------------------------------------- server side entry points

	public function tick(int $currentTick) : void{
		if(!$this->running){
			return;
		}
		$this->lastTick = $currentTick;
		try{
			if($this->busy){
				if($this->pumpUntil(["done"], $this->tickBudgetMs) === null){
					if($currentTick - $this->busySince > 200){
						$this->crashed("scripts did not return for 10 seconds (watchdog)");
					}
					return;
				}
			}
			$events = $this->queue;
			$this->queue = [];
			$this->write(["t" => "tick", "n" => $currentTick, "ev" => $events]);
			if($this->pumpUntil(["done"], $this->tickBudgetMs) === null){
				$this->busy = true;
				$this->busySince = $currentTick;
				$this->logger->debug("[Scripts] tick $currentTick is over its " . $this->tickBudgetMs . " ms budget");
			}
			if($currentTick % 20 === 0){
				$this->drainStderr();
			}
			if($this->dynamicDirty && $currentTick % 6000 === 0){
				$this->saveDynamic();
			}
		}catch(\RuntimeException $e){
			$this->crashed($e->getMessage());
		}
	}

	public function wants(string $key) : bool{
		return isset($this->subscriptions[$key]);
	}

	/** Queues an after-event for the next tick (only when a script listens to it). @param mixed[] $data */
	public function queueEvent(string $name, array $data) : void{
		if($this->running && ($name[0] === "_" || $name === "scriptEventReceive" || isset($this->subscriptions["after:$name"]))){
			$this->queue[] = [$name, $data];
		}
	}

	/**
	 * Runs a cancellable event through the scripts' before-event handlers right now.
	 *
	 * @param mixed[] $data
	 * @return mixed[] {"cancel": bool, ...changes}
	 */
	public function before(string $name, array $data) : array{
		if(!$this->running || !isset($this->subscriptions["before:$name"]) || $this->servicing){
			return ["cancel" => false];
		}
		return $this->syncRequest(["t" => "before", "name" => $name, "d" => $data]) ?? ["cancel" => false];
	}

	/**
	 * Calls script custom component callbacks (item or block) right now.
	 *
	 * @param list<string> $names custom component names on the item or block
	 * @param mixed[]      $data
	 */
	public function hook(string $kind, array $names, string $hook, array $data) : bool{
		if(!$this->running || $this->servicing){
			return false;
		}
		$registered = [];
		foreach($names as $name){
			if(isset($this->customComponents["$kind:$name"])){
				$registered[] = $name;
			}
		}
		if($registered === []){
			return false;
		}
		$reply = $this->syncRequest(["t" => "hook", "kind" => $kind, "names" => $registered, "hook" => $hook, "d" => $data]);
		return (bool) ($reply["cancel"] ?? false);
	}

	/**
	 * @param mixed[] $message
	 * @return mixed[]|null
	 */
	private function syncRequest(array $message) : ?array{
		try{
			//while serving a script's call (a command it ran, say), the script is blocked waiting for us: the request
			//goes straight in and the script handles it as a nested message. Otherwise let an overrunning tick end first.
			if(!$this->servicing && $this->busy && $this->pumpUntil(["done"], $this->tickBudgetMs) === null){
				return null;
			}
			$this->write($message);
			$reply = $this->pumpUntil(["bres"], max(50, $this->tickBudgetMs * 2));
			if($reply === null){
				$this->busy = true;
				$this->busySince = $this->server->getTick();
			}
			return $reply;
		}catch(\RuntimeException $e){
			$this->crashed($e->getMessage());
			return null;
		}
	}

	// ---------------------------------------------------------------- plugin API

	/**
	 * Makes a PHP function callable from scripts: plugins.call(name, ...args) in @amber/plugins.
	 * Arguments and the return value are JSON values.
	 *
	 * @param \Closure(mixed ...$args) : mixed $function
	 */
	public function exposeFunction(string $name, \Closure $function) : void{
		$this->pluginFunctions[$name] = $function;
	}

	/** Calls a function a script exposed with plugins.expose(). Returns its JSON result. */
	public function callScript(string $name, mixed ...$args) : mixed{
		if(!isset($this->scriptFunctions[$name])){
			throw new \InvalidArgumentException("No script exposes $name");
		}
		$reply = $this->syncRequest(["t" => "pcall", "name" => $name, "args" => array_values($args)]);
		if($reply === null){
			throw new \RuntimeException("Script function $name did not return in time");
		}
		if(isset($reply["e"])){
			throw new \RuntimeException((string) $reply["e"]);
		}
		return $reply["v"] ?? null;
	}

	/** @return list<string> functions scripts exposed */
	public function getScriptFunctions() : array{ return array_keys($this->scriptFunctions); }

	/** Sends a script event to every script listening to system.afterEvents.scriptEventReceive. */
	public function sendScriptEvent(string $id, string $message = "", ?Entity $source = null) : void{
		$event = new AddonScriptEvent($id, $message, "", true);
		$event->call();
		if(!$event->isCancelled()){
			$this->queueEvent("scriptEventReceive", ["id" => $id, "message" => $event->getMessage(), "entity" => $source?->getId()]);
		}
	}

	public function getDynamicProperty(string $key, ?Entity $entity = null) : mixed{
		return $this->readDynamic($entity === null ? self::DEFAULT_SCOPE : (string) $entity->getId(), $key);
	}

	public function setDynamicProperty(string $key, mixed $value, ?Entity $entity = null) : void{
		$this->writeDynamic($entity === null ? self::DEFAULT_SCOPE : (string) $entity->getId(), $key, $value);
	}

	/** Custom commands scripts registered (2.x startup event), for /help and the command map. @return array<string, array{string, int}> */
	public function getCustomCommands() : array{ return $this->customCommands; }

	/** @param list<mixed> $args */
	public function runCustomCommand(string $name, ?Player $player, array $args) : ?string{
		$reply = $this->syncRequest(["t" => "command", "name" => $name, "player" => $player?->getId(), "args" => $args]);
		return is_array($reply["v"] ?? null) ? ($reply["v"]["message"] ?? null) : null;
	}

	// ---------------------------------------------------------------- operations

	/** @param mixed[] $a */
	private function handle(string $op, array $a) : mixed{
		switch($op){
			case "ent":
				$e = $this->entity($a["id"] ?? null);
				return $e === null ? null : $this->snapshot($e);
			case "ents":
				return $this->queryEntities($a["dim"] ?? null, is_array($a["q"] ?? null) ? $a["q"] : []);
			case "comp":
				$e = $this->entity($a["id"] ?? null);
				return $e instanceof AddonEntity ? ($e->getComponent((string) ($a["name"] ?? "")) ?? null) : null;
			case "block":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				[$x, $y, $z] = [(int) floor((float) $a["x"]), (int) floor((float) $a["y"]), (int) floor((float) $a["z"])];
				if($world === null || !$world->isInWorld($x, $y, $z) || !$world->isChunkLoaded($x >> 4, $z >> 4)){
					return null;
				}
				return self::blockWire($world->getBlockAt($x, $y, $z));
			case "setblock":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				return $world !== null && $this->setBlock($world, (int) floor((float) $a["x"]), (int) floor((float) $a["y"]), (int) floor((float) $a["z"]), (string) $a["type"], is_array($a["states"] ?? null) ? $a["states"] : []);
			case "fill":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				return $world === null ? 0 : $this->fill($world, new Vector3((float) $a["x1"], (float) $a["y1"], (float) $a["z1"]), new Vector3((float) $a["x2"], (float) $a["y2"], (float) $a["z2"]), (string) $a["type"], is_array($a["states"] ?? null) ? $a["states"] : []);
			case "top":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				return $world?->getHighestBlockAt((int) $a["x"], (int) $a["z"]);
			case "light":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				return $world === null ? 0 : ((bool) ($a["sky"] ?? false) ? $world->getRealBlockSkyLightAt((int) $a["x"], (int) $a["y"], (int) $a["z"]) : $world->getFullLightAt((int) $a["x"], (int) $a["y"], (int) $a["z"]));
			case "biome":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				return $world === null ? "minecraft:plains" : "minecraft:" . $world->getBiome((int) $a["x"], (int) $a["y"], (int) $a["z"])->getName();
			case "ray":
				return $this->raycast($a);
			case "rayents":
				return $this->raycastEntities($a);
			case "cmd":
				$executor = isset($a["as"]) ? $this->entity($a["as"]) : null;
				$world = isset($a["dim"]) ? $this->worldFor((string) $a["dim"]) : null;
				return ["successCount" => $this->commands->run((string) ($a["cmd"] ?? ""), $executor, $world)];
			case "cmd_p":
				$this->commands->run((string) ($a["cmd"] ?? ""), $this->entity($a["as"] ?? null));
				return null;
			case "msg":
				$text = (string) ($a["text"] ?? "");
				$targets = is_array($a["ids"] ?? null) ? array_map(fn($id) => $this->entity($id), $a["ids"]) : $this->server->getOnlinePlayers();
				foreach($targets as $player){
					if($player instanceof Player){
						$player->sendMessage(self::translatedText($text));
					}
				}
				return null;
			case "title":
				$player = $this->entity($a["id"] ?? null);
				if($player instanceof Player){
					match($a["kind"] ?? "title"){
						"actionbar" => $player->sendActionBarMessage((string) $a["text"]),
						"subtitle" => $player->sendSubTitle((string) $a["text"]),
						default => $player->sendTitle((string) $a["text"], (string) ($a["sub"] ?? ""), (int) ($a["fi"] ?? 10), (int) ($a["st"] ?? 70), (int) ($a["fo"] ?? 20)),
					};
				}
				return null;
			case "tp":
				$e = $this->entity($a["id"] ?? null);
				if($e === null){
					return false;
				}
				$world = isset($a["dim"]) ? ($this->worldFor((string) $a["dim"]) ?? $e->getWorld()) : $e->getWorld();
				$yaw = isset($a["ry"]) ? (float) $a["ry"] : $e->getLocation()->yaw;
				$pitch = isset($a["rx"]) ? (float) $a["rx"] : $e->getLocation()->pitch;
				$target = new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]);
				if(isset($a["fx"])){
					$d = (new Vector3((float) $a["fx"], (float) $a["fy"], (float) $a["fz"]))->subtractVector($target);
					$yaw = atan2($d->z, $d->x) / M_PI * 180 - 90;
					$pitch = -atan2($d->y, sqrt($d->x ** 2 + $d->z ** 2)) / M_PI * 180;
				}
				return $e->teleport(Location::fromObject($target, $world, $yaw, $pitch));
			case "dmg":
				$e = $this->entity($a["id"] ?? null);
				if($e === null){
					return false;
				}
				$cause = self::damageCause((string) ($a["cause"] ?? "override"));
				$damager = isset($a["damager"]) ? $this->entity($a["damager"]) : null;
				$event = $damager !== null ? new EntityDamageByEntityEvent($damager, $e, $cause, (float) $a["amount"]) : new EntityDamageEvent($e, $cause, (float) $a["amount"]);
				$e->attack($event);
				return !$event->isCancelled();
			case "kill":
				$e = $this->entity($a["id"] ?? null);
				if($e === null){
					return false;
				}
				$e->kill();
				return true;
			case "remove":
				$e = $this->entity($a["id"] ?? null);
				if($e !== null && !$e instanceof Player){
					$e->flagForDespawn();
				}
				return null;
			case "imp":
				$e = $this->entity($a["id"] ?? null);
				if($e !== null){
					$v = new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]);
					$e->setMotion((bool) ($a["set"] ?? false) ? $v : $e->getMotion()->addVector($v));
					if(isset($a["owner"])){
						$e->setOwningEntity($this->entity($a["owner"]));
					}
				}
				return null;
			case "kb":
				$e = $this->entity($a["id"] ?? null);
				if($e !== null){
					$dx = (float) $a["dx"];
					$dz = (float) $a["dz"];
					$length = sqrt($dx * $dx + $dz * $dz);
					$h = (float) ($a["h"] ?? 0);
					[$nx, $nz] = $length > 0 ? [$dx / $length, $dz / $length] : [0.0, 0.0];
					$e->setMotion(new Vector3($nx * $h, (float) ($a["v"] ?? 0), $nz * $h));
				}
				return null;
			case "clrvel":
				$this->entity($a["id"] ?? null)?->setMotion(Vector3::zero());
				return null;
			case "rot":
				$e = $this->entity($a["id"] ?? null);
				$e?->setRotation((float) ($a["y"] ?? 0), (float) ($a["x"] ?? 0));
				return null;
			case "lookat":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Living){
					$e->lookAt(new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]));
				}
				return null;
			case "nametag":
				$this->entity($a["id"] ?? null)?->setNameTag((string) ($a["v"] ?? ""));
				return null;
			case "sneak":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Living){
					$e->setSneaking((bool) $a["v"]);
				}
				return null;
			case "tag":
				$e = $this->entity($a["id"] ?? null);
				if($e === null){
					return false;
				}
				return (bool) ($a["add"] ?? true) ? self::addTag($e, (string) $a["tag"], $this) : self::removeTag($e, (string) $a["tag"], $this);
			case "eff":
				$e = $this->entity($a["id"] ?? null);
				$type = StringToEffectParser::getInstance()->parse(self::bare((string) ($a["effect"] ?? "")));
				if($e instanceof Living && $type !== null){
					$e->getEffects()->add(new EffectInstance($type, max(1, (int) ($a["dur"] ?? 20)), max(0, min(255, (int) ($a["amp"] ?? 0))), (bool) ($a["particles"] ?? true)));
				}
				return null;
			case "reff":
				$e = $this->entity($a["id"] ?? null);
				$type = StringToEffectParser::getInstance()->parse(self::bare((string) ($a["effect"] ?? "")));
				if($e instanceof Living && $type !== null && $e->getEffects()->has($type)){
					$e->getEffects()->remove($type);
					return true;
				}
				return false;
			case "geff":
				$e = $this->entity($a["id"] ?? null);
				$type = StringToEffectParser::getInstance()->parse(self::bare((string) ($a["effect"] ?? "")));
				$instance = $e instanceof Living && $type !== null ? $e->getEffects()->get($type) : null;
				return $instance === null ? null : self::effectWire($instance);
			case "effs":
				$e = $this->entity($a["id"] ?? null);
				return $e instanceof Living ? array_values(array_map(static fn(EffectInstance $i) : array => self::effectWire($i), $e->getEffects()->all())) : [];
			case "fire":
				$e = $this->entity($a["id"] ?? null);
				if($e !== null){
					(int) $a["sec"] > 0 ? $e->setOnFire((int) $a["sec"]) : $e->extinguish();
				}
				return null;
			case "trig":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof AddonEntity){
					$e->triggerEvent((string) $a["event"]);
				}
				return null;
			case "prop":
				$e = $this->entity($a["id"] ?? null);
				return $e instanceof AddonEntity ? $e->getProperty((string) $a["name"]) : null;
			case "sprop":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof AddonEntity){
					$e->setProperty((string) $a["name"], $a["v"] ?? null);
				}
				return null;
			case "rprop":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof AddonEntity){
					$default = $e->getAddonDefinition()->getDefaultProperties()[(string) $a["name"]] ?? null;
					$e->setProperty((string) $a["name"], $default);
					return $default;
				}
				return null;
			case "hp":
				$e = $this->entity($a["id"] ?? null);
				if($e !== null){
					$value = max(0.0, min((float) $e->getMaxHealth(), (float) $a["v"]));
					$value <= 0 ? $e->kill() : $e->setHealth($value);
				}
				return null;
			case "speed":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Living){
					$e->setMovementSpeed(max(0.0, (float) $a["v"]));
				}
				return null;
			case "gm":
				$e = $this->entity($a["id"] ?? null);
				$mode = GameMode::fromString((string) ($a["mode"] ?? "survival"));
				if($e instanceof Player && $mode !== null){
					$e->setGamemode($mode);
				}
				return null;
			case "op":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Player){
					(bool) $a["v"] ? $this->server->addOp($e->getName()) : $this->server->removeOp($e->getName());
				}
				return null;
			case "xp":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Human){
					if((bool) ($a["reset"] ?? false)){
						$e->getXpManager()->setXpAndProgress(0, 0.0);
					}elseif((bool) ($a["levels"] ?? false)){
						$e->getXpManager()->addXpLevels((int) $a["amt"]);
					}else{
						(int) $a["amt"] >= 0 ? $e->getXpManager()->addXp((int) $a["amt"]) : $e->getXpManager()->subtractXp(-(int) $a["amt"]);
					}
				}
				return null;
			case "selslot":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Player){
					$e->getInventory()->setHeldItemIndex(max(0, min(8, (int) $a["v"])));
				}
				return null;
			case "sound":
				$this->playSound((string) ($a["dim"] ?? ""), (string) $a["name"], new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]), (float) ($a["vol"] ?? 1), (float) ($a["pitch"] ?? 1), is_array($a["ids"] ?? null) ? $a["ids"] : null);
				return null;
			case "stopsound":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Player){
					$e->getNetworkSession()->sendDataPacket(StopSoundPacket::create((string) ($a["name"] ?? ""), ($a["name"] ?? "") === "", false));
				}
				return null;
			case "particle":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				if($world !== null){
					$this->spawnParticle($world, (string) $a["name"], new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]), is_array($a["vars"] ?? null) ? $a["vars"] : null, is_array($a["ids"] ?? null) ? $a["ids"] : null);
				}
				return null;
			case "boom":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				if($world !== null){
					$explosion = new Explosion(new Position((float) $a["x"], (float) $a["y"], (float) $a["z"], $world), max(0.1, (float) $a["r"]), isset($a["src"]) ? $this->entity($a["src"]) : null, (bool) ($a["fire"] ?? false) ? 1 / 3 : 0.0);
					if((bool) ($a["breaks"] ?? true)){
						$explosion->explodeA();
					}
					$explosion->explodeB();
				}
				return null;
			case "spawn":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				if($world === null){
					return null;
				}
				$entity = $this->spawnEntity((string) $a["type"], new Location((float) $a["x"], (float) $a["y"], (float) $a["z"], $world, 0.0, 0.0), is_string($a["ev"] ?? null) ? $a["ev"] : null);
				return $entity === null ? null : $this->snapshot($entity);
			case "spawnitem":
				$world = $this->worldFor((string) ($a["dim"] ?? ""));
				$item = is_array($a["item"] ?? null) ? $this->wireToItem($a["item"]) : null;
				if($world === null || $item === null){
					return null;
				}
				$entity = $world->dropItem(new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]), $item, Vector3::zero());
				return $entity === null ? null : $this->snapshot($entity);
			case "inv":
				return $this->inventoryWire($this->entity($a["id"] ?? null), (string) ($a["kind"] ?? "inventory"));
			case "invset":
				$inventory = $this->inventory($this->entity($a["id"] ?? null), (string) ($a["kind"] ?? "inventory"));
				$slot = (int) $a["slot"];
				if($inventory !== null && $slot >= 0 && $slot < $inventory->getSize()){
					$inventory->setItem($slot, is_array($a["item"] ?? null) ? ($this->wireToItem($a["item"]) ?? VanillaItems::AIR()) : VanillaItems::AIR());
				}
				return null;
			case "invadd":
				$inventory = $this->inventory($this->entity($a["id"] ?? null), (string) ($a["kind"] ?? "inventory"));
				$item = is_array($a["item"] ?? null) ? $this->wireToItem($a["item"]) : null;
				if($inventory === null || $item === null){
					return $a["item"] ?? null;
				}
				$left = $inventory->addItem($item);
				return $left === [] ? null : self::itemWire($left[0]);
			case "invclear":
				$this->inventory($this->entity($a["id"] ?? null), (string) ($a["kind"] ?? "inventory"))?->clearAll();
				return null;
			case "equip":
				return self::itemWireOrNull($this->equipment($this->entity($a["id"] ?? null), (string) ($a["slot"] ?? "Mainhand")));
			case "setequip":
				$this->setEquipment($this->entity($a["id"] ?? null), (string) ($a["slot"] ?? "Mainhand"), is_array($a["item"] ?? null) ? $this->wireToItem($a["item"]) : null);
				return null;
			case "dp":
				return $this->readDynamic((string) ($a["scope"] ?? self::DEFAULT_SCOPE), (string) ($a["key"] ?? ""));
			case "sdp":
				$this->writeDynamic((string) ($a["scope"] ?? self::DEFAULT_SCOPE), (string) ($a["key"] ?? ""), $a["v"] ?? null);
				return null;
			case "dpids":
				return array_keys($this->scope((string) ($a["scope"] ?? self::DEFAULT_SCOPE)));
			case "dpclear":
				$this->clearScope((string) ($a["scope"] ?? self::DEFAULT_SCOPE));
				return null;
			case "time":
				$world = $this->server->getWorldManager()->getDefaultWorld();
				$time = $world?->getTime() ?? 0;
				return [
					"abs" => $time,
					"tod" => $time % World::TIME_FULL,
					"day" => (int) ($time / World::TIME_FULL),
					"moon" => (int) ($time / World::TIME_FULL) % 8,
					"difficulty" => ["Peaceful", "Easy", "Normal", "Hard"][$world?->getDifficulty() ?? 2] ?? "Normal",
				];
			case "settime":
				foreach($this->server->getWorldManager()->getWorlds() as $world){
					$world->setTime((bool) ($a["abs"] ?? false) ? (int) $a["v"] : (int) ($world->getTime() - $world->getTime() % World::TIME_FULL + (int) $a["v"]));
				}
				return null;
			case "spawnpoint":
				$spawn = $this->server->getWorldManager()->getDefaultWorld()?->getSpawnLocation();
				return $spawn === null ? ["x" => 0, "y" => 64, "z" => 0] : ["x" => $spawn->getFloorX(), "y" => $spawn->getFloorY(), "z" => $spawn->getFloorZ()];
			case "setspawn":
				$this->server->getWorldManager()->getDefaultWorld()?->setSpawnLocation(new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]));
				return null;
			case "pspawn":
				$e = $this->entity($a["id"] ?? null);
				if(!$e instanceof Player){
					return null;
				}
				$spawn = $e->getSpawn();
				return ["x" => $spawn->getFloorX(), "y" => $spawn->getFloorY(), "z" => $spawn->getFloorZ(), "dim" => $this->dimensionId($spawn->getWorld())];
			case "setpspawn":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Player){
					$world = isset($a["dim"]) ? ($this->worldFor((string) $a["dim"]) ?? $e->getWorld()) : $e->getWorld();
					$e->setSpawn(isset($a["x"]) ? new Position((float) $a["x"], (float) $a["y"], (float) $a["z"], $world) : null);
				}
				return null;
			case "inperm":
				$e = $this->entity($a["id"] ?? null);
				if($e instanceof Player){
					$this->commands->setInputPermission($e, is_int($a["perm"]) ? self::inputCategory($a["perm"]) : (string) $a["perm"], (bool) $a["v"]);
				}
				return null;
			case "camera":
				$this->reportOnce("camera", "player.camera is not supported by this server");
				return null;
			case "anim":
				$e = $this->entity($a["id"] ?? null);
				if($e !== null){
					$this->commands->run("playanimation @s " . $a["anim"] . (isset($a["next"]) ? " " . $a["next"] : ""), $e);
				}
				return null;
			case "form":
				$player = $this->entity($a["id"] ?? null);
				if($player instanceof Player && is_array($a["form"] ?? null)){
					$player->sendForm(new ScriptForm($a["form"], (int) $a["fid"], $this));
				}else{
					$this->queueEvent("__form", ["fid" => (int) ($a["fid"] ?? 0), "data" => null, "reason" => "UserBusy"]);
				}
				return null;
			case "closeforms":
				$player = $this->entity($a["id"] ?? null);
				if($player instanceof Player){
					$player->closeAllForms();
				}
				return null;
			case "sub":
				$this->subscriptions = [];
				foreach(is_array($a["events"] ?? null) ? $a["events"] : [] as $key){
					$this->subscriptions[(string) $key] = true;
				}
				return null;
			case "customcomp":
				$this->customComponents[(string) $a["kind"] . ":" . (string) $a["name"]] = true;
				return null;
			case "customcmd":
				$this->customCommands[(string) $a["name"]] = [(string) ($a["description"] ?? ""), (int) ($a["permission"] ?? 0)];
				$this->manager->registerScriptCommand((string) $a["name"], (string) ($a["description"] ?? ""), (int) ($a["permission"] ?? 0));
				return null;
			case "sev":
				$event = new AddonScriptEvent((string) $a["id"], (string) ($a["msg"] ?? ""), (string) ($a["pack"] ?? ""), false);
				$event->call();
				if(!$event->isCancelled()){
					$this->queueEvent("scriptEventReceive", ["id" => $event->getId(), "message" => $event->getMessage()]);
				}
				return null;
			case "expose":
				$this->scriptFunctions[(string) $a["name"]] = true;
				return null;
			case "pcall":
				$function = $this->pluginFunctions[(string) ($a["name"] ?? "")] ?? null;
				if($function === null){
					throw new \InvalidArgumentException("No plugin function " . ($a["name"] ?? ""));
				}
				return $function(...(is_array($a["args"] ?? null) ? array_values($a["args"]) : []));
			case "phas":
				return isset($this->pluginFunctions[(string) ($a["name"] ?? "")]);
			case "plist":
				return array_keys($this->pluginFunctions);
			case "gamerule":
				return null;
			case "setgamerule":
				return null;
			case "sbshow":
				$this->showObjective((string) $a["slot"], (string) $a["id"], (string) ($a["name"] ?? $a["id"]), (int) ($a["order"] ?? 1), is_array($a["scores"] ?? null) ? $a["scores"] : []);
				return null;
			case "sbclear":
				foreach($this->server->getOnlinePlayers() as $player){
					$player->getNetworkSession()->sendDataPacket(RemoveObjectivePacket::create("amber_script_" . strtolower((string) $a["slot"])));
				}
				return null;
			case "tame":
				$e = $this->entity($a["id"] ?? null);
				$owner = $this->entity($a["owner"] ?? null);
				if($e instanceof AddonEntity && $owner instanceof Player){
					$e->setOwner($owner);
				}
				return null;
			case "owner":
				$e = $this->entity($a["id"] ?? null);
				$e?->setOwningEntity(isset($a["owner"]) ? $this->entity($a["owner"]) : null);
				return null;
			case "log":
				$pack = (string) ($a["pack"] ?? "");
				$message = "[Script" . ($pack !== "" ? ":$pack" : "") . "] " . (string) ($a["msg"] ?? "");
				match($a["lvl"] ?? "info"){
					"error" => $this->logger->error($message),
					"warning" => $this->logger->warning($message),
					"debug" => $this->logger->debug($message),
					default => $this->logger->info($message),
				};
				return null;
		}
		throw new \InvalidArgumentException("unknown operation $op");
	}

	// ---------------------------------------------------------------- entities

	private function entity(mixed $id) : ?Entity{
		if(!is_numeric($id)){
			return null;
		}
		$entity = $this->server->getWorldManager()->findEntity((int) $id);
		return $entity !== null && !$entity->isClosed() ? $entity : null;
	}

	/** @return mixed[] */
	public function snapshot(Entity $e) : array{
		$pos = $e->getPosition();
		$location = $e->getLocation();
		$motion = $e->getMotion();
		$size = $e->getSize();
		$s = [
			"id" => (string) $e->getId(),
			"type" => self::typeId($e),
			"dim" => $this->dimensionId($e->getWorld()),
			"x" => $pos->x, "y" => $pos->y, "z" => $pos->z,
			"rx" => $location->pitch, "ry" => $location->yaw > 180 ? $location->yaw - 360 : $location->yaw,
			"vx" => $motion->x, "vy" => $motion->y, "vz" => $motion->z,
			"alive" => $e->isAlive(),
			"ground" => $e->isOnGround(),
			"fireTicks" => $e->getFireTicks(),
			"nameTag" => $e->getNameTag(),
			"tags" => self::tagsOf($e, $this),
			"fam" => EntityFilter::familiesOf($e),
			"h" => $size->getHeight(), "w" => $size->getWidth(), "eye" => $size->getEyeHeight(),
			"hp" => $e->getHealth(), "maxHp" => $e->getMaxHealth(),
		];
		if($e instanceof Living){
			$s["living"] = true;
			$s["sneak"] = $e->isSneaking();
			$s["sprint"] = $e->isSprinting();
			$s["swim"] = $e->isSwimming();
			$s["water"] = $e->isUnderwater();
			$s["armor"] = $e->getArmorPoints();
			$s["speed"] = $e->getMovementSpeed();
			$target = $e->getTargetEntityId();
			$s["target"] = $target === null ? null : (string) $target;
		}
		if($e instanceof Player){
			$s["player"] = true;
			$s["name"] = $e->getName();
			$s["gm"] = self::gameModeName($e->getGamemode());
			$s["op"] = $this->server->isOp($e->getName());
			$xp = $e->getXpManager();
			$s["xpl"] = $xp->getXpLevel();
			$s["xpp"] = (int) ($xp->getXpProgress() * 100);
			$s["xpt"] = $xp->getCurrentTotalXp();
			$s["slot"] = $e->getInventory()->getHeldItemIndex();
			$s["flying"] = $e->isFlying();
		}
		if($e instanceof AddonEntity){
			$s["comps"] = array_keys($e->getComponents());
			$s["tamed"] = $e->isTamed();
			$owner = $e->getOwningEntityId();
			$s["owner"] = $owner === null ? null : (string) $owner;
		}
		if($e instanceof ItemEntity){
			$s["item"] = self::itemWire($e->getItem());
		}
		return $s;
	}

	/**
	 * EntityQueryOptions, evaluated here so only matches cross the pipe.
	 *
	 * @param mixed[] $q
	 * @return list<mixed[]>
	 */
	private function queryEntities(?string $dim, array $q) : array{
		$worlds = $dim !== null ? array_filter([$this->worldFor($dim)]) : $this->server->getWorldManager()->getWorlds();
		$playersOnly = (bool) ($q["players"] ?? false);
		$location = is_array($q["location"] ?? null) ? new Vector3((float) $q["location"]["x"], (float) $q["location"]["y"], (float) $q["location"]["z"]) : null;
		$max = is_numeric($q["maxDistance"] ?? null) ? (float) $q["maxDistance"] : null;
		$min = is_numeric($q["minDistance"] ?? null) ? (float) $q["minDistance"] : null;
		$ids = is_array($q["ids"] ?? null) ? array_map("intval", $q["ids"]) : null;
		$matches = [];
		foreach($worlds as $world){
			if($location !== null && $max !== null && !$playersOnly){
				$pool = $world->getNearbyEntities(new AxisAlignedBB($location->x - $max, $location->y - $max, $location->z - $max, $location->x + $max, $location->y + $max, $location->z + $max));
			}else{
				$pool = $playersOnly ? $world->getPlayers() : $world->getEntities();
			}
			foreach($pool as $e){
				if($e->isClosed() || ($ids !== null && !in_array($e->getId(), $ids, true))){
					continue;
				}
				if($e instanceof Player && !$e->isConnected()){
					continue;
				}
				if($location !== null){
					$d = $e->getPosition()->distance($location);
					if(($max !== null && $d > $max) || ($min !== null && $d < $min)){
						continue;
					}
				}
				if(!$this->matchesQuery($e, $q)){
					continue;
				}
				$matches[] = $e;
			}
		}
		if($location !== null && (isset($q["closest"]) || isset($q["farthest"]))){
			usort($matches, static fn(Entity $a, Entity $b) : int => $a->getPosition()->distanceSquared($location) <=> $b->getPosition()->distanceSquared($location));
			if(isset($q["farthest"])){
				$matches = array_reverse($matches);
			}
			$matches = array_slice($matches, 0, max(0, (int) ($q["closest"] ?? $q["farthest"])));
		}
		return array_map(fn(Entity $e) : array => $this->snapshot($e), $matches);
	}

	/** @param mixed[] $q */
	private function matchesQuery(Entity $e, array $q) : bool{
		$type = self::typeId($e);
		if(isset($q["type"]) && $type !== $q["type"]){
			return false;
		}
		if(is_array($q["excludeTypes"] ?? null) && in_array($type, $q["excludeTypes"], true)){
			return false;
		}
		$name = $e instanceof Player ? $e->getName() : $e->getNameTag();
		if(isset($q["name"]) && $name !== $q["name"]){
			return false;
		}
		if(is_array($q["excludeNames"] ?? null) && in_array($name, $q["excludeNames"], true)){
			return false;
		}
		if(is_array($q["families"] ?? null) || is_array($q["excludeFamilies"] ?? null)){
			$families = EntityFilter::familiesOf($e);
			foreach(is_array($q["families"] ?? null) ? $q["families"] : [] as $family){
				if(!in_array($family, $families, true)){
					return false;
				}
			}
			foreach(is_array($q["excludeFamilies"] ?? null) ? $q["excludeFamilies"] : [] as $family){
				if(in_array($family, $families, true)){
					return false;
				}
			}
		}
		if(is_array($q["tags"] ?? null) || is_array($q["excludeTags"] ?? null)){
			$tags = self::tagsOf($e, $this);
			foreach(is_array($q["tags"] ?? null) ? $q["tags"] : [] as $tag){
				if(!in_array($tag, $tags, true)){
					return false;
				}
			}
			foreach(is_array($q["excludeTags"] ?? null) ? $q["excludeTags"] : [] as $tag){
				if(in_array($tag, $tags, true)){
					return false;
				}
			}
		}
		if(isset($q["gameMode"]) && (!$e instanceof Player || self::gameModeName($e->getGamemode()) !== strtolower((string) $q["gameMode"]))){
			return false;
		}
		if(is_array($q["excludeGameModes"] ?? null) && $e instanceof Player && in_array(self::gameModeName($e->getGamemode()), array_map("strtolower", $q["excludeGameModes"]), true)){
			return false;
		}
		return true;
	}

	/** The Bedrock identifier of any entity. */
	public static function typeId(Entity $e) : string{
		return match(true){
			$e instanceof AddonEntity => $e->getAddonIdentifier(),
			$e instanceof Player => "minecraft:player",
			default => $e::getNetworkTypeId(),
		};
	}

	/** @return list<string> */
	public static function tagsOf(Entity $e, ?self $host) : array{
		if($e instanceof AddonEntity){
			return $e->getTags();
		}
		if($host === null){
			return [];
		}
		if($e instanceof Player){
			$tags = $host->dynamic["player:" . $e->getName()]["__tags"] ?? [];
			return is_array($tags) ? array_values($tags) : [];
		}
		return array_keys($host->entityTags[$e] ?? []);
	}

	public static function addTag(Entity $e, string $tag, ?self $host) : bool{
		if(in_array($tag, self::tagsOf($e, $host), true)){
			return false;
		}
		if($e instanceof AddonEntity){
			$e->addTag($tag);
		}elseif($host !== null && $e instanceof Player){
			$tags = self::tagsOf($e, $host);
			$tags[] = $tag;
			$host->dynamic["player:" . $e->getName()]["__tags"] = $tags;
			$host->dynamicDirty = true;
		}elseif($host !== null){
			$tags = $host->entityTags[$e] ?? [];
			$tags[$tag] = true;
			$host->entityTags[$e] = $tags;
		}
		return true;
	}

	public static function removeTag(Entity $e, string $tag, ?self $host) : bool{
		if(!in_array($tag, self::tagsOf($e, $host), true)){
			return false;
		}
		if($e instanceof AddonEntity){
			$e->removeTag($tag);
		}elseif($host !== null && $e instanceof Player){
			$host->dynamic["player:" . $e->getName()]["__tags"] = array_values(array_filter(self::tagsOf($e, $host), static fn(string $t) : bool => $t !== $tag));
			$host->dynamicDirty = true;
		}elseif($host !== null){
			$tags = $host->entityTags[$e] ?? [];
			unset($tags[$tag]);
			$host->entityTags[$e] = $tags;
		}
		return true;
	}

	public static function gameModeName(GameMode $mode) : string{
		return match($mode){
			GameMode::CREATIVE => "creative",
			GameMode::ADVENTURE => "adventure",
			GameMode::SPECTATOR => "spectator",
			default => "survival",
		};
	}

	private static function damageCause(string $cause) : int{
		$map = [
			"entityAttack" => EntityDamageEvent::CAUSE_ENTITY_ATTACK, "projectile" => EntityDamageEvent::CAUSE_PROJECTILE, "fall" => EntityDamageEvent::CAUSE_FALL,
			"fire" => EntityDamageEvent::CAUSE_FIRE, "fireTick" => EntityDamageEvent::CAUSE_FIRE_TICK, "lava" => EntityDamageEvent::CAUSE_LAVA, "drowning" => EntityDamageEvent::CAUSE_DROWNING,
			"blockExplosion" => EntityDamageEvent::CAUSE_BLOCK_EXPLOSION, "entityExplosion" => EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, "void" => EntityDamageEvent::CAUSE_VOID,
			"suicide" => EntityDamageEvent::CAUSE_SUICIDE, "magic" => EntityDamageEvent::CAUSE_MAGIC, "starve" => EntityDamageEvent::CAUSE_STARVATION, "suffocation" => EntityDamageEvent::CAUSE_SUFFOCATION,
			"contact" => EntityDamageEvent::CAUSE_CONTACT, "wither" => EntityDamageEvent::CAUSE_MAGIC, "thorns" => EntityDamageEvent::CAUSE_ENTITY_ATTACK,
		];
		return $map[$cause] ?? EntityDamageEvent::CAUSE_CUSTOM;
	}

	/** @return mixed[] */
	private static function effectWire(EffectInstance $instance) : array{
		$id = EffectIdMap::getInstance()->toId($instance->getType());
		$name = strtolower(str_replace(" ", "_", $instance->getType()->getName()->getText()));
		foreach(StringToEffectParser::getInstance()->getKnownAliases() as $alias){
			$type = StringToEffectParser::getInstance()->parse($alias);
			if($type !== null && EffectIdMap::getInstance()->toId($type) === $id){
				$name = $alias;
				break;
			}
		}
		return ["type" => "minecraft:" . $name, "duration" => $instance->getDuration(), "amplifier" => $instance->getAmplifier(), "name" => $name];
	}

	/** Spawns an add-on entity or a vanilla one the server implements. */
	public function spawnEntity(string $type, Location $location, ?string $event) : ?Entity{
		$type = str_contains($type, ":") ? strtolower($type) : "minecraft:" . strtolower($type);
		$addon = $this->manager->createEntity($type, $location);
		if($addon !== null){
			if($event !== null){
				$addon->setSpawnEvent($event);
			}
			$addon->spawnToAll();
			return $addon;
		}
		$nbt = CompoundTag::create()
			->setString("identifier", $type)
			->setTag("Pos", new ListTag([new DoubleTag($location->x), new DoubleTag($location->y), new DoubleTag($location->z)]))
			->setTag("Motion", new ListTag([new DoubleTag(0.0), new DoubleTag(0.0), new DoubleTag(0.0)]))
			->setTag("Rotation", new ListTag([new FloatTag($location->yaw), new FloatTag($location->pitch)]));
		try{
			$entity = EntityFactory::getInstance()->createFromData($location->getWorld(), $nbt);
		}catch(\Throwable){
			$entity = null;
		}
		if($entity === null){
			$this->reportOnce("spawn:$type", "cannot spawn $type: this server does not implement it");
			return null;
		}
		$entity->spawnToAll();
		return $entity;
	}

	// ---------------------------------------------------------------- blocks

	/** @return array{type: string, states: array<string, mixed>, solid: bool} */
	public static function blockWire(Block $block) : array{
		try{
			$data = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block);
		}catch(\Throwable){
			return ["type" => "minecraft:unknown", "states" => [], "solid" => $block->isSolid()];
		}
		$states = [];
		foreach($data->getStates() as $name => $tag){
			$states[$name] = match(true){
				$tag instanceof ByteTag => $tag->getValue() !== 0,
				$tag instanceof IntTag => $tag->getValue(),
				default => (string) $tag->getValue(),
			};
		}
		return ["type" => $data->getName(), "states" => $states, "solid" => $block->isSolid()];
	}

	public static function blockTypeId(Block $block) : string{
		return self::blockWire($block)["type"];
	}

	/** @param array<string, mixed> $states */
	private function resolveBlock(string $type, array $states) : ?Block{
		$type = str_contains($type, ":") ? strtolower($type) : "minecraft:" . strtolower($type);
		$addon = $this->manager->getBlock($type);
		if($addon !== null){
			foreach($states as $name => $value){
				if(is_int($value) || is_string($value) || is_bool($value)){
					try{
						$addon = $addon->withStateValue((string) $name, $value);
					}catch(\Throwable){
					}
				}
			}
			return $addon;
		}
		//the block's full default state, then the requested states over it (typed like the defaults)
		$default = self::defaultBlock($type);
		if($default === null){
			return null;
		}
		if($states === []){
			return $default;
		}
		try{
			$data = GlobalBlockStateHandlers::getSerializer()->serializeBlock($default);
			$merged = $data->getStates();
			foreach($states as $name => $value){
				$name = (string) $name;
				$current = $merged[$name] ?? null;
				$merged[$name] = match(true){
					$current instanceof ByteTag => new ByteTag(is_bool($value) ? ($value ? 1 : 0) : (int) $value),
					$current instanceof IntTag => new IntTag((int) $value),
					$current instanceof StringTag => new StringTag(is_bool($value) ? ($value ? "true" : "false") : (string) $value),
					is_bool($value) => new ByteTag($value ? 1 : 0),
					is_int($value), is_float($value) => new IntTag((int) $value),
					default => new StringTag((string) $value),
				};
			}
			return GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(BlockStateData::current($data->getName(), $merged));
		}catch(\Throwable){
			$this->reportOnce("states:$type", "block states " . json_encode($states) . " are not valid for $type; its default state is used");
			return $default;
		}
	}

	/** The default state of a vanilla block by its identifier (current or legacy name). */
	private static function defaultBlock(string $type) : ?Block{
		$block = StringToItemParser::getInstance()->parse($type)?->getBlock();
		if($block !== null && ($block->getTypeId() !== VanillaBlocks::AIR()->getTypeId() || $type === "minecraft:air")){
			return $block;
		}
		try{
				return GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(GlobalBlockStateHandlers::getUpgrader()->upgradeStringIdMeta($type, 0));
		}catch(\Throwable){
			return null;
		}
	}

	/** @param array<string, mixed> $states */
	public function setBlock(World $world, int $x, int $y, int $z, string $type, array $states) : bool{
		if(!$world->isInWorld($x, $y, $z) || !$world->isChunkLoaded($x >> 4, $z >> 4)){
			return false;
		}
		$block = $this->resolveBlock($type, $states);
		if($block === null){
			$this->reportOnce("block:$type", "unknown block $type");
			return false;
		}
		$world->setBlockAt($x, $y, $z, $block);
		return true;
	}

	/** @param array<string, mixed> $states */
	public function fill(World $world, Vector3 $a, Vector3 $b, string $type, array $states) : int{
		$block = $this->resolveBlock($type, $states);
		if($block === null){
			return 0;
		}
		[$x1, $x2] = [(int) floor(min($a->x, $b->x)), (int) floor(max($a->x, $b->x))];
		[$y1, $y2] = [(int) floor(min($a->y, $b->y)), (int) floor(max($a->y, $b->y))];
		[$z1, $z2] = [(int) floor(min($a->z, $b->z)), (int) floor(max($a->z, $b->z))];
		if(($x2 - $x1 + 1) * ($y2 - $y1 + 1) * ($z2 - $z1 + 1) > 32768){
			return 0; //the game's limit
		}
		$count = 0;
		for($x = $x1; $x <= $x2; ++$x){
			for($z = $z1; $z <= $z2; ++$z){
				if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
					continue;
				}
				for($y = $y1; $y <= $y2; ++$y){
					if($world->isInWorld($x, $y, $z)){
						$world->setBlockAt($x, $y, $z, $block, false);
						$count++;
					}
				}
			}
		}
		return $count;
	}

	/**
	 * @param mixed[] $a
	 * @return mixed[]|null
	 */
	private function raycast(array $a) : ?array{
		$world = $this->worldFor((string) ($a["dim"] ?? ""));
		if($world === null){
			return null;
		}
		$start = new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]);
		$direction = (new Vector3((float) $a["dx"], (float) $a["dy"], (float) $a["dz"]))->normalize();
		$max = min(256.0, (float) ($a["max"] ?? 64));
		$liquid = (bool) ($a["liquid"] ?? false);
		$passable = (bool) ($a["passable"] ?? false);
		foreach(VoxelRayTrace::inDirection($start, $direction, $max) as $v){
			$block = $world->getBlockAt((int) $v->x, (int) $v->y, (int) $v->z);
			if($block->getTypeId() === VanillaBlocks::AIR()->getTypeId()){
				continue;
			}
			$isLiquid = $block instanceof \pocketmine\block\Liquid;
			if(($isLiquid && !$liquid) || (!$isLiquid && !$block->isSolid() && !$passable)){
				continue;
			}
			$wire = self::blockWire($block);
			return ["x" => (int) $v->x, "y" => (int) $v->y, "z" => (int) $v->z, "type" => $wire["type"], "states" => $wire["states"], "face" => "Up", "fx" => 0.5, "fy" => 1.0, "fz" => 0.5];
		}
		return null;
	}

	/**
	 * @param mixed[] $a
	 * @return list<mixed[]>
	 */
	private function raycastEntities(array $a) : array{
		$world = $this->worldFor((string) ($a["dim"] ?? ""));
		if($world === null){
			return [];
		}
		$start = new Vector3((float) $a["x"], (float) $a["y"], (float) $a["z"]);
		$direction = (new Vector3((float) $a["dx"], (float) $a["dy"], (float) $a["dz"]))->normalize();
		$max = min(128.0, (float) ($a["max"] ?? 64));
		$end = $start->addVector($direction->multiply($max));
		$self = isset($a["self"]) ? (int) $a["self"] : null;
		$hits = [];
		$box = new AxisAlignedBB(min($start->x, $end->x), min($start->y, $end->y), min($start->z, $end->z), max($start->x, $end->x), max($start->y, $end->y), max($start->z, $end->z));
		foreach($world->getNearbyEntities($box) as $e){
			if($e->getId() === $self){
				continue;
			}
			$hit = $e->getBoundingBox()->expandedCopy(0.1, 0.1, 0.1)->calculateIntercept($start, $end);
			if($hit !== null){
				$hits[] = ["id" => (string) $e->getId(), "snap" => $this->snapshot($e), "d" => $start->distance($hit->getHitVector())];
			}
		}
		usort($hits, static fn(array $x, array $y) : int => $x["d"] <=> $y["d"]);
		return $hits;
	}

	// ---------------------------------------------------------------- effects, sounds, particles

	/**
	 * @param list<mixed[]>|null $vars MolangVariableMap entries
	 * @param list<mixed>|null   $ids  players to show it to (all players in the world when null)
	 */
	public function spawnParticle(World $world, string $name, Vector3 $pos, ?array $vars, ?array $ids) : void{
		$json = $vars === null || $vars === [] ? null : json_encode($vars, JSON_PRESERVE_ZERO_FRACTION);
		$packet = SpawnParticleEffectPacket::create($this->dimensionNetworkId($world), -1, $pos, $name, $json === false ? null : $json);
		$targets = $ids === null ? $world->getViewersForPosition($pos) : array_filter(array_map(fn($id) => $this->entity($id), $ids), static fn($e) => $e instanceof Player);
		foreach($targets as $player){
			if($player instanceof Player){
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	/** @param list<mixed>|null $ids */
	private function playSound(string $dim, string $name, Vector3 $pos, float $volume, float $pitch, ?array $ids) : void{
		$world = $this->worldFor($dim);
		$packet = PlaySoundPacket::create($name, $pos->x, $pos->y, $pos->z, $volume, $pitch, 0, null);
		$targets = $ids !== null ? array_map(fn($id) => $this->entity($id), $ids) : ($world?->getViewersForPosition($pos) ?? []);
		foreach($targets as $player){
			if($player instanceof Player){
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	/** @param array<string, int> $scores */
	private function showObjective(string $slot, string $id, string $name, int $order, array $scores) : void{
		$objective = "amber_script_" . strtolower($slot);
		$display = match(strtolower($slot)){ "list" => "list", "belowname" => "belowname", default => "sidebar" };
		$entries = [];
		$i = 0;
		foreach($scores as $participant => $score){
			$entry = new ScorePacketEntry();
			$entry->scoreboardId = ++$i;
			$entry->objectiveName = $objective;
			$entry->score = (int) $score;
			$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
			$entry->customName = str_starts_with((string) $participant, "entity:") ? substr((string) $participant, 7) : (string) $participant;
			$entries[] = $entry;
			if($i >= 15 && $display === "sidebar"){
				break;
			}
		}
		foreach($this->server->getOnlinePlayers() as $player){
			$session = $player->getNetworkSession();
			$session->sendDataPacket(RemoveObjectivePacket::create($objective));
			$session->sendDataPacket(SetDisplayObjectivePacket::create($display, $objective, $name, "dummy", $order === 0 ? 0 : 1));
			$session->sendDataPacket(SetScorePacket::create(SetScorePacket::TYPE_CHANGE, $entries));
		}
	}

	private static function inputCategory(int $category) : string{
		return match($category){ 1 => "camera", 2 => "movement", 4 => "lateral_movement", 5 => "sneak", 6 => "jump", 7 => "mount", 8 => "dismount", 9 => "move_forward", 10 => "move_backward", 11 => "move_left", 12 => "move_right", default => "" };
	}

	/** Chat text from a flattened rawtext; a single "%key|a|b" piece is sent as a translation. */
	private static function translatedText(string $text) : string|\pocketmine\lang\Translatable{
		if(str_starts_with($text, "%") && !str_contains($text, " ")){
			$parts = explode("|", substr($text, 1));
			return new \pocketmine\lang\Translatable(array_shift($parts), $parts);
		}
		return $text;
	}

	/** Plain text of a rawtext JSON value (tellraw). */
	public static function rawText(mixed $json) : string{
		if(is_string($json)){
			return $json;
		}
		if(!is_array($json)){
			return "";
		}
		if(isset($json["rawtext"]) && is_array($json["rawtext"])){
			return implode("", array_map(static fn($p) : string => self::rawText($p), $json["rawtext"]));
		}
		if(isset($json["text"])){
			return (string) $json["text"];
		}
		if(isset($json["translate"])){
			return "%" . $json["translate"];
		}
		return "";
	}

	// ---------------------------------------------------------------- items and inventories

	/** @return array<string, mixed> */
	public static function itemWire(Item $item) : array{
		try{
			$name = GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
		}catch(\Throwable){
			$name = "minecraft:" . strtolower(str_replace(" ", "_", $item->getVanillaName()));
		}
		$wire = ["id" => $name, "count" => $item->getCount(), "name" => $item->hasCustomName() ? $item->getCustomName() : null, "lore" => $item->getLore(), "max" => $item->getMaxStackSize()];
		if($item instanceof Durable){
			$wire["dmg"] = $item->getDamage();
			$wire["maxDmg"] = $item->getMaxDurability();
		}
		$enchantments = [];
		foreach($item->getEnchantments() as $instance){
			$enchantments[] = ["id" => "minecraft:" . strtolower(str_replace(" ", "_", self::enchantmentName($instance))), "lvl" => $instance->getLevel(), "max" => $instance->getType()->getMaxLevel()];
		}
		$wire["ench"] = $enchantments;
		return $wire;
	}

	private static function enchantmentName(EnchantmentInstance $instance) : string{
		static $names = null;
		if($names === null){
			$names = [];
			foreach(StringToEnchantmentParser::getInstance()->getKnownAliases() as $alias){
				$type = StringToEnchantmentParser::getInstance()->parse($alias);
				if($type !== null && !isset($names[spl_object_id($type)])){
					$names[spl_object_id($type)] = $alias;
				}
			}
		}
		return $names[spl_object_id($instance->getType())] ?? "unknown";
	}

	/** @return array<string, mixed>|null */
	private static function itemWireOrNull(?Item $item) : ?array{
		return $item === null || $item->isNull() ? null : self::itemWire($item);
	}

	/** @param mixed[] $wire */
	public function wireToItem(array $wire) : ?Item{
		$id = strtolower((string) ($wire["id"] ?? ""));
		$item = $this->manager->getItem($id) ?? StringToItemParser::getInstance()->parse($id) ?? StringToItemParser::getInstance()->parse(str_replace("minecraft:", "", $id));
		if($item === null){
			$this->reportOnce("item:$id", "unknown item $id");
			return null;
		}
		$item->setCount(max(1, min(255, (int) ($wire["count"] ?? 1))));
		if(is_string($wire["name"] ?? null)){
			$item->setCustomName($wire["name"]);
		}
		if(is_array($wire["lore"] ?? null) && $wire["lore"] !== []){
			$item->setLore(array_map("strval", $wire["lore"]));
		}
		if($item instanceof Durable && is_numeric($wire["dmg"] ?? null)){
			$item->setDamage(max(0, min($item->getMaxDurability(), (int) $wire["dmg"])));
		}
		foreach(is_array($wire["ench"] ?? null) ? $wire["ench"] : [] as $e){
			$type = StringToEnchantmentParser::getInstance()->parse(self::bare((string) ($e["id"] ?? "")));
			if($type !== null){
				$item->addEnchantment(new EnchantmentInstance($type, max(1, (int) ($e["lvl"] ?? 1))));
			}
		}
		return $item;
	}

	private function inventory(?Entity $e, string $kind) : ?\pocketmine\inventory\Inventory{
		if($e instanceof Human){
			return $kind === "ender" ? $e->getEnderInventory() : $e->getInventory();
		}
		return null;
	}

	/** @return array{size: int, items: array<int, mixed>}|null */
	private function inventoryWire(?Entity $e, string $kind) : ?array{
		$inventory = $this->inventory($e, $kind);
		if($inventory === null){
			return null;
		}
		$items = [];
		foreach($inventory->getContents() as $slot => $item){
			$items[$slot] = self::itemWire($item);
		}
		return ["size" => $inventory->getSize(), "items" => (object) $items];
	}

	private function equipment(?Entity $e, string $slot) : ?Item{
		if(!$e instanceof Living){
			return null;
		}
		$armor = $e->getArmorInventory();
		return match($slot){
			"Head" => $armor->getHelmet(),
			"Chest" => $armor->getChestplate(),
			"Legs" => $armor->getLeggings(),
			"Feet" => $armor->getBoots(),
			"Offhand" => $e instanceof Human ? $e->getOffHandInventory()->getItem(0) : null,
			default => $e instanceof Human ? $e->getInventory()->getItemInHand() : null,
		};
	}

	private function setEquipment(?Entity $e, string $slot, ?Item $item) : void{
		if(!$e instanceof Living){
			return;
		}
		$item ??= VanillaItems::AIR();
		$armor = $e->getArmorInventory();
		match($slot){
			"Head" => $armor->setHelmet($item),
			"Chest" => $armor->setChestplate($item),
			"Legs" => $armor->setLeggings($item),
			"Feet" => $armor->setBoots($item),
			"Offhand" => $e instanceof Human ? $e->getOffHandInventory()->setItem(0, $item) : null,
			default => $e instanceof Human ? $e->getInventory()->setItemInHand($item) : null,
		};
	}

	// ---------------------------------------------------------------- dimensions

	/** @return list<array{id: string, min: int, max: int}> */
	private function dimensionList() : array{
		$list = [];
		foreach($this->server->getWorldManager()->getWorlds() as $world){
			$list[] = ["id" => $this->dimensionId($world), "min" => $world->getMinY(), "max" => $world->getMaxY()];
		}
		$ids = array_map(static fn(array $d) : string => $d["id"], $list);
		foreach(["minecraft:overworld", "minecraft:nether", "minecraft:the_end"] as $id){
			if(!in_array($id, $ids, true)){
				//scripts may ask for a dimension the server has no world for; it maps to the default world
				$list[] = ["id" => $id, "min" => -64, "max" => 320];
			}
		}
		return $list;
	}

	/**
	 * Worlds as dimensions: the default world is minecraft:overworld, the first world named like "nether" or "end"
	 * is minecraft:nether / minecraft:the_end, and any other world is "amber:<folder name>".
	 */
	public function dimensionId(World $world) : string{
		$default = $this->server->getWorldManager()->getDefaultWorld();
		if($world === $default){
			return "minecraft:overworld";
		}
		$folder = strtolower($world->getFolderName());
		foreach(["nether" => "minecraft:nether", "end" => "minecraft:the_end"] as $needle => $id){
			if(str_contains($folder, $needle) && $this->firstWorldLike($needle) === $world){
				return $id;
			}
		}
		return "amber:" . str_replace(" ", "_", $folder);
	}

	private function firstWorldLike(string $needle) : ?World{
		foreach($this->server->getWorldManager()->getWorlds() as $world){
			if($world !== $this->server->getWorldManager()->getDefaultWorld() && str_contains(strtolower($world->getFolderName()), $needle)){
				return $world;
			}
		}
		return null;
	}

	public function worldFor(string $dimension) : ?World{
		$dimension = strtolower($dimension);
		$manager = $this->server->getWorldManager();
		if($dimension === "" || $dimension === "minecraft:overworld" || $dimension === "overworld"){
			return $manager->getDefaultWorld();
		}
		foreach($manager->getWorlds() as $world){
			if($this->dimensionId($world) === $dimension || ($dimension === "nether" && $this->dimensionId($world) === "minecraft:nether") || (($dimension === "the_end" || $dimension === "end") && $this->dimensionId($world) === "minecraft:the_end")){
				return $world;
			}
		}
		return $manager->getDefaultWorld();
	}

	private function dimensionNetworkId(World $world) : int{
		return match($this->dimensionId($world)){
			"minecraft:nether" => DimensionIds::NETHER,
			"minecraft:the_end" => DimensionIds::THE_END,
			default => DimensionIds::OVERWORLD,
		};
	}

	// ---------------------------------------------------------------- dynamic properties

	private function dynamicFile() : string{
		return Path::join($this->runtimeDir, "dynamic_properties.json");
	}

	private function loadDynamic() : void{
		$file = $this->dynamicFile();
		$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		$this->dynamic = is_array($data) ? $data : [];
	}

	public function saveDynamic() : void{
		if(!$this->dynamicDirty){
			return;
		}
		$this->dynamicDirty = false;
		if(!is_dir($this->runtimeDir)){
			@mkdir($this->runtimeDir, 0777, true);
		}
		Filesystem::safeFilePutContents($this->dynamicFile(), (string) json_encode($this->dynamic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
	}

	/** Players' properties persist by name; other entities' live as long as the entity. */
	private function scopeKey(string $scope) : ?string{
		if($scope === self::DEFAULT_SCOPE || str_starts_with($scope, "amber:")){
			return $scope;
		}
		$e = $this->entity($scope);
		if($e instanceof Player){
			return "player:" . $e->getName();
		}
		return null;
	}

	/** @return array<string, mixed> */
	private function scope(string $scope) : array{
		$key = $this->scopeKey($scope);
		if($key !== null){
			$values = $this->dynamic[$key] ?? [];
			unset($values["__tags"]);
			return $values;
		}
		$e = $this->entity($scope);
		return $e === null ? [] : ($this->entityDynamic[$e] ?? []);
	}

	private function readDynamic(string $scope, string $key) : mixed{
		return $this->scope($scope)[$key] ?? null;
	}

	private function writeDynamic(string $scope, string $key, mixed $value) : void{
		$scopeKey = $this->scopeKey($scope);
		if($scopeKey !== null){
			if($value === null){
				unset($this->dynamic[$scopeKey][$key]);
			}else{
				$this->dynamic[$scopeKey][$key] = $value;
			}
			$this->dynamicDirty = true;
			return;
		}
		$e = $this->entity($scope);
		if($e !== null){
			$values = $this->entityDynamic[$e] ?? [];
			if($value === null){
				unset($values[$key]);
			}else{
				$values[$key] = $value;
			}
			$this->entityDynamic[$e] = $values;
		}
	}

	private function clearScope(string $scope) : void{
		$scopeKey = $this->scopeKey($scope);
		if($scopeKey !== null){
			$tags = $this->dynamic[$scopeKey]["__tags"] ?? null;
			$this->dynamic[$scopeKey] = $tags === null ? [] : ["__tags" => $tags];
			$this->dynamicDirty = true;
			return;
		}
		$e = $this->entity($scope);
		if($e !== null){
			$this->entityDynamic[$e] = [];
		}
	}

	/** @internal an add-on entity ran one of its events (world.afterEvents.dataDrivenEntityTrigger) */
	public function onEntityTrigger(AddonEntity $entity, string $event) : void{
		$this->queueEvent("dataDrivenEntityTrigger", ["entity" => $entity->getId(), "event" => $event]);
	}

	/** @internal an add-on projectile hit something (these are not PocketMine projectiles, so no server event) */
	public function onProjectileHit(AddonEntity $projectile, ?Entity $hit) : void{
		$pos = $projectile->getPosition();
		$data = [
			"projectile" => $projectile->getId(),
			"source" => $projectile->getOwningEntityId(),
			"dim" => $this->dimensionId($projectile->getWorld()),
			"location" => ["x" => $pos->x, "y" => $pos->y, "z" => $pos->z],
		];
		if($hit !== null){
			$this->queueEvent("projectileHitEntity", $data + ["entity" => $hit->getId()]);
		}else{
			$block = $projectile->getWorld()->getBlock($pos->addVector($projectile->getMotion()->normalize()));
			$wire = self::blockWire($block);
			$bp = $block->getPosition();
			$this->queueEvent("projectileHitBlock", $data + ["block" => ["dim" => $data["dim"], "x" => $bp->getFloorX(), "y" => $bp->getFloorY(), "z" => $bp->getFloorZ()] + $wire]);
		}
	}

	/** @internal answers from ScriptForm */
	public function formResponse(int $formId, mixed $data) : void{
		$this->queueEvent("__form", ["fid" => $formId, "data" => $data]);
	}

	private static function bare(string $id) : string{
		return strtolower(str_replace("minecraft:", "", $id));
	}

	/** @var array<string, true> */
	private array $reported = [];

	private function reportOnce(string $key, string $message) : void{
		if(!isset($this->reported[$key])){
			$this->reported[$key] = true;
			$this->logger->notice("[Scripts] $message");
		}
	}
}
