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

namespace pocketmine\utils;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use function spl_object_id;

trait ProtocolSingletonTrait{
	/** @var self[] */
	private static $instance = [];

	/** @var (\Closure(self): void)[] */
	private static array $creationListeners = [];

	private static function make(int $protocolId) : self{
		return new self($protocolId);
	}

	private function __construct(protected readonly int $protocolId){
		//NOOP
	}

	public static function getInstance(int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : self{
		if(!isset(self::$instance[$protocolId])){
			$instance = self::make($protocolId);

			foreach(self::$creationListeners as $listener){
				$listener($instance);
			}

			self::$instance[$protocolId] = $instance;
		}

		return self::$instance[$protocolId];
	}

	public function getProtocolId() : int{
		return $this->protocolId;
	}

	/**
	 * @param \Closure(self): void $listener
	 */
	public static function addCreationListener(\Closure $listener) : void{
		self::$creationListeners[] = $listener;
	}

	/**
	 * @return array<int, self>
	 */
	public static function getAll() : array{
		return self::$instance;
	}

	/**
	 * @param Player[] $players
	 *
	 * @return Player[][]
	 */
	public static function sortByProtocol(array $players) : array{
		$sortPlayers = [];

		foreach($players as $player){
			$protocolId = $player->getNetworkSession()->getProtocolId();
			$sortPlayers[$protocolId][spl_object_id($player)] = $player;
		}

		return $sortPlayers;
	}

	public static function setInstance(self $instance, int $protocolId) : void{
		self::$instance[$protocolId] = $instance;
	}

	public static function reset() : void{
		self::$instance = [];
	}
}
