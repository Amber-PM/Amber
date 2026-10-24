<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command;

use PHPUnit\Framework\TestCase;
use pocketmine\command\defaults\GameRuleCommand;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\Server;
use pocketmine\world\gamerule\GameRule;
use pocketmine\world\gamerule\GameRules;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function count;

final class GameRuleCommandTest extends TestCase{

	public static function setUpBeforeClass() : void{
		DefaultPermissions::registerCorePermissions();
	}

	public function testQueryAndSetRule() : void{
		$command = new GameRuleCommand();

		$world = $this->createMock(World::class);
		$rules = new GameRules($world);
		$world->method("getGameRules")->willReturn($rules);

		$worldManager = $this->createMock(WorldManager::class);
		$worldManager->method("getDefaultWorld")->willReturn($world);

		$server = $this->createMock(Server::class);
		$server->method("getWorldManager")->willReturn($worldManager);

		$messages = [];
		$sender = $this->createMock(CommandSender::class);
		$sender->method("hasPermission")->willReturn(true);
		$sender->method("getServer")->willReturn($server);
		$sender->method("getName")->willReturn("CONSOLE");
		$sender->method("getLanguage")->willReturn(new Language("eng"));
		$sender->method("sendMessage")->willReturnCallback(function(Translatable|string $msg) use (&$messages) : void{
			$messages[] = $msg instanceof Translatable ? $msg->getText() : $msg;
		});

		// 1. Query keepInventory
		$command->execute($sender, "gamerule", ["keepinventory"]);
		self::assertNotEmpty($messages);
		self::assertStringContainsString("keepinventory = false", $messages[count($messages) - 1]);

		// 2. Set keepInventory to true
		$command->execute($sender, "gamerule", ["keepinventory", "true"]);
		self::assertTrue($rules->getBool(GameRule::KEEP_INVENTORY));

		// 3. Set randomTickSpeed to 5
		$command->execute($sender, "gamerule", ["randomtickspeed", "5"]);
		self::assertSame(5, $rules->getInt(GameRule::RANDOM_TICK_SPEED));

		// 4. Query invalid rule
		$command->execute($sender, "gamerule", ["invalid_rule_name"]);
		self::assertStringContainsString("No game rule called", $messages[count($messages) - 1]);

		// 5. Set invalid boolean format
		$command->execute($sender, "gamerule", ["keepinventory", "not_a_bool"]);
		self::assertStringContainsString("Expected boolean value", $messages[count($messages) - 1]);
	}
}
