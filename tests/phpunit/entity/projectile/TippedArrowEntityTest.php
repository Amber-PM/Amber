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
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use function count;
use function round;

class DummyTippedArrowTarget extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:dummy"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Dummy"; }
}

final class TippedArrowEntityTest extends TestCase{

	/** @var array<int, Entity> */
	private static array $entitiesById = [];
	private World $testWorld;

	protected function setUp() : void{
		/** @var array<int, Entity> $empty */
		$empty = [];
		self::$entitiesById = $empty;

		$worldManager = $this->createMock(WorldManager::class);
		$worldManager->method("findEntity")->willReturnCallback(function(int $entityId) : ?Entity{
			return self::$entitiesById[$entityId] ?? null;
		});

		$server = $this->createMock(Server::class);
		$server->method("getWorldManager")->willReturn($worldManager);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getServer")->willReturn($server);
		$world->updateEntities = [];
		$this->testWorld = $world;
	}

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll();
		self::$entitiesById = [];
	}

	private function createArrow(?World $world = null, ?PotionType $potionType = null) : Arrow{
		static $arrowIdCounter = 100;
		$id = $arrowIdCounter++;
		$arrow = (new ReflectionClass(Arrow::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "closeInFlight"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($arrow, []);
		(new ReflectionProperty(Entity::class, "attachedShapes"))->setValue($arrow, []);
		(new ReflectionProperty(Entity::class, "id"))->setValue($arrow, $id);
		self::$entitiesById[$id] = $arrow;
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

		$w = $world ?? $this->testWorld;
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, new Location(0.0, 0.0, 0.0, $w, 0.0, 0.0));
		return $arrow;
	}

	private function createTarget(int $id) : DummyTippedArrowTarget{
		$entity = (new ReflectionClass(DummyTippedArrowTarget::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($entity, $id);
		self::$entitiesById[$id] = $entity;
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($entity, new AxisAlignedBB(0, 0, 0, 1, 2, 1));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($entity, Vector3::zero());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($entity, new EntityMetadataCollection());
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($entity, new EffectManager($entity));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($entity, new ArmorInventory($entity));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($entity, new AttributeMap());
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($entity);
		(new ReflectionProperty(Entity::class, "health"))->setValue($entity, 20.0);
		(new ReflectionProperty(Entity::class, "maxHealth"))->setValue($entity, 20);
		(new ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location(0.0, 0.0, 0.0, $this->testWorld, 0.0, 0.0));
		return $entity;
	}

	private function createHuman(float $yaw = 0.0, ?Vector3 $pos = null) : Human{
		static $humanIdCounter = 300;
		$id = $humanIdCounter++;
		$human = (new ReflectionClass(Human::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "id"))->setValue($human, $id);
		self::$entitiesById[$id] = $human;
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($human, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($human, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($human, 1.0);
		$pos = $pos ?? Vector3::zero();
		(new ReflectionProperty(Entity::class, "location"))->setValue($human, new Location($pos->x, $pos->y, $pos->z, $this->testWorld, $yaw, 0.0));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($human, new AttributeMap());
		(new ReflectionClass(Human::class))->getMethod("addAttributes")->invoke($human);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($human, new EffectManager($human));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($human, new ArmorInventory($human));
		(new ReflectionProperty(Human::class, "inventory"))->setValue($human, new PlayerInventory($human));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($human, new PlayerOffHandInventory($human));
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($human, new PlayerEnderInventory($human));
		(new ReflectionProperty(Living::class, "sneaking"))->setValue($human, false);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($human, Vector3::zero());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($human, false);
		return $human;
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

	public function testArrowConstructorBackwardCompatibility() : void{
		$location = new Location(1.0, 2.0, 3.0, $this->testWorld, 0.0, 0.0);

		$nbt = CompoundTag::create()
			->setByte(Arrow::TAG_CRIT, 1)
			->setByte("pickup", Arrow::PICKUP_CREATIVE);

		// 4-parameter constructor call: ($location, $shootingEntity, $critical, $nbt)
		$arrow = new Arrow($location, null, true, $nbt);
		self::assertNull($arrow->getPotionType());
		self::assertSame(Arrow::PICKUP_CREATIVE, $arrow->getPickupMode());

		// 5-parameter constructor call: ($location, $shootingEntity, $critical, $nbt, $potionType)
		$tippedArrow = new Arrow($location, null, true, null, PotionType::SWIFTNESS);
		self::assertSame(PotionType::SWIFTNESS, $tippedArrow->getPotionType());
	}

	public function testNetworkMetadataSync() : void{
		$normal = $this->createArrow();
		$properties = new EntityMetadataCollection();
		$sync = new ReflectionMethod(Arrow::class, "syncNetworkData");
		$sync->invoke($normal, $properties);
		$aux = $properties->getAll()[EntityMetadataProperties::MINECART_HAS_DISPLAY] ?? null;
		self::assertInstanceOf(ByteMetadataProperty::class, $aux);
		self::assertSame(0, $aux->getValue());

		$tipped = $this->createArrow(null, PotionType::POISON);
		$properties = new EntityMetadataCollection();
		$sync->invoke($tipped, $properties);
		$aux = $properties->getAll()[EntityMetadataProperties::MINECART_HAS_DISPLAY] ?? null;
		self::assertInstanceOf(ByteMetadataProperty::class, $aux);
		self::assertSame(26, $aux->getValue());
	}

	public function testNetworkMetadataReusedCollectionClearingPotionType() : void{
		$arrow = $this->createArrow(null, PotionType::POISON);
		$properties = new EntityMetadataCollection();
		$sync = new ReflectionMethod(Arrow::class, "syncNetworkData");

		$sync->invoke($arrow, $properties);
		$aux = $properties->getAll()[EntityMetadataProperties::MINECART_HAS_DISPLAY] ?? null;
		self::assertInstanceOf(ByteMetadataProperty::class, $aux);
		self::assertSame(26, $aux->getValue());
		$properties->clearDirtyProperties();

		$arrow->setPotionType(null);
		$sync->invoke($arrow, $properties);
		$auxAfter = $properties->getDirty()[EntityMetadataProperties::MINECART_HAS_DISPLAY] ?? null;
		self::assertInstanceOf(ByteMetadataProperty::class, $auxAfter);
		self::assertSame(0, $auxAfter->getValue());
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
		$initialHealth = $target->getHealth();

		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $target, $hitResult);

		self::assertTrue($arrow->isFlaggedForDespawn());
		self::assertLessThan($initialHealth, $target->getHealth());
	}

	public function testPotionEffectNotAppliedWhenShieldBlocked() : void{
		$defender = $this->createHuman(180.0, new Vector3(0, 0, 0)); // Facing North (toward -Z)
		$defender->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$defender->setSneaking(true);
		$defender->updateBlockingState();
		self::assertTrue($defender->isBlocking());

		$arrow = $this->createArrow(null, PotionType::POISON);
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, new Location(0.0, 0.0, -5.0, $this->testWorld, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($arrow, new Vector3(0.0, 0.0, 2.0));

		$hitResult = new RayTraceResult(new AxisAlignedBB(-0.5, 0, -0.5, 0.5, 1.8, 0.5), Facing::NORTH, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $defender, $hitResult);

		self::assertTrue($arrow->isHitBlocked());
		self::assertLessThan(0.0, $arrow->getMotion()->z);
		self::assertSame(20.0, $defender->getHealth());
		self::assertEmpty($defender->getEffects()->all());
	}

	public function testCancelledDamageEventSkipsPotionEffects() : void{
		$target = $this->createTarget(204);
		$initialHealth = $target->getHealth();

		$plugin = $this->createMock(Plugin::class);
		$listener = new RegisteredListener(
			function(EntityDamageEvent $event) : void{
				if($event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE){
					$event->cancel();
				}
			},
			EventPriority::NORMAL,
			$plugin,
			false,
			new TimingsHandler("cancelled damage test")
		);
		HandlerListManager::global()->getListFor(EntityDamageEvent::class)->register($listener);

		$arrow = $this->createArrow(null, PotionType::POISON);
		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));
		$onHitEntity = new ReflectionMethod(Arrow::class, "onHitEntity");
		$onHitEntity->invoke($arrow, $target, $hitResult);

		self::assertNotNull($arrow->getLastDamageEvent());
		self::assertTrue($arrow->getLastDamageEvent()->isCancelled());
		self::assertTrue($arrow->isHitCancelled());
		self::assertSame($initialHealth, $target->getHealth());
		self::assertEmpty($target->getEffects()->all());

		// Tick effect manager to verify no latent damage or poisoning occurs
		$target->getEffects()->tick(1);
		self::assertSame($initialHealth, $target->getHealth());
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
