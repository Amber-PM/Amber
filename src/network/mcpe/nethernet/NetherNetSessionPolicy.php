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

use amber\nethernet\identity\PeerIdentity;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\SessionTransportPolicy;

final class NetherNetSessionPolicy implements SessionTransportPolicy{
	private readonly string $publicKeyDer;
	public function __construct(PeerIdentity $identity){
		$this->publicKeyDer = self::canonicalKey($identity->publicKeyDer);
	}
	private static function canonicalKey(string $der) : string{
		$key = @openssl_pkey_get_public(JwtUtils::derPublicKeyToPem($der));
		if($key === false){
			throw new \InvalidArgumentException('Invalid transport identity public key');
		}
		$details = openssl_pkey_get_details($key);
		if($details === false || ($details['ec']['curve_name'] ?? null) !== 'secp384r1'){
			throw new \InvalidArgumentException('Transport identity requires a P-384 public key');
		}
		return JwtUtils::emitDerPublicKey($key);
	}
	
	public function usesGameEncryption() : bool{ return false; }
	public function hasSynchronousClose() : bool{ return true; }
	public function allowsRawPacketLogging() : bool{ return false; }
	public function validateLogin(bool $authenticated, ?string $clientPublicKeyDer) : ?string{
		if(!$authenticated){ return 'NetherNet requires authenticated Minecraft Login'; }
		if($clientPublicKeyDer === null){ return 'Missing authenticated client public key'; }
		try{ $canonical = self::canonicalKey($clientPublicKeyDer); }
		catch(\InvalidArgumentException){ return 'Invalid authenticated client public key'; }
		return hash_equals($this->publicKeyDer, $canonical) ? null : 'Transport and Minecraft identities do not match';
	}
}
