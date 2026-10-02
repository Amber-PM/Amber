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

use pocketmine\command\defaults\ProtocolsCommand;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\stats\ServerMetrics;
use pocketmine\utils\Filesystem;
use pocketmine\utils\TextFormat;
use pocketmine\VersionInfo;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function array_map;
use function array_values;
use function bin2hex;
use function chmod;
use function count;
use function file_get_contents;
use function hash_equals;
use function is_array;
use function is_file;
use function is_numeric;
use function is_string;
use function json_decode;
use function random_bytes;
use function round;
use function str_starts_with;
use function strlen;
use function substr;
use function time;
use function trim;
use const JSON_THROW_ON_ERROR;

/**
 * The built-in web panel (players, console, add-ons, performance) and the Prometheus endpoint.
 *
 * Everything but the static page and /metrics needs a token, sent as "Authorization: Bearer <token>". The admin
 * token can do everything; read-only tokens can look but not run commands. There are no cookies, so other sites
 * cannot make a logged-in browser act on the panel. Repeated wrong tokens from an address are refused for a while.
 */
final class WebPanel{
	public const SCOPE_ADMIN = "admin";
	public const SCOPE_READ = "read";

	private const MAX_AUTH_FAILURES = 10;
	private const AUTH_FAILURE_WINDOW = 60;
	private const MIN_TOKEN_LENGTH = 16;

	private const STATIC_FILES = [
		"/" => ["index.html", "text/html; charset=utf-8"],
		"/panel.js" => ["panel.js", "text/javascript; charset=utf-8"],
		"/panel.css" => ["panel.css", "text/css; charset=utf-8"],
	];

	private HttpServer $http;
	private WebLogBuffer $log;
	private MetricsHistory $history;
	private int $lastSample = 0;
	/** @var array<string, array{int, int}> address => failures, window start */
	private array $authFailures = [];
	/** @var list<string> */
	private array $readTokens = [];

	/**
	 * @param string[] $readTokens tokens that can view but not run commands
	 *
	 * @throws \RuntimeException if the address cannot be listened on, or the TLS files cannot be read
	 */
	public function __construct(
		private Server $server,
		string $address,
		int $port,
		private string $token,
		array $readTokens,
		private bool $metricsNeedToken,
		int $logLines = 1000,
		?string $tlsCertificate = null,
		?string $tlsKey = null
	){
		foreach($readTokens as $readToken){
			if(strlen($readToken) < self::MIN_TOKEN_LENGTH || hash_equals($this->token, $readToken)){
				$this->server->getLogger()->warning("Ignoring a web.read-only-tokens entry: tokens must be at least " . self::MIN_TOKEN_LENGTH . " characters and differ from web.token");
				continue;
			}
			$this->readTokens[] = $readToken;
		}
		$this->http = new HttpServer($address, $port, $this->handle(...), $tlsCertificate, $tlsKey);
		$this->history = new MetricsHistory();
		$this->log = new WebLogBuffer($logLines);
		$this->server->getLogger()->addAttachment($this->log);
	}

	public function isTls() : bool{
		return $this->http->isTls();
	}

	/**
	 * The configured token, or the one kept in web-token.txt (created with a random token if missing).
	 */
	public static function resolveToken(string $configured, string $dataPath, \Logger $logger) : string{
		if($configured !== ""){
			if(strlen($configured) < self::MIN_TOKEN_LENGTH){
				$logger->warning("web.token is shorter than " . self::MIN_TOKEN_LENGTH . " characters; use a long random token");
			}
			return $configured;
		}
		$file = Path::join($dataPath, "web-token.txt");
		$token = is_file($file) ? trim((string) @file_get_contents($file)) : "";
		if(strlen($token) < self::MIN_TOKEN_LENGTH){
			$token = bin2hex(random_bytes(24));
			Filesystem::safeFilePutContents($file, $token . "\n");
			@chmod($file, 0600);
			$logger->notice("Generated a web panel token in " . $file);
		}
		return $token;
	}

	public function tick() : void{
		$now = time();
		if($now - $this->lastSample >= MetricsHistory::INTERVAL){
			$this->lastSample = $now;
			$this->history->add($now, ServerMetrics::collect($this->server));
		}
		$this->http->tick();
	}

	public function shutdown() : void{
		$this->http->shutdown();
		$this->server->getLogger()->removeAttachment($this->log);
	}

	public function handle(HttpRequest $request) : HttpResponse{
		if(isset(self::STATIC_FILES[$request->path]) && $request->method === "GET"){
			[$file, $type] = self::STATIC_FILES[$request->path];
			$contents = @file_get_contents(Path::join(\pocketmine\RESOURCE_PATH, "web", $file));
			return $contents === false ? HttpResponse::error(404, "Not found") : new HttpResponse(200, $contents, $type);
		}
		if($request->path === "/favicon.ico"){
			return new HttpResponse(204, "");
		}
		if($request->path === "/metrics"){
			if($request->method !== "GET"){
				return HttpResponse::error(405, "Use GET");
			}
			if($this->metricsNeedToken && ($refusal = $this->authenticate($request, $scope)) !== null){
				return $refusal;
			}
			return new HttpResponse(200, PrometheusExporter::render(ServerMetrics::collect($this->server)), "text/plain; version=0.0.4; charset=utf-8");
		}
		if(!str_starts_with($request->path, "/api/")){
			return HttpResponse::error(404, "Not found");
		}
		$scope = null;
		if(($refusal = $this->authenticate($request, $scope)) !== null){
			return $refusal;
		}
		return match($request->method . " " . $request->path){
			"GET /api/session" => HttpResponse::json(["scope" => $scope, "tls" => $this->http->isTls()]),
			"GET /api/status" => HttpResponse::json($this->status()),
			"GET /api/history" => HttpResponse::json($this->history->toArray()),
			"GET /api/log" => $this->logLines($request),
			"GET /api/addons" => HttpResponse::json($this->addons()),
			"POST /api/command" => $scope === self::SCOPE_ADMIN ? $this->command($request) : HttpResponse::error(403, "This token can only view the panel"),
			default => HttpResponse::error(404, "Not found"),
		};
	}

	/**
	 * Checks the request's token. Returns the refusal to send, or null with $scope set to what the token allows.
	 *
	 * @param-out string|null $scope
	 */
	private function authenticate(HttpRequest $request, ?string &$scope) : ?HttpResponse{
		$scope = null;
		$address = $request->remoteAddress;
		$now = time();
		[$failures, $since] = $this->authFailures[$address] ?? [0, $now];
		if($now - $since >= self::AUTH_FAILURE_WINDOW){
			[$failures, $since] = [0, $now];
		}
		if(count($this->authFailures) > 1000){
			foreach($this->authFailures as $other => [, $otherSince]){
				if($now - $otherSince >= self::AUTH_FAILURE_WINDOW){
					unset($this->authFailures[$other]);
				}
			}
		}
		if($failures >= self::MAX_AUTH_FAILURES){
			return HttpResponse::error(429, "Too many failed logins, try again later");
		}
		$header = $request->getHeader("Authorization") ?? "";
		$given = str_starts_with($header, "Bearer ") ? substr($header, 7) : "";
		if($given !== "" && hash_equals($this->token, $given)){
			$scope = self::SCOPE_ADMIN;
		}elseif($given !== ""){
			foreach($this->readTokens as $readToken){
				if(hash_equals($readToken, $given)){
					$scope = self::SCOPE_READ;
				}
			}
		}
		if($scope === null){
			$this->authFailures[$address] = [$failures + 1, $since];
			return HttpResponse::error(401, "Missing or wrong token");
		}
		unset($this->authFailures[$address]);
		return null;
	}

	/** @return array<string, mixed> */
	private function status() : array{
		$m = ServerMetrics::collect($this->server);
		$players = [];
		foreach($this->server->getOnlinePlayers() as $player){
			$players[] = self::playerInfo($player);
		}
		$byVersion = [];
		foreach($m->playersByProtocol as $protocolId => $count){
			$byVersion[] = ["protocol" => $protocolId, "version" => ProtocolsCommand::getVersionLabel($protocolId), "players" => $count];
		}
		$cacheBytes = $hits = $misses = 0;
		foreach($m->chunkCaches as $cache){
			$cacheBytes += $cache["bytes"];
			$hits += $cache["hits"];
			$misses += $cache["misses"];
		}
		return [
			"server" => ["name" => VersionInfo::NAME, "version" => VersionInfo::VERSION()->getFullVersion(true), "motd" => TextFormat::clean($this->server->getMotd())],
			"uptime" => $m->uptime,
			"tps" => $m->tps,
			"tpsAverage" => $m->tpsAverage,
			"load" => $m->tickUsageAverage,
			"memory" => ["mainThread" => $m->mainThreadMemory, "process" => $m->processMemory],
			"players" => ["online" => $m->playerCount, "max" => $m->maxPlayers, "list" => $players, "byVersion" => $byVersion],
			"worlds" => array_map(static fn(string $name, array $w) : array => ["name" => $name] + $w, array_keys($m->worlds), array_values($m->worlds)),
			"chunkCache" => ["bytes" => $cacheBytes, "hits" => $hits, "misses" => $misses],
			"async" => ["workers" => count($m->asyncQueues), "max" => $m->asyncPoolSize, "queued" => $m->getQueuedAsyncTasks()],
			"scripts" => $m->scripts,
		];
	}

	/** @return array<string, mixed> */
	private static function playerInfo(Player $player) : array{
		$session = $player->getNetworkSession();
		$pos = $player->getPosition();
		return [
			"name" => $player->getName(),
			"protocol" => $session->getProtocolId(),
			"version" => ProtocolsCommand::getVersionLabel($session->getProtocolId()),
			"ping" => $session->getPing(),
			"world" => $player->getWorld()->getFolderName(),
			"position" => [round($pos->x, 1), round($pos->y, 1), round($pos->z, 1)],
			"gamemode" => $player->getGamemode()->name,
		];
	}

	private function logLines(HttpRequest $request) : HttpResponse{
		$after = $request->query["after"] ?? 0;
		[$lines, $next] = $this->log->getLinesAfter(is_numeric($after) ? (int) $after : 0);
		return HttpResponse::json(["lines" => $lines, "next" => $next]);
	}

	/** @return list<array<string, mixed>> */
	private function addons() : array{
		$packs = [];
		foreach($this->server->getAddonManager()->getPacks() as $pack){
			$packs[] = ["name" => $pack->getName(), "version" => $pack->getVersionString(), "type" => $pack->isResourcePack() ? "resource" : "behavior", "source" => $pack->getSource()];
		}
		return $packs;
	}

	private function command(HttpRequest $request) : HttpResponse{
		$type = $request->getHeader("Content-Type") ?? "";
		if(!str_starts_with($type, "application/json")){
			return HttpResponse::error(415, "Send JSON");
		}
		try{
			$data = json_decode($request->body, true, 4, JSON_THROW_ON_ERROR);
		}catch(\JsonException){
			return HttpResponse::error(400, "Invalid JSON");
		}
		$command = is_array($data) && is_string($data["command"] ?? null) ? trim($data["command"]) : "";
		if($command === "" || strlen($command) > 1000){
			return HttpResponse::error(400, "Give a command");
		}
		if($command[0] === "/"){
			$command = substr($command, 1);
		}
		$this->server->getLogger()->info("[Web panel] " . $request->remoteAddress . " ran: " . TextFormat::clean($command));
		$sender = new WebCommandSender($this->server, $this->server->getLanguage());
		$found = $this->server->dispatchCommand($sender, $command);
		return HttpResponse::json(["ok" => $found, "output" => $sender->getOutput()]);
	}
}
