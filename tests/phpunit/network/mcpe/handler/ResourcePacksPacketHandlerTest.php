<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use pocketmine\resourcepacks\ResourcePack;

final class ResourcePacksPacketHandlerTest extends TestCase{
	private const CONFIGURED_PACK_ID = "11111111-1111-1111-1111-111111111111";
	private const CHEMISTRY_PACK_IDS = [
		"b41c2785-c512-4a49-af56-3a87afd47c57",
		"a4df0cb3-17be-4163-88d7-fcf7002b935d",
		"d19adffe-a2e1-4b02-8436-ca4583368c89",
		"85d5603d-2824-4b21-8044-34f441f4fce1",
		"e977cd13-0a11-4618-96fb-03dfe9c43608",
		"0674721c-a0aa-41a1-9ba8-1ed33ea3e7ed",
		"0fba4063-dba1-4281-9b89-ff9390653530"
	];

	/** @return iterable<string, array{int, bool, bool}> */
	public static function protocolModes() : iterable{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocol){
			yield "$protocol enabled" => [$protocol, true, true];
			yield "$protocol disabled" => [$protocol, false, false];
		}
	}

	/**
	 * @param ClientboundPacket[] $sent
	 * @phpstan-param list<ClientboundPacket> $sent
	 */
	private function makeHandler(int $protocolId, bool $educationEnabled, array &$sent) : ResourcePacksPacketHandler{
		$pack = $this->createMock(ResourcePack::class);
		$pack->method("getPackId")->willReturn(self::CONFIGURED_PACK_ID);
		$pack->method("getPackVersion")->willReturn("2.3.4");
		$pack->method("getPackSize")->willReturn(1024);
		$pack->method("getSha256")->willReturn(str_repeat("\0", 32));

		$session = $this->createMock(NetworkSession::class);
		$session->method("getProtocolId")->willReturn($protocolId);
		$session->method("getLogger")->willReturn($this->createMock(\Logger::class));
		$session->method("sendDataPacket")->willReturnCallback(static function(ClientboundPacket $packet, bool $immediate = false) use (&$sent) : bool{
			$sent[] = $packet;
			return true;
		});

		return new ResourcePacksPacketHandler(
			session: $session,
			resourcePackStack: [$pack],
			encryptionKeys: [],
			mustAccept: false,
			forceDisableVibrantVisuals: false,
			educationContentEnabled: $educationEnabled,
			completionCallback: static function() : void{}
		);
	}

	#[DataProvider("protocolModes")]
	public function testChemistryStackEntriesRespectSettingAndProtocol(int $protocolId, bool $educationEnabled, bool $expectChemistry) : void{
		$sent = [];
		$handler = $this->makeHandler($protocolId, $educationEnabled, $sent);
		$handler->setUp();
		self::assertTrue($handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS, [])));

		self::assertCount(2, $sent);
		self::assertInstanceOf(ResourcePacksInfoPacket::class, $sent[0]);
		self::assertCount(1, $sent[0]->resourcePackEntries);
		self::assertSame(self::CONFIGURED_PACK_ID, $sent[0]->resourcePackEntries[0]->getPackId()->toString());
		self::assertInstanceOf(ResourcePackStackPacket::class, $sent[1]);
		$expectedIds = $expectChemistry ? [self::CONFIGURED_PACK_ID, ...self::CHEMISTRY_PACK_IDS] : [self::CONFIGURED_PACK_ID];
		if($expectChemistry && $protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			array_splice($expectedIds, 1, 0, ["d34cfa4b-2ad1-453d-a0db-668b429a3ea0"]);
		}
		self::assertSame($expectedIds, array_map(static fn(ResourcePackStackEntry $entry) : string => $entry->getPackId(), $sent[1]->resourcePackStack));
		self::assertSame("2.3.4", $sent[1]->resourcePackStack[0]->getVersion());
		$writer = new \pmmp\encoding\ByteBufferWriter();
		$sent[1]->encode($writer, $protocolId);
		$decoded = new ResourcePackStackPacket();
		$decoded->decode(new \pmmp\encoding\ByteBufferReader($writer->getData()), $protocolId);
		self::assertSame($expectedIds, array_map(static fn(ResourcePackStackEntry $entry) : string => $entry->getPackId(), $decoded->resourcePackStack));
		if($expectChemistry && $protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			self::assertSame("1.26.40", $decoded->resourcePackStack[1]->getVersion());
		}
	}

	public function testDisabledModeStillOffersConfiguredPackForDownload() : void{
		$sent = [];
		$handler = $this->makeHandler(ProtocolInfo::PROTOCOL_1_21_130, false, $sent);
		$handler->setUp();
		self::assertTrue($handler->handleResourcePackClientResponse(ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_SEND_PACKS, [self::CONFIGURED_PACK_ID])));
		self::assertCount(2, $sent);
		self::assertInstanceOf(ResourcePackDataInfoPacket::class, $sent[1]);
		self::assertSame(self::CONFIGURED_PACK_ID, $sent[1]->packId);
		self::assertSame(1024, $sent[1]->compressedPackSize);
	}

	public function testReplayedStatusCompletedOnlyInvokesCallbackOnce() : void{
		$session = $this->getMockBuilder(NetworkSession::class)
			->disableOriginalConstructor()
			->onlyMethods(["sendDataPacket", "getLogger", "unhandledPacketDebug", "getProtocolId", "usesLocalResourcePackSendCompletion"])
			->getMock();
		$session->method("getLogger")->willReturn($this->createMock(\PrefixedLogger::class));
		$session->method("getProtocolId")->willReturn(ProtocolInfo::CURRENT_PROTOCOL);
		$session->method("sendDataPacket")->willReturn(true);
		$session->method("usesLocalResourcePackSendCompletion")->willReturn(false);
		(new \ReflectionProperty(NetworkSession::class, "logger"))->setValue($session, $this->createMock(\PrefixedLogger::class));
		(new \ReflectionProperty(NetworkSession::class, "transportPolicy"))->setValue($session, null);

		$completionCount = 0;
		$handler = new ResourcePacksPacketHandler(
			session: $session,
			resourcePackStack: [],
			encryptionKeys: [],
			mustAccept: false,
			forceDisableVibrantVisuals: false,
			educationContentEnabled: false,
			completionCallback: function() use (&$completionCount) : void{
				$completionCount++;
			}
		);

		$session->setHandler($handler);

		$stream = new \pmmp\encoding\ByteBufferWriter();
		\pmmp\encoding\VarInt::writeUnsignedInt($stream, ResourcePackClientResponsePacket::NETWORK_ID);
		\pmmp\encoding\VarInt::writeUnsignedInt($stream, ResourcePackClientResponsePacket::STATUS_COMPLETED);
		\pocketmine\network\mcpe\protocol\serializer\CommonTypes::putString($stream, "resourcepackstackfinished");
		$buffer = $stream->getData();

		$packet = ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, []);
		$session->handleDataPacket($packet, $buffer);
		self::assertSame(1, $completionCount);

		$session->handleDataPacket($packet, $buffer);
		self::assertSame(1, $completionCount);
	}
}

