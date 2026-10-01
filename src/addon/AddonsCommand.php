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

use pocketmine\command\CommandSender;
use pocketmine\command\overload\attribute\IntRange;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\command\OverloadedCommand;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use function array_keys;
use function count;
use function implode;

/**
 * /addons - lists loaded add-ons and their content, gives add-on items and spawns add-on entities.
 */
final class AddonsCommand extends OverloadedCommand{
	public const PERMISSION = "pocketmine.command.addons";

	public function __construct(private AddonManager $manager){
		parent::__construct("addons", "Lists add-ons and gives or spawns add-on content");
		$operator = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
		if(PermissionManager::getInstance()->getPermission(self::PERMISSION) === null && $operator !== null){
			DefaultPermissions::registerPermission(new Permission(self::PERMISSION, "Allows the user to list add-ons and use add-on content"), [$operator]);
		}
		$this->setPermission(self::PERMISSION);

		$this->addOverload(fn(CommandSender $sender) => $this->listPacks($sender));
		$this->addOverload(
			fn(CommandSender $sender, string $list) => match($list){
				"items" => $this->listIds($sender, "Items", array_keys($this->manager->getItemDefinitions())),
				"blocks" => $this->listIds($sender, "Blocks", array_keys($this->manager->getBlockDefinitions())),
				"entities" => $this->listIds($sender, "Entities", array_keys($this->manager->getEntityDefinitions())),
				"reload" => $this->reload($sender),
				default => $this->listPacks($sender),
			},
			null,
			["list" => new StringArgumentParser(["packs", "items", "blocks", "entities", "reload"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, string $id, #[IntRange(1, 64 * 36)] ?int $count = null, ?Player $player = null) => $this->give($sender, $id, $count ?? 1, $player),
			null,
			["action" => new StringArgumentParser(["give"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $action, string $id) => $this->spawn($sender, $id),
			null,
			["action" => new StringArgumentParser(["spawn"])]
		);
	}

	private function listPacks(CommandSender $sender) : bool{
		$packs = $this->manager->getPacks();
		$sender->sendMessage(TF::GOLD . "Add-on packs (" . count($packs) . "):");
		foreach($packs as $pack){
			$sender->sendMessage(TF::GRAY . "- " . TF::WHITE . $pack->getName() . TF::GRAY . " v" . $pack->getVersionString() . " (" . ($pack->isResourcePack() ? "resource" : "behavior") . ", " . $pack->getSource() . ")");
		}
		$sender->sendMessage(TF::GRAY . count($this->manager->getItemDefinitions()) . " items, " . count($this->manager->getBlockDefinitions()) . " blocks, " . count($this->manager->getEntityDefinitions()) . " entities, " . $this->manager->getRecipeCount() . " recipes");
		return true;
	}

	private function give(CommandSender $sender, string $id, int $count, ?Player $target) : bool{
		$target ??= $sender instanceof Player ? $sender : null;
		if($target === null){
			$sender->sendMessage(TF::RED . "Name a player to give the item to.");
			return true;
		}
		$item = $this->manager->getItem($id, $count);
		if($item === null){
			$sender->sendMessage(TF::RED . "No add-on item or block is called $id. See /addons items and /addons blocks.");
			return true;
		}
		$target->getInventory()->addItem($item);
		$sender->sendMessage(TF::GREEN . "Gave " . $count . " " . $item->getName() . " to " . $target->getName());
		return true;
	}

	private function spawn(CommandSender $sender, string $id) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TF::RED . "Run this in game.");
			return true;
		}
		$entity = $this->manager->createEntity($id, $sender->getLocation());
		if($entity === null){
			$sender->sendMessage(TF::RED . "No add-on entity is called $id. See /addons entities.");
			return true;
		}
		$entity->spawnToAll();
		$sender->sendMessage(TF::GREEN . "Spawned " . $entity->getName());
		return true;
	}

	private function reload(CommandSender $sender) : bool{
		$result = $this->manager->reload();
		$sender->sendMessage(TF::GREEN . "Reloaded loot tables, structures, trade tables and spawn rules.");
		if($result["scripts"] !== null){
			$sender->sendMessage($result["scripts"] ? TF::GREEN . "Scripts restarted from the pack files." : TF::RED . "Scripts could not be restarted, see the console.");
		}
		foreach($result["restartNeeded"] as $what){
			$sender->sendMessage(TF::YELLOW . "Changed: $what. Restart the server to apply it.");
		}
		return true;
	}

	/** @param list<string> $ids */
	private function listIds(CommandSender $sender, string $label, array $ids) : bool{
		$sender->sendMessage(TF::GOLD . "$label (" . count($ids) . "): " . TF::WHITE . ($ids === [] ? TF::GRAY . "none" : implode(", ", $ids)));
		return true;
	}
}
