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

namespace pocketmine\world\weather;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\World;

final class WeatherEngineTest extends TestCase{

	public function testWeatherStateTransitions() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$engine = new WeatherEngine($world, DimensionIds::OVERWORLD);

		self::assertSame(WeatherState::CLEAR, $engine->getState());
		self::assertFalse($engine->isRaining());
		self::assertFalse($engine->isThundering());

		// Change to RAIN
		$engine->setState(WeatherState::RAIN, 1000);
		self::assertSame(WeatherState::RAIN, $engine->getState());
		self::assertTrue($engine->isRaining());
		self::assertFalse($engine->isThundering());
		self::assertSame(1000, $engine->getDuration());

		// Change to THUNDER
		$engine->setState(WeatherState::THUNDER, 500);
		self::assertSame(WeatherState::THUNDER, $engine->getState());
		self::assertTrue($engine->isRaining());
		self::assertTrue($engine->isThundering());
	}

	public function testNetherAndEndDimensionWeatherImmunity() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$netherEngine = new WeatherEngine($world, DimensionIds::NETHER);
		$endEngine = new WeatherEngine($world, DimensionIds::THE_END);

		// Weather cannot be set to RAIN or THUNDER in Nether or The End
		$netherEngine->setState(WeatherState::RAIN);
		self::assertSame(WeatherState::CLEAR, $netherEngine->getState());
		self::assertFalse($netherEngine->isRaining());

		$endEngine->setState(WeatherState::THUNDER);
		self::assertSame(WeatherState::CLEAR, $endEngine->getState());
		self::assertFalse($endEngine->isThundering());
	}

	public function testSmoothRainAndThunderLevelTransitions() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$engine = new WeatherEngine($world, DimensionIds::OVERWORLD);
		$engine->setState(WeatherState::RAIN, 5000);

		self::assertEquals(0.0, $engine->getRainLevel());

		// Tick 10 times -> rain level increases by 0.1
		for($i = 0; $i < 10; ++$i){
			$engine->tick();
		}
		self::assertEqualsWithDelta(0.1, $engine->getRainLevel(), 0.01);

		// Clear weather -> rain level ramps back down
		$engine->setState(WeatherState::CLEAR, 5000);
		for($i = 0; $i < 5; ++$i){
			$engine->tick();
		}
		self::assertEqualsWithDelta(0.05, $engine->getRainLevel(), 0.01);
	}
}
