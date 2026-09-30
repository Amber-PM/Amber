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

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use ReflectionClass;
use ReflectionProperty;

final class DungeonMobsTest extends TestCase{

	public function testSkeletonProperties() : void{
		self::assertSame(EntityIds::SKELETON, Skeleton::getNetworkTypeId());
		$egg = VanillaItems::SKELETON_SPAWN_EGG();
		self::assertSame("Skeleton Spawn Egg", $egg->getName());
	}

	public function testSpiderProperties() : void{
		self::assertSame(EntityIds::SPIDER, Spider::getNetworkTypeId());
		$egg = VanillaItems::SPIDER_SPAWN_EGG();
		self::assertSame("Spider Spawn Egg", $egg->getName());
	}

	public function testSpiderHealth() : void{
		$spider = (new ReflectionClass(Spider::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($spider, true);
		$attrMapProp = new ReflectionProperty(Entity::class, "attributeMap");
		$attrMapProp->setValue($spider, new AttributeMap());
		$addAttributes = (new ReflectionClass(Spider::class))->getMethod("addAttributes");
		$addAttributes->invoke($spider);

		self::assertSame(16, $spider->getMaxHealth());
	}
}
