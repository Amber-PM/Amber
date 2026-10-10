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
use pocketmine\data\bedrock\PotionTypeIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\item\PotionType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\ShortMetadataProperty;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use function count;
use function round;

class DummyTippedArrowTarget extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:dummy"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Dummy"; }
	public function attack(\pocketmine\event\entity\EntityDamageEvent $source) : void{}
}

final class TippedArrowEntityTest extends TestCase{

	private function createArrow(?World $world = null, ?PotionType $potionType = null) : Arrow{
		$arrow = (new ReflectionClass(Arrow::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "closeInFlight"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($arrow, []);
		(new ReflectionProperty(Entity::class, "attachedShapes"))->setValue($arrow, []);
		(new ReflectionProperty(Entity::class, "id"))->setValue($arrow, 100);
		(new ReflectionProperty(Entity::class, "size"))->setValue($arrow, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($arrow, new Vector3(0.0, 0.0, 1.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($arrow, new AxisAlignedBB(0, 0, 0, 0.25, 0.25, 0.25));
		(new ReflectionProperty(Arrow::class, "pickupMode"))->setValue($arrow, Arrow::PICKUP_ANY);
		(new ReflectionProperty(Arrow::class, "pierceLevel"))->setValue($arrow, 0);
		(new ReflectionProperty(Arrow::class, "piercedEntityIds"))->setValue($arrow, []);
		(new ReflectionProperty(Arrow::class, "potionType"))->setValue($arrow, $potionType);
		(new ReflectionProperty(Arrow::class, "customPotionEffects"))->setValue($arrow, null);
		(new ReflectionProperty(Arrow::class, "critical"))->setValue($arrow, false);
		(new ReflectionProperty(Arrow::class, "collideTicks"))->setValue($arrow, 0);
		(new ReflectionProperty(Arrow::class, "hitBlocked"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($arrow, new EntityMetadataCollection());

		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->updateEntities = [];
		}
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		return $arrow;
	}

	private function createTarget(int $id) : DummyTippedArrowTarget{
		$entity = (new ReflectionClass(DummyTippedArrowTarget::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($entity, $id);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($entity, new AxisAlignedBB(0, 0, 0, 1, 2, 1));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($entity, Vector3::zero());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($entity, new EntityMetadataCollection());
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($entity, new EffectManager($entity));
		$attributeMap = new \pocketmine\entity\AttributeMap();
		$moveSpeed = \pocketmine\entity\AttributeFactory::getInstance()->mustGet(\pocketmine\entity\Attribute::MOVEMENT_SPEED);
		$attributeMap->add($moveSpeed);
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($entity, $attributeMap);
		(new ReflectionProperty(Living::class, "moveSpeedAttr"))->setValue($entity, $moveSpeed);
		return $entity;
	}

	public function testPotionTypeGetterSetter() : void{
		$arrow = $this->createArrow();
		self::assertNull($arrow->getPotionType());
		self::assertEmpty($arrow->getPotionEffects());

		$arrow->setPotionType(PotionType::SWIFTNESS);
		self::assertSame(PotionType::SWIFTNESS, $arrow->getPotionType());
		self::assertNotEmpty($arrow->getPotionEffects());

		$arrow->setPotionType(null);
		self::assertNull($arrow->getPotionType());
		self::assertEmpty($arrow->getPotionEffects());
	}

	public function testCustomEffectsApi() : void{
		$arrow = $this->createArrow(null, PotionType::SWIFTNESS);
		self::assertNull($arrow->getCustomEffects());

		$customJump = new EffectInstance(VanillaEffects::JUMP_BOOST(), 200, 1);
		$arrow->addCustomEffect($customJump);

		self::assertCount(1, $arrow->getCustomEffects() ?? []);
		self::assertSame($customJump, ($arrow->getCustomEffects() ?? [])[0]);

		// Custom effects should be included alongside potion type effects
		$effects = $arrow->getPotionEffects();
		self::assertCount(2, $effects);

		// Override potion type effect with custom effect of same type
		$customSpeed = new EffectInstance(VanillaEffects::SPEED(), 500, 2);
		$arrow->addCustomEffect($customSpeed);

		$effects = $arrow->getPotionEffects();
		self::assertCount(2, $effects); // Speed was replaced, jump boost preserved
		$speedEffect = null;
		foreach($effects as $effect){
			if($effect->getType() === VanillaEffects::SPEED()){
				$speedEffect = $effect;
				break;
			}
		}
		self::assertNotNull($speedEffect);
		self::assertSame(500, $speedEffect->getDuration());
		self::assertSame(2, $speedEffect->getAmplifier());

		// Clear custom effects
		$arrow->clearCustomEffects();
		self::assertNull($arrow->getCustomEffects());
		self::assertCount(1, $arrow->getPotionEffects());
		self::assertSame(PotionType::SWIFTNESS->getEffects()[0]->getDuration(), $arrow->getPotionEffects()[0]->getDuration());
	}

	public function testNbtSerializationRoundtrip() : void{
		// Untipped arrow should not write PotionId tag
		$normal = $this->createArrow();
		$normalNbt = $normal->saveNBT();
		self::assertNull($normalNbt->getTag(Arrow::TAG_POTION_ID));

		// Tipped arrow should write PotionId tag
		$tipped = $this->createArrow(null, PotionType::SLOWNESS);
		$tippedNbt = $tipped->saveNBT();
		$potionIdTag = $tippedNbt->getTag(Arrow::TAG_POTION_ID);
		self::assertInstanceOf(ShortTag::class, $potionIdTag);
		self::assertSame(PotionTypeIdMap::getInstance()->toId(PotionType::SLOWNESS), $potionIdTag->getValue());

		// Restore via initEntity
		$restored = $this->createArrow();
		$initEntity = new ReflectionMethod(Arrow::class, "initEntity");
		$initEntity->invoke($restored, $tippedNbt);
		self::assertSame(PotionType::SLOWNESS, $restored->getPotionType());
	}

	public function testNetworkMetadataSync() : void{
		$normal = $this->createArrow();
		$properties = new EntityMetadataCollection();
		$sync = new ReflectionMethod(Arrow::class, "syncNetworkData");
		$sync->invoke($normal, $properties);
		self::assertArrayNotHasKey(EntityMetadataProperties::POTION_AUX_VALUE, $properties->getAll());

		$tipped = $this->createArrow(null, PotionType::POISON);
		$properties = new EntityMetadataCollection();
		$sync->invoke($tipped, $properties);
		$aux = $properties->getAll()[EntityMetadataProperties::POTION_AUX_VALUE] ?? null;
		self::assertInstanceOf(ShortMetadataProperty::class, $aux);
		self::assertSame(PotionTypeIdMap::getInstance()->toId(PotionType::POISON), $aux->getValue());
	}

	public function testPotionEffectAppliedOnHitLivingEntity() : void{
		$arrow = $this->createArrow(null, PotionType::SWIFTNESS);
		$target = $this->createTarget(201);

		$originalDuration = PotionType::SWIFTNESS->getEffects()[0]->getDuration();
		$expectedDuration = (int) round($originalDuration / 8);

		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $target, $hitResult);

		$effect = $target->getEffects()->get(VanillaEffects::SPEED());
		self::assertNotNull($effect);
		self::assertSame($expectedDuration, $effect->getDuration());
	}

	public function testInstantEffectAppliedOnHitLivingEntity() : void{
		$arrow = $this->createArrow(null, PotionType::STRONG_HARMING);
		$target = $this->createTarget(205);

		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $target, $hitResult);

		self::assertTrue($arrow->isFlaggedForDespawn());
	}

	public function testPotionEffectNotAppliedWhenShieldBlocked() : void{
		$arrow = $this->createArrow(null, PotionType::SWIFTNESS);
		(new ReflectionProperty(Arrow::class, "hitBlocked"))->setValue($arrow, true);

		$target = $this->createTarget(202);
		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $target, $hitResult);

		self::assertNull($target->getEffects()->get(VanillaEffects::SPEED()));
	}

	public function testUntippedArrowDoesNotApplyPotionEffect() : void{
		$arrow = $this->createArrow(null, null);
		$target = $this->createTarget(203);

		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $target, $hitResult);

		self::assertEmpty($target->getEffects()->all());
	}
}
