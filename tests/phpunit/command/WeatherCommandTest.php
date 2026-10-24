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
use pocketmine\command\defaults\WeatherCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\Server;
use pocketmine\world\weather\Weather;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

final class WeatherCommandTest extends TestCase{

	public static function setUpBeforeClass() : void{
		DefaultPermissions::registerCorePermissions();
	}

	public function testWeatherCommand() : void{
		$command = new WeatherCommand();

		$currentWeather = Weather::CLEAR;
		$currentDuration = 0;

		$world = $this->createMock(World::class);
		$world->method("setWeather")->willReturnCallback(function(int $w, ?int $d = null) use (&$currentWeather, &$currentDuration) : void{
			$currentWeather = $w;
			$currentDuration = $d ?? 12000;
		});

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

		// 1. Set weather rain
		$command->execute($sender, "weather", ["rain", "60"]);
		self::assertSame(Weather::RAIN, $currentWeather);
		self::assertSame(60 * 20, $currentDuration);

		// 2. Set weather thunder
		$command->execute($sender, "weather", ["thunder"]);
		self::assertSame(Weather::THUNDER, $currentWeather);

		// 3. Set weather clear
		$command->execute($sender, "weather", ["clear"]);
		self::assertSame(Weather::CLEAR, $currentWeather);

		// 4. Invalid weather type throws syntax exception
		$this->expectException(InvalidCommandSyntaxException::class);
		$command->execute($sender, "weather", ["snowstorm"]);
	}
}
