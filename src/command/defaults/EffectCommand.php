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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\IntegerArgumentParser;
use pocketmine\command\overload\PlayerOrSelfArgumentParser;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\Limits;
use pocketmine\utils\TextFormat;
use function count;
use function strtolower;

class EffectCommand extends OverloadedCommand{
	use BoundedNumberHelperTrait;

	public function __construct(){
		parent::__construct(
			"effect",
			KnownTranslationFactory::pocketmine_command_effect_description(),
			KnownTranslationFactory::commands_effect_usage()
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_EFFECT_SELF,
			DefaultPermissionNames::COMMAND_EFFECT_OTHER
		]);

		$effectOrClearParser = new StringArgumentParser([...StringToEffectParser::getInstance()->getKnownAliases(), "clear"]);

		$this->addOverload(
			fn(CommandSender $sender, Player $target, string $effect, ?string $duration = null, ?int $amplifier = null, ?bool $hideParticles = null)
				=> $this->applyEffect($sender, $target, $effect, $duration, $amplifier, $hideParticles),
			explicitParsers: [
				"target" => new PlayerOrSelfArgumentParser(),
				"effect" => $effectOrClearParser,
				"amplifier" => new IntegerArgumentParser(min: 0, max: 255)
			]
		);
	}

	private function applyEffect(CommandSender $sender, Player $target, string $effectName, ?string $durationToken, ?int $amplifier, ?bool $hideParticles) : bool{
		//permission depends on whether the resolved target is the sender itself or someone else, same as vanilla
		if(!$this->testPermission($sender, $target === $sender ? DefaultPermissionNames::COMMAND_EFFECT_SELF : DefaultPermissionNames::COMMAND_EFFECT_OTHER)){
			return true;
		}

		$effectManager = $target->getEffects();

		if(strtolower($effectName) === "clear"){
			$effectManager->clear();
			$sender->sendMessage(KnownTranslationFactory::commands_effect_success_removed_all($target->getDisplayName()));
			return true;
		}

		$effect = StringToEffectParser::getInstance()->parse($effectName);
		if($effect === null){
			$sender->sendMessage(KnownTranslationFactory::commands_effect_notFound($effectName)->prefix(TextFormat::RED));
			return true;
		}

		$infinite = false;
		$duration = null;
		if($durationToken !== null){
			if(strtolower($durationToken) === "infinite"){
				$infinite = true;
			}else{
				$seconds = $this->getBoundedInt($sender, $durationToken, 0, (int) (Limits::INT32_MAX / 20));
				if($seconds === null){
					return false;
				}
				$duration = $seconds * 20; // ticks
			}
		}

		$amplification = $amplifier ?? 0;
		$visible = !($hideParticles ?? false);

		if($duration === 0){
			if(!$effectManager->has($effect)){
				if(count($effectManager->all()) === 0){
					$sender->sendMessage(KnownTranslationFactory::commands_effect_failure_notActive_all($target->getDisplayName()));
				}else{
					$sender->sendMessage(KnownTranslationFactory::commands_effect_failure_notActive($effect->getName(), $target->getDisplayName()));
				}
				return true;
			}

			$effectManager->remove($effect);
			$sender->sendMessage(KnownTranslationFactory::commands_effect_success_removed($effect->getName(), $target->getDisplayName()));
		}else{
			$instance = new EffectInstance($effect, $duration, $amplification, $visible, infinite: $infinite);
			$effectManager->add($instance);

			if($infinite){
				self::broadcastCommandMessage($sender, KnownTranslationFactory::commands_effect_success_infinite($effect->getName(), (string) $instance->getAmplifier(), $target->getDisplayName()));
			}else{
				self::broadcastCommandMessage($sender, KnownTranslationFactory::commands_effect_success($effect->getName(), (string) $instance->getAmplifier(), $target->getDisplayName(), (string) ($instance->getDuration() / 20)));
			}
		}

		return true;
	}
}
