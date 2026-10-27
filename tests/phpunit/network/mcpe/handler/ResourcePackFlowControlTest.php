<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\lang\Language;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\LocalCompletionPacketSender;
use pocketmine\network\mcpe\LocalSendCompletion;
use pocketmine\network\mcpe\LocalSendResult;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\plugin\Plugin;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\ObjectSet;

class FlowControlReceiptSender implements PacketSender{
	/** @var list<array{string, ?int}> */
	public array $sent = [];
	public bool $closed = false;
	public function send(string $payload, bool $immediate, ?int $receiptId) : void{ $this->sent[] = [$payload, $receiptId]; }
	public function close(string $reason = "unknown reason") : void{ $this->closed = true; }
}

final class FlowControlLocalSender extends FlowControlReceiptSender implements LocalCompletionPacketSender{
	/** @var list<\Closure(bool): void> */
	public array $callbacks = [];
	public LocalSendResult $result = LocalSendResult::ACCEPTED;
	public ?bool $synchronousCompletion = null;
	public bool $throwOnSend = false;
	public function sendWithLocalCompletion(string $payload, bool $immediate, \Closure $completed) : LocalSendResult{
		$this->sent[] = [$payload, null];
		$this->callbacks[] = $completed;
		if($this->synchronousCompletion !== null){ $completed($this->synchronousCompletion); }
		if($this->throwOnSend){ throw new \RuntimeException('backend failure'); }
		return $this->result;
	}
	public function close(string $reason = "unknown reason") : void{
		parent::close($reason);
		foreach($this->callbacks as $callback){ $callback(false); }
	}
}

#[Group('nethernet-flow-control')]
final class ResourcePackFlowControlTest extends TestCase{
	private const PACK_ID = '11111111-1111-1111-1111-111111111111';
	private NetworkSession $session;
	private ResourcePacksPacketHandler $handler;
	private bool $protocolCompleted = false;
	private ?CompressBatchPromise $compression = null;
	/** @var list<RegisteredListener<DataPacketSendEvent>> */
	private array $listeners = [];

	protected function setUp() : void{ Timings::init(); }
	protected function tearDown() : void{
		foreach($this->listeners as $listener){ HandlerListManager::global()->getListFor(DataPacketSendEvent::class)->unregister($listener); }
	}

	/** @param \Closure(DataPacketSendEvent): void $callback */
	private function onSend(\Closure $callback) : void{
		$listener = new RegisteredListener($callback, EventPriority::NORMAL, $this->createMock(Plugin::class), true, new TimingsHandler('flow-control send event'));
		$this->listeners[] = $listener;
		HandlerListManager::global()->getListFor(DataPacketSendEvent::class)->register($listener);
	}

	private function setProperty(object $object, string $name, mixed $value) : void{
		(new \ReflectionProperty($object, $name))->setValue($object, $value);
	}

	private function makeSession(PacketSender $sender, bool $compressed = false) : NetworkSession{
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['getLanguage', 'prepareBatch'])->getMock();
		$language = $this->createMock(Language::class);
		$language->method('translate')->willReturn('translated');
		$server->method('getLanguage')->willReturn($language);
		$this->compression = $compressed ? new CompressBatchPromise() : null;
		if($compressed){ $server->method('prepareBatch')->willReturn($this->compression); }
		$session = (new \ReflectionClass(NetworkSession::class))->newInstanceWithoutConstructor();
		$this->setProperty($session, 'server', $server);
		$this->setProperty($session, 'sender', $sender);
		$this->setProperty($session, 'transportPolicy', null);
		$this->setProperty($session, 'logger', new \PrefixedLogger($this->createMock(\Logger::class), 'test'));
		$this->setProperty($session, 'compressedQueue', new \SplQueue());
		$this->setProperty($session, 'disposeHooks', new ObjectSet());
		$this->setProperty($session, 'loggedIn', true);
		$this->setProperty($session, 'enableCompression', $compressed);
		$this->setProperty($session, 'connectTime', time() + 10000);
		if($compressed){ $this->setProperty($session, 'compressor', $this->createMock(\pocketmine\network\mcpe\compression\Compressor::class)); }
		return $session;
	}

	private function makeHandler(PacketSender $sender, int $chunks = 32, bool $compressed = false) : void{
		$this->session = $this->makeSession($sender, $compressed);
		$pack = $this->createMock(ResourcePack::class);
		$pack->method('getPackId')->willReturn(self::PACK_ID);
		$pack->method('getPackSize')->willReturn($chunks * 262144);
		$pack->method('getPackChunk')->willReturnCallback(static fn(int $offset, int $length) => 'chunk-' . $offset);
		$pack->method('getSha256')->willReturn(str_repeat("\0", 32));
		$this->handler = new ResourcePacksPacketHandler($this->session, [$pack], [], false, false, false, function() : void{ $this->protocolCompleted = true; });
		$this->setProperty($this->session, 'handler', $this->handler);
		$this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_SEND_PACKS, [self::PACK_ID]));
		// metadata and the first chunk share a batch
	}

	private function request(int $index) : bool{
		return $this->handler->handleResourcePackChunkRequest(ResourcePackChunkRequestPacket::create(self::PACK_ID, $index));
	}

	private function flush() : void{ (new \ReflectionMethod(NetworkSession::class, 'flushGamePacketQueue'))->invoke($this->session); }

	public function testRakNetWaitsForReceiptAndPreservesOrdering() : void{
		$sender = new FlowControlReceiptSender();
		$this->makeHandler($sender, 2);
		self::assertTrue($this->request(0));
		self::assertTrue($this->request(1));
		$this->flush();
		self::assertCount(1, $sender->sent);
		self::assertSame(0, $sender->sent[0][1]);
		$this->flush();
		self::assertCount(1, $sender->sent);
		$this->session->handleAckReceipt(0);
		$this->flush();
		self::assertCount(2, $sender->sent);
		self::assertSame(1, $sender->sent[1][1]);
		$this->session->handleAckReceipt(0);
		self::assertFalse($this->protocolCompleted);
	}

	public function testAdmissionAloneDoesNotReleaseSlotOrResolveRemoteReceipt() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 2);
		$this->request(0);
		$this->request(1);
		$this->flush();
		self::assertCount(1, $sender->callbacks);
		self::assertFalse($this->protocolCompleted);
		$this->session->handleAckReceipt(0);
		$this->flush();
		self::assertCount(1, $sender->callbacks, 'A RakNet receipt cannot resolve a local-completion operation.');
		($sender->callbacks[0])(true);
		$this->flush();
		self::assertCount(2, $sender->callbacks);
		self::assertNull($sender->sent[0][1]);
		($sender->callbacks[0])(false);
		($sender->callbacks[0])(true);
		self::assertTrue($this->session->isConnected());
		self::assertFalse($this->protocolCompleted);
		($sender->callbacks[1])(true);
		self::assertFalse($this->protocolCompleted);
		$this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS, []));
		$this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, []));
		self::assertTrue($this->protocolCompleted);
	}

	public function testDelayedCompressionCannotCompleteOrSendEarly() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 2, true);
		$this->request(0);
		$this->request(1);
		$this->flush();
		self::assertCount(0, $sender->callbacks);
		$this->compression?->resolve('first-compressed-batch');
		self::assertSame('first-compressed-batch', $sender->sent[0][0]);
		self::assertCount(1, $sender->callbacks);
		self::assertFalse($this->protocolCompleted);
	}

	/** @return iterable<string, array{LocalSendResult, ?bool, bool}> */
	public static function failures() : iterable{
		yield 'backpressure without callback' => [LocalSendResult::BACKPRESSURE, null, false];
		yield 'backpressure false callback before return' => [LocalSendResult::BACKPRESSURE, false, false];
		yield 'backpressure misleading success before return' => [LocalSendResult::BACKPRESSURE, true, false];
		yield 'dropped' => [LocalSendResult::DROPPED, null, false];
		yield 'native failure' => [LocalSendResult::ACCEPTED, false, false];
		yield 'backend exception' => [LocalSendResult::ACCEPTED, null, true];
	}

	#[DataProvider('failures')]
	public function testRejectedSendsFailClosedWithoutRetry(LocalSendResult $result, ?bool $callback, bool $throw) : void{
		$sender = new FlowControlLocalSender();
		$sender->result = $result;
		$sender->synchronousCompletion = $callback;
		$sender->throwOnSend = $throw;
		$this->makeHandler($sender, 2);
		$this->request(0);
		$this->request(1);
		$this->flush();
		self::assertFalse($this->session->isConnected());
		self::assertTrue($sender->closed);
		self::assertCount(1, $sender->callbacks);
		($sender->callbacks[0])(true);
		self::assertFalse($this->protocolCompleted);
	}

	public function testDelayedNativeFailureClearsQueuedWork() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 3);
		$this->request(0);
		$this->request(1);
		$this->request(2);
		$this->flush();
		($sender->callbacks[0])(false);
		self::assertFalse($this->session->isConnected());
		($sender->callbacks[0])(true);
		$this->flush();
		self::assertCount(1, $sender->callbacks);
	}

	public function testDisconnectCancelsOutstandingWorkAndLateCallbackCannotReachNewSession() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 2);
		$this->request(0);
		$this->request(1);
		$this->flush();
		$oldSession = \WeakReference::create($this->session);
		$oldHandler = \WeakReference::create($this->handler);
		$this->session->onClientDisconnect('test close');
		self::assertFalse($this->session->isConnected());
		$newSender = new FlowControlLocalSender();
		$this->makeHandler($newSender, 1);
		$this->request(0);
		($sender->callbacks[0])(true);
		$this->flush();
		self::assertTrue($this->session->isConnected());
		self::assertCount(1, $newSender->callbacks);
		gc_collect_cycles();
		self::assertNull($oldSession->get());
		self::assertNull($oldHandler->get());
	}

	public function testDelayedCompressionAfterDisconnectDoesNotRetainSessionOrSend() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1, true);
		$this->request(0);
		$this->flush();
		$session = \WeakReference::create($this->session);
		$this->session->onClientDisconnect('closed before compression');
		unset($this->session, $this->handler);
		gc_collect_cycles();
		self::assertNull($session->get());
		$this->compression?->resolve('late batch');
		self::assertCount(0, $sender->callbacks);
	}

	public function testVanillaVelocityBurstWaitsForNativeCompletionAndFinishesNegotiation() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 40);
		// vanilla requests chunks before the first batch flush
		for($i = 0; $i < 40; ++$i){
			self::assertTrue($this->request($i), "Vanilla burst chunk $i should be admitted");
		}
		self::assertTrue($this->session->isConnected());
		self::assertCount(0, $sender->callbacks);
		for($i = 0; $i < 40; ++$i){
			$this->flush();
			self::assertCount($i + 1, $sender->callbacks);
			$this->flush();
			self::assertCount($i + 1, $sender->callbacks, 'One active chunk until native completion');
			($sender->callbacks[$i])(true);
		}
		self::assertTrue($this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS, [])));
		self::assertFalse($this->protocolCompleted);
		self::assertTrue($this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, [])));
		self::assertTrue($this->protocolCompleted);
	}

	public function testQueueBoundFailsInsteadOfAccumulatingRequests() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 66);
		for($i = 0; $i < 65; ++$i){ self::assertTrue($this->request($i)); }
		self::assertFalse($this->request(65));
		self::assertFalse($this->session->isConnected());
		$this->flush();
		self::assertCount(0, $sender->callbacks);
	}

	public function testNativeSuccessBeforeReturnStillProgressesWithoutRecursiveSending() : void{
		$sender = new FlowControlLocalSender();
		$sender->synchronousCompletion = true;
		$this->makeHandler($sender, 3);
		$this->request(0);
		$this->request(1);
		$this->request(2);
		$this->flush();
		$this->flush();
		$this->flush();
		self::assertCount(3, $sender->callbacks);
		self::assertTrue($this->session->isConnected());
		self::assertFalse($this->protocolCompleted);
	}

	public function testClientCannotCompleteWhileChunkIsPending() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->request(0);
		$this->flush();
		$this->expectException(\pocketmine\network\PacketHandlingException::class);
		$this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS, []));
	}

	public function testClientCannotSkipStackStage() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->expectException(\pocketmine\network\PacketHandlingException::class);
		$this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, []));
	}

	public function testNegotiationDeadlineCancelsLocalWork() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->handler->checkLocalTimeout(PHP_FLOAT_MAX);
		self::assertFalse($this->session->isConnected());
		self::assertFalse($this->protocolCompleted);
	}

	public function testOperationDeadlineRejectsOnceAndIgnoresLateSuccess() : void{
		$sender = new FlowControlLocalSender();
		$operation = new LocalSendCompletion(10.0);
		$success = $failure = 0;
		$operation->getPromise()->onCompletion(static function() use (&$success) : void{ ++$success; }, static function() use (&$failure) : void{ ++$failure; });
		$operation->submit($sender, 'batch', false);
		$operation->checkTimeout(9.0);
		self::assertSame(0, $failure);
		$operation->checkTimeout(10.0);
		($sender->callbacks[0])(true);
		$operation->cancel();
		self::assertSame(0, $success);
		self::assertSame(1, $failure);
	}

	public function testSessionTickExpiresAChunkStillWaitingForCompression() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1, true);
		$this->request(0);
		$this->flush();
		$operation = (new \ReflectionProperty(NetworkSession::class, 'resourcePackSendCompletion'))->getValue($this->session);
		$this->setProperty($operation, 'deadline', 0.0);
		$this->session->tick();
		self::assertFalse($this->session->isConnected());
		$this->compression?->resolve('late');
		self::assertCount(0, $sender->callbacks);
	}

	public function testUnrelatedCompressionAheadOfChunkCannotCompleteWrongBatch() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$earlier = new CompressBatchPromise();
		$this->session->queueCompressed($earlier);
		$sender->sent = []; // metadata was flushed before the unresolved batch
		$this->request(0);
		$this->flush();
		self::assertCount(0, $sender->sent);
		$earlier->resolve('earlier-batch');
		self::assertCount(2, $sender->sent);
		self::assertSame(['earlier-batch', null], $sender->sent[0]);
		self::assertNotSame('earlier-batch', $sender->sent[1][0]);
		self::assertCount(1, $sender->callbacks);
		self::assertFalse($this->protocolCompleted);
		($sender->callbacks[0])(true);
		self::assertFalse($this->protocolCompleted);
	}

	public function testHandlerReplacementCancelsOutstandingTransfer() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 2);
		$this->request(0);
		$this->request(1);
		$this->flush();
		$this->session->setHandler(null);
		($sender->callbacks[0])(true);
		$this->flush();
		self::assertCount(1, $sender->callbacks);
		self::assertTrue($this->session->isConnected());
		self::assertFalse($this->protocolCompleted);
	}

	public function testConcurrentDirectLocalChunkOperationIsRejectedWithoutSendingTwice() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$packet = ResourcePackChunkDataPacket::create(self::PACK_ID, 0, 0, 'chunk');
		$first = $this->session->sendResourcePackChunk($packet);
		$second = $this->session->sendResourcePackChunk($packet);
		$rejected = false;
		$second->onCompletion(static function() : void{ self::fail('Second operation must be rejected'); }, static function() use (&$rejected) : void{ $rejected = true; });
		self::assertTrue($rejected);
		$this->flush();
		self::assertCount(1, $sender->callbacks);
		self::assertFalse($first->isResolved());
		($sender->callbacks[0])(true);
		self::assertTrue($first->isResolved());
	}

	public function testLocalSenderRejectsUnsupportedRemoteReceiptRatherThanMixingGuarantees() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$receipt = $this->session->sendDataPacketWithReceipt(ResourcePackChunkDataPacket::create(self::PACK_ID, 0, 0, 'chunk'));
		$rejected = false;
		$receipt->onCompletion(static function() : void{ self::fail('A local-only sender cannot provide a remote ACK'); }, static function() use (&$rejected) : void{ $rejected = true; });
		self::assertTrue($rejected);
		$this->request(0);
		$this->flush();
		($sender->callbacks[0])(true);
		self::assertFalse($receipt->isResolved());
	}

	public function testClientCannotClaimDownloadAfterOnlySomeChunksWereSubmitted() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 2);
		$this->request(0);
		$this->flush();
		($sender->callbacks[0])(true);
		$this->expectException(\pocketmine\network\PacketHandlingException::class);
		$this->handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS, []));
	}

	public function testPluginCancellationRejectsChunkAndCancelsQueue() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->onSend(static function(DataPacketSendEvent $event) : void{
			if($event->getPackets()[0] instanceof ResourcePackChunkDataPacket){ $event->cancel(); }
		});
		$this->request(0);
		self::assertFalse($this->session->isConnected());
		self::assertCount(0, $sender->callbacks);
	}

	public function testPluginRemovingPacketCannotSatisfyLocalCompletion() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->onSend(static function(DataPacketSendEvent $event) : void{
			if($event->getPackets()[0] instanceof ResourcePackChunkDataPacket){ $event->setPackets([]); }
		});
		$this->request(0);
		self::assertFalse($this->session->isConnected());
		self::assertCount(0, $sender->callbacks);
	}

	public function testPluginReentrantFlushCannotCompleteChunkBeforeItIsInBatch() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->onSend(function(DataPacketSendEvent $event) : void{
			if($event->getPackets()[0] instanceof ResourcePackChunkDataPacket){
				$this->session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::LOGIN_SUCCESS), true);
			}
		});
		$this->request(0);
		self::assertCount(1, $sender->sent);
		self::assertCount(0, $sender->callbacks);
		$this->flush();
		self::assertCount(2, $sender->sent);
		self::assertCount(1, $sender->callbacks);
		self::assertFalse($this->protocolCompleted);
	}

	public function testPluginHandlerReplacementDuringChunkSendCannotLeaveBufferedChunk() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 1);
		$this->onSend(function(DataPacketSendEvent $event) : void{
			if($event->getPackets()[0] instanceof ResourcePackChunkDataPacket){ $this->session->setHandler(null); }
		});
		$this->request(0);
		$this->flush();
		self::assertTrue($this->session->isConnected());
		self::assertCount(0, $sender->callbacks);
		self::assertFalse($this->protocolCompleted);
	}

	public function testTotalRequestBoundIncludesSuccessfullySubmittedChunks() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 4097);
		for($index = 0; $index < 4096; ++$index){
			self::assertTrue($this->request($index));
			$this->flush();
			($sender->callbacks[$index])(true);
		}
		self::assertCount(4096, $sender->callbacks);
		self::assertTrue($this->session->isConnected());
		self::assertFalse($this->request(4096));
		self::assertFalse($this->session->isConnected());
		self::assertCount(4096, $sender->callbacks);
	}

	public function testDistinctCompressionPromisesResolveOutOfOrderWithoutCrossingChunkCompletion() : void{
		$sender = new FlowControlLocalSender();
		$this->makeHandler($sender, 2);
		$this->flush(); // metadata precedes the compression scenario
		$sender->sent = [];
		$earlier = new CompressBatchPromise();
		$firstChunk = new CompressBatchPromise();
		$secondChunk = new CompressBatchPromise();
		$server = (new \ReflectionProperty(NetworkSession::class, 'server'))->getValue($this->session);
		$server->method('prepareBatch')->willReturnOnConsecutiveCalls($earlier, $firstChunk, $secondChunk);
		$this->setProperty($this->session, 'enableCompression', true);
		$this->setProperty($this->session, 'compressor', $this->createMock(\pocketmine\network\mcpe\compression\Compressor::class));
		$this->session->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::LOGIN_SUCCESS), true);
		$this->request(0);
		$this->request(1);
		$this->flush();
		$firstChunk->resolve('first-chunk-compressed');
		self::assertCount(0, $sender->sent);
		$earlier->resolve('earlier-compressed');
		self::assertSame([['earlier-compressed', null], ['first-chunk-compressed', null]], $sender->sent);
		self::assertCount(1, $sender->callbacks);
		($sender->callbacks[0])(true);
		$this->flush();
		self::assertCount(1, $sender->callbacks);
		$secondChunk->resolve('second-chunk-compressed');
		self::assertSame(['second-chunk-compressed', null], $sender->sent[2]);
		self::assertCount(2, $sender->callbacks);
		($sender->callbacks[0])(true);
		self::assertFalse($this->protocolCompleted);
		($sender->callbacks[1])(true);
		self::assertTrue($this->session->isConnected());
		self::assertFalse($this->protocolCompleted);
	}
}
