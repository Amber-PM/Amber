<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\GameMode as ProtocolGameMode;
use pocketmine\player\GameMode;

final class GameModeConversionTest extends TestCase{
	private TypeConverter $converter;

	protected function setUp() : void{
		$this->converter = new TypeConverter(ProtocolInfo::CURRENT_PROTOCOL);
	}

	public function testCoreToProtocolWithNativeSpectatorEnabled() : void{
		$this->converter->setNativeSpectator(true);

		self::assertSame(ProtocolGameMode::SURVIVAL, $this->converter->coreGameModeToProtocol(GameMode::SURVIVAL));
		self::assertSame(ProtocolGameMode::CREATIVE, $this->converter->coreGameModeToProtocol(GameMode::CREATIVE));
		self::assertSame(ProtocolGameMode::ADVENTURE, $this->converter->coreGameModeToProtocol(GameMode::ADVENTURE));
		self::assertSame(ProtocolGameMode::SPECTATOR, $this->converter->coreGameModeToProtocol(GameMode::SPECTATOR));
	}

	public function testCoreToProtocolWithNativeSpectatorDisabled() : void{
		$this->converter->setNativeSpectator(false);

		self::assertSame(ProtocolGameMode::SURVIVAL, $this->converter->coreGameModeToProtocol(GameMode::SURVIVAL));
		self::assertSame(ProtocolGameMode::CREATIVE, $this->converter->coreGameModeToProtocol(GameMode::CREATIVE));
		self::assertSame(ProtocolGameMode::ADVENTURE, $this->converter->coreGameModeToProtocol(GameMode::ADVENTURE));
		self::assertSame(ProtocolGameMode::CREATIVE, $this->converter->coreGameModeToProtocol(GameMode::SPECTATOR));
	}

	public function testProtocolToCore() : void{
		self::assertSame(GameMode::SURVIVAL, $this->converter->protocolGameModeToCore(ProtocolGameMode::SURVIVAL));
		self::assertSame(GameMode::CREATIVE, $this->converter->protocolGameModeToCore(ProtocolGameMode::CREATIVE));
		self::assertSame(GameMode::ADVENTURE, $this->converter->protocolGameModeToCore(ProtocolGameMode::ADVENTURE));
		self::assertSame(GameMode::SPECTATOR, $this->converter->protocolGameModeToCore(ProtocolGameMode::SPECTATOR));
		self::assertSame(GameMode::SPECTATOR, $this->converter->protocolGameModeToCore(ProtocolGameMode::SURVIVAL_VIEWER));
		self::assertSame(GameMode::SPECTATOR, $this->converter->protocolGameModeToCore(ProtocolGameMode::CREATIVE_VIEWER));
		self::assertNull($this->converter->protocolGameModeToCore(9999));
	}

	public function testSpectatorPolicyIsIsolatedBetweenConverters() : void{
		$legacyConverter = new TypeConverter(ProtocolInfo::PROTOCOL_1_20_0);
		$legacyConverter->setNativeSpectator(false);

		self::assertSame(ProtocolGameMode::SPECTATOR, $this->converter->coreGameModeToProtocol(GameMode::SPECTATOR));
		self::assertSame(ProtocolGameMode::CREATIVE, $legacyConverter->coreGameModeToProtocol(GameMode::SPECTATOR));
		self::assertSame(GameMode::SPECTATOR, $legacyConverter->protocolGameModeToCore(ProtocolGameMode::SPECTATOR));
	}
}
