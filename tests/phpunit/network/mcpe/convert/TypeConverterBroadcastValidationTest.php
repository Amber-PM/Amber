<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;

final class TypeConverterBroadcastValidationTest extends TestCase{

	public function testWrongReturnTypeIsRejected() : void{
		$this->expectException(\TypeError::class);
		TypeConverter::broadcastByTypeConverter([], fn(TypeConverter $typeConverter) : int => 0);
	}

	public function testWrongParameterTypeIsRejected() : void{
		$this->expectException(\TypeError::class);
		TypeConverter::broadcastByTypeConverter([], fn(string $typeConverter) : array => []);
	}

	public function testValidClosureIsAccepted() : void{
		$invocations = 0;
		TypeConverter::broadcastByTypeConverter([], function(TypeConverter $typeConverter) use (&$invocations) : array{
			$invocations++;
			return [];
		});

		self::assertSame(0, $invocations);
	}

	public function testUncheckedBroadcastSkipsSignatureValidation() : void{
		$invocations = 0;
		TypeConverter::broadcastByTypeConverterUnchecked([], function(string $typeConverter) use (&$invocations) : int{
			$invocations++;
			return 0;
		});

		self::assertSame(0, $invocations);
	}
}
