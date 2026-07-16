<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\utils\TextFormat;
use function is_numeric;

/**
 * Small helper trait providing the numeric bounds-checking helpers that VanillaCommand offers, for use by
 * OverloadedCommand-based default commands (which can't extend VanillaCommand, since PHP has no multiple
 * inheritance and OverloadedCommand already extends Command directly).
 */
trait BoundedNumberHelperTrait{

	protected function getInteger(CommandSender $sender, string $value, int $min = VanillaCommand::MIN_COORD, int $max = VanillaCommand::MAX_COORD) : int{
		$i = (int) $value;

		if($i < $min){
			$i = $min;
		}elseif($i > $max){
			$i = $max;
		}

		return $i;
	}

	protected function getBoundedInt(CommandSender $sender, string $input, int $min, int $max) : ?int{
		if(!is_numeric($input)){
			throw new InvalidCommandSyntaxException();
		}

		$v = (int) $input;
		if($v > $max){
			$sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooBig($input, (string) $max)->prefix(TextFormat::RED));
			return null;
		}
		if($v < $min){
			$sender->sendMessage(KnownTranslationFactory::commands_generic_num_tooSmall($input, (string) $min)->prefix(TextFormat::RED));
			return null;
		}

		return $v;
	}
}
