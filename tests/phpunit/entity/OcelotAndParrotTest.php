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
use pocketmine\entity\effect\EffectManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

if(!class_exists(OcelotTestCreeper::class)){
	class OcelotTestCreeper extends Living{
		public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.7, 0.6); }
		public function getName() : string{ return "Creeper"; }
	}
}

if(!class_exists(OcelotTestPhantom::class)){
	class OcelotTestPhantom extends Living{
		public static function getNetworkTypeId() : string{ return EntityIds::PHANTOM; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.5, 0.9); }
		public function getName() : string{ return "Phantom"; }
	}
}

if(!class_exists(OcelotTestZombie::class)){
	class OcelotTestZombie extends Living{
		public static function getNetworkTypeId() : string{ return EntityIds::ZOMBIE; }
		protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.9, 0.6); }
		public function getName() : string{ return "Zombie"; }
	}
}

class OcelotAndParrotTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];

	protected function tearDown() : void{
		foreach($this->createdEntities as $entity){
			if(!$entity->isClosed()){
				$ref = new ReflectionProperty(Entity::class, "closed");
				$ref->setValue($entity, true);
			}
		}
		$this->createdEntities = [];
		parent::tearDown();
	}

	private function createMockWorld(array $entities = []) : World{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$world->method('getEntities')->willReturn($entities);
		$world->method('getNearbyEntities')->willReturn($entities);
		$world->method('getBlockAt')->willReturn(\pocketmine\block\VanillaBlocks::AIR());
		return $world;
	}

	private function createOcelot(?World $world = null) : Ocelot{
		$world ??= $this->createMockWorld();
		$location = new Location(100.0, 64.0, 100.0, $world, 0.0, 0.0);
		$nbt = new CompoundTag();
		$ocelot = new Ocelot($location, $nbt);

		$ref = new ReflectionClass(Entity::class);
		$lastLocProp = $ref->getProperty('lastLocation');
		$lastLocProp->setValue($ocelot, clone $location);
		$lastMotProp = $ref->getProperty('lastMotion');
		$lastMotProp->setValue($ocelot, Vector3::zero());

		$this->createdEntities[] = $ocelot;
		return $ocelot;
	}

	private function createParrot(?World $world = null) : Parrot{
		$world ??= $this->createMockWorld();
		$location = new Location(100.0, 64.0, 100.0, $world, 0.0, 0.0);
		$nbt = new CompoundTag();
		$parrot = new Parrot($location, $nbt);

		$ref = new ReflectionClass(Entity::class);
		$lastLocProp = $ref->getProperty('lastLocation');
		$lastLocProp->setValue($parrot, clone $location);
		$lastMotProp = $ref->getProperty('lastMotion');
		$lastMotProp->setValue($parrot, Vector3::zero());

		$this->createdEntities[] = $parrot;
		return $parrot;
	}

	private function createMockPlayer(string $name = "Steve", ?string $uuid = null, ?Item $heldItem = null, bool $survival = true, ?World $world = null) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation"])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());

		$world ??= $this->createMockWorld();
		$location = new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($player, clone $location);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(-0.3, 0, -0.3, 0.3, 1.8, 0.3));

		$inventory = new PlayerInventory($player);
		if($heldItem !== null){
			$inventory->setItemInHand($heldItem);
		}
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, $inventory);
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(!$survival);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn($survival);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);

		$this->createdEntities[] = $player;
		return $player;
	}

	public function testOcelotDefaults() : void{
		$ocelot = $this->createOcelot();
		self::assertSame(EntityIds::OCELOT, Ocelot::getNetworkTypeId());
		self::assertSame("Ocelot", $ocelot->getName());
		self::assertSame(10, $ocelot->getMaxHealth());
		self::assertSame(10.0, $ocelot->getHealth());
		self::assertSame(0.0, $ocelot->calculateFallDamage(20.0));
		self::assertFalse($ocelot->isTrusting());
		self::assertFalse($ocelot->isBaby());
	}

	public function testOcelotTrustingAndInteractions() : void{
		$ocelot = $this->createOcelot();
		$player = $this->createMockPlayer();

		// Non-fish item does nothing
		$stick = VanillaItems::STICK();
		$player->getInventory()->setItemInHand($stick);
		self::assertFalse($ocelot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertFalse($ocelot->isTrusting());

		// Trusting flag directly
		$ocelot->setTrusting(true);
		self::assertTrue($ocelot->isTrusting());

		// Fish heals when damaged
		$ocelot->setHealth(6.0);
		$fish = VanillaItems::RAW_FISH();
		$player->getInventory()->setItemInHand($fish);
		self::assertTrue($ocelot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame(8.0, $ocelot->getHealth());

		// Fish enters love mode when full health
		$ocelot->setHealth(10.0);
		$salmon = VanillaItems::RAW_SALMON();
		$player->getInventory()->setItemInHand($salmon);
		self::assertTrue($ocelot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertTrue($ocelot->isInLove());
		self::assertSame(600, $ocelot->getInLoveTicks());
	}

	public function testOcelotBreedingAndBaby() : void{
		$ocelot1 = $this->createOcelot();
		$ocelot1->setTrusting(true);
		$ocelot1->setInLoveTicks(600);

		$ocelot2 = $this->createOcelot();
		$ocelot2->setTrusting(true);
		$ocelot2->setInLoveTicks(600);

		$baby = $ocelot1->breed($ocelot2);
		self::assertNotNull($baby);
		self::assertTrue($baby->isBaby());
		self::assertSame(-24000, $baby->getAge());
		self::assertTrue($baby->isTrusting());
		self::assertSame(Ocelot::BREEDING_COOLDOWN_TICKS, $ocelot1->getBreedingCooldownTicks());
		self::assertSame(Ocelot::BREEDING_COOLDOWN_TICKS, $ocelot2->getBreedingCooldownTicks());
		self::assertFalse($ocelot1->isInLove());

		// Feed baby speeds up growth
		$player = $this->createMockPlayer();
		$player->getInventory()->setItemInHand(VanillaItems::RAW_FISH());
		self::assertTrue($baby->onInteract($player, new Vector3(0, 0, 0)));
		self::assertSame(-21600, $baby->getAge());
	}

	public function testOcelotRepelsCreepersAndPhantoms() : void{
		$ocelot = $this->createOcelot();

		$creeper = new OcelotTestCreeper(new Location(0, 0, 0, $this->createMockWorld(), 0, 0), new CompoundTag());
		self::assertTrue($ocelot->isRepelledMob($creeper));

		$phantom = new OcelotTestPhantom(new Location(0, 0, 0, $this->createMockWorld(), 0, 0), new CompoundTag());
		self::assertTrue($ocelot->isRepelledMob($phantom));

		$zombie = new OcelotTestZombie(new Location(0, 0, 0, $this->createMockWorld(), 0, 0), new CompoundTag());
		self::assertFalse($ocelot->isRepelledMob($zombie));
	}

	public function testOcelotNbtRoundtrip() : void{
		$ocelot = $this->createOcelot();
		$ocelot->setTrusting(true);
		$ocelot->setAge(-12000);
		$ocelot->setInLoveTicks(450);
		$ocelot->setBreedingCooldownTicks(3000);

		$nbt = $ocelot->saveNBT();

		$loaded = $this->createOcelot();
		$ref = new ReflectionClass(Ocelot::class);
		$readMethod = $ref->getMethod('readSaveData');
		$readMethod->invoke($loaded, $nbt);

		self::assertTrue($loaded->isTrusting());
		self::assertSame(-12000, $loaded->getAge());
		self::assertSame(450, $loaded->getInLoveTicks());
		self::assertSame(3000, $loaded->getBreedingCooldownTicks());
	}

	public function testParrotDefaultsAndVariants() : void{
		$parrot = $this->createParrot();
		self::assertSame(EntityIds::PARROT, Parrot::getNetworkTypeId());
		self::assertSame("Parrot", $parrot->getName());
		self::assertSame(6, $parrot->getMaxHealth());
		self::assertSame(6.0, $parrot->getHealth());
		self::assertSame(0.0, $parrot->calculateFallDamage(20.0));
		self::assertFalse($parrot->isTamed());
		self::assertFalse($parrot->isSitting());
		self::assertFalse($parrot->canBreed());

		foreach([Parrot::VARIANT_RED, Parrot::VARIANT_BLUE, Parrot::VARIANT_GREEN, Parrot::VARIANT_CYAN, Parrot::VARIANT_GRAY] as $v){
			$parrot->setVariant($v);
			self::assertSame($v, $parrot->getVariant());
		}
	}

	public function testParrotTamingAndSittingToggle() : void{
		$parrot = $this->createParrot();
		$player = $this->createMockPlayer("Alex");

		// Non-seed item does not tame
		$player->getInventory()->setItemInHand(VanillaItems::STICK());
		self::assertFalse($parrot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertFalse($parrot->isTamed());

		// Tame directly
		self::assertTrue($parrot->tame($player));
		self::assertTrue($parrot->isTamed());
		self::assertSame($player->getUniqueId()->toString(), $parrot->getOwnerUUID());
		self::assertSame("Alex", $parrot->getOwnerName());
		self::assertTrue($parrot->isSitting());

		// Toggle sitting by owner
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		self::assertTrue($parrot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertFalse($parrot->isSitting());

		// Toggle sitting back to true
		self::assertTrue($parrot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertTrue($parrot->isSitting());

		// Non-owner cannot toggle sitting
		$stranger = $this->createMockPlayer("Stranger");
		$stranger->getInventory()->setItemInHand(VanillaItems::AIR());
		self::assertFalse($parrot->onInteract($stranger, new Vector3(0, 0, 0)));
		self::assertTrue($parrot->isSitting());
	}

	public function testParrotCookieLethal() : void{
		$parrot = $this->createParrot();
		$player = $this->createMockPlayer();
		$cookie = VanillaItems::COOKIE();
		$player->getInventory()->setItemInHand($cookie);

		self::assertTrue($parrot->onInteract($player, new Vector3(0, 0, 0)));
		self::assertFalse($parrot->isAlive());
	}

	public function testParrotDrops() : void{
		$parrot = $this->createParrot();
		$drops = $parrot->getDrops();
		self::assertNotEmpty($drops);
		self::assertSame(ItemTypeIds::FEATHER, $drops[0]->getTypeId());
		self::assertGreaterThanOrEqual(1, $drops[0]->getCount());
	}

	public function testParrotNbtRoundtrip() : void{
		$parrot = $this->createParrot();
		$uuid = Uuid::uuid4()->toString();
		$parrot->setTamed(true);
		$parrot->setOwnerUUID($uuid);
		$parrot->setOwnerName("BirdLover");
		$parrot->setSitting(true);
		$parrot->setVariant(Parrot::VARIANT_CYAN);

		$nbt = $parrot->saveNBT();

		$loaded = $this->createParrot();
		$ref = new ReflectionClass(Parrot::class);
		$readMethod = $ref->getMethod('readSaveData');
		$readMethod->invoke($loaded, $nbt);

		self::assertTrue($loaded->isTamed());
		self::assertSame($uuid, $loaded->getOwnerUUID());
		self::assertSame("BirdLover", $loaded->getOwnerName());
		self::assertTrue($loaded->isSitting());
		self::assertSame(Parrot::VARIANT_CYAN, $loaded->getVariant());
	}
}
