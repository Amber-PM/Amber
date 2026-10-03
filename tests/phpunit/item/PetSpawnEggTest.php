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

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\World;
use ReflectionMethod;
use ReflectionNamedType;
use function array_slice;
use function file;
use function implode;

final class PetSpawnEggTest extends TestCase{

	public function testWolfSpawnEggProperties() : void{
		$item = VanillaItems::WOLF_SPAWN_EGG();

		self::assertInstanceOf(SpawnEgg::class, $item);
		self::assertSame("Wolf Spawn Egg", $item->getName());
		self::assertSame(ItemTypeIds::WOLF_SPAWN_EGG, $item->getTypeId());
		self::assertSame(EntityIds::WOLF, $item->getSpawnEntityNetworkId());
		self::assertSame(64, $item->getMaxStackSize());
	}

	public function testCatSpawnEggProperties() : void{
		$item = VanillaItems::CAT_SPAWN_EGG();

		self::assertInstanceOf(SpawnEgg::class, $item);
		self::assertSame("Cat Spawn Egg", $item->getName());
		self::assertSame(ItemTypeIds::CAT_SPAWN_EGG, $item->getTypeId());
		self::assertSame(EntityIds::CAT, $item->getSpawnEntityNetworkId());
		self::assertSame(64, $item->getMaxStackSize());
	}

	public function testStringToItemParser() : void{
		$parser = StringToItemParser::getInstance();

		$wolfEgg = $parser->parse("wolf_spawn_egg");
		self::assertNotNull($wolfEgg);
		self::assertInstanceOf(SpawnEgg::class, $wolfEgg);
		self::assertSame(ItemTypeIds::WOLF_SPAWN_EGG, $wolfEgg->getTypeId());
		self::assertSame("Wolf Spawn Egg", $wolfEgg->getName());
		self::assertContains("wolf_spawn_egg", $parser->lookupAliases(VanillaItems::WOLF_SPAWN_EGG()));

		$catEgg = $parser->parse("cat_spawn_egg");
		self::assertNotNull($catEgg);
		self::assertInstanceOf(SpawnEgg::class, $catEgg);
		self::assertSame(ItemTypeIds::CAT_SPAWN_EGG, $catEgg->getTypeId());
		self::assertSame("Cat Spawn Egg", $catEgg->getName());
		self::assertContains("cat_spawn_egg", $parser->lookupAliases(VanillaItems::CAT_SPAWN_EGG()));
	}

	public function testWolfSpawnEggSerialization() : void{
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();

		$wolfEgg = VanillaItems::WOLF_SPAWN_EGG();
		$data = $serializer->serializeType($wolfEgg);
		self::assertSame(ItemTypeNames::WOLF_SPAWN_EGG, $data->getName());

		$deserialized = $deserializer->deserializeType(new SavedItemData(ItemTypeNames::WOLF_SPAWN_EGG));
		self::assertSame(ItemTypeIds::WOLF_SPAWN_EGG, $deserialized->getTypeId());
		self::assertSame("Wolf Spawn Egg", $deserialized->getName());
	}

	public function testCatSpawnEggSerialization() : void{
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();

		$catEgg = VanillaItems::CAT_SPAWN_EGG();
		$data = $serializer->serializeType($catEgg);
		self::assertSame(ItemTypeNames::CAT_SPAWN_EGG, $data->getName());

		$deserialized = $deserializer->deserializeType(new SavedItemData(ItemTypeNames::CAT_SPAWN_EGG));
		self::assertSame(ItemTypeIds::CAT_SPAWN_EGG, $deserialized->getTypeId());
		self::assertSame("Cat Spawn Egg", $deserialized->getName());
	}

	public function testWolfSpawnEggCreateEntitySignature() : void{
		$item = VanillaItems::WOLF_SPAWN_EGG();
		$method = new ReflectionMethod($item, "createEntity");

		self::assertTrue($method->isProtected());
		self::assertSame(4, $method->getNumberOfParameters());

		$params = $method->getParameters();
		self::assertSame("world", $params[0]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[0]->getType());
		self::assertSame(World::class, $params[0]->getType()->getName());

		self::assertSame("pos", $params[1]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[1]->getType());
		self::assertSame(Vector3::class, $params[1]->getType()->getName());

		self::assertSame("yaw", $params[2]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[2]->getType());
		self::assertSame("float", $params[2]->getType()->getName());

		self::assertSame("pitch", $params[3]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[3]->getType());
		self::assertSame("float", $params[3]->getType()->getName());

		$returnType = $method->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $returnType);
		self::assertSame(Entity::class, $returnType->getName());

		$file = $method->getFileName();
		self::assertIsString($file);
		$lines = file($file);
		self::assertIsArray($lines);
		$methodBody = implode("", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
		self::assertStringContainsString("new Wolf(", $methodBody);
	}

	public function testCatSpawnEggCreateEntitySignature() : void{
		$item = VanillaItems::CAT_SPAWN_EGG();
		$method = new ReflectionMethod($item, "createEntity");

		self::assertTrue($method->isProtected());
		self::assertSame(4, $method->getNumberOfParameters());

		$params = $method->getParameters();
		self::assertSame("world", $params[0]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[0]->getType());
		self::assertSame(World::class, $params[0]->getType()->getName());

		self::assertSame("pos", $params[1]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[1]->getType());
		self::assertSame(Vector3::class, $params[1]->getType()->getName());

		self::assertSame("yaw", $params[2]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[2]->getType());
		self::assertSame("float", $params[2]->getType()->getName());

		self::assertSame("pitch", $params[3]->getName());
		self::assertInstanceOf(ReflectionNamedType::class, $params[3]->getType());
		self::assertSame("float", $params[3]->getType()->getName());

		$returnType = $method->getReturnType();
		self::assertInstanceOf(ReflectionNamedType::class, $returnType);
		self::assertSame(Entity::class, $returnType->getName());

		$file = $method->getFileName();
		self::assertIsString($file);
		$lines = file($file);
		self::assertIsArray($lines);
		$methodBody = implode("", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
		self::assertStringContainsString("new Cat(", $methodBody);
	}
}
