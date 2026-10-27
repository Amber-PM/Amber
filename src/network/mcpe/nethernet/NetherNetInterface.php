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

use amber\nethernet\backend\WebRtcPeer;
use amber\nethernet\Channel;
use amber\nethernet\Connection;
use amber\nethernet\ConnectionState;
use amber\nethernet\identity\IdentityHandler;
use amber\nethernet\signaling\HttpServer;
use amber\nethernet\signaling\HttpSignaling;
use amber\nethernet\signaling\ServerMetadata;
use amber\nethernet\SignalingPeerAddress;
use amber\nethernet\SignalingPeerConnection;
use amber\nethernet\Transport;
use amber\nethernet\TransportListener;
use pocketmine\network\AdvancedNetworkInterface;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\Network;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\GameMode;
use pocketmine\Server;

final class NetherNetInterface implements AdvancedNetworkInterface, TransportListener{
	private ?Transport $transport = null;
	private ?HttpServer $http = null;
	private bool $started = false;
	private bool $stopped = false;
	private bool $polling = false;
	private string $name;
	private Network $network;
	private \Logger $logger;
	/** @var array<int, array{connection: Connection, sender: NetherNetPacketSender, address: SignalingPeerAddress, session: NetworkSession|null}> */
	private array $clients = [];
	/** @var array<string, int> */
	private array $connectionsByIp = [];
	/** @var array<string, float> */
	private array $blocked = [];
	/** @var array<string, float> */
	private array $lastWarnings = [];

	/**
	 * @param \Closure(Connection, NetherNetPacketSender, SignalingPeerAddress): NetworkSession|null $sessionFactory
	 * @param \Closure(): WebRtcPeer|null $peerFactory
	 */
	public function __construct(
		private Server $server,
		private NetherNetConfig $config,
		private PacketBroadcaster $broadcaster,
		private EntityEventBroadcaster $entities,
		private TypeConverter $converter,
		private ?\Closure $sessionFactory = null,
		private ?\Closure $peerFactory = null,
		?\Logger $logger = null,
		private ?IdentityHandler $identityHandler = null
	){
		$this->network = $server->getNetwork();
		$this->logger = $logger ?? $server->getLogger();
		$this->name = $server->getMotd();
	}

	public function start() : void{
		if($this->started || $this->stopped){ throw new NetworkInterfaceStartException('NetherNet interface cannot be restarted'); }
		$this->started = true;
		try{
			if($this->peerFactory === null){
				if(!PHP_ZTS){ throw new NetworkInterfaceStartException('Amber requires the official PHP ZTS runtime with WebRTC support'); }
				try{ $this->config->assertBackendAvailable(); }
				catch(\Throwable $e){ throw new NetworkInterfaceStartException('WebRTC runtime is missing or incompatible; reinstall the official Amber PHP runtime (' . $e->getMessage() . ')', 0, $e); }
				$backend = $this->config->getBackendName();
				$extension = $backend === 'ext-rtc' ? 'rtc' : 'webrtc';
				$this->logger->info('NetherNet backend: ' . $backend . ' (' . $extension . ' ' . (phpversion($extension) ?: 'unknown') . ', webrtc loaded=' . (extension_loaded('webrtc') ? 'yes' : 'no') . ', rtc loaded=' . (extension_loaded('rtc') ? 'yes' : 'no') . ')');
			}
			$limits = $this->config->createTransportConfig();
			$identity = $this->identityHandler ?? $this->config->createIdentityHandler($this->server->getAuthKeyProvider());
			$reference = \WeakReference::create($this);
			$signaling = new HttpSignaling($limits, static function() use ($reference) : ServerMetadata{
				$interface = $reference->get() ?? throw new \RuntimeException('NetherNet interface unavailable');
				$mode = $interface->server->getGamemode();
				$gameType = match($mode){ GameMode::CREATIVE => 1, GameMode::ADVENTURE => 2, default => 0 };
				return new ServerMetadata($interface->name, ProtocolInfo::CURRENT_PROTOCOL, ProtocolInfo::MINECRAFT_VERSION_NETWORK, '', $interface->network->getValidConnectionCount(), $interface->server->getMaxPlayers(), $gameType);
			}, $this);
			$this->transport = new Transport($limits, $identity, $signaling, $this->peerFactory ?? $this->config->createPeer(...));
			$signaling->bind($this->transport);
			$this->http = new HttpServer($limits, $signaling, $this->config->getBind(), ioBytes: 8192, admit: static function(string $ip) use ($reference) : bool{
				return $reference->get()?->admits($ip) ?? false;
			});
		}catch(\Throwable $e){
			$this->shutdown();
			throw new NetworkInterfaceStartException('NetherNet initialization failed: ' . $e->getMessage(), 0, $e);
		}
	}

	public function getAddress() : ?string{ return $this->http?->getAddress(); }
	public function setName(string $name) : void{ $this->name = $name; }
	public function setNetwork(Network $network) : void{ $this->network = $network; }

	public function tick() : void{
		if($this->stopped || !$this->started || $this->polling){ return; }
		$this->polling = true;
		try{
			$now = hrtime(true) / 1_000_000_000;
			foreach($this->blocked as $ip => $until){ if($until <= $now){ unset($this->blocked[$ip]); } }
			$this->transport?->poll();
			$this->http?->poll(0); // never wait on the server tick
		}catch(\Throwable $e){
			$this->shutdown();
			throw new \RuntimeException('Required NetherNet transport stopped after polling failure (' . get_class($e) . ')', 0, $e);
		}finally{
			$this->polling = false;
		}
	}

	public function onAnswer(int $id, string $answerSdp) : void{ // signaling owns the answer
		$this->logger->debug('NetherNet answer ready for connection ' . $id);
	}

	public function onOpen(Connection $connection) : void{
		$id = $connection->getId();
		$this->logger->debug('NetherNet data channels open for connection ' . $id);
		if(isset($this->clients[$id])){
			if($this->clients[$id]['connection'] !== $connection){ $connection->close('Duplicate connection ID'); }
			return;
		}
		$address = $connection instanceof SignalingPeerConnection ? $connection->getSignalingPeerAddress() : null;
		if($this->stopped || $this->transport === null || $connection->getState() !== ConnectionState::OPEN || $address === null){
			$connection->close('NetherNet connection is unavailable or has no trusted signaling address');
			return;
		}
		if(!$this->admits($address->ip)){
			$connection->close('NetherNet signaling address admission rejected');
			return;
		}
		if(($this->connectionsByIp[$address->ip] ?? 0) >= $this->config->getMaxPerIp()){
			$this->warnLimited('max-per-ip', 'NetherNet rejected a player from ' . $address->ip . ' because max-per-ip is reached');
			$connection->close('NetherNet signaling address admission rejected');
			return;
		}
		try{ new NetherNetSessionPolicy($connection->getIdentity()); }catch(\Throwable){
			$connection->close('NetherNet authenticated transport identity is invalid');
			return;
		}
		$reference = \WeakReference::create($this);
		$connectionReference = \WeakReference::create($connection);
		$sender = new NetherNetPacketSender($connection, static function(string $reason) use ($reference, $connectionReference) : void{
			$owner = $reference->get();
			$peer = $connectionReference->get();
			if($owner !== null && $peer !== null && ($owner->clients[$peer->getId()]['connection'] ?? null) === $peer){ $owner->release($peer->getId(), $reason); }
		});
		$this->clients[$id] = ['connection' => $connection, 'sender' => $sender, 'address' => $address, 'session' => null];
		$this->connectionsByIp[$address->ip] = ($this->connectionsByIp[$address->ip] ?? 0) + 1;
		try{
			$session = $this->sessionFactory !== null ? ($this->sessionFactory)($connection, $sender, $address) : new NetworkSession($this->server, $this->network->getSessionManager(), PacketPool::getInstance(), $sender, $this->broadcaster, $this->entities, ZlibCompressor::getInstance(), $this->converter, $address->ip, $address->port, new NetherNetSessionPolicy($connection->getIdentity()));
		}catch(\Throwable $e){
			$this->release($id, 'NetherNet session construction failed');
			$this->logger->error('NetherNet session construction failed (' . get_class($e) . ')');
			return;
		}
		if($session === null){
			$this->release($id, 'NetherNet session factory rejected admission');
			return;
		}
		if($this->stopped || ($this->clients[$id]['connection'] ?? null) !== $connection || $connection->getState() !== ConnectionState::OPEN || !$session->isConnected()){
			$session->onClientDisconnect('NetherNet closed during session construction');
			$this->network->getSessionManager()->remove($session);
			$this->release($id, 'NetherNet closed during session construction');
			return;
		}
		$this->clients[$id]['session'] = $session;
	}

	public function onPayload(Connection $connection, string $payload, Channel $channel) : void{
		$entry = $this->clients[$connection->getId()] ?? null;
		if($this->stopped || $entry === null || $entry['connection'] !== $connection || $entry['session'] === null){ return; }
		$session = $entry['session'];
		if(!$session->isConnected()){
			$this->release($connection->getId(), 'NetherNet game channel unavailable');
			return;
		}
		// both channels carry game batches since clients may send movement unreliably
		try{
			$session->handleEncoded($payload); // no raknet fe prefix and no extra fragment header
		}catch(PacketHandlingException){
			// only the sender goes since players behind cgnat share an ip
			$this->blocked[$entry['address']->ip] = max($this->blocked[$entry['address']->ip] ?? 0, hrtime(true) / 1_000_000_000 + 5);
			$this->release($connection->getId(), 'Invalid NetherNet game payload');
		}catch(\Throwable $e){
			$this->release($connection->getId(), 'NetherNet game payload handler failed');
			$this->logger->error('NetherNet game payload handler failed (' . get_class($e) . ')');
		}
	}

	public function onClose(int $id, string $code, string $reason) : void{
		// ids stay unique because this transport never restarts
		$joined = isset($this->clients[$id]);
		$this->release($id, 'NetherNet transport closed'); // do not log arbitrary peer/native reason text
		// codes come from the library so they are safe to log
		if(!$joined && $code === 'connect_timeout'){
			$this->warnLimited('connect', 'NetherNet player could not reach the ICE UDP port (connect_timeout), check that it is open and set advertised-address when the server is behind NAT');
		}elseif(!$joined && str_starts_with($code, 'identity_')){
			$this->warnLimited('identity', 'NetherNet player failed authentication (' . $code . ')');
		}else{
			$this->logger->debug('NetherNet connection ' . $id . ' closed (' . $code . ')');
		}
	}

	private function warnLimited(string $kind, string $message) : void{
		$now = hrtime(true) / 1_000_000_000;
		if(($this->lastWarnings[$kind] ?? -INF) + 60 > $now){
			$this->logger->debug($message);
			return;
		}
		$this->lastWarnings[$kind] = $now;
		$this->logger->warning($message);
	}

	private function release(int $id, string $reason) : void{
		$entry = $this->clients[$id] ?? null;
		if($entry === null){ return; }
		unset($this->clients[$id]); // detach before close can reenter
		$ip = $entry['address']->ip;
		if(--$this->connectionsByIp[$ip] === 0){ unset($this->connectionsByIp[$ip]); }
		try{ $entry['session']?->onClientDisconnect($reason); }finally{
			if($entry['session'] !== null){ $this->network->getSessionManager()->remove($entry['session']); }
			$entry['sender']->close($reason);
		}
	}

	public function shutdown() : void{
		if($this->stopped){ return; }
		$this->stopped = true;
		$http = $this->http;
		$transport = $this->transport;
		$this->http = null;
		$this->transport = null;
		try{ $http?->stop(); }catch(\Throwable){ /* still close peers */ }
		try{ $transport?->stop(); }catch(\Throwable){ /* still dispose local sessions */ }
		foreach(array_keys($this->clients) as $id){
			try{ $this->release($id, 'NetherNet interface shutdown'); }catch(\Throwable){ /* drain other clients */ }
		}
		$this->connectionsByIp = $this->blocked = [];
	}

	public function blockAddress(string $address, int $timeout = 300) : void{
		if(filter_var($address, FILTER_VALIDATE_IP) === false){ return; }
		$this->blocked[$address] = $timeout > 0 ? hrtime(true) / 1_000_000_000 + $timeout : INF;
		foreach($this->clients as $id => $entry){ if($entry['address']->ip === $address){ $this->release($id, 'NetherNet signaling address blocked'); } }
	}
	public function unblockAddress(string $address) : void{ unset($this->blocked[$address]); }
	private function admits(string $ip) : bool{
		return ($this->blocked[$ip] ?? 0) <= hrtime(true) / 1_000_000_000 && $this->server->getIPBans()->getEntry($ip) === null;
	}
	public function addRawPacketFilter(string $regex) : void{} // no raw datagrams are forwarded by this interface
	public function sendRawPacket(string $address, int $port, string $payload) : void{} // datagram interfaces own raw udp
}
