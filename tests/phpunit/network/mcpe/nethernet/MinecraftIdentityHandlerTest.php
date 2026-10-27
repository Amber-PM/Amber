<?php

declare(strict_types=1);

namespace pocketmine\tests\network\mcpe\nethernet;

use amber\nethernet\identity\PeerIdentity;
use amber\nethernet\identity\ServerIdentity;
use amber\nethernet\TransportException;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\auth\AuthKeyProvider;
use pocketmine\network\mcpe\auth\AuthKeyring;
use pocketmine\network\mcpe\auth\ProcessOpenIdLoginTask;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\nethernet\MinecraftIdentityHandler;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\network\mcpe\protocol\types\login\openid\api\AuthServiceKey;
use pocketmine\scheduler\AsyncPool;

final class MinecraftIdentityHandlerTest extends TestCase{
	private static \OpenSSLAsymmetricKey $issuer;
	private static \OpenSSLAsymmetricKey $client;
	private const FINGERPRINTS = '{"fingerprint":[{"algorithm":"sha-256","digest":"AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99"}]}';

	public static function setUpBeforeClass() : void{
		self::$issuer = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048, 'config' => __DIR__ . '/openssl.cnf']);
		self::$client = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1', 'config' => __DIR__ . '/openssl.cnf']);
	}

	/** @return array<string, mixed> */
	private static function claims() : array{
		return ['iss' => 'https://issuer.test/', 'aud' => ProcessOpenIdLoginTask::MOJANG_AUDIENCE, 'sub' => 'player-123', 'exp' => time() + 300, 'nbf' => time() - 30, 'cpk' => base64_encode(JwtUtils::emitDerPublicKey(self::$client)), 'xid' => '1234'];
	}

	/**
	 * @param array<string, mixed>|null $claims
	 * @param array<string, mixed> $header
	 */
	private static function token(?array $claims = null, array $header = ['alg' => 'RS256', 'kid' => 'trusted']) : string{
		$input = JwtUtils::b64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)) . '.' . JwtUtils::b64UrlEncode(json_encode($claims ?? self::claims(), JSON_THROW_ON_ERROR));
		self::assertTrue(openssl_sign($input, $signature, self::$issuer, OPENSSL_ALGO_SHA256));
		return $input . '.' . JwtUtils::b64UrlEncode($signature);
	}

	private static function detached() : string{
		// jwtutils converts der ec signatures to jose r||s
		$jwt = JwtUtils::create(['alg' => 'ES384'], json_decode(self::FINGERPRINTS, true, flags: JSON_THROW_ON_ERROR), self::$client);
		[$header, , $signature] = explode('.', $jwt);
		return $header . '..' . $signature;
	}

	/** @return AuthKeyProvider&object{lookups: int} */
	private static function provider(PromiseResolver $resolver) : AuthKeyProvider{
		return new class($resolver) extends AuthKeyProvider{
			public int $lookups = 0;
			public function __construct(private readonly PromiseResolver $result){}
			public function getKey(string $keyId) : Promise{
				++$this->lookups;
				TestCase::assertSame('trusted', $keyId);
				return $this->result->getPromise();
			}
		};
	}

	private static function handler(AuthKeyProvider $provider) : MinecraftIdentityHandler{
		return new MinecraftIdentityHandler($provider, new ServerIdentity(self::$client, 'server.test'));
	}

	public function testAdapterExists() : void{
		self::assertTrue(class_exists(MinecraftIdentityHandler::class));
	}

	public function testKeyProviderRejectsFetchedBatchWithoutUsableRsaKeys() : void{
		$pool = (new \ReflectionClass(AsyncPool::class))->newInstanceWithoutConstructor();
		$provider = new AuthKeyProvider($this->createMock(\Logger::class), $pool);
		$resolver = new PromiseResolver();
		(new \ReflectionProperty(AuthKeyProvider::class, 'resolver'))->setValue($provider, $resolver);
		$key = new AuthServiceKey();
		$key->kid = 'not-rsa';
		$key->use = 'sig';
		$key->kty = 'EC';
		$rejected = false;
		$resolver->getPromise()->onCompletion(static function() : void{ self::fail('Unusable key batch was accepted'); }, static function() use (&$rejected) : void{ $rejected = true; });
		(new \ReflectionMethod(AuthKeyProvider::class, 'onKeysFetched'))->invoke($provider, ['not-rsa' => $key], 'https://issuer.test/', null);
		self::assertTrue($rejected);
	}

	public function testSharedWarmCacheAuthenticatesRepeatedOffersWithoutSchedulingRefresh() : void{
		$pool = $this->createMock(AsyncPool::class);
		$pool->expects(self::never())->method('submitTask');
		$provider = new AuthKeyProvider($this->createMock(\Logger::class), $pool);
		(new \ReflectionProperty(AuthKeyProvider::class, 'keyring'))->setValue($provider, new AuthKeyring('https://issuer.test/', ['trusted' => JwtUtils::emitDerPublicKey(self::$issuer)]));
		(new \ReflectionProperty(AuthKeyProvider::class, 'lastFetch'))->setValue($provider, time());
		$handler = self::handler($provider);
		$completed = 0;
		for($i = 0; $i < 2; ++$i){
			$handler->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function(?PeerIdentity $peer, ?TransportException $error) use (&$completed) : void{
				++$completed;
				self::assertNull($error);
				self::assertSame('player-123', $peer->subject);
			});
		}
		self::assertSame(2, $completed);
		$rejected = false;
		$provider->getKey('unknown')->onCompletion(static function() : void{ self::fail('Unknown key was accepted'); }, static function() use (&$rejected) : void{ $rejected = true; });
		self::assertTrue($rejected);
	}

	public function testUnavailableRefreshRejectsPendingLookupAndRetainsWarmCache() : void{
		$pool = $this->createMock(AsyncPool::class);
		$pool->expects(self::once())->method('submitTask');
		$logger = $this->createMock(\Logger::class);
		$logger->expects(self::once())->method('error')->with(self::stringContains('discovery unavailable'));
		$logger->expects(self::once())->method('critical')->with(self::stringContains('Failed to fetch authentication keys'));
		$provider = new AuthKeyProvider($logger, $pool);
		(new \ReflectionProperty(AuthKeyProvider::class, 'keyring'))->setValue($provider, new AuthKeyring('https://issuer.test/', ['trusted' => JwtUtils::emitDerPublicKey(self::$issuer)]));
		(new \ReflectionProperty(AuthKeyProvider::class, 'lastFetch'))->setValue($provider, time() - 3600);
		$rejected = false;
		$provider->getKey('rotated')->onCompletion(static function() : void{ self::fail('Unavailable refresh was accepted'); }, static function() use (&$rejected) : void{ $rejected = true; });
		self::assertFalse($rejected);
		(new \ReflectionMethod(AuthKeyProvider::class, 'onKeysFetched'))->invoke($provider, null, 'https://issuer.test/', ['discovery unavailable']);
		self::assertTrue($rejected);
		$completed = 0;
		self::handler($provider)->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function(?PeerIdentity $peer, ?TransportException $error) use (&$completed) : void{
			++$completed;
			self::assertNull($error);
			self::assertSame('player-123', $peer->subject);
		});
		self::assertSame(1, $completed);
	}

	public function testDeferredTrustedDerKeyCompletesWithVerifiedPeer() : void{
		$resolver = new PromiseResolver();
		$provider = self::provider($resolver);
		$completions = [];
		self::handler($provider)->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function(?PeerIdentity $peer, ?TransportException $error) use (&$completions) : void{ $completions[] = [$peer, $error]; });
		self::assertSame([], $completions);
		$resolver->resolve(['https://issuer.test/', JwtUtils::emitDerPublicKey(self::$issuer)]);
		self::assertCount(1, $completions);
		self::assertNull($completions[0][1]);
		self::assertSame('player-123', $completions[0][0]->subject);
		self::assertSame(JwtUtils::emitDerPublicKey(self::$client), $completions[0][0]->publicKeyDer);
		self::assertSame('1234', $completions[0][0]->claims['xid']);
	}

	public function testRejectedKeyLookupFailsClosed() : void{
		$resolver = new PromiseResolver();
		$completions = [];
		self::handler(self::provider($resolver))->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function(?PeerIdentity $peer, ?TransportException $error) use (&$completions) : void{ $completions[] = [$peer, $error]; });
		$resolver->reject();
		self::assertCount(1, $completions);
		self::assertNull($completions[0][0]);
		self::assertSame('identity_trust_failed', $completions[0][1]->errorCode);
	}

	public function testCancelledLookupCannotCompleteOnSuccessOrFailure() : void{
		foreach([true, false] as $success){
			$resolver = new PromiseResolver();
			$completed = 0;
			$cancel = self::handler(self::provider($resolver))->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function() use (&$completed) : void{ ++$completed; });
			$cancel();
			$cancel();
			if($success){ $resolver->resolve(['https://issuer.test/', JwtUtils::emitDerPublicKey(self::$issuer)]); }else{ $resolver->reject(); }
			self::assertSame(0, $completed);
		}
	}

	public function testConsumerExceptionIsPropagatedWithoutSecondCompletion() : void{
		$resolver = new PromiseResolver();
		$completed = 0;
		$failure = new \RuntimeException('consumer failed');
		self::handler(self::provider($resolver))->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function() use (&$completed, $failure) : void{ ++$completed; throw $failure; });
		try{
			$resolver->resolve(['https://issuer.test/', JwtUtils::emitDerPublicKey(self::$issuer)]);
			self::fail('Consumer exception was swallowed');
		}catch(\RuntimeException $e){
			self::assertSame($failure, $e);
		}
		self::assertSame(1, $completed);
	}

	public function testNonRsaTrustedKeyFailsClosed() : void{
		$resolver = new PromiseResolver();
		$resolver->resolve(['https://issuer.test/', JwtUtils::emitDerPublicKey(self::$client)]);
		$completed = 0;
		self::handler(self::provider($resolver))->verifyClient(self::token(), self::FINGERPRINTS, self::detached(), static function(?PeerIdentity $peer, ?TransportException $error) use (&$completed) : void{
			++$completed;
			self::assertNull($peer);
			self::assertSame('identity_untrusted', $error->errorCode);
		});
		self::assertSame(1, $completed);
	}

	public function testInvalidAssertionsDoNotFetchKeys() : void{
		foreach([
			['garbage', self::FINGERPRINTS, self::detached()],
			[self::token(header: ['alg' => 'none', 'kid' => 'trusted']), self::FINGERPRINTS, self::detached()],
			[self::token(header: ['alg' => 'RS256']), self::FINGERPRINTS, self::detached()],
			[self::token(header: ['alg' => 'RS256', 'kid' => str_repeat('x', 257)]), self::FINGERPRINTS, self::detached()],
			[self::token(header: ['alg' => 'RS256', 'kid' => "invalid\n"]), self::FINGERPRINTS, self::detached()],
			[self::token(header: ['alg' => 'RS256', 'kid' => 'trusted', 'crit' => []]), self::FINGERPRINTS, self::detached()],
			[self::token(), '{}', self::detached()],
			[str_repeat('x', 32769), self::FINGERPRINTS, self::detached()]
		] as [$token, $fingerprints, $jws]){
			$resolver = new PromiseResolver();
			$provider = self::provider($resolver);
			$completed = 0;
			self::handler($provider)->verifyClient($token, $fingerprints, $jws, static function(?PeerIdentity $peer, ?TransportException $error) use (&$completed) : void{ ++$completed; self::assertNull($peer); self::assertNotNull($error); });
			self::assertSame(1, $completed);
			self::assertSame(0, $provider->lookups);
		}
	}

	public function testInvalidClaimsAndSignaturesFailClosed() : void{
		$claims = self::claims();
		foreach([
			[self::token(array_replace($claims, ['exp' => time() - 1])), self::FINGERPRINTS, self::detached()],
			[self::token(array_replace($claims, ['nbf' => time() + 300])), self::FINGERPRINTS, self::detached()],
			[self::token(array_replace($claims, ['iss' => 'https://attacker.test/'])), self::FINGERPRINTS, self::detached()],
			[self::token(array_replace($claims, ['aud' => 'wrong'])), self::FINGERPRINTS, self::detached()],
			[self::token() . 'x', self::FINGERPRINTS, self::detached()],
			[self::token(), str_replace('AA:', 'AB:', self::FINGERPRINTS), self::detached()],
			[self::token(), self::FINGERPRINTS, self::detached() . 'x']
		] as [$token, $fingerprints, $jws]){
			$resolver = new PromiseResolver();
			$resolver->resolve(['https://issuer.test/', JwtUtils::emitDerPublicKey(self::$issuer)]);
			$completed = 0;
			self::handler(self::provider($resolver))->verifyClient($token, $fingerprints, $jws, static function(?PeerIdentity $peer, ?TransportException $error) use (&$completed) : void{ ++$completed; self::assertNull($peer); self::assertNotNull($error); });
			self::assertSame(1, $completed);
		}
	}

	public function testServerSigningUsesConfiguredIdentity() : void{
		$server = new ServerIdentity(self::$client, 'server.test');
		$handler = new MinecraftIdentityHandler(self::provider(new PromiseResolver()), $server);
		$result = $handler->signServer(self::FINGERPRINTS, time());
		self::assertNotEmpty($result);
		$envelope = json_decode(base64_decode($result, true), true, flags: JSON_THROW_ON_ERROR);
		self::assertSame('server.test', $envelope['idp']['domain']);
	}
}
