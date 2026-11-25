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

namespace pocketmine\network\mcpe\nethernet;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\TextFormat;

final class NetherNetCommand extends OverloadedCommand{
	public function __construct(private readonly NetherNetInterface $interface){
		parent::__construct('nethernet', 'Shows NetherNet transport status');
		// same audience as /status
		$this->setPermission(DefaultPermissionNames::COMMAND_STATUS);
		$this->addOverload(fn(CommandSender $sender) => $this->showStatus($sender));
	}

	private function showStatus(CommandSender $sender) : bool{
		foreach(self::format($this->interface->getStatus()) as $line){ $sender->sendMessage($line); }
		return true;
	}

	/**
	 * @param array{signaling: ?string, tls: ?string, schemes: array{https: int, http: int, tlsRefused: int, tlsFailed: int}|null, udp: string, advertised: list<string>, stun: ?string, trustedProxies: list<string>, players: int, negotiating: int, closeCodes: array<string, int>, native: array<string, int>|null, connections: list<array{id: int, ip: string, rttMs: ?int, bytesSent: int, bytesReceived: int, sendQueueBytes: int, receiveQueueBytes: int}>} $status
	 * @return list<string>
	 */
	public static function format(array $status) : array{
		$gold = TextFormat::GOLD;
		$red = TextFormat::RED;
		$lines = [TextFormat::GREEN . '---- ' . TextFormat::RESET . 'NetherNet' . TextFormat::GREEN . ' ----'];
		$lines[] = $gold . 'Signaling TCP: ' . $red . ($status['signaling'] ?? 'stopped') . $gold . ' HTTPS: ' . $red . ($status['tls'] ?? 'off') . $gold . ' ICE UDP: ' . $red . $status['udp'];
		$lines[] = $gold . 'Public address: ' . $red . ($status['advertised'] !== [] ? implode(', ', $status['advertised']) : 'not set') . $gold . ' STUN: ' . $red . ($status['stun'] ?? 'off') . $gold . ' PROXY protocol: ' . $red . ($status['trustedProxies'] !== [] ? implode(', ', $status['trustedProxies']) : 'off');
		if($status['schemes'] !== null){
			$schemes = $status['schemes'];
			$lines[] = $gold . 'Signaling requests: HTTPS ' . $red . $schemes['https'] . $gold . ', HTTP ' . $red . $schemes['http'] . $gold . ', TLS refused ' . $red . $schemes['tlsRefused'] . $gold . ', TLS failed ' . $red . $schemes['tlsFailed'];
		}
		$lines[] = $gold . 'Players: ' . $red . $status['players'] . $gold . ' Signaling in progress: ' . $red . $status['negotiating'];
		if($status['native'] !== null){
			$parts = [];
			foreach($status['native'] as $name => $value){ $parts[] = $name . ' ' . $red . $value . $gold; }
			$lines[] = $gold . 'Native: ' . implode(', ', $parts);
		}
		if($status['closeCodes'] !== []){
			arsort($status['closeCodes']);
			$parts = [];
			foreach($status['closeCodes'] as $code => $count){ $parts[] = $code . ' ' . $red . $count . $gold; }
			$lines[] = $gold . 'Closed since start: ' . implode(', ', $parts);
		}
		foreach(array_slice($status['connections'], 0, 20) as $connection){
			$lines[] = $gold . '#' . $connection['id'] . ' ' . $red . $connection['ip'] . $gold . ' rtt ' . $red . ($connection['rttMs'] !== null ? $connection['rttMs'] . 'ms' : '?') . $gold .
				' sent ' . $red . round($connection['bytesSent'] / 1024) . 'K' . $gold . ' received ' . $red . round($connection['bytesReceived'] / 1024) . 'K' . $gold .
				' queued out ' . $red . $connection['sendQueueBytes'] . $gold . ' in ' . $red . $connection['receiveQueueBytes'];
		}
		if(count($status['connections']) > 20){ $lines[] = $gold . '... ' . (count($status['connections']) - 20) . ' more'; }
		return $lines;
	}
}
