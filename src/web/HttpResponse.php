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

use function json_encode;
use function strlen;
use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class HttpResponse{
	private const REASONS = [
		200 => "OK", 204 => "No Content", 400 => "Bad Request", 401 => "Unauthorized", 403 => "Forbidden", 404 => "Not Found",
		405 => "Method Not Allowed", 408 => "Request Timeout", 413 => "Content Too Large", 415 => "Unsupported Media Type",
		429 => "Too Many Requests", 431 => "Request Header Fields Too Large", 500 => "Internal Server Error", 501 => "Not Implemented",
		503 => "Service Unavailable",
	];

	/**
	 * @param array<string, string> $headers
	 */
	public function __construct(
		public readonly int $status,
		public readonly string $body,
		public readonly string $contentType = "text/plain; charset=utf-8",
		public readonly array $headers = []
	){}

	public static function json(mixed $data, int $status = 200) : self{
		return new self($status, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "application/json; charset=utf-8");
	}

	public static function error(int $status, string $message) : self{
		return self::json(["error" => $message], $status);
	}

	public function encode() : string{
		$head = "HTTP/1.1 " . $this->status . " " . (self::REASONS[$this->status] ?? "Unknown") . "\r\n" .
			"Content-Type: " . $this->contentType . "\r\n" .
			"Content-Length: " . strlen($this->body) . "\r\n" .
			"Connection: close\r\n" .
			"Cache-Control: no-store\r\n" .
			"X-Content-Type-Options: nosniff\r\n" .
			"X-Frame-Options: DENY\r\n" .
			"Referrer-Policy: no-referrer\r\n" .
			"Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'\r\n";
		foreach($this->headers as $name => $value){
			$head .= $name . ": " . $value . "\r\n";
		}
		return $head . "\r\n" . $this->body;
	}
}
