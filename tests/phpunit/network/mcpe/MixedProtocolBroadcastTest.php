<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use ReflectionProperty;
use function iterator_to_array;

final class MixedProtocolBroadcastTest extends TestCase{

	protected function setUp() : void{
		Timings::init();
	}

	private static function soundPacket() : PlaySoundPacket{
		$packet = PlaySoundPacket::create('random.click', 1.0, 2.0, 3.0, 1.0, 1.0, 2, 7);
		$packet->bypassListenerRangeCheck = true;
		$packet->playbackPositionSeconds = 1.5;
		return $packet;
	}

	/**
	 * @param list<string> $sent
	 * @param list<CompressBatchPromise|string> $batches
	 */
	private function createPlayer(Server $server, Compressor $compressor, int $protocolId, array &$sent, array &$batches) : Player{
		$session = $this->createMock(NetworkSession::class);
		$session->method('getBroadcaster')->willReturn($server->getPacketBroadcaster($protocolId));
		$session->method('getProtocolId')->willReturn($protocolId);
		$session->method('getCompressor')->willReturn($compressor);
		$session->method('addToSendBuffer')->willReturnCallback(function(string $buffer) use (&$sent) : void{
			$sent[] = $buffer;
		});
		$session->method('queueCompressed')->willReturnCallback(function(CompressBatchPromise|string $batch) use (&$batches) : void{
			$batches[] = $batch;
		});
		$player = $this->createMock(Player::class);
		(new ReflectionProperty(Entity::class, 'closed'))->setValue($player, true);
		(new ReflectionProperty(Player::class, 'logger'))->setValue($player, $this->createMock(\Logger::class));
		$player->method('isConnected')->willReturn(true);
		$player->method('getNetworkSession')->willReturn($session);
		return $player;
	}

	private static function decode(string $buffer, int $protocolId) : PlaySoundPacket{
		$reader = new ByteBufferReader($buffer);
		$packet = new PlaySoundPacket();
		$packet->decode($reader, $protocolId);
		self::assertSame(0, $reader->getUnreadLength(), "protocol $protocolId");
		return $packet;
	}

	public function testEachClientGetsItsOwnProtocolEncoding() : void{
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['prepareBatch'])->getMock();
		$server->expects(self::never())->method('prepareBatch');
		$compressor = $this->createMock(Compressor::class);
		$compressor->method('getCompressionThreshold')->willReturn(null);

		$protocols = [ProtocolInfo::PROTOCOL_1_26_44, ProtocolInfo::PROTOCOL_1_26_50, ProtocolInfo::PROTOCOL_1_26_51];
		$sent = [];
		$batches = [];
		$players = [];
		foreach($protocols as $protocolId){
			$sent[$protocolId] = [];
			$batches[$protocolId] = [];
			$players[] = $this->createPlayer($server, $compressor, $protocolId, $sent[$protocolId], $batches[$protocolId]);
		}
		self::assertTrue(NetworkBroadcastUtils::broadcastPackets($players, [self::soundPacket()]));

		foreach($protocols as $protocolId){
			self::assertCount(1, $sent[$protocolId], "protocol $protocolId");
			$decoded = self::decode($sent[$protocolId][0], $protocolId);
			self::assertSame(7, $decoded->serverSoundHandle);
			self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_50, $decoded->bypassListenerRangeCheck, "protocol $protocolId");
		}
		//2192 and 2193 share the wire format but keep separate broadcasters
		self::assertSame($sent[ProtocolInfo::PROTOCOL_1_26_50][0], $sent[ProtocolInfo::PROTOCOL_1_26_51][0]);
		self::assertNotSame($sent[ProtocolInfo::PROTOCOL_1_26_44][0], $sent[ProtocolInfo::PROTOCOL_1_26_51][0]);
		self::assertNotSame($server->getPacketBroadcaster(ProtocolInfo::PROTOCOL_1_26_50), $server->getPacketBroadcaster(ProtocolInfo::PROTOCOL_1_26_51));

		//a 26.50 buffer read as 26.44 leaves bytes unread and misreads the sound handle
		$reader = new ByteBufferReader($sent[ProtocolInfo::PROTOCOL_1_26_51][0]);
		$misread = new PlaySoundPacket();
		$misread->decode($reader, ProtocolInfo::PROTOCOL_1_26_44);
		self::assertNotSame(0, $reader->getUnreadLength());
		self::assertNotSame(7, $misread->serverSoundHandle);
	}

	public function testSharedCompressedBatchIsNotReusedAcrossProtocols() : void{
		$compressor = $this->createMock(Compressor::class);
		$compressor->method('getCompressionThreshold')->willReturn(0);
		$prepared = [];
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['prepareBatch'])->getMock();
		$server->method('prepareBatch')->willReturnCallback(function(string $buffer, int $protocolId) use (&$prepared) : string{
			$prepared[] = [$protocolId, $buffer];
			return "batch-$protocolId";
		});

		$sent = ['old' => [], 'a' => [], 'b' => []];
		$batches = ['old' => [], 'a' => [], 'b' => []];
		$players = [
			$this->createPlayer($server, $compressor, ProtocolInfo::PROTOCOL_1_26_44, $sent['old'], $batches['old']),
			$this->createPlayer($server, $compressor, ProtocolInfo::PROTOCOL_1_26_51, $sent['a'], $batches['a']),
			$this->createPlayer($server, $compressor, ProtocolInfo::PROTOCOL_1_26_51, $sent['b'], $batches['b']),
		];
		NetworkBroadcastUtils::broadcastPackets($players, [self::soundPacket()]);

		self::assertCount(1, $prepared);
		[$protocolId, $buffer] = $prepared[0];
		self::assertSame(ProtocolInfo::PROTOCOL_1_26_51, $protocolId);
		$buffers = iterator_to_array(PacketBatch::decodeRaw(new ByteBufferReader($buffer)), false);
		self::assertCount(1, $buffers);
		self::assertTrue(self::decode($buffers[0], ProtocolInfo::PROTOCOL_1_26_51)->bypassListenerRangeCheck);

		self::assertSame(['batch-' . ProtocolInfo::PROTOCOL_1_26_51], $batches['a']);
		self::assertSame(['batch-' . ProtocolInfo::PROTOCOL_1_26_51], $batches['b']);
		//lone 26.44 recipient gets its own uncompressed 26.44 encoding
		self::assertSame([], $batches['old']);
		self::assertCount(1, $sent['old']);
		self::assertFalse(self::decode($sent['old'][0], ProtocolInfo::PROTOCOL_1_26_44)->bypassListenerRangeCheck);
	}
}
