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
use amber\nethernet\identity\ClientServerIdentityHandler;
use amber\nethernet\identity\IdentityHandler;
use amber\nethernet\identity\ServerIdentity;
use amber\nethernet\backend\ExtRtcPeer;
use amber\nethernet\backend\ExtWebRtcPeer;
use amber\nethernet\backend\WebRtcPeer;
use amber\nethernet\TransportConfig;
use pocketmine\network\mcpe\auth\AuthKeyProvider;
use pocketmine\utils\TextFormat;
use Symfony\Component\Filesystem\Path;

final class NetherNetConfig{
	/** @param array<string, mixed> $settings */
	private function __construct(private array $settings, private string $dataPath){}

	/** @param array<string, mixed> $settings */
	public static function fromArray(array $settings, string $dataPath, int $serverPort = 19132, string $serverIp = '0.0.0.0', string $motd = 'self') : ?self{
		$enabled = $settings['enabled'] ?? true;
		if(!is_bool($enabled)){ throw new \InvalidArgumentException('network.nethernet.enabled must be a boolean'); }
		if(!$enabled){ return null; }
		$port = $settings['signaling-port'] ?? $serverPort;
		if(!is_int($port) || $port < 1 || $port > 65535){ throw new \InvalidArgumentException('NetherNet signaling-port must be between 1 and 65535'); }
		$address = $settings['bind-address'] ?? $serverIp;
		if(!is_string($address) || filter_var($address, FILTER_VALIDATE_IP) === false){ throw new \InvalidArgumentException('NetherNet bind-address must be a numeric IPv4 or IPv6 address'); }
		$settings['bind'] ??= 'tcp://' . (str_contains($address, ':') ? '[' . $address . ']' : $address) . ':' . $port;
		foreach(['operator-domain', 'private-key-file'] as $name){
			if(($settings[$name] ?? '') === ''){ unset($settings[$name]); }
		}
		$domain = trim(strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', TextFormat::clean($motd))), '-');
		$settings['operator-domain'] ??= trim(substr($domain, 0, 63), '-') ?: 'self';
		foreach(['bind', 'operator-domain'] as $name){
			$value = $settings[$name] ?? null;
			if(!is_string($value) || $value === '' || strlen($value) > 1024 || preg_match('/[\x00-\x1f]/', $value)){
				throw new \InvalidArgumentException('NetherNet requires a bounded ' . $name . ' string');
			}
		}
		if(preg_match('~^tcp://([0-9.]+|\[[0-9a-fA-F:.]+\]):([0-9]{1,5})$~D', $settings['bind'], $parts) !== 1 || filter_var(trim($parts[1], '[]'), FILTER_VALIDATE_IP) === false || (int) $parts[2] > 65535){
			throw new \InvalidArgumentException('NetherNet bind must be a numeric TCP endpoint');
		}
		$keys = $settings['trusted-key-files'] ?? [];
		if(!is_array($keys) || count($keys) > 8){ throw new \InvalidArgumentException('NetherNet trusted-key-files must be a map of at most 8 keys'); }
		foreach($keys as $kid => $file){
			if(!is_string($kid) || $kid === '' || strlen($kid) > 256 || preg_match('/[\x00-\x1f]/', $kid) || !is_string($file) || $file === '' || strlen($file) > 1024){
				throw new \InvalidArgumentException('NetherNet trusted-key-files must map nonempty key IDs to local PEM paths');
			}
		}
		$settings['trusted-key-files'] = $keys;
		$range = $settings['ice-port-range'] ?? [20000, 20127];
		if(!is_array($range) || !array_is_list($range) || count($range) !== 2 || !is_int($range[0]) || !is_int($range[1]) || $range[0] < 1 || $range[1] > 65535 || $range[0] > $range[1]){
			throw new \InvalidArgumentException('NetherNet ice-port-range must contain an ordered pair of UDP ports between 1 and 65535');
		}
		$settings['ice-port-range'] = $range;
		$iceBind = $settings['ice-bind-address'] ?? trim($parts[1], '[]');
		if(!is_string($iceBind) || filter_var($iceBind, FILTER_VALIDATE_IP) === false){ throw new \InvalidArgumentException('NetherNet ice-bind-address must be numeric'); }
		$settings['ice-bind-address'] = $iceBind;
		$mux = $settings['ice-udp-mux'] ?? false;
		if(!is_bool($mux)){ throw new \InvalidArgumentException('NetherNet ice-udp-mux must be a boolean'); }
		// fail at startup, not on the first player
		if($mux){ ExtRtcPeer::assertMuxOptions($range[0], $range[1], $iceBind, PHP_OS_FAMILY === 'Windows'); }
		$settings['ice-udp-mux'] = $mux;
		if(isset($settings['advertised-address'])){ throw new \InvalidArgumentException('NetherNet advertised-address is blocked: the pinned native backend has no advertised-address API'); }
		foreach(['max-connections' => [128, 1024], 'max-per-ip' => [4, 1024], 'max-http-connections' => [64, 1024], 'max-pending-negotiations' => [32, 1024]] as $name => [$default, $maximum]){
			$value = $settings[$name] ?? $default;
			if(!is_int($value) || $value < 1 || $value > $maximum){ throw new \InvalidArgumentException('NetherNet ' . $name . ' is outside its bounded integer range'); }
			$settings[$name] = $value;
		}
		if($settings['max-per-ip'] > $settings['max-connections'] || $settings['max-pending-negotiations'] > $settings['max-connections']){
			throw new \InvalidArgumentException('NetherNet per-IP and pending limits cannot exceed the connection limit');
		}
		if(isset($settings['server-token-claims']) && !is_array($settings['server-token-claims'])){ throw new \InvalidArgumentException('NetherNet server-token-claims must be a map'); }
		// ext-rtc is the default, ext-webrtc stays selectable for A/B checks
		$backend = getenv('AMBER_NETHERNET_BACKEND');
		$settings['backend'] = $backend === false || $backend === '' ? 'ext-rtc' : $backend;
		if(!in_array($settings['backend'], ['ext-webrtc', 'ext-rtc'], true)){ throw new \InvalidArgumentException('AMBER_NETHERNET_BACKEND must be ext-webrtc or ext-rtc'); }
		return new self($settings, $dataPath);
	}

	public function getBackendName() : string{ return $this->settings['backend']; }
	public function assertBackendAvailable() : void{
		// both link libdatachannel 0.24, one process must load only one of them
		if(extension_loaded('rtc') && extension_loaded('webrtc')){ throw new \RuntimeException('ext-rtc and ext-webrtc must not be loaded in the same PHP process'); }
		$this->settings['backend'] === 'ext-rtc' ? ExtRtcPeer::assertAvailable() : ExtWebRtcPeer::assertAvailable();
	}

	public function getBind() : string{ return $this->settings['bind']; }
	public function getMaxPerIp() : int{ return $this->settings['max-per-ip']; }
	public function createPeer() : WebRtcPeer{
		$class = $this->settings['backend'] === 'ext-rtc' ? ExtRtcPeer::class : ExtWebRtcPeer::class;
		return new $class($this->createTransportConfig(), $this->settings['ice-port-range'][0], $this->settings['ice-port-range'][1], $this->settings['ice-bind-address'], $this->settings['ice-udp-mux']);
	}
	public function createTransportConfig() : TransportConfig{
		return new TransportConfig(maxConnections: $this->settings['max-connections'], maxPendingNegotiations: $this->settings['max-pending-negotiations'], maxHttpConnections: $this->settings['max-http-connections']);
	}

	private function readPem(string $file) : string{
		if(str_contains($file, "\0") || preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*://~', $file)){
			throw new \InvalidArgumentException('NetherNet keys must be local regular files');
		}
		$path = Path::makeAbsolute($file, $this->dataPath);
		if(!is_file($path) || !is_readable($path)){ throw new \InvalidArgumentException('NetherNet key file is unavailable'); }
		$pem = @file_get_contents($path, false, null, 0, 16385);
		if($pem === false || strlen($pem) > 16384 || !str_starts_with($pem, '-----BEGIN ')){
			throw new \InvalidArgumentException('NetherNet key file must contain at most 16384 bytes of PEM');
		}
		return $pem;
	}

	public function createIdentityHandler(?AuthKeyProvider $provider = null) : IdentityHandler{
		$server = $this->createServerIdentity();
		if($provider !== null){ return new MinecraftIdentityHandler($provider, $server, $this->createTransportConfig()); }
		// pinned trust supports offline setups
		if($this->settings['trusted-key-files'] === [] || !is_string($this->settings['issuer'] ?? null) || !is_string($this->settings['audience'] ?? null)){
			throw new \InvalidArgumentException('NetherNet requires the Minecraft authentication key provider');
		}
		$keys = [];
		foreach($this->settings['trusted-key-files'] as $kid => $file){
			$key = @openssl_pkey_get_public($this->readPem($file));
			$details = $key !== false ? openssl_pkey_get_details($key) : false;
			if($key === false || $details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048){
				throw new \InvalidArgumentException('NetherNet issuer keys must be RSA public keys of at least 2048 bits');
			}
			$keys[$kid] = $key;
		}
		$verifier = new ClientIdentityVerifier(static fn(?string $kid) => $kid !== null ? ($keys[$kid] ?? null) : array_values($keys), $this->settings['issuer'], $this->settings['audience']);
		return new ClientServerIdentityHandler($verifier, $server, $this->createTransportConfig());
	}

	public function createServerIdentity() : ServerIdentity{
		$file = $this->settings['private-key-file'] ?? null;
		if($file !== null){
			if(!is_string($file) || $file === ''){ throw new \InvalidArgumentException('NetherNet private-key-file must be a local path'); }
			return new ServerIdentity($this->readPem($file), $this->settings['operator-domain'], claims: $this->settings['server-token-claims'] ?? []);
		}
		$path = Path::makeAbsolute('nethernet.key', $this->dataPath);
		$lock = @fopen($path . '.lock', 'c');
		if($lock === false){ throw new \RuntimeException('Cannot lock nethernet.key; check data directory permissions'); }
		$temporary = null;
		try{
			if(!flock($lock, LOCK_EX)){ throw new \RuntimeException('Cannot lock nethernet.key'); }
			if(is_link($path)){ throw new \InvalidArgumentException('Existing nethernet.key must not be a symbolic link'); }
			if(!file_exists($path)){
				$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
				if($key === false || !openssl_pkey_export($key, $pem)){ throw new \RuntimeException('Cannot generate NetherNet P-384 identity'); }
				$temporary = tempnam(dirname($path), '.nethernet-key-');
				if($temporary === false){
					throw new \RuntimeException('Cannot restrict nethernet.key permissions');
				}
				self::restrictIdentityPermissions($temporary);
				if(file_put_contents($temporary, $pem) !== strlen($pem) || !rename($temporary, $path)){
					throw new \RuntimeException('Cannot securely save nethernet.key; check data directory permissions');
				}
				$temporary = null;
			}
			self::restrictIdentityPermissions($path);
			try{ return new ServerIdentity($this->readPem('nethernet.key'), $this->settings['operator-domain'], claims: $this->settings['server-token-claims'] ?? []); }
			catch(\InvalidArgumentException $e){ throw new \InvalidArgumentException('Existing nethernet.key is invalid; restore the server identity from backup', 0, $e); }
		}finally{
			if(is_string($temporary)){ @unlink($temporary); }
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private static function restrictIdentityPermissions(string $path) : void{
		if(PHP_OS_FAMILY !== 'Windows'){
			if(!chmod($path, 0600)){ throw new \RuntimeException('Cannot restrict nethernet.key permissions'); }
			clearstatcache(true, $path);
			if((fileperms($path) & 0777) !== 0600){
				throw new \RuntimeException('The data directory filesystem cannot restrict nethernet.key to its owner; use a filesystem supporting private file permissions');
			}
			return;
		}
		// windows chmod does not set access rights
		$script = <<<'POWERSHELL'
$ErrorActionPreference='Stop'
try{
    $path=$env:AMBER_NETHERNET_KEY_PATH
    $sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User
    $acl=New-Object System.Security.AccessControl.FileSecurity
    $acl.SetOwner($sid)
    $acl.SetAccessRuleProtection($true,$false)
    $rule=New-Object System.Security.AccessControl.FileSystemAccessRule($sid,'FullControl','Allow')
    $acl.AddAccessRule($rule)
    [System.IO.File]::SetAccessControl($path,$acl)
    $actual=[System.IO.File]::GetAccessControl($path)
    $rules=$actual.GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier])
    if(!$actual.AreAccessRulesProtected -or $rules.Count -ne 1 -or $rules[0].IdentityReference.Value -ne $sid.Value -or $rules[0].AccessControlType -ne 'Allow'){exit 1}
}catch{exit 1}
POWERSHELL;
		$executable = getenv('SystemRoot') . '/System32/WindowsPowerShell/v1.0/powershell.exe';
		$process = proc_open([$executable, '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, env_vars: array_merge(getenv(), ['AMBER_NETHERNET_KEY_PATH' => $path]));
		if($process === false){ throw new \RuntimeException('Cannot establish private Windows permissions for nethernet.key'); }
		fclose($pipes[0]);
		stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		if(proc_close($process) !== 0){ throw new \RuntimeException('Cannot establish private Windows permissions for nethernet.key'); }
	}
}
