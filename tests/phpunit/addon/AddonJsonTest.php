<?php

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;

final class AddonJsonTest extends TestCase{

	public function testTrailingCommaInObject() : void{
		$decoded = AddonJson::decode('{"a":1,}', "test");
		self::assertSame(["a" => 1], $decoded);
	}

	public function testTrailingCommaInArray() : void{
		$decoded = AddonJson::decode('{"a":[1,2,],}', "test");
		self::assertSame(["a" => [1, 2]], $decoded);
	}

	public function testStringPreservesCommaBeforeBrace() : void{
		$decoded = AddonJson::decode('{"text":"hello,}"}', "test");
		self::assertSame(["text" => "hello,}"], $decoded);
	}

	public function testStringPreservesCommaBeforeBracket() : void{
		$decoded = AddonJson::decode('{"text":"hello,]"}', "test");
		self::assertSame(["text" => "hello,]"], $decoded);
	}

	public function testStringWithEscapedQuoteAndTrailingComma() : void{
		$decoded = AddonJson::decode('{"text":"escaped \" quote,}",}', "test");
		self::assertSame(["text" => 'escaped " quote,}'], $decoded);
	}

	public function testStringWithEscapedBackslashAndTrailingComma() : void{
		$decoded = AddonJson::decode('{"path":"dir\\\\",}', "test");
		self::assertSame(["path" => 'dir\\'], $decoded);
	}

	public function testCommentsCombinedWithTrailingCommas() : void{
		$json = <<<JSON
		{
			// line comment
			"key": "value", // inline comment
			"list": [
				1,
				2, /* block comment */
			],
			/* multi
			   line
			   comment */
		}
		JSON;

		$decoded = AddonJson::decode($json, "test");
		self::assertSame([
			"key" => "value",
			"list" => [1, 2]
		], $decoded);
	}

	public function testInvalidJsonThrowsAddonException() : void{
		$this->expectException(AddonException::class);
		AddonJson::decode('{"unclosed": "string', "test");
	}

	public function testUnterminatedBlockCommentIsRejected() : void{
		$this->expectException(AddonException::class);
		AddonJson::decode('{"a":1} /* unterminated', "test");
	}
}
