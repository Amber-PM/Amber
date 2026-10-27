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

use amber\nethernet\SignalingPeerConnection;
use amber\nethernet\Transport;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\Server;
use pocketmine\YmlServerProperties as Yml;

final class NetherNetBootstrap{
	public static function register(Server $server, PacketBroadcaster $broadcaster, EntityEventBroadcaster $entities, TypeConverter $converter) : bool{
		$settings = $server->getConfigGroup()->getProperty(Yml::NETWORK_NETHERNET, []);
		if(!is_array($settings)){ throw new NetworkInterfaceStartException('network.nethernet must be a configuration map'); }
		try{
			$config = NetherNetConfig::fromArray($settings, $server->getDataPath(), $server->getPort(), $server->getIp(), $server->getMotd());
			if($config === null){
				$server->getLogger()->warning('NetherNet explicitly disabled. Legacy RakNet clients only, Minecraft 26.60 requires NetherNet');
				return false;
			}
			if(!class_exists(Transport::class) || !interface_exists(SignalingPeerConnection::class)){
				throw new NetworkInterfaceStartException('Required NetherNet library is missing or incompatible. Reinstall the official Amber distribution');
			}
			$interface = new NetherNetInterface($server, $config, $broadcaster, $entities, $converter);
			if(!$server->getNetwork()->registerInterface($interface)){
				$interface->shutdown();
				throw new NetworkInterfaceStartException('Required NetherNet interface registration was cancelled by a plugin');
			}
			$server->getLogger()->info('NetherNet signaling listening on ' . $interface->getAddress() . ' (TCP signaling address policy)');
			return true;
		}catch(NetworkInterfaceStartException $e){ throw $e; }
		catch(\Throwable $e){ throw new NetworkInterfaceStartException('Required NetherNet configuration could not initialize: ' . $e->getMessage(), 0, $e); }
	}
}
