<?php

declare(strict_types=1);

namespace pocketmine\web;

use PHPUnit\Framework\TestCase;
use function str_repeat;

require_once __DIR__ . '/../../../src/web/HttpException.php';
require_once __DIR__ . '/../../../src/web/HttpRequest.php';

final class HttpRequestTest extends TestCase{

	public function testIncompleteRequestWaits() : void{
		self::assertNull(HttpRequest::parse("GET / HTTP/1.1\r\nHost: x\r\n", "1.2.3.4"));
		self::assertNull(HttpRequest::parse("POST /api/command HTTP/1.1\r\nContent-Length: 10\r\n\r\n{\"a\":", "1.2.3.4"));
	}

	public function testParsesRequest() : void{
		$request = HttpRequest::parse("POST /api/log?after=5 HTTP/1.1\r\nAuthorization: Bearer abc\r\nContent-Length: 2\r\n\r\n{}", "1.2.3.4");
		self::assertNotNull($request);
		self::assertSame("POST", $request->method);
		self::assertSame("/api/log", $request->path);
		self::assertSame(["after" => "5"], $request->query);
		self::assertSame("Bearer abc", $request->getHeader("authorization"));
		self::assertSame("{}", $request->body);
		self::assertSame("1.2.3.4", $request->remoteAddress);
	}

	/**
	 * @return \Generator<string, array{string, int}>
	 */
	public static function badRequests() : \Generator{
		yield "garbage" => ["hello\r\n\r\n", 400];
		yield "no leading slash" => ["GET api HTTP/1.1\r\n\r\n", 400];
		yield "bad header" => ["GET / HTTP/1.1\r\nBad Header: x\r\n\r\n", 400];
		yield "huge headers" => ["GET / HTTP/1.1\r\nX: " . str_repeat("a", HttpRequest::MAX_HEADER_BYTES + 1), 431];
		yield "huge body" => ["POST / HTTP/1.1\r\nContent-Length: " . (HttpRequest::MAX_BODY_BYTES + 1) . "\r\n\r\n", 413];
		yield "negative length" => ["POST / HTTP/1.1\r\nContent-Length: -1\r\n\r\n", 400];
		yield "chunked" => ["POST / HTTP/1.1\r\nTransfer-Encoding: chunked\r\n\r\n", 501];
	}

	/**
	 * @dataProvider badRequests
	 */
	public function testRejectsBadRequests(string $raw, int $status) : void{
		try{
			HttpRequest::parse($raw, "1.2.3.4");
			self::fail("accepted");
		}catch(HttpException $e){
			self::assertSame($status, $e->getStatus());
		}
	}
}
