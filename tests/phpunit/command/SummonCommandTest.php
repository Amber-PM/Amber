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
use pocketmine\command\defaults\SummonCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\entity\EntityFactory;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissions;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function count;

final class SummonCommandTest extends TestCase{

	public static function setUpBeforeClass() : void{
		DefaultPermissions::registerCorePermissions();
	}

	public function testSummonCommand() : void{
		$command = new SummonCommand();

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getSpawnLocation")->willReturn(new Position(10, 64, 10, $world));

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

		// 1. Unknown entity
		$command->execute($sender, "summon", ["non_existent_entity_xyz"]);
		self::assertNotEmpty($messages);
		self::assertStringContainsString("Unknown entity type", $messages[count($messages) - 1]);

		// 2. Missing args
		$this->expectException(InvalidCommandSyntaxException::class);
		$command->execute($sender, "summon", []);
	}
}
