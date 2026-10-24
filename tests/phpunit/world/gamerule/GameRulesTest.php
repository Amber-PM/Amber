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

namespace pocketmine\world\gamerule;

use PHPUnit\Framework\TestCase;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;

final class GameRulesTest extends TestCase{

	public function testDefaults() : void{
		$rules = new GameRules();

		self::assertTrue($rules->has(GameRule::KEEP_INVENTORY));
		self::assertFalse($rules->getBool(GameRule::KEEP_INVENTORY));

		self::assertTrue($rules->has(GameRule::DO_MOB_SPAWNING));
		self::assertTrue($rules->getBool(GameRule::DO_MOB_SPAWNING));

		self::assertTrue($rules->has(GameRule::DO_DAYLIGHT_CYCLE));
		self::assertTrue($rules->getBool(GameRule::DO_DAYLIGHT_CYCLE));

		self::assertTrue($rules->has(GameRule::RANDOM_TICK_SPEED));
		self::assertSame(1, $rules->getInt(GameRule::RANDOM_TICK_SPEED));

		self::assertTrue($rules->has(GameRule::PVP));
		self::assertTrue($rules->getBool(GameRule::PVP));

		self::assertTrue($rules->has(GameRule::FALL_DAMAGE));
		self::assertTrue($rules->getBool(GameRule::FALL_DAMAGE));
	}

	public function testCaseInsensitiveAndAliases() : void{
		$rules = new GameRules();

		self::assertSame(GameRule::KEEP_INVENTORY, $rules->canonicalizeName("keepInventory"));
		self::assertSame(GameRule::KEEP_INVENTORY, $rules->canonicalizeName("KEEP_INVENTORY"));
		self::assertSame(GameRule::KEEP_INVENTORY, $rules->canonicalizeName("keep_inventory"));

		self::assertSame(GameRule::DO_MOB_SPAWNING, $rules->canonicalizeName("doMobSpawning"));
		self::assertSame(GameRule::DO_MOB_SPAWNING, $rules->canonicalizeName("do_mob_spawning"));

		self::assertSame(GameRule::RANDOM_TICK_SPEED, $rules->canonicalizeName("randomTickSpeed"));
		self::assertSame(GameRule::RANDOM_TICK_SPEED, $rules->canonicalizeName("random_tick_speed"));
	}

	public function testSetAndGet() : void{
		$rules = new GameRules();

		self::assertTrue($rules->set(GameRule::KEEP_INVENTORY, true, broadcast: false));
		self::assertTrue($rules->getBool(GameRule::KEEP_INVENTORY));

		self::assertTrue($rules->set(GameRule::RANDOM_TICK_SPEED, 3, broadcast: false));
		self::assertSame(3, $rules->getInt(GameRule::RANDOM_TICK_SPEED));

		// Reject wrong type
		self::assertFalse($rules->set(GameRule::KEEP_INVENTORY, 5, broadcast: false));
		self::assertFalse($rules->set(GameRule::RANDOM_TICK_SPEED, true, broadcast: false));
	}

	public function testNbtSerialization() : void{
		$rules = new GameRules();
		$rules->set(GameRule::KEEP_INVENTORY, true, broadcast: false);
		$rules->set(GameRule::RANDOM_TICK_SPEED, 5, broadcast: false);

		$nbt = CompoundTag::create();
		$rules->writeToNbt($nbt);

		$restored = new GameRules();
		$restored->readFromNbt($nbt);

		self::assertTrue($restored->getBool(GameRule::KEEP_INVENTORY));
		self::assertSame(5, $restored->getInt(GameRule::RANDOM_TICK_SPEED));
	}

	public function testNetworkRulesGeneration() : void{
		$rules = new GameRules();
		$rules->set(GameRule::KEEP_INVENTORY, true, broadcast: false);

		$networkRules = $rules->toNetworkRules();
		self::assertArrayHasKey(GameRule::KEEP_INVENTORY, $networkRules);
		self::assertInstanceOf(BoolGameRule::class, $networkRules[GameRule::KEEP_INVENTORY]);
		self::assertTrue($networkRules[GameRule::KEEP_INVENTORY]->getValue());

		self::assertArrayHasKey(GameRule::RANDOM_TICK_SPEED, $networkRules);
		self::assertInstanceOf(IntGameRule::class, $networkRules[GameRule::RANDOM_TICK_SPEED]);
		self::assertSame(1, $networkRules[GameRule::RANDOM_TICK_SPEED]->getValue());
	}
}
