<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

require_once dirname(__DIR__, 3) . '/nethernet-bootstrap.php';

use amber\nethernet\Channel;
use amber\nethernet\Connection;
use amber\nethernet\ConnectionState;
use amber\nethernet\ConnectionStats;
use amber\nethernet\identity\PeerIdentity;
use amber\nethernet\SendResult;
use amber\nethernet\SignalingPeerAddress;
use amber\nethernet\SignalingPeerConnection;
use PHPUnit\Framework\TestCase;
use pocketmine\lang\Language;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\Network;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use pocketmine\timings\Timings;

if(interface_exists(SignalingPeerConnection::class)){
final class InterfaceConnection implements SignalingPeerConnection{
	public ConnectionState $state = ConnectionState::OPEN;
	public int $closes = 0;
	public ?\Closure $onClose = null;
	public bool $completeImmediately = true;
	/** @var list<\Closure(bool): void> */
	public array $nativeCallbacks = [];
	/** @var list<string> */
	public array $sent = [];
	public function __construct(private int $id, private PeerIdentity $identity, private ?SignalingPeerAddress $address){}
	public function getId() : int{ return $this->id; }
	public function getNetworkId() : string{ return 'client-controlled-not-an-IP'; }
	public function getIdentity() : PeerIdentity{ return $this->identity; }
	public function getSignalingPeerAddress() : ?SignalingPeerAddress{ return $this->address; }
	public function getState() : ConnectionState{ return $this->state; }
	public function send(string $payload, Channel $channel = Channel::RELIABLE, bool $immediate = false, ?\Closure $accepted = null) : SendResult{
		$this->sent[] = $payload;
		if($accepted !== null){ $this->nativeCallbacks[] = $accepted; if($this->completeImmediately){ $accepted(true); } }
		return SendResult::ACCEPTED;
	}
	public ?ConnectionStats $stats = null;
	public function getStats() : ?ConnectionStats{ return $this->stats; }
	public function close(string $reason = 'local close') : void{ ++$this->closes; $this->state = ConnectionState::CLOSED; $this->onClose?->__invoke(); }
}

}

final class NetherNetInterfaceTest extends TestCase{
	public function testSharedRawPacketApiStillDispatchesToDatagramInterfaces() : void{
		$this->make();
		$datagrams = $this->createMock(\pocketmine\network\AdvancedNetworkInterface::class);
		$datagrams->expects(self::once())->method('sendRawPacket')->with('127.0.0.1', 19132, 'query');
		self::assertTrue($this->network->registerInterface($datagrams));
		$this->network->sendPacket('127.0.0.1', 19132, 'query');
	}
	public static function setUpBeforeClass() : void{
		if(!class_exists(\amber\nethernet\Transport::class)){ self::fail('Required NetherNet package is unavailable; run composer install'); }
	}

	private string $directory;
	private NetherNetConfig $config;
	private Network $network;
	private Server $server;
	private PacketBroadcaster $broadcaster;
	private EntityEventBroadcaster $entities;
	private TypeConverter $converter;
	private PeerIdentity $identity;
	private \Logger $logger;
	/** @var list<NetworkSession> */
	private array $created = [];
	/** @var array<int, list<string>> */
	private array $received = [];
	private ?NetherNetInterface $interface = null;

	protected function setUp() : void{
		Timings::init();
		$this->directory = sys_get_temp_dir() . '/amber-interface-' . bin2hex(random_bytes(6));
		mkdir($this->directory);
		$operator = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1', 'config' => __DIR__ . '/openssl.cnf']);
		$issuer = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048, 'config' => __DIR__ . '/openssl.cnf']);
		openssl_pkey_export($operator, $private, null, ['config' => __DIR__ . '/openssl.cnf']);
		file_put_contents($this->directory . '/operator.pem', $private);
		file_put_contents($this->directory . '/issuer.pem', openssl_pkey_get_details($issuer)['key']);
		$public = openssl_pkey_get_details($operator)['key'];
		$der = base64_decode(str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"], '', $public), true);
		$this->identity = new PeerIdentity('trusted', $der);
		$this->config = NetherNetConfig::fromArray(['enabled' => true, 'bind' => 'tcp://127.0.0.1:0', 'operator-domain' => 'test.invalid', 'private-key-file' => 'operator.pem', 'trusted-key-files' => ['issuer' => 'issuer.pem'], 'issuer' => 'issuer', 'audience' => 'audience', 'max-per-ip' => 1], $this->directory);
		$logger = $this->logger = $this->createMock(\Logger::class);
		$this->network = new Network($logger);
		$this->network->setName('Amber');
		$this->server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['getNetwork', 'getLanguage', 'getMotd', 'getIPBans', 'getMaxPlayers', 'getGamemode'])->getMock();
		$this->server->method('getNetwork')->willReturn($this->network);
		$language = $this->createMock(Language::class);
		$language->method('translate')->willReturn('translated');
		$this->server->method('getLanguage')->willReturn($language);
		$this->server->method('getMotd')->willReturn('Amber');
		$this->server->method('getMaxPlayers')->willReturn(20);
		$this->server->method('getGamemode')->willReturn(\pocketmine\player\GameMode::SURVIVAL);
		$this->server->method('getIPBans')->willReturn($this->createMock(\pocketmine\permission\BanList::class));
		$this->broadcaster = $this->createMock(PacketBroadcaster::class);
		$this->entities = $this->createMock(EntityEventBroadcaster::class);
		$this->converter = $this->getMockBuilder(TypeConverter::class)->disableOriginalConstructor()->getMock();
	}

	protected function tearDown() : void{
		$this->interface?->shutdown();
		foreach(['operator.pem', 'issuer.pem'] as $file){ unlink($this->directory . '/' . $file); }
		rmdir($this->directory);
	}

	private function make(?\Closure $factory = null, ?\Closure $peerFactory = null, bool $native = false) : NetherNetInterface{
		$factory ??= function(Connection $connection, NetherNetPacketSender $sender, SignalingPeerAddress $address) : NetworkSession{
			$session = $this->makeSession($connection, $sender, $address, true);
			self::assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $session);
			$session->method('handleEncoded')->willReturnCallback(function(string $payload) use ($connection) : void{ $this->received[$connection->getId()][] = $payload; });
			$this->created[] = $session;
			return $session;
		};
		$this->interface = new NetherNetInterface($this->server, $this->config, $this->broadcaster, $this->entities, $this->converter, $factory, $native ? null : ($peerFactory ?? static fn() => throw new \LogicException('No backend is needed for this listener boundary test')), $this->logger, $this->config->createIdentityHandler());
		self::assertTrue($this->network->registerInterface($this->interface));
		return $this->interface;
	}

	private function makeSession(Connection $connection, NetherNetPacketSender $sender, SignalingPeerAddress $address, bool $mockReceive = false) : NetworkSession{
		$session = $mockReceive ? $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->onlyMethods(['handleEncoded'])->getMock() : (new \ReflectionClass(NetworkSession::class))->newInstanceWithoutConstructor();
		foreach(['server' => $this->server, 'manager' => $this->network->getSessionManager(), 'sender' => $sender, 'compressor' => ZlibCompressor::getInstance(), 'ip' => $address->ip, 'port' => $address->port, 'transportPolicy' => new NetherNetSessionPolicy($connection->getIdentity()), 'logger' => new \PrefixedLogger($this->logger, 'test'), 'compressedQueue' => new \SplQueue(), 'disposeHooks' => new \pocketmine\utils\ObjectSet()] as $name => $value){
			(new \ReflectionProperty(NetworkSession::class, $name))->setValue($session, $value);
		}
		$this->network->getSessionManager()->add($session);
		return $session;
	}

	private function connection(int $id, string $ip = '127.0.0.1') : InterfaceConnection{
		return new InterfaceConnection($id, $this->identity, new SignalingPeerAddress($ip, 40000 + $id));
	}

	public function testInterfaceWidePollingFailureIsFatalAfterSessionCleanup() : void{
		$interface = $this->make();
		$connection = $this->connection(1);
		$interface->onOpen($connection);
		// inject a transport-wide fault
		$broken = (new \ReflectionClass(\amber\nethernet\Transport::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(NetherNetInterface::class, 'transport'))->setValue($interface, $broken);
		try{
			$interface->tick();
			self::fail('A required transport must not disappear while the server remains ready');
		}catch(\RuntimeException $e){
			self::assertStringContainsString('Required NetherNet transport stopped', $e->getMessage());
			self::assertInstanceOf(\Error::class, $e->getPrevious());
		}
		self::assertSame(0, $this->network->getConnectionCount());
		self::assertFalse($this->created[0]->isConnected());
		self::assertSame(1, $connection->closes);
	}

	public function testSessionDisconnectHookFailureDoesNotStopOtherClients() : void{
		$interface = $this->make();
		$a = $this->connection(1);
		$b = $this->connection(2, '127.0.0.2');
		$interface->onOpen($a);
		$interface->onOpen($b);
		$this->created[0]->getDisposeHooks()->add(static function() : void{
			throw new \RuntimeException('individual disconnect hook failed');
		});
		$laterHookCalled = false;
		$this->created[0]->getDisposeHooks()->add(static function() use (&$laterHookCalled) : void{ $laterHookCalled = true; });
		$interface->onClose(1, $a->getNetworkId(), 'closed');
		self::assertTrue($laterHookCalled);
		self::assertFalse($this->created[0]->isConnected());
		self::assertSame(1, $this->network->getConnectionCount());
		self::assertSame(1, $a->closes);
		self::assertTrue($this->created[1]->isConnected());
		$interface->onPayload($b, 'still-open', Channel::RELIABLE);
		self::assertSame([2 => ['still-open']], $this->received);
		$interface->tick();
	}

	public function testDisconnectEventFailureStillCompletesSessionDisposal() : void{
		$interface = $this->make();
		$peer = $this->connection(1);
		$interface->onOpen($peer);
		$session = $this->created[0];
		$inventory = (new \ReflectionClass(\pocketmine\network\mcpe\InventoryManager::class))->newInstanceWithoutConstructor();
		$reference = \WeakReference::create($inventory);
		(new \ReflectionProperty(NetworkSession::class, 'invManager'))->setValue($session, $inventory);
		unset($inventory);
		$disposed = false;
		$session->getDisposeHooks()->add(static function() use (&$disposed) : void{ $disposed = true; });
		$receipt = new \pocketmine\promise\PromiseResolver();
		$rejected = false;
		$receipt->getPromise()->onCompletion(static function() : void{}, static function() use (&$rejected) : void{ $rejected = true; });
		(new \ReflectionProperty(NetworkSession::class, 'ackPromisesByReceiptId'))->setValue($session, [1 => [$receipt]]);
		$registration = new \pocketmine\event\RegisteredListener(static function() : void{ throw new \RuntimeException('individual disconnect event failed'); }, \pocketmine\event\EventPriority::NORMAL, $this->createMock(\pocketmine\plugin\Plugin::class), true, new \pocketmine\timings\TimingsHandler('disconnect failure'));
		$list = \pocketmine\event\HandlerListManager::global()->getListFor(\pocketmine\event\player\SessionDisconnectEvent::class);
		$list->register($registration);
		try{
			$interface->onClose(1, $peer->getNetworkId(), 'closed');
			self::assertFalse($session->isConnected());
			self::assertFalse((new \ReflectionProperty(NetworkSession::class, 'connected'))->getValue($session));
			self::assertFalse((new \ReflectionProperty(NetworkSession::class, 'disconnectGuard'))->getValue($session));
			self::assertTrue($disposed);
			self::assertNull($reference->get());
			self::assertTrue($rejected);
			self::assertSame(0, $this->network->getConnectionCount());
			self::assertSame(1, $peer->closes);
		}finally{ $list->unregister($registration); }
	}

	public function testCloseDisposesInventoryEvenAfterManagerRemoval() : void{
		$interface = $this->make();
		$connection = $this->connection(1);
		$interface->onOpen($connection);
		$inventory = (new \ReflectionClass(\pocketmine\network\mcpe\InventoryManager::class))->newInstanceWithoutConstructor();
		$reference = \WeakReference::create($inventory);
		(new \ReflectionProperty(NetworkSession::class, 'invManager'))->setValue($this->created[0], $inventory);
		unset($inventory);
		$interface->onClose(1, $connection->getNetworkId(), 'closed');
		self::assertNull($reference->get());
	}

	public function testRegistrationDuplicateOpenIndependentClientsAndForwarding() : void{
		$interface = $this->make();
		$a = $this->connection(1);
		$b = $this->connection(2, '127.0.0.2');
		$interface->onOpen($a);
		$interface->onOpen($a);
		$interface->onOpen($b);
		self::assertCount(2, $this->created);
		self::assertSame(2, $this->network->getConnectionCount());
		$interface->onPayload($b, 'second-batch', Channel::RELIABLE);
		$interface->onPayload($a, 'first-batch', Channel::RELIABLE);
		self::assertSame([2 => ['second-batch'], 1 => ['first-batch']], $this->received);
		self::assertSame('127.0.0.1', $this->created[0]->getIp());
		$interface->shutdown();
		self::assertSame(0, $this->network->getConnectionCount());
		self::assertFalse($this->created[0]->isConnected());
		$interface->shutdown();
		self::assertSame(1, $a->closes);
	}

	public function testAddressBanAndPerIpLimitUseSocketAddress() : void{
		$interface = $this->make();
		$this->network->blockAddress('127.0.0.1');
		$a = $this->connection(1);
		$interface->onOpen($a);
		self::assertSame(1, $a->closes);
		self::assertCount(0, $this->created);
		$this->network->unblockAddress('127.0.0.1');
		$interface->onOpen($this->connection(2));
		$second = $this->connection(3);
		$interface->onOpen($second);
		self::assertSame(1, $second->closes);
		self::assertSame(1, $this->network->getConnectionCount());
	}

	public function testUnreliableChannelPayloadReachesTheSession() : void{
		$interface = $this->make();
		$connection = $this->connection(1);
		$interface->onOpen($connection);
		$interface->onPayload($connection, 'movement', Channel::UNRELIABLE);
		self::assertSame([1 => ['movement']], $this->received);
		self::assertSame(0, $connection->closes);
		self::assertTrue($this->created[0]->isConnected());
	}

	public function testInvalidPayloadOnlyDropsTheSenderAndBlocksNewJoins() : void{
		$this->config = NetherNetConfig::fromArray(['enabled' => true, 'bind' => 'tcp://127.0.0.1:0', 'operator-domain' => 'test.invalid', 'private-key-file' => 'operator.pem', 'trusted-key-files' => ['issuer' => 'issuer.pem'], 'issuer' => 'issuer', 'audience' => 'audience', 'max-per-ip' => 4], $this->directory);
		$interface = $this->make(function(Connection $connection, NetherNetPacketSender $sender, SignalingPeerAddress $address) : NetworkSession{
			$session = $this->makeSession($connection, $sender, $address, true);
			self::assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $session);
			$session->method('handleEncoded')->willReturnCallback(static function(string $payload) : void{
				if($payload === 'bad'){ throw new PacketHandlingException('bad batch'); }
			});
			$this->created[] = $session;
			return $session;
		});
		$bad = $this->connection(1);
		$good = $this->connection(2);
		$interface->onOpen($bad);
		$interface->onOpen($good);
		$interface->onPayload($bad, 'bad', Channel::RELIABLE);
		self::assertSame(1, $bad->closes);
		self::assertSame(0, $good->closes);
		self::assertTrue($this->created[1]->isConnected());
		$late = $this->connection(3);
		$interface->onOpen($late);
		self::assertSame(1, $late->closes);
		self::assertSame(1, $this->network->getConnectionCount());
	}

	public function testNativeStatsUpdatePingAndBandwidthByDifference() : void{
		$interface = $this->make();
		$connection = $this->connection(1);
		$interface->onOpen($connection);
		$connection->stats = new ConnectionStats(37, 1000, 400, 0, 0);
		$interface->tick();
		self::assertSame(37, $this->created[0]->getPing());
		$bandwidth = $this->network->getBandwidthTracker();
		self::assertSame(1000, $bandwidth->getSend()->getTotalBytes());
		self::assertSame(400, $bandwidth->getReceive()->getTotalBytes());
		$connection->stats = new ConnectionStats(null, 1500, 450, 0, 0);
		$interface->tick();
		self::assertSame(1000, $bandwidth->getSend()->getTotalBytes());
		(new \ReflectionProperty(NetherNetInterface::class, 'nextStatsAt'))->setValue($interface, 0.0);
		$interface->tick();
		self::assertSame(37, $this->created[0]->getPing());
		self::assertSame(1500, $bandwidth->getSend()->getTotalBytes());
		self::assertSame(450, $bandwidth->getReceive()->getTotalBytes());
	}

	public function testFailedNegotiationsAreLoggedWithRateLimit() : void{
		$this->logger->expects(self::once())->method('warning')->with(self::stringContains('connect_timeout'));
		$interface = $this->make();
		$interface->onClose(1, 'connect_timeout', 'DTLS or channels timed out');
		$interface->onClose(2, 'connect_timeout', 'DTLS or channels timed out');
	}

	public function testPerIpRejectionIsLogged() : void{
		$this->logger->expects(self::once())->method('warning')->with(self::stringContains('max-per-ip'));
		$interface = $this->make();
		$interface->onOpen($this->connection(1));
		$second = $this->connection(2);
		$interface->onOpen($second);
		self::assertSame(1, $second->closes);
	}

	public function testMissingAddressAndMalformedIdentityFailClosed() : void{
		$interface = $this->make();
		$a = new InterfaceConnection(1, $this->identity, null);
		$b = new InterfaceConnection(2, new PeerIdentity('untrusted', 'invalid'), new SignalingPeerAddress('127.0.0.1', 3));
		$interface->onOpen($a);
		$interface->onOpen($b);
		self::assertCount(0, $this->created);
		self::assertSame(1, $a->closes);
		self::assertSame(1, $b->closes);
	}

	public function testReentrantCloseDuringConstructionDisposesResultAndManagerEntry() : void{
		$interface = $this->make(function(Connection $connection, NetherNetPacketSender $sender, SignalingPeerAddress $address) : NetworkSession{
			$this->interface->onClose($connection->getId(), 'closed', 'construction race');
			return $this->makeSession($connection, $sender, $address);
		});
		$interface->onOpen($this->connection(1));
		self::assertSame(0, $this->network->getConnectionCount());
	}

	public function testLatePayloadAndDuplicateCloseCannotReachAnotherConnection() : void{
		$interface = $this->make();
		$a = $this->connection(1);
		$a->onClose = static function() use ($interface) : void{ $interface->onClose(1, 'local_close', 'closed'); };
		$interface->onOpen($a);
		$interface->onClose(1, 'client_close', 'closed');
		$b = $this->connection(2);
		$interface->onOpen($b);
		$interface->onPayload($a, 'stale', Channel::RELIABLE);
		$interface->onClose(1, 'late', 'late');
		$interface->onPayload($b, 'current', Channel::RELIABLE);
		self::assertSame([2 => ['current']], $this->received);
		self::assertSame(1, $this->network->getConnectionCount());
	}

	public function testSessionInitiatedCloseCannotEmitDuplicateDisconnectEvents() : void{
		$interface = $this->make();
		$peer = $this->connection(1);
		$peer->onClose = static function() use ($interface) : void{ $interface->onClose(1, 'local_close', 'closed'); };
		$interface->onOpen($peer);
		$events = 0;
		$registration = new \pocketmine\event\RegisteredListener(static function() use (&$events) : void{ ++$events; }, \pocketmine\event\EventPriority::NORMAL, $this->createMock(\pocketmine\plugin\Plugin::class), true, new \pocketmine\timings\TimingsHandler('disconnect count'));
		$list = \pocketmine\event\HandlerListManager::global()->getListFor(\pocketmine\event\player\SessionDisconnectEvent::class);
		$list->register($registration);
		try{
			$this->created[0]->onClientDisconnect('session initiated');
			self::assertSame(1, $events);
			self::assertSame(0, $this->network->getConnectionCount());
		}finally{ $list->unregister($registration); }
	}

	public function testResourcePackFlowUsesProductionSenderNativeCompletionWithoutRemoteAck() : void{
		$interface = $this->make();
		$peer = $this->connection(1);
		$peer->completeImmediately = false;
		$peer->onClose = static function() use ($interface) : void{ $interface->onClose(1, 'local_close', 'closed'); };
		$interface->onOpen($peer);
		$session = $this->created[0];
		(new \ReflectionProperty(NetworkSession::class, 'loggedIn'))->setValue($session, true);
		$pack = $this->createMock(\pocketmine\resourcepacks\ResourcePack::class);
		$id = '11111111-1111-1111-1111-111111111111';
		$pack->method('getPackId')->willReturn($id);
		$pack->method('getPackSize')->willReturn(524288);
		$pack->method('getPackChunk')->willReturn('chunk');
		$pack->method('getSha256')->willReturn(str_repeat("\0", 32));
		$completed = false;
		$handler = new \pocketmine\network\mcpe\handler\ResourcePacksPacketHandler($session, [$pack], [], false, false, false, static function() use (&$completed) : void{ $completed = true; });
		(new \ReflectionProperty(NetworkSession::class, 'handler'))->setValue($session, $handler);
		$response = \pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket::class;
		$handler->handleResourcePackClientResponse($response::create($response::STATUS_SEND_PACKS, [$id]));
		for($index = 0; $index < 2; ++$index){ $handler->handleResourcePackChunkRequest(\pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket::create($id, $index)); }
		$flush = new \ReflectionMethod(NetworkSession::class, 'flushGamePacketQueue');
		$flush->invoke($session);
		self::assertCount(1, $peer->sent);
		self::assertFalse($completed);
		$session->handleAckReceipt(0);
		$flush->invoke($session);
		self::assertCount(1, $peer->sent);
		($peer->nativeCallbacks[0])(true);
		$flush->invoke($session);
		self::assertCount(2, $peer->sent);
		($peer->nativeCallbacks[0])(true);
		($peer->nativeCallbacks[1])(true);
		self::assertFalse($completed);
		$handler->handleResourcePackClientResponse($response::create($response::STATUS_HAVE_ALL_PACKS, []));
		$handler->handleResourcePackClientResponse($response::create($response::STATUS_COMPLETED, []));
		self::assertTrue($completed);
		self::assertSame(1, $this->network->getConnectionCount());
	}

	public function testProductionHttpAndTransportVerifyIdentityAndForwardReassembledBatches() : void{
		require_once dirname(__DIR__, 5) . '/vendor/amber-pm/nethernet/tests/phpunit/identity/IdentityFixture.php';
		require_once dirname(__DIR__, 5) . '/vendor/amber-pm/nethernet/tests/phpunit/transport/FakePeer.php';
		$fixture = new \amber\nethernet\tests\identity\IdentityFixture();
		file_put_contents($this->directory . '/issuer.pem', openssl_pkey_get_details($fixture->issuer)['key']);
		$this->config = NetherNetConfig::fromArray(['enabled' => true, 'bind' => 'tcp://127.0.0.1:0', 'operator-domain' => 'test.invalid', 'private-key-file' => 'operator.pem', 'trusted-key-files' => ['issuer' => 'issuer.pem'], 'issuer' => 'issuer', 'audience' => 'game'], $this->directory);
		$backend = new \amber\nethernet\tests\transport\FakePeer();
		$backend->capacity = 4096;
		$interface = $this->make(peerFactory: static fn() => $backend);
		$offer = $fixture->offer($backend::SDP, time());
		$socket = stream_socket_client('tcp://' . $interface->getAddress(), $code, $error, 1);
		self::assertNotFalse($socket);
		try{
			stream_set_blocking($socket, false);
			$request = "POST /v1/join/203.0.113.99 HTTP/1.1\r\nHost: test\r\nX-Forwarded-For: 203.0.113.99\r\nContent-Type: application/sdp\r\nContent-Length: " . strlen($offer) . "\r\n\r\n" . $offer;
			self::assertSame(strlen($request), fwrite($socket, $request));
			$response = '';
			for($ticks = 0; $ticks < 100 && (count($this->created) === 0 || !str_contains($response, 'HTTP/1.1 200')); ++$ticks){
				$interface->tick();
				$response .= fread($socket, 65536);
				usleep(1000);
			}
			self::assertStringContainsString('HTTP/1.1 200', $response);
			self::assertCount(1, $this->created);
			self::assertSame('127.0.0.1', $this->created[0]->getIp());
			$backend->incoming[Channel::RELIABLE->name][] = "\x00" . 'plain-application-batch';
			$interface->tick();
			self::assertSame([1 => ['plain-application-batch']], $this->received);
			$interface->shutdown();
			self::assertSame(0, $this->network->getConnectionCount());
			self::assertSame(1, $backend->closes);
		}finally{ fclose($socket); }
	}

	public function testShutdownDuringPartialSignalingClosesSocketWithoutCreatingSession() : void{
		$interface = $this->make();
		$socket = stream_socket_client('tcp://' . $interface->getAddress(), $code, $error, 1);
		self::assertNotFalse($socket);
		try{
			stream_set_blocking($socket, false);
			fwrite($socket, "POST /v1/join/id HTTP/1.1\r\nHost: test\r\nContent-Type: application/sdp\r\nContent-Length: 100\r\n\r\nv=0\r\n");
			$interface->tick();
			$interface->tick();
			$interface->shutdown();
			for($ticks = 0; $ticks < 100 && !feof($socket); ++$ticks){ fread($socket, 4096); usleep(1000); }
			self::assertTrue(feof($socket));
			self::assertSame(0, $this->network->getConnectionCount());
			self::assertCount(0, $this->created);
		}finally{ fclose($socket); }
	}

	public function testNullableFactoryRejectsWithoutOrphansAndReleasesPerIpReservation() : void{
		$attempts = 0;
		$interface = $this->make(function(Connection $connection, NetherNetPacketSender $sender, SignalingPeerAddress $address) use (&$attempts) : ?NetworkSession{
			if(++$attempts === 2){ return null; }
			return $this->makeSession($connection, $sender, $address);
		});
		$healthy = $this->connection(1, '127.0.0.2');
		$interface->onOpen($healthy);
		$rejected = $this->connection(2);
		$interface->onOpen($rejected);
		self::assertSame(ConnectionState::CLOSED, $rejected->state);
		self::assertSame(1, $rejected->closes);
		self::assertSame(1, $this->network->getConnectionCount());
		$replacement = $this->connection(3);
		$interface->onOpen($replacement);
		self::assertSame(2, $this->network->getConnectionCount());
		self::assertSame(ConnectionState::OPEN, $healthy->state);
		self::assertSame(ConnectionState::OPEN, $replacement->state);
		$interface->shutdown();
		self::assertSame(0, $this->network->getConnectionCount());
		self::assertSame(1, $rejected->closes);
	}

	public function testConstructionFailureDoesNotKeepPerIpReservation() : void{
		$attempts = 0;
		$interface = $this->make(function(Connection $connection, NetherNetPacketSender $sender, SignalingPeerAddress $address) use (&$attempts) : NetworkSession{
			if(++$attempts === 1){ throw new \RuntimeException('fixture construction failure'); }
			return $this->makeSession($connection, $sender, $address);
		});
		$failed = $this->connection(1);
		$interface->onOpen($failed);
		$interface->onOpen($this->connection(2));
		self::assertSame(1, $failed->closes);
		self::assertSame(1, $this->network->getConnectionCount());
	}

	public function testNativeWebRtcBridgeWithRealSignalingAndAuthenticatedTransport() : void{
		require_once dirname(__DIR__, 5) . '/vendor/amber-pm/nethernet/tests/phpunit/identity/IdentityFixture.php';
		if(!extension_loaded('webrtc')){ self::markTestSkipped('Native WebRTC backend unavailable'); }
		\amber\nethernet\backend\ExtWebRtcPeer::assertAvailable();
		$fixture = new \amber\nethernet\tests\identity\IdentityFixture();
		file_put_contents($this->directory . '/issuer.pem', openssl_pkey_get_details($fixture->issuer)['key']);
		$this->config = NetherNetConfig::fromArray(['enabled' => true, 'bind' => 'tcp://127.0.0.1:0', 'operator-domain' => 'test.invalid', 'private-key-file' => 'operator.pem', 'trusted-key-files' => ['issuer' => 'issuer.pem'], 'issuer' => 'issuer', 'audience' => 'game'], $this->directory);
		$interface = $this->make(native: true);
		$client = new \pmmp\webrtc\PeerConnection(\pmmp\webrtc\PeerConnectionOptions::create()->setMaxMessageSize(262144)->setIceTcpEnabled(false));
		$reliable = $client->createDataChannel('ReliableDataChannel', \pmmp\webrtc\DataChannelOptions::create());
		$unreliable = $client->createDataChannel('UnreliableDataChannel', \pmmp\webrtc\DataChannelOptions::create()->setUnordered(true)->setMaxRetransmits(0));
		$socket = null;
		try{
			$deadline = hrtime(true) + 10_000_000_000;
			while($client->getGatheringState() !== \pmmp\webrtc\GatheringState::COMPLETE && hrtime(true) < $deadline){ usleep(1000); }
			self::assertSame(\pmmp\webrtc\GatheringState::COMPLETE, $client->getGatheringState());
			$offer = $fixture->offer($client->getLocalDescription(), time());
			$socket = stream_socket_client('tcp://' . $interface->getAddress(), $code, $error, 1);
			self::assertNotFalse($socket);
			stream_set_blocking($socket, false);
			$request = "POST /v1/join/native HTTP/1.1\r\nHost: test\r\nContent-Type: application/sdp\r\nContent-Length: " . strlen($offer) . "\r\n\r\n" . $offer;
			self::assertSame(strlen($request), fwrite($socket, $request));
			$response = '';
			$answered = false;
			while(hrtime(true) < $deadline && (count($this->created) === 0 || !$reliable->isOpen())){
				$interface->tick();
				$response .= fread($socket, 65536);
				$split = strpos($response, "\r\n\r\n");
				if(!$answered && $split !== false && preg_match('/Content-Length: ([0-9]+)/i', substr($response, 0, $split), $match) === 1 && strlen($response) - $split - 4 >= (int) $match[1]){
					self::assertStringStartsWith('HTTP/1.1 200', $response);
					$client->setRemoteAnswer(substr($response, $split + 4, (int) $match[1]));
					$answered = true;
				}
				$client->pollDataChannels();
				usleep(1000);
			}
			self::assertTrue($answered);
			self::assertTrue($reliable->isOpen());
			self::assertCount(1, $this->created);
			self::assertTrue($reliable->send("\x00" . 'native-application-batch'));
			while($this->received === [] && hrtime(true) < $deadline){ $interface->tick(); usleep(1000); }
			self::assertSame([1 => ['native-application-batch']], $this->received);
			self::assertSame('127.0.0.1', $this->created[0]->getIp());
			$interface->shutdown();
			self::assertSame(0, $this->network->getConnectionCount());
		}finally{
			$reliable->close();
			$unreliable->close();
			$client->close();
			if(is_resource($socket)){ fclose($socket); }
		}
	}
}
