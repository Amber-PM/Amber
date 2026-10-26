<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

require_once dirname(__DIR__, 3) . '/nethernet-bootstrap.php';

use PHPUnit\Framework\TestCase;

final class NetherNetConfigTest extends TestCase{
	public static function setUpBeforeClass() : void{
		if(!class_exists(\amber\nethernet\Transport::class)){ self::fail('Required NetherNet package is unavailable; run composer install'); }
	}

	public function testDisabledConfigurationDoesNotReadKeysOrRequireNativeBackend() : void{
		self::assertNull(NetherNetConfig::fromArray(['enabled' => false, 'private-key-file' => 'missing', 'trusted-key-files' => 'invalid'], sys_get_temp_dir()));
	}

	public function testAbsentEnableFlagUsesPocketMineDefaults() : void{
		self::assertSame('tcp://0.0.0.0:19132', NetherNetConfig::fromArray([], sys_get_temp_dir())->getBind());
	}

	public function testStringFalseCannotAccidentallyEnableTransport() : void{
		$this->expectException(\InvalidArgumentException::class);
		NetherNetConfig::fromArray(['enabled' => 'false'], sys_get_temp_dir());
	}

	public function testMissingTrustFailsBeforeListening() : void{
		$this->expectException(\InvalidArgumentException::class);
		NetherNetConfig::fromArray(['enabled' => true, 'private-key-file' => 'missing-identity.pem'], sys_get_temp_dir())->createIdentityHandler();
	}

	public function testBackendDefaultsToExtRtcAndAcceptsOnlyKnownOptIn() : void{
		$previous = getenv('AMBER_NETHERNET_BACKEND');
		try{
			putenv('AMBER_NETHERNET_BACKEND');
			self::assertSame('ext-rtc', NetherNetConfig::fromArray([], sys_get_temp_dir())?->getBackendName());
			putenv('AMBER_NETHERNET_BACKEND=');
			self::assertSame('ext-rtc', NetherNetConfig::fromArray([], sys_get_temp_dir())?->getBackendName());
			putenv('AMBER_NETHERNET_BACKEND=ext-webrtc');
			self::assertSame('ext-webrtc', NetherNetConfig::fromArray([], sys_get_temp_dir())?->getBackendName());
			putenv('AMBER_NETHERNET_BACKEND=webrtc');
			$this->expectException(\InvalidArgumentException::class);
			NetherNetConfig::fromArray([], sys_get_temp_dir());
		}finally{
			putenv($previous === false ? 'AMBER_NETHERNET_BACKEND' : 'AMBER_NETHERNET_BACKEND=' . $previous);
		}
	}

	/** @return iterable<string, array{array<string, mixed>}> */
	public static function invalidMuxSettings() : iterable{
		yield 'not a boolean' => [['ice-udp-mux' => 'true']];
		yield 'port range' => [['ice-udp-mux' => true, 'ice-port-range' => [20000, 20001], 'ice-bind-address' => '127.0.0.1']];
	}

	/** @param array<string, mixed> $settings */
	#[\PHPUnit\Framework\Attributes\DataProvider('invalidMuxSettings')]
	public function testUnsafeMuxSettingsFailAtStartup(array $settings) : void{
		$this->expectException(\InvalidArgumentException::class);
		NetherNetConfig::fromArray($settings, sys_get_temp_dir());
	}

	public function testWindowsMuxRequiresExplicitIceBindAddress() : void{
		if(PHP_OS_FAMILY !== 'Windows'){
			self::assertNotNull(NetherNetConfig::fromArray(['ice-udp-mux' => true, 'ice-port-range' => [20000, 20000]], sys_get_temp_dir()));
			return;
		}
		self::assertNotNull(NetherNetConfig::fromArray(['ice-udp-mux' => true, 'ice-port-range' => [20000, 20000], 'ice-bind-address' => '127.0.0.1'], sys_get_temp_dir()));
		$this->expectException(\InvalidArgumentException::class);
		NetherNetConfig::fromArray(['ice-udp-mux' => true, 'ice-port-range' => [20000, 20000]], sys_get_temp_dir());
	}

	public function testInvalidLimitsFailClosed() : void{
		$this->expectException(\InvalidArgumentException::class);
		NetherNetConfig::fromArray($this->settings() + ['max-connections' => 0], sys_get_temp_dir());
	}

	/** @return array<string, mixed> */
	public function settings() : array{
		return ['enabled' => true, 'bind' => 'tcp://127.0.0.1:0', 'operator-domain' => 'test.invalid', 'private-key-file' => 'operator.pem', 'trusted-key-files' => ['issuer-key' => 'issuer.pem'], 'issuer' => 'test-issuer', 'audience' => 'test-audience'];
	}

	public function testMissingKeyFilesFailClosed() : void{
		$config = NetherNetConfig::fromArray($this->settings(), sys_get_temp_dir());
		self::assertNotNull($config);
		$this->expectException(\InvalidArgumentException::class);
		$config->createIdentityHandler();
	}

	public function testLoadedTrustRejectsMalformedTransportIdentity() : void{
		$directory = sys_get_temp_dir() . '/amber-nethernet-' . bin2hex(random_bytes(6));
		mkdir($directory);
		try{
			$operator = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
			$issuer = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
			self::assertNotFalse($operator);
			self::assertNotFalse($issuer);
			self::assertTrue(openssl_pkey_export($operator, $private));
			file_put_contents($directory . '/operator.pem', $private);
			file_put_contents($directory . '/issuer.pem', openssl_pkey_get_details($issuer)['key']);
			$config = NetherNetConfig::fromArray($this->settings(), $directory);
			$handler = $config->createIdentityHandler();
			$failed = false;
			$handler->verifyClient('invalid', '{"fingerprint":[]}', 'invalid', static function($identity, $error) use (&$failed) : void{ $failed = $identity === null && $error !== null; });
			self::assertTrue($failed);
		}finally{
			foreach(['operator.pem', 'issuer.pem'] as $name){ if(is_file($directory . '/' . $name)){ unlink($directory . '/' . $name); } }
			rmdir($directory);
		}
	}
}
