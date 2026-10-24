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

namespace pocketmine\entity\projectile;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\World;

final class FishingHookTest extends TestCase{

	public function testNetworkTypeId() : void{
		self::assertSame(EntityIds::FISHING_HOOK, FishingHook::getNetworkTypeId());
	}

	public function testHookCreation() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$location = new Location(0, 64, 0, $world, 0.0, 0.0);
		$hook = new FishingHook($location, null);

		self::assertFalse($hook->isInWater());
		self::assertFalse($hook->isInBiteWindow());
		self::assertFalse($hook->isHookedBlock());

		$damage = $hook->reelIn();
		self::assertSame(0, $damage);
		self::assertTrue($hook->isFlaggedForDespawn());
	}
}
