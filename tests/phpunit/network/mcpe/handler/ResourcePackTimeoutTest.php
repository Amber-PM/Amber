<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\handler;

use pocketmine\network\mcpe\handler\fixtures\ResourcePackTimeoutClock as Clock;
use Logger;
use PrefixedLogger;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use SplQueue;
use PHPUnit\Framework\TestCase;
use pocketmine\lang\Language;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\PacketHandlingException;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\ObjectSet;

// reuse sender doubles with real sessions and handlers
require_once __DIR__ . '/ResourcePackFlowControlTest.php';
require_once __DIR__ . '/fixtures/ResourcePackTimeoutClock.php';

final class ResourcePackTimeoutTest extends TestCase{
	private const A = '11111111-1111-1111-1111-111111111111';
	private const B = '22222222-2222-2222-2222-222222222222';
	private NetworkSession $session;
	private ResourcePacksPacketHandler $handler;
	private FlowControlReceiptSender $sender;
	private bool $completed = false;

	protected function setUp() : void{
		Clock::$now = 1000.0;
		Timings::init();
	}
	protected function tearDown() : void{ Clock::$now = null; }

	private function set(object $object, string $name, mixed $value) : void{
		(new ReflectionProperty($object, $name))->setValue($object, $value);
	}
	/** @param array<string, int> $packs */
	private function make(bool $local = true, array $packs = [self::A => 40], bool $metadata = true) : void{
		$this->sender = $local ? new FlowControlLocalSender() : new FlowControlReceiptSender();
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['getLanguage'])->getMock();
		$language = $this->createMock(Language::class);
		$language->method('translate')->willReturn('translated');
		$server->method('getLanguage')->willReturn($language);
		$this->session = (new ReflectionClass(NetworkSession::class))->newInstanceWithoutConstructor();
		foreach(['server' => $server, 'sender' => $this->sender, 'transportPolicy' => null,
			'logger' => new PrefixedLogger($this->createMock(Logger::class), 'audit'),
			'compressedQueue' => new SplQueue(), 'disposeHooks' => new ObjectSet(),
			'loggedIn' => true, 'enableCompression' => false, 'connectTime' => time() + 10000] as $key => $value){
			$this->set($this->session, $key, $value);
		}
		$stack = [];
		foreach($packs as $id => $chunks){
			$pack = $this->createMock(ResourcePack::class);
			$pack->method('getPackId')->willReturn($id);
			$pack->method('getPackSize')->willReturn($chunks * 262144);
			$pack->method('getPackVersion')->willReturn('1.0.0');
			$pack->method('getPackChunk')->willReturnCallback(static fn(int $offset, int $length) => str_repeat('x', min($length, $chunks * 262144 - $offset)));
			$pack->method('getSha256')->willReturn(str_repeat("\0", 32));
			$stack[] = $pack;
		}
		$this->handler = new ResourcePacksPacketHandler($this->session, $stack, [], false, false, false, function() : void{ $this->completed = true; });
		$this->set($this->session, 'handler', $this->handler);
		$this->handler->setUp();
		if($metadata){
			self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_SEND_PACKS, array_keys($packs)));
		}
	}
	/** @param list<string> $ids */
	private function response(int $status, array $ids = []) : bool{
		return $this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create($status, $ids));
	}
	private function request(int $index, string $id = self::A) : bool{
		return $this->handler->handleResourcePackChunkRequest(ResourcePackChunkRequestPacket::create($id, $index));
	}
	private function sendChunk(int $index, string $id = self::A) : void{
		self::assertTrue($this->request($index, $id));
		(new ReflectionMethod(NetworkSession::class, 'flushGamePacketQueue'))->invoke($this->session);
		if($this->sender instanceof FlowControlLocalSender){
			$callback = $this->sender->callbacks[array_key_last($this->sender->callbacks)];
			$callback(true);
		}else{
			$sent = $this->sender->sent[array_key_last($this->sender->sent)];
			$this->session->handleAckReceipt($sent[1]);
		}
	}

	public function testActiveTransferCanFinishAfter120Seconds() : void{
		$this->make();
		for($i = 0; $i < 40; ++$i){
			Clock::$now = 1000.0 + $i * 4;
			$this->session->tick();
			self::assertTrue($this->session->isConnected(), 'Active transfer disconnected at elapsed ' . ($i * 4));
			$this->sendChunk($i);
		}
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS));
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_COMPLETED));
		self::assertTrue($this->completed);
	}
	public function testIdleBeforeAcceptingPacksTimesOutAt120Seconds() : void{
		$this->make(metadata: false);
		Clock::$now = 1119.999; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		Clock::$now = 1120.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
	}
	public function testDuplicateChunkCannotKeepSessionAlive() : void{
		$this->make(); $this->sendChunk(0);
		Clock::$now = 1119.0;
		self::assertFalse($this->request(0));
		self::assertFalse($this->session->isConnected());
	}
	public function testCompletedTransferNoLongerTimesOut() : void{
		$this->make(packs: [self::A => 1]); $this->sendChunk(0);
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS));
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_COMPLETED));
		Clock::$now = 4000.0; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		self::assertTrue($this->completed);
	}
	public function testUnknownPackMetadataRejected() : void{
		$this->make(metadata: false);
		self::assertFalse($this->response(ResourcePackClientResponsePacket::STATUS_SEND_PACKS, [self::B]));
		self::assertFalse($this->session->isConnected());
	}
	public function testUnknownChunkPackRejected() : void{
		$this->make();
		$this->expectException(PacketHandlingException::class);
		$this->request(0, self::B);
	}
	public function testOutOfBoundsChunkRejected() : void{
		$this->make(packs: [self::A => 1]);
		self::assertFalse($this->request(1));
		self::assertFalse($this->session->isConnected());
	}
	public function testMultiplePacksShareProgressBudget() : void{
		$this->make(packs: [self::A => 2, self::B => 2]);
		foreach([[0, self::A], [1, self::A], [0, self::B], [1, self::B]] as $i => [$chunk, $id]){
			Clock::$now = 1000.0 + $i * 50;
			$this->session->tick();
			self::assertTrue($this->session->isConnected(), 'Second pack must not inherit an exhausted absolute 120s budget');
			$this->sendChunk($chunk, $id);
		}
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS));
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_COMPLETED));
		self::assertTrue($this->completed);
	}
	public function testRakNetReceiptTransferUnaffectedAfter120Seconds() : void{
		$this->make(local: false, packs: [self::A => 2]);
		$this->sendChunk(0);
		Clock::$now = 1150.0; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		$this->sendChunk(1);
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS));
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_COMPLETED));
		self::assertTrue($this->completed);
	}
	public function testIdleBudgetExpires120SecondsAfterLastUniqueSubmission() : void{
		$this->make();
		Clock::$now = 1119.0; $this->sendChunk(0);
		Clock::$now = 1238.999; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		Clock::$now = 1239.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
	}
	public function testAbsoluteDeadlineCannotBeExtendedByUniqueChunks() : void{
		$this->make(packs: [self::A => 20]);
		for($i = 0; $i < 18; ++$i){
			Clock::$now = 1000.0 + $i * 100;
			$this->session->tick();
			self::assertTrue($this->session->isConnected());
			$this->sendChunk($i);
		}
		Clock::$now = 2799.999; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		Clock::$now = 2800.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
	}
	public function testDuplicateSuccessCallbackDoesNotRenewIdleBudget() : void{
		$this->make(); $this->sendChunk(0);
		Clock::$now = 1119.0;
		/** @var FlowControlLocalSender $sender */
		$sender = $this->sender;
		($sender->callbacks[0])(true);
		$this->session->tick();
		self::assertTrue($this->session->isConnected());
		Clock::$now = 1120.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
	}
	public function testRepeatedMetadataRejectedWithoutRenewingBudget() : void{
		$this->make();
		Clock::$now = 1119.0;
		try{
			$this->response(ResourcePackClientResponsePacket::STATUS_SEND_PACKS, [self::A]);
			self::fail('Repeated metadata must fail');
		}catch(PacketHandlingException){
			Clock::$now = 1120.0; $this->session->tick();
			self::assertFalse($this->session->isConnected());
		}
	}
	public function testAdmittedChunkWithoutCompletionStillExpiresAfter15Seconds() : void{
		$this->make(); self::assertTrue($this->request(0));
		(new ReflectionMethod(NetworkSession::class, 'flushGamePacketQueue'))->invoke($this->session);
		Clock::$now = 1014.999; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		Clock::$now = 1015.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
		/** @var FlowControlLocalSender $sender */
		$sender = $this->sender;
		($sender->callbacks[0])(true);
		self::assertFalse($this->completed);
	}
	public function testValidMetadataIsOneTimeProgress() : void{
		$this->make(metadata: false);
		Clock::$now = 1119.0;
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_SEND_PACKS, [self::A]));
		Clock::$now = 1120.0; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		Clock::$now = 1239.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
	}
	public function testValidatedStackTransitionIsOneTimeProgress() : void{
		$this->make(packs: [self::A => 1]); $this->sendChunk(0);
		Clock::$now = 1119.0;
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS));
		Clock::$now = 1120.0; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		self::assertFalse($this->completed);
		Clock::$now = 1239.0; $this->session->tick();
		self::assertFalse($this->session->isConnected());
	}
	public function testCachedPacksCompleteWithoutDownloadingChunks() : void{
		$this->make(metadata: false);
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS));
		self::assertTrue($this->response(ResourcePackClientResponsePacket::STATUS_COMPLETED));
		Clock::$now = 3000.0; $this->session->tick();
		self::assertTrue($this->session->isConnected());
		self::assertTrue($this->completed);
	}
}
