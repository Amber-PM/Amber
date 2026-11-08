<?php

/*
 *
 *     _             _               
 *    / \   _ __ ___ | |__   ___ _ __ 
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |   
 * /_/   \_\_| |_| |_|_.__/ \___|_|   
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */


declare(strict_types=1);

namespace pocketmine\network\mcpe\nethernet;

use amber\nethernet\identity\ClientIdentityVerifier;
use amber\nethernet\identity\IdentityHandler;
use amber\nethernet\identity\PeerIdentity;
use amber\nethernet\identity\SdpIdentity;
use amber\nethernet\identity\ServerIdentity;
use amber\nethernet\TransportConfig;
use amber\nethernet\TransportException;
use pocketmine\network\mcpe\auth\AuthKeyProvider;
use pocketmine\network\mcpe\auth\ProcessOpenIdLoginTask;
use pocketmine\network\mcpe\JwtUtils;

final class MinecraftIdentityHandler implements IdentityHandler{
	public function __construct(
		private readonly AuthKeyProvider $provider,
		private readonly ServerIdentity $server,
		private readonly TransportConfig $config = new TransportConfig()
	){}

	/**
	 * @param \Closure(?PeerIdentity, ?TransportException): void $complete
	 * @return \Closure(): void
	 */
	public function verifyClient(string $token, string $canonicalFingerprints, string $detachedJws, \Closure $complete) : \Closure{
		$active = true;
		$cancel = static function() use (&$active) : void{ $active = false; };
		$finish = static function(?PeerIdentity $peer, ?TransportException $error) use (&$active, $complete) : void{
			if($active){
				$active = false;
				$complete($peer, $error);
			}
		};
		try{
			$identity = SdpIdentity::fromAssertion($token, $canonicalFingerprints, $detachedJws, $this->config);
			$encodedHeader = explode('.', $token)[0];
			if(strlen($encodedHeader) > 4096){
				throw new TransportException('identity_invalid', 'Client JOSE header exceeds limit');
			}
			$header = json_decode(JwtUtils::b64UrlDecode($encodedHeader), false, 16, JSON_THROW_ON_ERROR);
			if(!$header instanceof \stdClass || ($header->alg ?? null) !== 'RS256' ||
				property_exists($header, 'crit') || (property_exists($header, 'b64') && $header->b64 !== true) ||
				!is_string($header->kid ?? null) || strlen($header->kid) > 256 || preg_match('/^[\x21-\x7e]+$/D', $header->kid) !== 1){
				throw new TransportException('identity_invalid', 'Client token requires RS256 and a bounded printable key ID');
			}
			$promise = $this->provider->getKey($header->kid);
		}catch(\Throwable $e){
			$finish(null, $e instanceof TransportException ? $e : new TransportException('identity_invalid', 'Client identity assertion could not be processed', $e));
			return $cancel;
		}
		$promise->onCompletion(
			function(array $trusted) use (&$active, $identity, $finish) : void{
				if(!$active){
					return;
				}
				$peer = null;
				$error = null;
				try{
					[$issuer, $der] = $trusted;
					// auth keys are der bytes rather than openssl handles
					$key = JwtUtils::parseDerPublicKey($der);
					$verifier = new ClientIdentityVerifier(static fn(?string $kid) => $key, $issuer, ProcessOpenIdLoginTask::MOJANG_AUDIENCE);
					$peer = $verifier->verify($identity, time());
				}catch(\Throwable $e){
					$error = $e instanceof TransportException ? $e : new TransportException('identity_trust_failed', 'Client identity verification failed', $e);
				}
				// keep consumer errors out of verification handling
				$finish($peer, $error);
			},
			static fn() => $finish(null, new TransportException('identity_trust_failed', 'Minecraft authentication key lookup failed'))
		);
		return $cancel;
	}

	public function signServer(string $canonicalFingerprints, int $now) : string{
		return $this->server->signServer($canonicalFingerprints, $now);
	}
}
