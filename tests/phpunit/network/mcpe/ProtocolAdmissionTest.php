<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\handler\SessionStartPacketHandler;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\Server;
use pocketmine\ServerConfigGroup;
use pocketmine\utils\Config;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

class ProtocolAdmissionTest extends TestCase{

	private Server $server;
	private ServerConfigGroup $configGroup;
	private string $configPath;
	private string $propsPath;
	private ReflectionProperty $serverInstanceProp;

	protected function setUp() : void{
		$this->configPath = sys_get_temp_dir() . '/test_pocketmine_' . uniqid() . '.json';
		$this->propsPath = sys_get_temp_dir() . '/test_server_' . uniqid() . '.properties';

		$json = new Config($this->configPath, Config::JSON);
		$props = new Config($this->propsPath, Config::PROPERTIES);

		$this->configGroup = new ServerConfigGroup($json, $props);

		$this->server = $this->getMockBuilder(Server::class)
			->disableOriginalConstructor()
			->onlyMethods(['getName'])
			->getMock();

		$ref = new ReflectionClass(Server::class);
		$prop = $ref->getProperty('configGroup');
		$prop->setAccessible(true);
		$prop->setValue($this->server, $this->configGroup);

		$this->serverInstanceProp = $ref->getProperty('instance');
		$this->serverInstanceProp->setAccessible(true);
		$this->serverInstanceProp->setValue(null, $this->server);
	}

	protected function tearDown() : void{
		$this->serverInstanceProp->setValue(null, null);
		@unlink($this->configPath);
		@unlink($this->propsPath);
	}

	private function updateConfig(array $pocketmineConfigData) : void{
		$json = new Config($this->configPath, Config::JSON);
		$json->setAll($pocketmineConfigData);
		$json->save();

		$this->configGroup = new ServerConfigGroup($json, new Config($this->propsPath, Config::PROPERTIES));
		$ref = new ReflectionClass(Server::class);
		$prop = $ref->getProperty('configGroup');
		$prop->setAccessible(true);
		$prop->setValue($this->server, $this->configGroup);
	}

	public function testAcceptedProtocol() : void{
		$this->assertTrue($this->server->isProtocolAllowed(ProtocolInfo::CURRENT_PROTOCOL));
	}

	public function testUnknownProtocol() : void{
		$this->assertFalse($this->server->isProtocolAllowed(999999));
	}

	public function testDisabledProtocol() : void{
		$this->updateConfig([
			'network' => [
				'disabled-protocols' => [ProtocolInfo::PROTOCOL_1_20_0, (string) ProtocolInfo::PROTOCOL_1_20_10]
			]
		]);

		$this->assertFalse($this->server->isProtocolAllowed(ProtocolInfo::PROTOCOL_1_20_0));
		$this->assertFalse($this->server->isProtocolAllowed(ProtocolInfo::PROTOCOL_1_20_10));
		$this->assertTrue($this->server->isProtocolAllowed(ProtocolInfo::CURRENT_PROTOCOL));
	}

	public function testAllowedProtocol() : void{
		$this->updateConfig([
			'network' => [
				'allowed-protocols' => [ProtocolInfo::CURRENT_PROTOCOL]
			]
		]);

		$this->assertTrue($this->server->isProtocolAllowed(ProtocolInfo::CURRENT_PROTOCOL));
		$this->assertFalse($this->server->isProtocolAllowed(ProtocolInfo::PROTOCOL_1_20_0));
	}

	public function testAllowedProtocolNumericString() : void{
		$this->updateConfig([
			'network' => [
				'allowed-protocols' => [(string) ProtocolInfo::CURRENT_PROTOCOL]
			]
		]);

		$this->assertTrue($this->server->isProtocolAllowed(ProtocolInfo::CURRENT_PROTOCOL));
		$this->assertFalse($this->server->isProtocolAllowed(ProtocolInfo::PROTOCOL_1_20_0));
	}

	public function testDisabledPrecedenceOverAllowed() : void{
		$this->updateConfig([
			'network' => [
				'allowed-protocols' => [ProtocolInfo::CURRENT_PROTOCOL],
				'disabled-protocols' => [(string) ProtocolInfo::CURRENT_PROTOCOL]
			]
		]);

		$this->assertFalse($this->server->isProtocolAllowed(ProtocolInfo::CURRENT_PROTOCOL));
	}

	public function testAllowedProtocolsEmptyAllowsAllAccepted() : void{
		$this->updateConfig([
			'network' => [
				'allowed-protocols' => [],
				'disabled-protocols' => [ProtocolInfo::PROTOCOL_1_20_0]
			]
		]);

		$this->assertTrue($this->server->isProtocolAllowed(ProtocolInfo::CURRENT_PROTOCOL));
		$this->assertFalse($this->server->isProtocolAllowed(ProtocolInfo::PROTOCOL_1_20_0));
	}

	public function testProtocolOutsideAcceptedListRejectedEvenIfInAllowed() : void{
		$this->updateConfig([
			'network' => [
				'allowed-protocols' => [999999]
			]
		]);

		$this->assertFalse($this->server->isProtocolAllowed(999999));
	}

	public function testSessionStartPacketHandlerDelegatesToServer() : void{
		$server = $this->getMockBuilder(Server::class)
			->disableOriginalConstructor()
			->onlyMethods(['isProtocolAllowed'])
			->getMock();
		$this->serverInstanceProp->setValue(null, $server);

		$server->expects($this->once())
			->method('isProtocolAllowed')
			->with(ProtocolInfo::CURRENT_PROTOCOL)
			->willReturn(true);

		$session = $this->createMock(NetworkSession::class);
		$handler = new SessionStartPacketHandler($session, function() : void{});

		$method = new ReflectionMethod(SessionStartPacketHandler::class, 'isCompatibleProtocol');
		$method->setAccessible(true);

		$this->assertTrue($method->invoke($handler, ProtocolInfo::CURRENT_PROTOCOL));
	}
}
