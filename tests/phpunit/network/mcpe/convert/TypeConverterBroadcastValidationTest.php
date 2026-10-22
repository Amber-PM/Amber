<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

final class TypeConverterBroadcastValidationTest extends TestCase{

	public static function invalidClosureProvider() : array{
		return [
			'wrong return type' => [fn(TypeConverter $typeConverter) : int => 0],
			'wrong parameter type' => [fn(string $typeConverter) : array => []],
		];
	}

	#[DataProvider('invalidClosureProvider')]
	public function testPublicBroadcastRejectsInvalidClosure(\Closure $closure) : void{
		$this->expectException(TypeError::class);
		TypeConverter::broadcastByTypeConverter([], $closure);
	}

	public function testPublicBroadcastAcceptsValidClosures() : void{
		$calls = 0;
		TypeConverter::broadcastByTypeConverter([], function(TypeConverter $typeConverter) use (&$calls) : array{
			++$calls;
			return [];
		});
		TypeConverter::broadcastByTypeConverter([], fn($typeConverter) : array => []);

		self::assertSame(0, $calls);
	}

	public function testUncheckedBroadcastSkipsSignatureValidation() : void{
		TypeConverter::broadcastByTypeConverterUnchecked([], fn(string $typeConverter) : int => 0);

		$this->addToAssertionCount(1);
	}
}
