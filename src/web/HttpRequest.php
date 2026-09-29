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

use function count;
use function explode;
use function is_string;
use function ltrim;
use function parse_str;
use function parse_url;
use function preg_match;
use function rawurldecode;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * An HTTP/1.x request, parsed with strict limits (the web server is reachable by anyone who can connect).
 */
final class HttpRequest{
	public const MAX_HEADER_BYTES = 16 * 1024;
	public const MAX_BODY_BYTES = 16 * 1024;

	/**
	 * @param array<string, string> $headers lower-case names
	 * @param array<string, mixed>  $query
	 */
	private function __construct(
		public readonly string $method,
		public readonly string $path,
		public readonly array $query,
		public readonly array $headers,
		public readonly string $body,
		public readonly string $remoteAddress
	){}

	public function getHeader(string $name) : ?string{
		return $this->headers[strtolower($name)] ?? null;
	}

	/**
	 * Parses a request from the bytes received so far.
	 *
	 * @return self|null null while the request is not complete
	 * @throws HttpException if the request is malformed or too large
	 */
	public static function parse(string $buffer, string $remoteAddress) : ?self{
		$headerEnd = strpos($buffer, "\r\n\r\n");
		if($headerEnd === false){
			if(strlen($buffer) > self::MAX_HEADER_BYTES){
				throw new HttpException(431, "Request headers too large");
			}
			return null;
		}
		if($headerEnd > self::MAX_HEADER_BYTES){
			throw new HttpException(431, "Request headers too large");
		}
		$lines = explode("\r\n", substr($buffer, 0, $headerEnd));
		if(preg_match('#^([A-Z]{1,10}) (/[^ ]{0,2048}) HTTP/1\.[01]$#', $lines[0], $matches) !== 1){
			throw new HttpException(400, "Malformed request line");
		}
		$headers = [];
		for($i = 1, $count = count($lines); $i < $count; ++$i){
			$parts = explode(":", $lines[$i], 2);
			if(count($parts) !== 2 || preg_match('/^[A-Za-z0-9-]+$/', $parts[0]) !== 1){
				throw new HttpException(400, "Malformed header");
			}
			$headers[strtolower($parts[0])] = trim($parts[1]);
		}

		$length = 0;
		if(isset($headers["transfer-encoding"])){
			throw new HttpException(501, "Transfer-Encoding is not supported");
		}
		if(isset($headers["content-length"])){
			if(preg_match('/^\d{1,7}$/', $headers["content-length"]) !== 1){
				throw new HttpException(400, "Invalid Content-Length");
			}
			$length = (int) $headers["content-length"];
			if($length > self::MAX_BODY_BYTES){
				throw new HttpException(413, "Request body too large");
			}
		}
		if(strlen($buffer) < $headerEnd + 4 + $length){
			return null;
		}

		$target = $matches[2];
		$path = parse_url($target, PHP_URL_PATH);
		$queryString = parse_url($target, PHP_URL_QUERY);
		if(!is_string($path)){
			throw new HttpException(400, "Invalid path");
		}
		$query = [];
		if(is_string($queryString)){
			parse_str($queryString, $query);
		}
		return new self(
			strtoupper($matches[1]),
			"/" . ltrim(rawurldecode($path), "/"),
			$query,
			$headers,
			substr($buffer, $headerEnd + 4, $length),
			$remoteAddress
		);
	}
}
