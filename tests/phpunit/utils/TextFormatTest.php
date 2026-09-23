<?php

declare(strict_types=1);

namespace pocketmine\utils;

use PHPUnit\Framework\TestCase;
use function preg_match;
use function strlen;

class TextFormatTest extends TestCase{

	public function testPartyBlueConstant() : void{
		$this->assertSame(TextFormat::ESCAPE . "w", TextFormat::PARTY_BLUE);
	}

	public function testTokenizeIncludesPartyBlue() : void{
		$tokens = TextFormat::tokenize("a§wb");
		$this->assertSame(["a", "§w", "b"], $tokens);
	}

	public function testCleanRemovesPartyBlue() : void{
		$cleaned = TextFormat::clean("a§wb");
		$this->assertSame("ab", $cleaned);
	}

	public function testColorizeAppliesPartyBlue() : void{
		$colorized = TextFormat::colorize("&w");
		$this->assertSame("§w", $colorized);
	}

	public function testAddBaseAcceptsPartyBlue() : void{
		$this->assertSame("§r§wx", TextFormat::addBase(TextFormat::PARTY_BLUE, "x"));
	}

	public function testToHTMLIncludesPartyBlue() : void{
		$html = TextFormat::toHTML("§wx§r");
		$this->assertStringContainsString("color:#8cb3ff", $html);
	}

	public function testAllColorsInHtmlPalette() : void{
		$expected = [
			"0" => "#000000", "1" => "#0000aa", "2" => "#00aa00", "3" => "#00aaaa", "4" => "#aa0000",
			"5" => "#aa00aa", "6" => "#ffaa00", "7" => "#c6c6c6", "8" => "#555555", "9" => "#447fff",
			"a" => "#55ff55", "b" => "#55ffff", "c" => "#ff5555", "d" => "#ff55ff", "e" => "#ffff55",
			"f" => "#ffffff", "g" => "#ddd605", "h" => "#d9ccb8", "i" => "#a9b4b7", "j" => "#8f727d",
			"m" => "#ee222c", "n" => "#c87363", "p" => "#ffbf1e", "q" => "#13a045", "s" => "#5fecff",
			"t" => "#577bff", "u" => "#b66cdd", "v" => "#ff6a00", "w" => "#8cb3ff"
		];

		foreach($expected as $char => $hex){
			$this->assertSame(7, strlen($hex));
			$this->assertSame(1, preg_match('/^#[0-9a-f]{6}$/', $hex));
			$html = TextFormat::toHTML("§" . $char . "text§r");
			$this->assertStringContainsString("color:" . $hex, $html);
		}
	}

	public function testRegressions() : void{
		$this->assertSame(["a", "§v", "b"], TextFormat::tokenize("a§vb"));
		$this->assertSame(["a§xb"], TextFormat::tokenize("a§xb"));
		$this->assertSame("&x", TextFormat::colorize("&x"));
		$this->assertSame("axb", TextFormat::clean("a§xb"));
	}
}
