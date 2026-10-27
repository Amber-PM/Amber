<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

require_once dirname(__DIR__, 3) . '/nethernet-bootstrap.php';

use amber\nethernet\identity\PeerIdentity;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\timings\Timings;

final class NetherNetLoggingTest extends TestCase{
	public static function setUpBeforeClass() : void{
		if(!class_exists(\amber\nethernet\Transport::class)){ self::fail('Required NetherNet package is unavailable; run composer install'); }
	}

	public function testRejectedLoginDebugCannotExposeCredentials() : void{
		Timings::init();
		$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1', 'config' => __DIR__ . '/openssl.cnf']);
		$policy = new NetherNetSessionPolicy(new PeerIdentity('verified', JwtUtils::emitDerPublicKey($key)));
		$session = (new \ReflectionClass(NetworkSession::class))->newInstanceWithoutConstructor();
		$messages = [];
		$logger = $this->createMock(\Logger::class);
		$logger->method('log')->willReturnCallback(static function($level, string $message) use (&$messages) : void{ $messages[] = $message; });
		(new \ReflectionProperty(NetworkSession::class, 'logger'))->setValue($session, new \PrefixedLogger($logger, 'test'));
		(new \ReflectionProperty(NetworkSession::class, 'transportPolicy'))->setValue($session, $policy);
		$credentials = 'sensitive-jwt-and-client-key';
		$session->handleDataPacket(new LoginPacket(), $credentials);
		self::assertCount(1, $messages);
		self::assertStringNotContainsString(base64_encode($credentials), $messages[0]);
		self::assertStringNotContainsString($credentials, $messages[0]);
		self::assertStringContainsString('Login', $messages[0]);
	}
}
