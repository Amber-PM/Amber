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

namespace pocketmine\web;

use function array_key_first;
use function count;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function hrtime;
use function is_file;
use function is_readable;
use function is_resource;
use function str_contains;
use function stream_context_create;
use function stream_select;
use function stream_set_blocking;
use function stream_socket_accept;
use function stream_socket_enable_crypto;
use function stream_socket_server;
use function strrpos;
use function substr;
use function trim;
use const STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;
use const STREAM_CRYPTO_METHOD_TLSv1_3_SERVER;
use const STREAM_SERVER_BIND;
use const STREAM_SERVER_LISTEN;

/**
 * A small HTTP/1.1 server run from the main thread: sockets are non-blocking and polled once a tick, so a slow or
 * hostile client costs nothing but a connection slot. One request per connection. With a certificate and key it
 * speaks HTTPS (TLS 1.2 or 1.3), the handshake being polled like the rest.
 */
final class HttpServer{
	private const MAX_CONNECTIONS = 32;
	private const MAX_ACCEPTS_PER_TICK = 16;
	private const CONNECTION_TIMEOUT_NS = 5_000_000_000;
	private const READ_CHUNK = 8192;

	/** @var resource */
	private $socket;
	/**
	 * @var array<int, array{resource, string, string, int, ?string, bool}> connection id => socket, received bytes,
	 *      remote address, deadline, response still to write (null while reading), whether TLS is set up
	 */
	private array $connections = [];
	private bool $tls;

	/**
	 * @phpstan-param \Closure(HttpRequest) : HttpResponse $handler
	 * @throws \RuntimeException if the address cannot be listened on, or the certificate or key cannot be read
	 */
	public function __construct(string $address, int $port, private \Closure $handler, ?string $certificateFile = null, ?string $keyFile = null){
		$host = str_contains($address, ":") ? "[$address]" : $address;
		$errno = 0;
		$error = "";
		$options = ["socket" => ["so_reuseaddr" => true]];
		$this->tls = $certificateFile !== null;
		if($certificateFile !== null){
			foreach([$certificateFile, $keyFile] as $file){
				if($file === null || !is_file($file) || !is_readable($file)){
					throw new \RuntimeException("Cannot read TLS certificate or key file " . ($file ?? "(none)"));
				}
			}
			$options["ssl"] = [
				"local_cert" => $certificateFile,
				"local_pk" => $keyFile,
				"disable_compression" => true,
				"honor_cipher_order" => true,
				"crypto_method" => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
			];
		}
		$context = stream_context_create($options);
		$socket = @stream_socket_server("tcp://$host:$port", $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
		if($socket === false){
			throw new \RuntimeException("Cannot listen on $host:$port: $error");
		}
		stream_set_blocking($socket, false);
		$this->socket = $socket;
	}

	/** Accepts, reads and answers whatever is ready, without waiting. */
	public function tick() : void{
		for($i = 0; $i < self::MAX_ACCEPTS_PER_TICK; ++$i){
			$read = [$this->socket];
			$write = $except = null;
			if(@stream_select($read, $write, $except, 0) !== 1){
				break;
			}
			$client = @stream_socket_accept($this->socket, 0, $peer);
			if($client === false){
				break;
			}
			if(count($this->connections) >= self::MAX_CONNECTIONS){
				//full: drop the oldest connection, so idle clients cannot lock everyone else out
				$this->close(array_key_first($this->connections));
			}
			stream_set_blocking($client, false);
			$this->connections[(int) $client] = [$client, "", self::hostOf((string) $peer), hrtime(true) + self::CONNECTION_TIMEOUT_NS, null, !$this->tls];
		}

		$now = hrtime(true);
		foreach($this->connections as $id => [$client, $buffer, $remote, $deadline, $response, $secured]){
			if(!is_resource($client) || $now > $deadline){
				$this->close($id);
				continue;
			}
			if(!$secured){
				$handshake = @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);
				if($handshake === 0){
					continue; //needs more data from the client
				}
				if($handshake !== true){
					$this->close($id); //not TLS, or a failed handshake
					continue;
				}
				$this->connections[$id][5] = true;
			}
			if($response === null){
				$chunk = @fread($client, self::READ_CHUNK);
				if($chunk === false || ($chunk === "" && feof($client))){
					$this->close($id);
					continue;
				}
				$buffer .= $chunk;
				$this->connections[$id][1] = $buffer;
				try{
					$request = HttpRequest::parse($buffer, $remote);
					if($request === null){
						continue;
					}
					$response = ($this->handler)($request)->encode();
				}catch(HttpException $e){
					$response = HttpResponse::error($e->getStatus(), $e->getMessage())->encode();
				}catch(\Throwable $e){
					\GlobalLogger::get()->logException($e);
					$response = HttpResponse::error(500, "Internal error")->encode();
				}
			}
			$written = @fwrite($client, $response);
			if($written === false){
				$this->close($id);
				continue;
			}
			$response = substr($response, $written);
			if($response === ""){
				$this->close($id);
			}else{
				$this->connections[$id][4] = $response;
			}
		}
	}

	private function close(int $id) : void{
		$client = $this->connections[$id][0];
		if(is_resource($client)){
			@fclose($client);
		}
		unset($this->connections[$id]);
	}

	public function shutdown() : void{
		foreach($this->connections as $id => $_){
			$this->close($id);
		}
		if(is_resource($this->socket)){
			fclose($this->socket);
		}
	}

	public function isTls() : bool{
		return $this->tls;
	}

	private static function hostOf(string $peer) : string{
		$colon = strrpos($peer, ":");
		$host = $colon === false ? $peer : substr($peer, 0, $colon);
		return trim($host, "[]");
	}
}
