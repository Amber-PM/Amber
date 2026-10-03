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

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class ConcreteTameableAnimal extends TameableAnimal{
	public static function getNetworkTypeId() : string{ return "minecraft:test_pet"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Test Pet"; }

	public function testSyncNetworkData(EntityMetadataCollection $properties) : void{
		$this->syncNetworkData($properties);
	}
}

final class TameableAnimalTest extends TestCase{

	private function createPet(?World $world = null) : ConcreteTameableAnimal{
		$pet = (new ReflectionClass(ConcreteTameableAnimal::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($pet, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($pet, 10);
		(new ReflectionProperty(Entity::class, "size"))->setValue($pet, new EntitySizeInfo(1.0, 1.0));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($pet, 1.0);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($pet, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($pet, new AttributeMap());
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($pet, new EffectManager($pet));
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getPlayers")->willReturn([]);
		}
		(new ReflectionProperty(Entity::class, "location"))->setValue($pet, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		return $pet;
	}

	private function getGenericFlag(EntityMetadataCollection $flags, int $flagId) : bool{
		$propertyId = $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
		$realFlagId = $flagId % 64;
		$prop = $flags->getAll()[$propertyId] ?? null;
		if($prop instanceof LongMetadataProperty){
			return (($prop->getValue() >> $realFlagId) & 1) !== 0;
		}
		return false;
	}

	private function getByte(EntityMetadataCollection $flags, int $propertyId) : ?int{
		$prop = $flags->getAll()[$propertyId] ?? null;
		return $prop instanceof ByteMetadataProperty ? $prop->getValue() : null;
	}

	private function getLong(EntityMetadataCollection $flags, int $propertyId) : ?int{
		$prop = $flags->getAll()[$propertyId] ?? null;
		return $prop instanceof LongMetadataProperty ? $prop->getValue() : null;
	}

	public function testDefaultProperties() : void{
		$pet = $this->createPet();
		self::assertFalse($pet->isTamed());
		self::assertFalse($pet->isSitting());
		self::assertNull($pet->getOwnerUUID());
		self::assertNull($pet->getOwnerName());
		self::assertSame(DyeColor::RED, $pet->getCollarColor());
		self::assertSame(0, $pet->getAge());
		self::assertFalse($pet->isBaby());
		self::assertSame(0, $pet->getInLoveTicks());
		self::assertFalse($pet->isInLove());
		self::assertNull($pet->getOwner());
	}

	public function testSettersAndMetadataFlags() : void{
		$pet = $this->createPet();
		$pet->setOwnerUUID("abcd-1234-uuid");
		$pet->setOwnerName("Mani");
		$pet->setTamed(true);
		$pet->setSitting(true);
		$pet->setCollarColor(DyeColor::BLUE);
		$pet->setAge(-12000);
		$pet->setInLoveTicks(300);

		self::assertTrue($pet->isTamed());
		self::assertTrue($pet->isSitting());
		self::assertSame("abcd-1234-uuid", $pet->getOwnerUUID());
		self::assertSame("Mani", $pet->getOwnerName());
		self::assertSame(DyeColor::BLUE, $pet->getCollarColor());
		self::assertSame(-12000, $pet->getAge());
		self::assertTrue($pet->isBaby());
		self::assertSame(300, $pet->getInLoveTicks());
		self::assertTrue($pet->isInLove());

		// Verify network metadata flags
		$flags = (new ReflectionProperty(Entity::class, "networkProperties"))->getValue($pet);
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::TAMED));
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::SITTING));
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::BABY));
		self::assertSame(DyeColorIdMap::getInstance()->toId(DyeColor::BLUE), $this->getByte($flags, EntityMetadataProperties::COLOR));
	}

	public function testSettersToggleFlagsFalse() : void{
		$pet = $this->createPet();
		$pet->setTamed(true);
		$pet->setSitting(true);
		$pet->setAge(-100);

		$flags = (new ReflectionProperty(Entity::class, "networkProperties"))->getValue($pet);
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::TAMED));
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::SITTING));
		self::assertTrue($this->getGenericFlag($flags, EntityMetadataFlags::BABY));

		$pet->setTamed(false);
		$pet->setSitting(false);
		$pet->setAge(100);

		self::assertFalse($pet->isTamed());
		self::assertFalse($pet->isSitting());
		self::assertFalse($pet->isBaby());
		self::assertFalse($this->getGenericFlag($flags, EntityMetadataFlags::TAMED));
		self::assertFalse($this->getGenericFlag($flags, EntityMetadataFlags::SITTING));
		self::assertFalse($this->getGenericFlag($flags, EntityMetadataFlags::BABY));
	}

	public function testNbtSerializationRoundtrip() : void{
		$pet = $this->createPet();
		$pet->setOwnerUUID("uuid-5678");
		$pet->setOwnerName("Steve");
		$pet->setTamed(true);
		$pet->setSitting(true);
		$pet->setCollarColor(DyeColor::PURPLE);
		$pet->setAge(-24000);
		$pet->setInLoveTicks(550);

		$nbt = CompoundTag::create();
		$pet->saveNBTData($nbt);

		self::assertSame("uuid-5678", $nbt->getString(TameableAnimal::TAG_OWNER_UUID));
		self::assertSame("Steve", $nbt->getString(TameableAnimal::TAG_OWNER_NAME));
		self::assertSame(1, $nbt->getByte(TameableAnimal::TAG_SITTING));
		self::assertSame(DyeColorIdMap::getInstance()->toId(DyeColor::PURPLE), $nbt->getByte(TameableAnimal::TAG_COLLAR_COLOR));
		self::assertSame(-24000, $nbt->getInt(TameableAnimal::TAG_AGE));
		self::assertSame(550, $nbt->getInt(TameableAnimal::TAG_IN_LOVE));

		$newPet = $this->createPet();
		$newPet->readNBTData($nbt);

		self::assertTrue($newPet->isTamed());
		self::assertTrue($newPet->isSitting());
		self::assertSame("uuid-5678", $newPet->getOwnerUUID());
		self::assertSame("Steve", $newPet->getOwnerName());
		self::assertSame(DyeColor::PURPLE, $newPet->getCollarColor());
		self::assertSame(-24000, $newPet->getAge());
		self::assertTrue($newPet->isBaby());
		self::assertSame(550, $newPet->getInLoveTicks());
		self::assertTrue($newPet->isInLove());
	}

	public function testNbtMissingDefaults() : void{
		$nbt = CompoundTag::create();
		$pet = $this->createPet();
		$pet->readNBTData($nbt);

		self::assertFalse($pet->isTamed());
		self::assertFalse($pet->isSitting());
		self::assertNull($pet->getOwnerUUID());
		self::assertNull($pet->getOwnerName());
		self::assertSame(DyeColor::RED, $pet->getCollarColor());
		self::assertSame(0, $pet->getAge());
		self::assertFalse($pet->isBaby());
		self::assertSame(0, $pet->getInLoveTicks());
		self::assertFalse($pet->isInLove());
	}

	public function testOwnerResolutionInWorld() : void{
		$ownerUuid = Uuid::uuid4()->toString();

		$playerMock = $this->createMock(Player::class);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($playerMock, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($playerMock, 42);
		(new ReflectionProperty(Player::class, "logger"))->setValue($playerMock, $this->createMock(Logger::class));
		$playerMock->method("getId")->willReturn(42);
		$playerMock->method("getUniqueId")->willReturn(Uuid::fromString($ownerUuid));

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([$playerMock]);

		$pet = $this->createPet($world);
		$pet->setOwnerUUID($ownerUuid);

		self::assertSame($playerMock, $pet->getOwner());

		// Test syncNetworkData with owner online
		$properties = new EntityMetadataCollection();
		$pet->testSyncNetworkData($properties);
		self::assertSame(42, $this->getLong($properties, EntityMetadataProperties::OWNER_EID));
	}

	public function testSyncNetworkDataWithoutOwner() : void{
		$pet = $this->createPet();
		$pet->setCollarColor(DyeColor::GREEN);
		$pet->setSitting(true);
		$pet->setTamed(true);
		$pet->setAge(-100);

		$properties = new EntityMetadataCollection();
		$pet->testSyncNetworkData($properties);

		self::assertTrue($this->getGenericFlag($properties, EntityMetadataFlags::TAMED));
		self::assertTrue($this->getGenericFlag($properties, EntityMetadataFlags::SITTING));
		self::assertTrue($this->getGenericFlag($properties, EntityMetadataFlags::BABY));
		self::assertSame(DyeColorIdMap::getInstance()->toId(DyeColor::GREEN), $this->getByte($properties, EntityMetadataProperties::COLOR));
		self::assertSame(0, $this->getLong($properties, EntityMetadataProperties::OWNER_EID));
	}
}
