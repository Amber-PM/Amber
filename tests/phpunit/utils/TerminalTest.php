<?php

declare(strict_types=1);

namespace pocketmine\utils;

use PHPUnit\Framework\TestCase;

class TerminalTest extends TestCase{

	public function testPartyBlueToANSI() : void{
		Terminal::$COLOR_PARTY_BLUE = "\x1b[38;5;111m";
		$ansi = Terminal::toANSI(TextFormat::PARTY_BLUE . "text");
		$this->assertSame("\x1b[38;5;111mtext", $ansi);
	}

	public function testFallback256IndicesAll14Colors() : void{
		$class = new class extends Terminal{
			public static function initFallback() : void{
				self::getFallbackEscapeCodes();
			}
		};
		$class::initFallback();

		$expectedColors = [
			[Terminal::$COLOR_PARTY_BLUE, "\x1b[38;5;111m"],
			[Terminal::$COLOR_GRAY, "\x1b[38;5;251m"],
			[Terminal::$COLOR_DARK_GRAY, "\x1b[38;5;240m"],
			[Terminal::$COLOR_BLUE, "\x1b[38;5;69m"],
			[Terminal::$COLOR_MATERIAL_QUARTZ, "\x1b[38;5;187m"],
			[Terminal::$COLOR_MATERIAL_IRON, "\x1b[38;5;249m"],
			[Terminal::$COLOR_MATERIAL_NETHERITE, "\x1b[38;5;244m"],
			[Terminal::$COLOR_MATERIAL_REDSTONE, "\x1b[38;5;196m"],
			[Terminal::$COLOR_MATERIAL_COPPER, "\x1b[38;5;167m"],
			[Terminal::$COLOR_MATERIAL_GOLD, "\x1b[38;5;214m"],
			[Terminal::$COLOR_MATERIAL_DIAMOND, "\x1b[38;5;87m"],
			[Terminal::$COLOR_MATERIAL_LAPIS, "\x1b[38;5;69m"],
			[Terminal::$COLOR_MATERIAL_AMETHYST, "\x1b[38;5;134m"],
			[Terminal::$COLOR_MATERIAL_RESIN, "\x1b[38;5;202m"],
		];

		$this->assertCount(14, $expectedColors);
		foreach($expectedColors as [$actual, $expected]){
			$this->assertSame($expected, $actual);
		}
	}
}
