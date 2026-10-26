<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

require_once dirname(__DIR__, 3) . '/nethernet-bootstrap.php';

use amber\nethernet\identity\PeerIdentity;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\JwtUtils;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\entity\Skin;
use pocketmine\lang\Language;
use pocketmine\network\NetworkSessionManager;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\encryption\EncryptionContext;
use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\handler\ResourcePacksPacketHandler;
use pocketmine\network\mcpe\handler\SessionStartPacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\PacketRateLimiter;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\NetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\Server;
use pocketmine\ServerConfigGroup;
use pocketmine\timings\Timings;
use pocketmine\utils\Config;
use pocketmine\utils\ObjectSet;
use Ramsey\Uuid\Uuid;

final class PolicyTestSender implements PacketSender{
	/** @var list<string> */
	public array $sent = [];
	public bool $closed = false;
	public function send(string $payload, bool $immediate, ?int $receiptId) : void{ $this->sent[] = $payload; }
	public function close(string $reason = 'unknown reason') : void{ $this->closed = true; }
}

final class SessionTransportPolicyTest extends TestCase{
	public static function setUpBeforeClass() : void{
		if(!class_exists(\amber\nethernet\Transport::class)){ self::fail('Required NetherNet package is unavailable; run composer install'); }
	}

	private static function key() : string{
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1', 'config' => __DIR__ . '/openssl.cnf']);
		self::assertNotFalse($key);
		return JwtUtils::emitDerPublicKey($key);
	}
	private static function property(object $object, string $name, mixed $value) : void{
		(new \ReflectionProperty(NetworkSession::class, $name))->setValue($object, $value);
	}
	/** @return array{NetworkSession, PolicyTestSender, Server} */
	private function session(?NetherNetSessionPolicy $policy, bool $expectEncryption = false) : array{
		Timings::init();
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['getLanguage', 'isProtocolAllowed', 'getConfigGroup', 'getOfflinePlayerData', 'getResourcePackManager', 'getAsyncPool', 'isEducationContentEnabled'])->getMock();
		$language = $this->createMock(Language::class);
		$language->method('translate')->willReturn('translated');
		$server->method('getLanguage')->willReturn($language);
		$server->method('isProtocolAllowed')->willReturn(true);
		$config = $this->createMock(Config::class);
		$config->method('getNested')->willReturn(null);
		$server->method('getConfigGroup')->willReturn(new ServerConfigGroup($config, $config));
		$server->method('getOfflinePlayerData')->willReturn(null);
		$packs = $this->createMock(ResourcePackManager::class);
		$packs->method('getResourceStack')->willReturn([]);
		$server->method('getResourcePackManager')->willReturn($packs);
		if($expectEncryption){
			$server->expects(self::once())->method('getAsyncPool')->willThrowException(new \RuntimeException('RakNet encryption task path'));
		}else{
			$server->expects(self::never())->method('getAsyncPool');
		}
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->onlyMethods(['setProtocolId'])->getMock();
		$sender = new PolicyTestSender();
		foreach(['server' => $server, 'sender' => $sender, 'transportPolicy' => $policy, 'compressor' => ZlibCompressor::getInstance(), 'protocolId' => ProtocolInfo::CURRENT_PROTOCOL,'manager' => new NetworkSessionManager(), 'logger' => new \PrefixedLogger($this->createMock(\Logger::class), 'test'), 'compressedQueue' => new \SplQueue(), 'disposeHooks' => new ObjectSet()] as $name => $value){ self::property($session, $name, $value); }
		return [$session, $sender, $server];
	}
	private static function auth(NetworkSession $session, bool $authenticated, ?string $key, bool $authRequired = false, ?string $error = null) : void{
		(new \ReflectionMethod(NetworkSession::class, 'setAuthenticationStatus'))->invoke($session, $authenticated, $authRequired, $error, $key);
	}
	private static function flush(NetworkSession $session) : void{ (new \ReflectionMethod(NetworkSession::class, 'flushGamePacketQueue'))->invoke($session); }
	private static function handler(NetworkSession $session) : mixed{ return (new \ReflectionProperty(NetworkSession::class, 'handler'))->getValue($session); }
	public function testImmediateCleanupDoesNotDependOnResourcePackCompletionSender() : void{
		[$session] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', self::key())));
		self::assertFalse($session->usesLocalResourcePackSendCompletion());
		$inventory = (new \ReflectionClass(\pocketmine\network\mcpe\InventoryManager::class))->newInstanceWithoutConstructor();
		$reference = \WeakReference::create($inventory);
		self::property($session, 'invManager', $inventory);
		unset($inventory);
		$session->onClientDisconnect('closed');
		self::assertNull($reference->get());
	}

	public function testAuthenticatedPolicyBindsLoginWithoutGameEncryption() : void{
		self::assertTrue(class_exists(NetherNetSessionPolicy::class), 'NetherNet needs an explicit per-session policy');
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1', 'config' => __DIR__ . '/openssl.cnf']);
		self::assertNotFalse($key);
		$der = JwtUtils::emitDerPublicKey($key);
		$policy = new NetherNetSessionPolicy(new PeerIdentity('trusted', $der));
		self::assertFalse($policy->usesGameEncryption());
		self::assertNull($policy->validateLogin(true, $der));
		self::assertNotNull($policy->validateLogin(false, $der));
		self::assertNotNull($policy->validateLogin(true, null));
		self::assertNotNull($policy->validateLogin(true, 'invalid DER'));
	}

	public function testDifferentTrustedKeysDoNotBind() : void{
		$policy = new NetherNetSessionPolicy(new PeerIdentity('same-name', self::key()));
		self::assertNotNull($policy->validateLogin(true, self::key()));
	}

	public function testWrongCurveTransportKeyFailsClosed() : void{
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'config' => __DIR__ . '/openssl.cnf']);
		self::assertNotFalse($key);
		$details = openssl_pkey_get_details($key);
		self::assertNotFalse($details);
		$der = base64_decode(str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"], '', $details['key']), true);
		self::assertNotFalse($der);
		$this->expectException(\InvalidArgumentException::class);
		new NetherNetSessionPolicy(new PeerIdentity('wrong curve', $der));
	}

	public function testMalformedTransportKeyFailsClosed() : void{
		$this->expectException(\InvalidArgumentException::class);
		new NetherNetSessionPolicy(new PeerIdentity('untrusted', 'garbage'));
	}

	public function testNetworkSettingsAndSessionTransitionEnableZlib() : void{
		[$session, $sender, $server] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', self::key())));
		$instance = new \ReflectionProperty(Server::class, 'instance');
		$previous = $instance->getValue();
		$instance->setValue(null, $server);
		try{
			$handler = new SessionStartPacketHandler($session, fn() => (new \ReflectionMethod(NetworkSession::class, 'onSessionStartSuccess'))->invoke($session));
			self::assertTrue($handler->handleRequestNetworkSettings(RequestNetworkSettingsPacket::create(ProtocolInfo::CURRENT_PROTOCOL)));
			self::assertCount(1, $sender->sent);
			$buffers = iterator_to_array(PacketBatch::decodeRaw(new ByteBufferReader($sender->sent[0])));
			$settings = new NetworkSettingsPacket();
			$settings->decode(new ByteBufferReader($buffers[0]), ProtocolInfo::CURRENT_PROTOCOL);
			self::assertSame(CompressionAlgorithm::ZLIB, $settings->getCompressionAlgorithm());
			self::assertTrue((new \ReflectionProperty(NetworkSession::class, 'enableCompression'))->getValue($session));
			self::assertInstanceOf(LoginPacketHandler::class, self::handler($session));
		}finally{ $instance->setValue(null, $previous); }
	}

	public function testRakNetCompressionStillActivatesAfterHandshake() : void{
		[$session] = $this->session(null);
		(new \ReflectionMethod(NetworkSession::class, 'onSessionStartSuccess'))->invoke($session);
		self::assertTrue((new \ReflectionProperty(NetworkSession::class, 'enableCompression'))->getValue($session));
	}

	public function testIncomingBatchesAfterSessionStartDecodeZlibAndUncompressedMarkers() : void{
		[$session] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', self::key())));
		(new \ReflectionMethod(NetworkSession::class, 'onSessionStartSuccess'))->invoke($session);
		self::property($session, 'packetPool', PacketPool::getInstance());
		self::property($session, 'packetBatchLimiter', new PacketRateLimiter('batches', 100, 10));
		self::property($session, 'gamePacketLimiter', new PacketRateLimiter('packets', 100, 10));
		$received = [];
		$handler = new class($received) extends PacketHandler{
			/** @param list<int> $received */
			public function __construct(private array &$received){}
			public function handleRequestNetworkSettings(RequestNetworkSettingsPacket $packet) : bool{
				$this->received[] = $packet->getProtocolVersion();
				return true;
			}
		};
		$session->setHandler($handler);
		$stream = new ByteBufferWriter();
		PacketBatch::encodePackets($stream, ProtocolInfo::CURRENT_PROTOCOL, [RequestNetworkSettingsPacket::create(ProtocolInfo::CURRENT_PROTOCOL)]);
		$session->handleEncoded(chr(CompressionAlgorithm::ZLIB) . ZlibCompressor::getInstance()->compress($stream->getData()));
		$session->handleEncoded(chr(CompressionAlgorithm::NONE) . $stream->getData());
		self::assertSame([ProtocolInfo::CURRENT_PROTOCOL, ProtocolInfo::CURRENT_PROTOCOL], $received);
	}

	public function testUnauthenticatedLoginRejectedEvenWhenServerAllowsOffline() : void{
		$key = self::key();
		[$session, $sender] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', $key)));
		self::auth($session, false, $key, false);
		self::assertFalse($session->isConnected());
		self::assertFalse((new \ReflectionProperty(NetworkSession::class, 'loggedIn'))->getValue($session));
		self::assertTrue($sender->closed);
	}

	public function testMismatchedAuthenticatedLoginRejectedBeforeSuccess() : void{
		[$session] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', self::key())));
		self::auth($session, true, self::key());
		self::assertFalse($session->isConnected());
		self::assertFalse((new \ReflectionProperty(NetworkSession::class, 'loggedIn'))->getValue($session));
		self::assertNull($session->getPlayer());
	}

	public function testBoundAuthenticatedLoginEntersResourcePacksWithoutEncryptionHandshake() : void{
		$key = self::key();
		[$session, $sender] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', $key)));
		self::property($session, 'info', new XboxLivePlayerInfo('123', 'tester', Uuid::uuid4(), new Skin('test', str_repeat("\0", 8192)), 'en_US'));
		$previous = EncryptionContext::$ENABLED;
		EncryptionContext::$ENABLED = true;
		try{
			self::auth($session, true, $key);
			self::assertTrue($session->isConnected());
			self::assertTrue((new \ReflectionProperty(NetworkSession::class, 'loggedIn'))->getValue($session));
			self::assertInstanceOf(ResourcePacksPacketHandler::class, self::handler($session));
			self::assertNull((new \ReflectionProperty(NetworkSession::class, 'cipher'))->getValue($session));
			self::assertTrue(EncryptionContext::$ENABLED);
			self::flush($session);
			$buffers = iterator_to_array(PacketBatch::decodeRaw(new ByteBufferReader($sender->sent[0])));
			$status = new PlayStatusPacket();
			$status->decode(new ByteBufferReader($buffers[0]), ProtocolInfo::CURRENT_PROTOCOL);
			self::assertSame(PlayStatusPacket::LOGIN_SUCCESS, $status->status);
		}finally{ EncryptionContext::$ENABLED = $previous; }
	}

	public function testLateAuthenticationAfterDisconnectDoesNothing() : void{
		$key = self::key();
		[$session, $sender] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', $key)));
		$session->onClientDisconnect('closed during authentication');
		self::auth($session, true, $key);
		self::assertFalse($session->isConnected());
		self::assertSame([], $sender->sent);
		self::assertFalse((new \ReflectionProperty(NetworkSession::class, 'loggedIn'))->getValue($session));
	}

	public function testRakNetStillEntersExistingGameEncryptionTaskPath() : void{
		[$session] = $this->session(null, true);
		self::property($session, 'info', new XboxLivePlayerInfo('123', 'tester', Uuid::uuid4(), new Skin('test', str_repeat("\0", 8192)), 'en_US'));
		$previous = EncryptionContext::$ENABLED;
		EncryptionContext::$ENABLED = true;
		try{
			$this->expectException(\RuntimeException::class);
			$this->expectExceptionMessage('RakNet encryption task path');
			self::auth($session, true, self::key());
		}finally{ EncryptionContext::$ENABLED = $previous; }
	}

	public function testOriginalLoginVerificationFailureCannotBeOverriddenByMatchingIdentity() : void{
		$key = self::key();
		[$session] = $this->session(new NetherNetSessionPolicy(new PeerIdentity('trusted', $key)));
		self::auth($session, true, $key, true, 'Login verifier rejected signature');
		self::assertFalse($session->isConnected());
		self::assertFalse((new \ReflectionProperty(NetworkSession::class, 'loggedIn'))->getValue($session));
	}

	public function testBroadcastBuffersShareOneMarkedBatchForMultipleSessions() : void{
		$policy = new NetherNetSessionPolicy(new PeerIdentity('trusted', self::key()));
		[$first, $firstSender, $server] = $this->session($policy);
		[$second, $secondSender] = $this->session($policy);
		// compression starts once NetworkSettings was sent
		self::property($first, 'enableCompression', true);
		self::property($second, 'enableCompression', true);
		$packet = PlayStatusPacket::create(PlayStatusPacket::LOGIN_SUCCESS);
		(new StandardPacketBroadcaster($server, ProtocolInfo::CURRENT_PROTOCOL))->broadcastPackets([$first, $second], [$packet]);
		self::flush($first);
		self::flush($second);
		$stream = new ByteBufferWriter();
		PacketBatch::encodePackets($stream, ProtocolInfo::CURRENT_PROTOCOL, [$packet]);
		// below the zlib threshold the batch carries the uncompressed marker, as over RakNet
		self::assertSame([chr(CompressionAlgorithm::NONE) . $stream->getData()], $firstSender->sent);
		self::assertSame($firstSender->sent, $secondSender->sent);
	}

	public function testLargeBatchesAreZlibCompressed() : void{
		Timings::init();
		$server = (new \ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Server::class, 'networkCompressionAsync'))->setValue($server, false);
		$batch = str_repeat("\x02\x02\x00", 1000);
		$prepared = $server->prepareBatch($batch, ProtocolInfo::CURRENT_PROTOCOL, ZlibCompressor::getInstance());
		self::assertIsString($prepared);
		self::assertSame(chr(CompressionAlgorithm::ZLIB), $prepared[0]);
		self::assertLessThan(strlen($batch), strlen($prepared));
		self::assertSame($batch, ZlibCompressor::getInstance()->decompress(substr($prepared, 1)));
	}
}
