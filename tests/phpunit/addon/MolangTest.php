<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\Molang;

final class MolangTest extends TestCase{

	/**
	 * @phpstan-return \Generator<int, array{string, float|bool|string}, void, void>
	 */
	public static function expressionProvider() : \Generator{
		yield ["1 + 2 * 3", 7.0];
		yield ["(1 + 2) * 3", 9.0];
		yield ["10 / 4", 2.5];
		yield ["1 / 0", 0.0];
		yield ["-3 + 5", 2.0];
		yield ["math.clamp(12, 0, 10)", 10.0];
		yield ["math.max(2, 9) - math.min(2, 9)", 7.0];
		yield ["math.floor(2.7) + math.ceil(0.2)", 3.0];
		yield ["1 < 2 ? 5 : 6", 5.0];
		yield ["2 <= 1 ? 5 : 6", 6.0];
		yield ["!(1 == 1) ? 1 : 0", 0.0];
		yield ["true && false", false];
		yield ["true || false", true];
		yield ["1 != 2", true];
		yield ["return 4;", 4.0];
		yield ["'abc' == 'abc'", true];
		yield ["'Calm'", "Calm"];
		yield ["QUERY.UNKNOWN + 1", 1.0];
		yield ["0 ?? 3", 3.0];
		yield ["query.unknown_thing", 0.0];
		yield ["@@@", 0.0];
	}

	/**
	 * @dataProvider expressionProvider
	 */
	public function testEvaluate(string $expression, float|bool|string $expected) : void{
		self::assertSame($expected, Molang::evaluate($expression));
		//compiled expressions are cached: a second evaluation must give the same result
		self::assertSame($expected, Molang::evaluate($expression));
	}

	public function testLiterals() : void{
		self::assertSame(5.0, Molang::evaluate(5));
		self::assertSame(true, Molang::evaluate(true));
		self::assertSame(0.0, Molang::evaluate(null));
		self::assertSame(1.0, Molang::number(true));
	}

	public function testRandomInRange() : void{
		for($i = 0; $i < 50; ++$i){
			$value = Molang::number("math.random_integer(3, 5)");
			self::assertGreaterThanOrEqual(3.0, $value);
			self::assertLessThanOrEqual(5.0, $value);
		}
	}
}
