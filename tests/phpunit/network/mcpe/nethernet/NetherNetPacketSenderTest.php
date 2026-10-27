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
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\LocalSendCompletion;
use pocketmine\network\mcpe\LocalSendResult;

if(interface_exists(Connection::class)){
final class SenderTestConnection implements Connection{
	public SendResult $result = SendResult::ACCEPTED;
	public ?bool $synchronous = null;
	public bool $throw = false;
	public bool $throwOnClose = false;
	public int $closes = 0;
	public ?\Closure $onClose = null;
	/** @var list<array{string, Channel, bool}> */
	public array $sent = [];
	/** @var list<\Closure(bool): void> */
	public array $callbacks = [];
	public function getId() : int{ return 42; }
	public function getNetworkId() : string{ return 'peer'; }
	public function getIdentity() : PeerIdentity{ throw new \LogicException('Unused'); }
	public function getState() : ConnectionState{ return ConnectionState::OPEN; }
	public function getStats() : ?ConnectionStats{ return null; }
	public function send(string $payload, Channel $channel = Channel::RELIABLE, bool $immediate = false, ?\Closure $accepted = null) : SendResult{
		$this->sent[] = [$payload, $channel, $immediate];
		if($accepted !== null){
			$this->callbacks[] = $accepted;
			if($this->synchronous !== null){ $accepted($this->synchronous); }
		}
		if($this->throw){ throw new \RuntimeException('native secret'); }
		return $this->result;
	}
	public function close(string $reason = 'local close') : void{
		++$this->closes;
		($this->onClose)?->__invoke();
		if($this->throwOnClose){ throw new \RuntimeException('close failure'); }
	}
}

}

final class NetherNetPacketSenderTest extends TestCase{
	public static function setUpBeforeClass() : void{
		if(!class_exists(\amber\nethernet\Transport::class)){ self::fail('Required NetherNet package is unavailable; run composer install'); }
	}

	private SenderTestConnection $connection;
	/** @var list<string> */
	private array $failures = [];
	private function sender(int $callbacks = 128, int $bytes = 4194304) : NetherNetPacketSender{
		$this->connection = new SenderTestConnection();
		return new NetherNetPacketSender($this->connection, function(string $reason) : void{ $this->failures[] = $reason; }, $callbacks, $bytes);
	}
	public function testCapabilityExists() : void{
		self::assertTrue(class_exists(NetherNetPacketSender::class));
	}
	public function testReliablePlainPayloadAndLocalCompletion() : void{
		$sender = $this->sender();
		$completed = [];
		self::assertSame(LocalSendResult::ACCEPTED, $sender->sendWithLocalCompletion("\x03abc", true, static function(bool $success) use (&$completed) : void{ $completed[] = $success; }));
		self::assertSame([["\x03abc", Channel::RELIABLE, true]], $this->connection->sent);
		self::assertSame([], $completed);
		($this->connection->callbacks[0])(true);
		($this->connection->callbacks[0])(false);
		self::assertSame([true], $completed);
		self::assertSame([], $this->failures);
	}
	public function testAdmissionMappingsAndSynchronousArbitration() : void{
		foreach([[SendResult::ACCEPTED, LocalSendResult::ACCEPTED], [SendResult::BACKPRESSURE, LocalSendResult::BACKPRESSURE], [SendResult::DROPPED, LocalSendResult::DROPPED]] as [$native, $local]){
			$sender = $this->sender();
			$this->connection->result = $native;
			$this->connection->synchronous = true;
			$outcomes = [];
			self::assertSame($local, $sender->sendWithLocalCompletion('batch', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; }));
			self::assertSame([$native === SendResult::ACCEPTED], $outcomes);
		}
	}
	public function testGenericRejectionFailsClosedWithoutReceipts() : void{
		foreach([SendResult::BACKPRESSURE, SendResult::DROPPED] as $result){
			$this->failures = [];
			$sender = $this->sender();
			$this->connection->result = $result;
			$sender->send('gameplay', false, 123);
			self::assertSame(1, $this->connection->closes);
			self::assertCount(1, $this->failures);
			$sender->send('late', false, null);
			self::assertCount(1, $this->connection->sent);
		}
	}
	public function testAsyncNativeFailureClosesAndFailsAllPending() : void{
		$sender = $this->sender();
		$outcomes = [];
		$sender->send('generic', false, null);
		$sender->sendWithLocalCompletion('local', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; });
		($this->connection->callbacks[0])(false);
		($this->connection->callbacks[1])(true);
		self::assertSame([false], $outcomes);
		self::assertSame(1, $this->connection->closes);
		self::assertCount(1, $this->failures);
	}
	public function testCloseDrainsCallbacksWithReentrantCleanup() : void{
		$sender = $this->sender();
		$outcomes = [];
		$sender->sendWithLocalCompletion('batch', false, static function(bool $value) use (&$outcomes, $sender) : void{ $outcomes[] = $value; $sender->close(); });
		$this->connection->onClose = static function() use ($sender) : void{ $sender->close(); };
		$sender->close();
		($this->connection->callbacks[0])(true);
		self::assertSame([false], $outcomes);
		self::assertSame(1, $this->connection->closes);
		self::assertSame(LocalSendResult::DROPPED, $sender->sendWithLocalCompletion('late', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; }));
		self::assertSame([false, false], $outcomes);
	}
	public function testSharedCountLimitAndReleasedCapacity() : void{
		$sender = $this->sender(2);
		$sender->send('generic', false, null);
		$sender->sendWithLocalCompletion('local', false, static function(bool $value) : void{});
		self::assertSame(LocalSendResult::BACKPRESSURE, $sender->sendWithLocalCompletion('full', false, static function(bool $value) : void{ self::assertFalse($value); }));
		self::assertCount(2, $this->connection->sent);
		($this->connection->callbacks[0])(true);
		self::assertSame(LocalSendResult::ACCEPTED, $sender->sendWithLocalCompletion('room', false, static function(bool $value) : void{}));
	}
	public function testSharedByteLimitAndReleasedCapacity() : void{
		$sender = $this->sender(128, 5);
		$sender->send('123', false, null);
		self::assertSame(LocalSendResult::BACKPRESSURE, $sender->sendWithLocalCompletion('456', false, static function(bool $value) : void{ self::assertFalse($value); }));
		($this->connection->callbacks[0])(true);
		self::assertSame(LocalSendResult::ACCEPTED, $sender->sendWithLocalCompletion('12345', false, static function(bool $value) : void{}));
		self::assertCount(2, $this->connection->sent);
	}
	public function testNativeExceptionFailsClosedWithoutSensitiveReason() : void{
		$sender = $this->sender();
		$this->connection->throw = true;
		$outcomes = [];
		self::assertSame(LocalSendResult::DROPPED, $sender->sendWithLocalCompletion('batch', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; }));
		self::assertSame([false], $outcomes);
		self::assertSame(1, $this->connection->closes);
		self::assertCount(1, $this->failures);
		self::assertStringNotContainsString('secret', $this->failures[0]);
	}
	public function testNativeCallbacksDoNotRetainSenderOrSessionOwner() : void{
		$sender = $this->sender();
		$owner = new \stdClass();
		$ownerReference = \WeakReference::create($owner);
		$senderReference = \WeakReference::create($sender);
		$sender->sendWithLocalCompletion('batch', false, static function(bool $value) use ($owner) : void{ $owner->completed = $value; });
		unset($owner, $sender);
		gc_collect_cycles();
		self::assertNull($senderReference->get());
		self::assertNull($ownerReference->get());
		($this->connection->callbacks[0])(true);
	}
	public function testI1aSuccessAndRejection() : void{
		foreach([SendResult::ACCEPTED, SendResult::BACKPRESSURE] as $result){
			$sender = $this->sender();
			$this->connection->result = $result;
			$this->connection->synchronous = true;
			$completion = new LocalSendCompletion(99999999.0);
			$outcomes = [];
			$completion->getPromise()->onCompletion(static function() use (&$outcomes) : void{ $outcomes[] = true; }, static function() use (&$outcomes) : void{ $outcomes[] = false; });
			$completion->submit($sender, 'pack', false);
			self::assertSame([$result === SendResult::ACCEPTED], $outcomes);
		}
	}
	public function testGenericLimitFailsClosedAndReleasesLocalWaiter() : void{
		$sender = $this->sender(1);
		$outcomes = [];
		$sender->sendWithLocalCompletion('local', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; });
		$sender->send('generic', false, null);
		self::assertCount(1, $this->connection->sent);
		self::assertSame([false], $outcomes);
		self::assertCount(1, $this->failures);
		self::assertSame(1, $this->connection->closes);
	}
	public function testThrowingShutdownCallbackAndBackendStillDrainOtherWaiters() : void{
		$sender = $this->sender();
		$this->connection->throwOnClose = true;
		$outcomes = [];
		$sender->sendWithLocalCompletion('first', false, static function(bool $value) : void{ throw new \RuntimeException('application error'); });
		$sender->sendWithLocalCompletion('second', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; });
		$sender->close();
		self::assertSame([false], $outcomes);
		self::assertSame(1, $this->connection->closes);
		$sender->close();
		self::assertSame(1, $this->connection->closes);
	}
	public function testSynchronousNativeFailureNotifiesOnce() : void{
		$sender = $this->sender();
		$this->connection->synchronous = false;
		$outcomes = [];
		self::assertSame(LocalSendResult::ACCEPTED, $sender->sendWithLocalCompletion('batch', false, static function(bool $value) use (&$outcomes) : void{ $outcomes[] = $value; }));
		($this->connection->callbacks[0])(false);
		self::assertSame([false], $outcomes);
		self::assertCount(1, $this->failures);
		self::assertSame(1, $this->connection->closes);
	}
	public function testOldConnectionCallbackCannotAffectNewSender() : void{
		$old = $this->sender();
		$oldConnection = $this->connection;
		$old->send('old', false, null);
		$old->close();
		$new = $this->sender();
		$new->send('new', false, null);
		($oldConnection->callbacks[0])(false);
		self::assertSame(0, $this->connection->closes);
		self::assertSame([], $this->failures);
		($this->connection->callbacks[0])(true);
	}
	public function testInvalidBoundsRejected() : void{
		$this->expectException(\InvalidArgumentException::class);
		$this->sender(0);
	}
}
