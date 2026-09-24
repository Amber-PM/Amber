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

namespace pocketmine\addon\world;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use function array_keys;
use function array_slice;
use function arsort;
use function asort;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function str_starts_with;
use function substr;
use const JSON_UNESCAPED_UNICODE;

/**
 * The Bedrock scoreboard for add-ons: objectives, scores and display slots, shared by scripts
 * (world.scoreboard), the /scoreboard command and plugins, saved across restarts, and shown to players on
 * the sidebar, the player list or below names.
 *
 * Participants are player names, "entity:<runtime id>" for entities, or any other string (fake players).
 */
final class AddonScoreboard{
	public const SLOTS = ["sidebar", "list", "belowname"];

	/** @var array<string, array{name: string, scores: array<string, int>}> */
	private array $objectives = [];
	/** @var array<string, array{objective: string, order: int}> slot => display */
	private array $display = [];
	private bool $dirty = false;
	private bool $displayDirty = false;

	public function __construct(private Server $server, private string $file){
		$data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
		if(is_array($data)){
			foreach(is_array($data["objectives"] ?? null) ? $data["objectives"] : [] as $id => $objective){
				if(is_array($objective)){
					$scores = [];
					foreach(is_array($objective["scores"] ?? null) ? $objective["scores"] : [] as $participant => $score){
						$scores[(string) $participant] = (int) $score;
					}
					$this->objectives[(string) $id] = ["name" => (string) ($objective["name"] ?? $id), "scores" => $scores];
				}
			}
			foreach(is_array($data["display"] ?? null) ? $data["display"] : [] as $slot => $display){
				if(is_array($display) && isset($this->objectives[(string) ($display["objective"] ?? "")])){
					$this->display[(string) $slot] = ["objective" => (string) $display["objective"], "order" => (int) ($display["order"] ?? 1)];
				}
			}
		}
	}

	public function hasObjective(string $id) : bool{ return isset($this->objectives[$id]); }

	/** @return array<string, string> id => display name */
	public function getObjectives() : array{
		$out = [];
		foreach($this->objectives as $id => $objective){
			$out[$id] = $objective["name"];
		}
		return $out;
	}

	public function addObjective(string $id, string $displayName) : bool{
		if(isset($this->objectives[$id]) || $id === ""){
			return false;
		}
		$this->objectives[$id] = ["name" => $displayName !== "" ? $displayName : $id, "scores" => []];
		$this->dirty = true;
		return true;
	}

	public function removeObjective(string $id) : bool{
		if(!isset($this->objectives[$id])){
			return false;
		}
		unset($this->objectives[$id]);
		foreach($this->display as $slot => $display){
			if($display["objective"] === $id){
				unset($this->display[$slot]);
				$this->broadcast(RemoveObjectivePacket::create(self::networkName($slot)));
			}
		}
		$this->dirty = $this->displayDirty = true;
		return true;
	}

	public function getScore(string $objective, string $participant) : ?int{
		return $this->objectives[$objective]["scores"][$participant] ?? null;
	}

	/** @return array<string, int> */
	public function getScores(string $objective) : array{
		return $this->objectives[$objective]["scores"] ?? [];
	}

	public function setScore(string $objective, string $participant, int $score) : bool{
		if(!isset($this->objectives[$objective])){
			return false;
		}
		$this->objectives[$objective]["scores"][$participant] = $score;
		$this->changed($objective);
		return true;
	}

	public function addScore(string $objective, string $participant, int $amount) : ?int{
		if(!isset($this->objectives[$objective])){
			return null;
		}
		$score = ($this->objectives[$objective]["scores"][$participant] ?? 0) + $amount;
		$this->objectives[$objective]["scores"][$participant] = $score;
		$this->changed($objective);
		return $score;
	}

	/** Removes a participant from one objective, or from all when $objective is null. */
	public function resetScore(?string $objective, string $participant) : bool{
		$had = false;
		foreach($objective === null ? array_keys($this->objectives) : [$objective] as $id){
			if(isset($this->objectives[$id]["scores"][$participant])){
				unset($this->objectives[$id]["scores"][$participant]);
				$this->changed($id);
				$had = true;
			}
		}
		return $had;
	}

	/** @return list<string> */
	public function getParticipants() : array{
		$all = [];
		foreach($this->objectives as $objective){
			foreach($objective["scores"] as $participant => $_){
				$all[$participant] = true;
			}
		}
		return array_keys($all);
	}

	/** @return array{objective: string, order: int}|null */
	public function getDisplay(string $slot) : ?array{ return $this->display[$slot] ?? null; }

	public function setDisplay(string $slot, ?string $objective, int $order = 1) : bool{
		if(!in_array($slot, self::SLOTS, true)){
			return false;
		}
		if($objective === null){
			if(isset($this->display[$slot])){
				unset($this->display[$slot]);
				$this->broadcast(RemoveObjectivePacket::create(self::networkName($slot)));
				$this->dirty = true;
			}
			return true;
		}
		if(!isset($this->objectives[$objective])){
			return false;
		}
		$this->display[$slot] = ["objective" => $objective, "order" => $order === 0 ? 0 : 1];
		$this->dirty = $this->displayDirty = true;
		return true;
	}

	private function changed(string $objective) : void{
		$this->dirty = true;
		foreach($this->display as $display){
			if($display["objective"] === $objective){
				$this->displayDirty = true;
			}
		}
	}

	/** Sends changed displays (once a tick at most) and saves when needed. */
	public function tick(int $currentTick) : void{
		if($this->displayDirty){
			$this->displayDirty = false;
			foreach($this->server->getOnlinePlayers() as $player){
				$this->sendTo($player);
			}
		}
		if($this->dirty && $currentTick % 200 === 0){
			$this->save();
		}
	}

	/** Shows every displayed objective to a player (on join, or after a change). */
	public function sendTo(Player $player) : void{
		$session = $player->getNetworkSession();
		foreach($this->display as $slot => $display){
			$objective = $this->objectives[$display["objective"]] ?? null;
			if($objective === null){
				continue;
			}
			$name = self::networkName($slot);
			$session->sendDataPacket(RemoveObjectivePacket::create($name));
			$session->sendDataPacket(SetDisplayObjectivePacket::create($slot, $name, $objective["name"], "dummy", $display["order"]));
			$scores = $objective["scores"];
			$display["order"] === 0 ? asort($scores) : arsort($scores);
			if($slot === "sidebar"){
				$scores = array_slice($scores, 0, 15, true);
			}
			$entries = [];
			$i = 0;
			foreach($scores as $participant => $score){
				$entry = new ScorePacketEntry();
				$entry->scoreboardId = ++$i;
				$entry->objectiveName = $name;
				$entry->score = $score;
				$online = str_starts_with($participant, "entity:") ? null : $this->server->getPlayerExact($participant);
				if($online !== null && $slot !== "sidebar"){
					$entry->type = ScorePacketEntry::TYPE_PLAYER;
					$entry->actorUniqueId = $online->getId();
				}else{
					$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
					$entry->customName = str_starts_with($participant, "entity:") ? substr($participant, 7) : $participant;
				}
				$entries[] = $entry;
			}
			if($entries !== []){
				$session->sendDataPacket(SetScorePacket::create(SetScorePacket::TYPE_CHANGE, $entries));
			}
		}
	}

	public function save() : void{
		$this->dirty = false;
		Filesystem::safeFilePutContents($this->file, (string) json_encode(["objectives" => $this->objectives, "display" => $this->display], JSON_UNESCAPED_UNICODE));
	}

	private function broadcast(RemoveObjectivePacket $packet) : void{
		foreach($this->server->getOnlinePlayers() as $player){
			$player->getNetworkSession()->sendDataPacket($packet);
		}
	}

	private static function networkName(string $slot) : string{
		return "amber_addon_" . $slot;
	}
}
