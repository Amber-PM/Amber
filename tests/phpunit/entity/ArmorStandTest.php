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

namespace pocketmine\entity\object;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use ReflectionClass;
use ReflectionProperty;

final class ArmorStandTest extends TestCase{

	/** @var ArmorStand[] */
	private array $createdStands = [];

	protected function tearDown() : void{
		foreach($this->createdStands as $stand){
			$this->markClosed($stand);
		}
		$this->createdStands = [];
		parent::tearDown();
	}

	private function markClosed(ArmorStand $stand) : void{
		(new ReflectionProperty(Entity::class, "closed"))->setValue($stand, true);
	}

	private function createArmorStand() : ArmorStand{
		$stand = (new ReflectionClass(ArmorStand::class))->newInstanceWithoutConstructor();
		$this->markClosed($stand);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($stand, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($stand, new EntitySizeInfo(1.975, 0.5));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($stand, new EffectManager($stand));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($stand, new ArmorInventory($stand));
		$this->createdStands[] = $stand;
		return $stand;
	}

	private function getMetadataInt(ArmorStand $stand, int $propertyId) : ?int{
		$prop = $stand->getNetworkProperties()->getAll()[$propertyId] ?? null;
		return $prop instanceof IntMetadataProperty ? $prop->getValue() : null;
	}

	private function getMetadataFlag(ArmorStand $stand, int $flagId) : bool{
		$propertyId = $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
		$realFlagId = $flagId % 64;
		$prop = $stand->getNetworkProperties()->getAll()[$propertyId] ?? null;
		if($prop instanceof LongMetadataProperty){
			return (($prop->getValue() >> $realFlagId) & 1) === 1;
		}
		return false;
	}

	public function testNetworkTypeId() : void{
		self::assertSame(EntityIds::ARMOR_STAND, ArmorStand::getNetworkTypeId());
	}

	public function testInitialSizeInfo() : void{
		$stand = $this->createArmorStand();
		$method = (new ReflectionClass(ArmorStand::class))->getMethod("getInitialSizeInfo");
		/** @var EntitySizeInfo $size */
		$size = $method->invoke($stand);

		self::assertSame(1.975, $size->getHeight());
		self::assertSame(0.5, $size->getWidth());
	}

	public function testName() : void{
		$stand = $this->createArmorStand();
		self::assertSame("Armor Stand", $stand->getName());
	}

	public function testInitialDragAndGravity() : void{
		$stand = $this->createArmorStand();
		$dragMethod = (new ReflectionClass(ArmorStand::class))->getMethod("getInitialDragMultiplier");
		$gravityMethod = (new ReflectionClass(ArmorStand::class))->getMethod("getInitialGravity");

		self::assertSame(0.02, $dragMethod->invoke($stand));
		self::assertSame(0.08, $gravityMethod->invoke($stand));
	}

	public function testPoseGetterSetterModulo() : void{
		$stand = $this->createArmorStand();
		self::assertSame(0, $stand->getPose());

		// Test normal range (0..12)
		for($i = 0; $i < ArmorStand::POSE_COUNT; ++$i){
			$stand->setPose($i);
			self::assertSame($i, $stand->getPose());
			self::assertSame($i, $this->getMetadataInt($stand, EntityMetadataProperties::ARMOR_STAND_POSE_INDEX));
		}

		// Test positive overflow modulo wrapping
		$stand->setPose(13);
		self::assertSame(0, $stand->getPose());
		self::assertSame(0, $this->getMetadataInt($stand, EntityMetadataProperties::ARMOR_STAND_POSE_INDEX));

		$stand->setPose(14);
		self::assertSame(1, $stand->getPose());

		$stand->setPose(25);
		self::assertSame(12, $stand->getPose());

		$stand->setPose(26);
		self::assertSame(0, $stand->getPose());

		// Test negative numbers modulo wrapping
		$stand->setPose(-1);
		self::assertSame(12, $stand->getPose());
		self::assertSame(12, $this->getMetadataInt($stand, EntityMetadataProperties::ARMOR_STAND_POSE_INDEX));

		$stand->setPose(-2);
		self::assertSame(11, $stand->getPose());

		$stand->setPose(-13);
		self::assertSame(0, $stand->getPose());

		$stand->setPose(-14);
		self::assertSame(12, $stand->getPose());
	}

	public function testBasePlateGetterSetter() : void{
		$stand = $this->createArmorStand();
		self::assertTrue($stand->hasBasePlate());

		$stand->setBasePlate(false);
		self::assertFalse($stand->hasBasePlate());
		self::assertFalse($this->getMetadataFlag($stand, EntityMetadataFlags::SHOWBASE));

		$stand->setBasePlate(true);
		self::assertTrue($stand->hasBasePlate());
		self::assertTrue($this->getMetadataFlag($stand, EntityMetadataFlags::SHOWBASE));
	}

	public function testLockedGetterSetter() : void{
		$stand = $this->createArmorStand();
		self::assertFalse($stand->isLocked());

		$stand->setLocked(true);
		self::assertTrue($stand->isLocked());

		$stand->setLocked(false);
		self::assertFalse($stand->isLocked());
	}

	public function testMainHandItemGetterSetterWithCloning() : void{
		$stand = $this->createArmorStand();
		self::assertTrue($stand->getMainHandItem()->isNull());

		$sword = VanillaItems::DIAMOND_SWORD();
		$stand->setMainHandItem($sword);

		$retrieved = $stand->getMainHandItem();
		self::assertTrue($retrieved->equalsExact($sword));

		// Modifying retrieved item does not mutate internal entity item
		$retrieved->setCount(5);
		self::assertSame(1, $stand->getMainHandItem()->getCount());

		// Modifying source item after setting does not mutate internal entity item
		$sword->setCount(10);
		self::assertSame(1, $stand->getMainHandItem()->getCount());
	}

	public function testPickedItem() : void{
		$stand = $this->createArmorStand();
		$picked = $stand->getPickedItem();
		self::assertNotNull($picked);
		self::assertTrue($picked->equalsExact(VanillaItems::ARMOR_STAND()));
	}

	public function testNbtReadWriteRoundtrip() : void{
		$stand1 = $this->createArmorStand();
		$stand1->setPose(7);
		$stand1->setLocked(true);
		$stand1->setBasePlate(false);
		$stand1->setMainHandItem(VanillaItems::DIAMOND_SWORD());

		$nbt = CompoundTag::create();
		$stand1->writeSaveData($nbt);

		self::assertSame(7, $nbt->getInt(ArmorStand::TAG_POSE));
		self::assertSame(1, $nbt->getByte(ArmorStand::TAG_LOCKED));
		self::assertSame(0, $nbt->getByte(ArmorStand::TAG_SHOW_BASE_PLATE));
		self::assertNotNull($nbt->getCompoundTag(ArmorStand::TAG_MAIN_HAND));

		// Deserialize into second instance
		$stand2 = $this->createArmorStand();
		$stand2->readSaveData($nbt);

		self::assertSame(7, $stand2->getPose());
		self::assertTrue($stand2->isLocked());
		self::assertFalse($stand2->hasBasePlate());
		self::assertTrue($stand2->getMainHandItem()->equalsExact(VanillaItems::DIAMOND_SWORD()));
	}

	public function testNbtDefaults() : void{
		$stand = $this->createArmorStand();
		$stand->readSaveData(CompoundTag::create());

		self::assertSame(0, $stand->getPose());
		self::assertFalse($stand->isLocked());
		self::assertTrue($stand->hasBasePlate());
		self::assertTrue($stand->getMainHandItem()->isNull());

		$nbt = CompoundTag::create();
		$stand->writeSaveData($nbt);

		self::assertSame(0, $nbt->getInt(ArmorStand::TAG_POSE));
		self::assertSame(0, $nbt->getByte(ArmorStand::TAG_LOCKED));
		self::assertSame(1, $nbt->getByte(ArmorStand::TAG_SHOW_BASE_PLATE));
		self::assertNull($nbt->getTag(ArmorStand::TAG_MAIN_HAND));
	}

	public function testEntityFactoryRegistration() : void{
		$factory = EntityFactory::getInstance();
		self::assertTrue($factory->isRegistered(ArmorStand::class));
		self::assertSame("ArmorStand", $factory->getSaveId(ArmorStand::class));

		$saveNbt = CompoundTag::create();
		$factory->injectSaveId(ArmorStand::class, $saveNbt);
		self::assertSame("ArmorStand", $saveNbt->getString(EntityFactory::TAG_IDENTIFIER));
	}

	public function testSyncNetworkData() : void{
		$stand = $this->createArmorStand();
		$stand->setPose(4);
		$stand->setBasePlate(true);

		$properties = new EntityMetadataCollection();
		$syncMethod = (new ReflectionClass(ArmorStand::class))->getMethod("syncNetworkData");
		$syncMethod->invoke($stand, $properties);

		$all = $properties->getAll();
		$flagProp = $all[EntityMetadataProperties::FLAGS] ?? null;
		self::assertInstanceOf(LongMetadataProperty::class, $flagProp);
		$realFlagId = EntityMetadataFlags::SHOWBASE % 64;
		self::assertSame(1, ($flagProp->getValue() >> $realFlagId) & 1);

		$poseProp = $all[EntityMetadataProperties::ARMOR_STAND_POSE_INDEX] ?? null;
		self::assertInstanceOf(IntMetadataProperty::class, $poseProp);
		self::assertSame(4, $poseProp->getValue());
	}

	public function testArmorNbtRoundtrip() : void{
		$stand1 = $this->createArmorStand();
		$stand1->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());
		$stand1->getArmorInventory()->setChestplate(VanillaItems::DIAMOND_CHESTPLATE());
		$stand1->getArmorInventory()->setLeggings(VanillaItems::DIAMOND_LEGGINGS());
		$stand1->getArmorInventory()->setBoots(VanillaItems::DIAMOND_BOOTS());

		$nbt = CompoundTag::create();
		$stand1->writeSaveData($nbt);

		$armorTag = $nbt->getListTag(ArmorStand::TAG_ARMOR);
		self::assertNotNull($armorTag);
		self::assertCount(4, $armorTag);

		$stand2 = $this->createArmorStand();
		$stand2->readSaveData($nbt);

		self::assertTrue($stand2->getArmorInventory()->getHelmet()->equalsExact(VanillaItems::DIAMOND_HELMET()));
		self::assertTrue($stand2->getArmorInventory()->getChestplate()->equalsExact(VanillaItems::DIAMOND_CHESTPLATE()));
		self::assertTrue($stand2->getArmorInventory()->getLeggings()->equalsExact(VanillaItems::DIAMOND_LEGGINGS()));
		self::assertTrue($stand2->getArmorInventory()->getBoots()->equalsExact(VanillaItems::DIAMOND_BOOTS()));
	}

	public function testNbtStaleMainHandRemoval() : void{
		$stand = $this->createArmorStand();
		$nbt = CompoundTag::create();
		$nbt->setTag(ArmorStand::TAG_MAIN_HAND, VanillaItems::DIAMOND_SWORD()->nbtSerialize());

		$stand->setMainHandItem(VanillaItems::AIR());
		$stand->writeSaveData($nbt);

		self::assertNull($nbt->getTag(ArmorStand::TAG_MAIN_HAND));
	}

	public function testMaxHealthIsSix() : void{
		$stand = (new ReflectionClass(ArmorStand::class))->newInstanceWithoutConstructor();
		$this->markClosed($stand);
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($stand, new AttributeMap());
		$method = (new ReflectionClass(ArmorStand::class))->getMethod("addAttributes");
		$method->invoke($stand);

		self::assertSame(6, $stand->getMaxHealth());
		self::assertSame(6.0, $stand->getHealth());
	}
}
