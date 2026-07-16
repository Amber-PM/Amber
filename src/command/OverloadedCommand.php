<?php

declare(strict_types=1);

namespace pocketmine\command;

use pocketmine\command\overload\CommandOverload;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\Translatable;
use pocketmine\utils\TextFormat;
use function count;
use function implode;
use function usort;

abstract class OverloadedCommand extends Command{

	private array $overloads = [];

	private bool $sorted = true;

	final protected function addOverload(\Closure $handler, ?string $permission = null, array $explicitParsers = []) : static{
		$this->overloads[] = new CommandOverload($handler, $permission, $explicitParsers);
		$this->sorted = false;
		return $this;
	}

	public function getOverloads() : array{
		$this->sortOverloads();
		return $this->overloads;
	}

	private function sortOverloads() : void{
		if($this->sorted){
			return;
		}

		usort($this->overloads, fn(CommandOverload $a, CommandOverload $b) => $b->getMinTokens() <=> $a->getMinTokens());
		$this->sorted = true;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		$this->sortOverloads();

		if(!$this->testPermissionSilent($sender) && $this->getPermissions() !== []){
			$this->testPermission($sender);
			return false;
		}

		$argCount = count($args);
		$deniedPermission = null;
		$lastError = null;

		foreach($this->overloads as $overload){
			if(!$overload->acceptsSender($sender)){
				continue;
			}
			if($argCount < $overload->getMinTokens() || $argCount > $overload->getMaxTokens()){
				continue;
			}

			$attempt = $overload->tryParse($sender, $args);
			if(!$attempt->isOk()){
				$lastError = $attempt->getError();
				continue;
			}

			$permission = $overload->getPermission();
			if($permission !== null && !$sender->hasPermission($permission)){
				$deniedPermission = $permission;
				continue;
			}

			return $overload->call($sender, $attempt->getValues()) ?? true;
		}

		if($deniedPermission !== null){
			$this->testPermission($sender, $deniedPermission);
			return false;
		}

		if($lastError !== null){
			$sender->sendMessage($lastError instanceof Translatable ? $lastError->prefix(TextFormat::RED) : TextFormat::RED . $lastError);
		}

		throw new InvalidCommandSyntaxException();
	}

	public function getUsage() : Translatable|string{
		$this->sortOverloads();
		if($this->overloads === []){
			return parent::getUsage();
		}

		$lines = [];
		foreach($this->overloads as $overload){
			$lines[] = $overload->getUsage($this->getLabel());
		}

		return implode("\n", $lines);
	}
}
