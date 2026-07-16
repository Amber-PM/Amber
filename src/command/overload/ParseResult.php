<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\lang\Translatable;

final class ParseResult{

	private function __construct(
		private bool $success,
		private mixed $value,
		private Translatable|string|null $error
	){}

	public static function ok(mixed $value) : self{
		return new self(true, $value, null);
	}

	public static function fail(Translatable|string $error) : self{
		return new self(false, null, $error);
	}

	public function isOk() : bool{
		return $this->success;
	}

	public function getValue() : mixed{
		return $this->value;
	}

	public function getError() : Translatable|string{
		return $this->error ?? "";
	}
}
