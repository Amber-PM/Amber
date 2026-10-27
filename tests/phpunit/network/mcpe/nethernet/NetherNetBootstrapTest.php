<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

require_once dirname(__DIR__, 3) . '/nethernet-bootstrap.php';

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\Network;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\Server;
use pocketmine\ServerConfigGroup;
use pocketmine\utils\Config;

final class NetherNetBootstrapTest extends TestCase{
	public function testCancelledRequiredInterfaceRegistrationFailsStartup() : void{
		$settings = ['enabled' => true, 'bind' => 'tcp://127.0.0.1:0', 'operator-domain' => 'test.invalid', 'private-key-file' => 'operator.pem', 'trusted-key-files' => ['issuer' => 'issuer.pem'], 'issuer' => 'issuer', 'audience' => 'audience'];
		$yml = $this->createMock(Config::class);
		$yml->method('getNested')->with('network.nethernet')->willReturn($settings);
		$network = $this->getMockBuilder(Network::class)->disableOriginalConstructor()->onlyMethods(['registerInterface'])->getMock();
		$network->expects(self::once())->method('registerInterface')->willReturn(false);
		$server = $this->getMockBuilder(Server::class)->disableOriginalConstructor()->onlyMethods(['getConfigGroup', 'getDataPath', 'getNetwork', 'getLogger', 'getMotd', 'getPort', 'getIp'])->getMock();
		$server->method('getPort')->willReturn(19132);
		$server->method('getIp')->willReturn('127.0.0.1');
		$server->method('getConfigGroup')->willReturn(new ServerConfigGroup($yml, $yml));
		$server->method('getDataPath')->willReturn(sys_get_temp_dir() . '/');
		$server->method('getNetwork')->willReturn($network);
		$server->method('getLogger')->willReturn(new \pocketmine\utils\MainLogger(null, false, 'test', new \DateTimeZone('UTC')));
		$server->method('getMotd')->willReturn('Amber');
		$this->expectException(NetworkInterfaceStartException::class);
		$this->expectExceptionMessage('registration was cancelled');
		NetherNetBootstrap::register($server, $this->createMock(PacketBroadcaster::class), $this->createMock(EntityEventBroadcaster::class), $this->getMockBuilder(TypeConverter::class)->disableOriginalConstructor()->getMock());
	}
}
