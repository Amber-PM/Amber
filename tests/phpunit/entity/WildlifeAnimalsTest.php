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
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class WildlifeAnimalsTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];
	private ?World $defaultWorld = null;

	protected function setUp() : void{
		parent::setUp();
	}

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll();
		foreach($this->createdEntities as $entity){
			(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		}
		$this->createdEntities = [];
		$this->defaultWorld = null;
		parent::tearDown();
	}

	private function getDefaultWorld() : World{
		if($this->defaultWorld === null){
			$this->defaultWorld = $this->createMock(World::class);
			$this->defaultWorld->method("isLoaded")->willReturn(true);
			$this->defaultWorld->method("getPlayers")->willReturn([]);
		}
		return $this->defaultWorld;
	}

	private function createPlayer(string $name = "Steve", ?string $uuid = null, ?Item $heldItem = null, ?World $world = null, ?Vector3 $pos = null) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline"])
			->getMock();

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("hasFiniteResources")->willReturn(true);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$player->method("isConnected")->willReturn(true);
		$player->method("isOnline")->willReturn(true);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection());

		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 1.8,
			$location->z + 0.3
		));

		$inv = new PlayerInventory($player);
		if($heldItem !== null){
			$inv->setItemInHand($heldItem);
		}
		$player->method("getInventory")->willReturn($inv);

		$this->createdEntities[] = $player;
		return $player;
	}

	private function createDamageByEntityEvent(Entity $damager, Entity $victim, float $damage = 2.0) : EntityDamageByEntityEvent{
		$ev = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager"])
			->getMock();
		$ev->method("getDamager")->willReturn($damager);
		(new ReflectionProperty(EntityDamageEvent::class, "entity"))->setValue($ev, $victim);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($ev, EntityDamageEvent::CAUSE_ENTITY_ATTACK);
		(new ReflectionProperty(EntityDamageEvent::class, "baseDamage"))->setValue($ev, $damage);
		(new ReflectionProperty(EntityDamageEvent::class, "originalBase"))->setValue($ev, $damage);
		(new ReflectionProperty(EntityDamageEvent::class, "modifiers"))->setValue($ev, []);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "knockBack"))->setValue($ev, Living::DEFAULT_KNOCKBACK_FORCE);
		(new ReflectionProperty(EntityDamageByEntityEvent::class, "verticalKnockBackLimit"))->setValue($ev, Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT);
		return $ev;
	}

	private function createFox(?World $world = null, ?Vector3 $pos = null) : Fox{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$fox = new Fox($location);
		$this->createdEntities[] = $fox;
		return $fox;
	}

	private function createPanda(?World $world = null, ?Vector3 $pos = null) : Panda{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$panda = new Panda($location);
		$this->createdEntities[] = $panda;
		return $panda;
	}

	private function createPolarBear(?World $world = null, ?Vector3 $pos = null) : PolarBear{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$bear = new PolarBear($location);
		$this->createdEntities[] = $bear;
		return $bear;
	}

	// ---------------- FOX TESTS ---------------- //

	public function testFoxInitializationAndNetworkId() : void{
		$fox = $this->createFox();
		self::assertSame(EntityIds::FOX, Fox::getNetworkTypeId());
		self::assertSame("Fox", $fox->getName());
		self::assertSame(10, $fox->getDefaultMaxHealth());
		self::assertSame(Fox::VARIANT_RED, $fox->getVariant());
		self::assertFalse($fox->isBaby());
		self::assertFalse($fox->isSleeping());
		self::assertNull($fox->getItemInMouth());
		self::assertNull($fox->getTrustedUuid());
	}

	public function testFoxVariantSwitching() : void{
		$fox = $this->createFox();
		$fox->setVariant(Fox::VARIANT_ARCTIC);
		self::assertSame(Fox::VARIANT_ARCTIC, $fox->getVariant());

		$this->expectException(\InvalidArgumentException::class);
		$fox->setVariant(99);
	}

	public function testFoxItemInMouthAndPlayerTake() : void{
		$fox = $this->createFox();
		$sword = VanillaItems::DIAMOND_SWORD();
		$fox->setItemInMouth($sword);
		self::assertNotNull($fox->getItemInMouth());
		self::assertSame($sword->getTypeId(), $fox->getItemInMouth()->getTypeId());

		// Untrusted player cannot take item with empty hand
		$player = $this->createPlayer("Player1");
		$interacted = $fox->interact($player, new Vector3(0, 0, 0));
		self::assertFalse($interacted);
		self::assertNotNull($fox->getItemInMouth());

		// Trusted player can retrieve item
		$fox->setTrustedUuid($player->getUniqueId()->toString());
		self::assertTrue($fox->isTrusted($player));

		$interacted = $fox->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertNull($fox->getItemInMouth());
		self::assertTrue($player->getInventory()->contains($sword));
	}

	public function testFoxBerryFeedingAndBreeding() : void{
		$fox = $this->createFox();
		$fox->setHealth(6.0);

		$player = $this->createPlayer("Breeder", null, VanillaItems::SWEET_BERRIES()->setCount(64));
		$interacted = $fox->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertSame(8.0, $fox->getHealth());

		// Heal to max
		$fox->setHealth(10.0);
		$interacted = $fox->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertTrue($fox->isInLove());
		self::assertTrue($fox->isTrusted($player));
	}

	public function testFoxSleepingAndWaking() : void{
		$fox = $this->createFox();
		$fox->setSleeping(true);
		self::assertTrue($fox->isSleeping());

		// Damage wakes fox up
		$damageEv = new EntityDamageEvent($fox, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 1.0);
		$fox->attack($damageEv);
		self::assertFalse($fox->isSleeping());
	}

	public function testFoxBerryBushImmunityAndZeroFallDamage() : void{
		$fox = $this->createFox();
		$health = $fox->getHealth();

		// Fall damage: fox takes 0 fall damage
		$fox->fall(20.0);
		self::assertSame($health, $fox->getHealth());

		// Berry bush damage
		$bush = $this->createMock(Block::class);
		$bush->method("getName")->willReturn("Sweet Berry Bush");
		$blockEv = new EntityDamageByBlockEvent($bush, $fox, EntityDamageEvent::CAUSE_CONTACT, 2.0);
		$fox->attack($blockEv);
		self::assertTrue($blockEv->isCancelled());
	}

	public function testFoxNbtSerialization() : void{
		$fox = $this->createFox();
		$fox->setVariant(Fox::VARIANT_ARCTIC);
		$fox->setItemInMouth(VanillaItems::EMERALD());
		$fox->setTrustedUuid("00000000-0000-0000-0000-000000000001");
		$fox->setSleeping(true);
		$fox->setAge(-12000);

		$nbt = $fox->saveNBT();
		$loadedFox = new Fox($fox->getLocation(), $nbt);
		$this->createdEntities[] = $loadedFox;

		self::assertSame(Fox::VARIANT_ARCTIC, $loadedFox->getVariant());
		self::assertNotNull($loadedFox->getItemInMouth());
		self::assertSame(VanillaItems::EMERALD()->getTypeId(), $loadedFox->getItemInMouth()->getTypeId());
		self::assertSame("00000000-0000-0000-0000-000000000001", $loadedFox->getTrustedUuid());
		self::assertTrue($loadedFox->isSleeping());
		self::assertSame(-12000, $loadedFox->getAge());
		self::assertTrue($loadedFox->isBaby());
	}

	// ---------------- PANDA TESTS ---------------- //

	public function testPandaInitializationAndMaxHealth() : void{
		$panda = $this->createPanda();
		self::assertSame(EntityIds::PANDA, Panda::getNetworkTypeId());
		self::assertSame("Panda", $panda->getName());
		self::assertSame(20, $panda->getDefaultMaxHealth());
		self::assertSame(Panda::GENE_NORMAL, $panda->getExpressedGene());

		// Weak gene has 10 max health
		$weakPanda = $this->createPanda();
		$weakPanda->setMainGene(Panda::GENE_WEAK);
		$weakPanda->setHiddenGene(Panda::GENE_WEAK);
		self::assertSame(Panda::GENE_WEAK, $weakPanda->getExpressedGene());
		self::assertSame(10, $weakPanda->getDefaultMaxHealth());
	}

	public function testPandaRecessiveGenetics() : void{
		$panda = $this->createPanda();

		// Brown is recessive: needs both alleles
		$panda->setMainGene(Panda::GENE_BROWN);
		$panda->setHiddenGene(Panda::GENE_LAZY);
		self::assertSame(Panda::GENE_LAZY, $panda->getExpressedGene()); // Dominant expressed

		$panda->setHiddenGene(Panda::GENE_BROWN);
		self::assertSame(Panda::GENE_BROWN, $panda->getExpressedGene()); // Recessive expressed!

		// Weak is recessive: needs both alleles
		$panda->setMainGene(Panda::GENE_WEAK);
		$panda->setHiddenGene(Panda::GENE_PLAYFUL);
		self::assertSame(Panda::GENE_PLAYFUL, $panda->getExpressedGene());

		$panda->setHiddenGene(Panda::GENE_WEAK);
		self::assertSame(Panda::GENE_WEAK, $panda->getExpressedGene());
	}

	public function testPandaEatingAndSitting() : void{
		$panda = $this->createPanda();
		$panda->setHealth(10.0);

		$player = $this->createPlayer("Feeder", null, VanillaItems::BAMBOO());
		$interacted = $panda->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertSame(12.0, $panda->getHealth());
		self::assertTrue($panda->isEating());
	}

	public function testPandaRolling() : void{
		$panda = $this->createPanda();
		self::assertFalse($panda->isRolling());
		$panda->setRolling(30);
		self::assertTrue($panda->isRolling());
	}

	public function testPandaNbtSerialization() : void{
		$panda = $this->createPanda();
		$panda->setMainGene(Panda::GENE_PLAYFUL);
		$panda->setHiddenGene(Panda::GENE_BROWN);
		$panda->setAge(-6000);

		$nbt = $panda->saveNBT();
		$loadedPanda = new Panda($panda->getLocation(), $nbt);
		$this->createdEntities[] = $loadedPanda;

		self::assertSame(Panda::GENE_PLAYFUL, $loadedPanda->getMainGene());
		self::assertSame(Panda::GENE_BROWN, $loadedPanda->getHiddenGene());
		self::assertSame(-6000, $loadedPanda->getAge());
		self::assertTrue($loadedPanda->isBaby());
	}

	// ---------------- POLAR BEAR TESTS ---------------- //

	public function testPolarBearInitializationAndStats() : void{
		$bear = $this->createPolarBear();
		self::assertSame(EntityIds::POLAR_BEAR, PolarBear::getNetworkTypeId());
		self::assertSame("Polar Bear", $bear->getName());
		self::assertSame(30, $bear->getDefaultMaxHealth());
		self::assertFalse($bear->isAngry());
		self::assertFalse($bear->isBaby());
	}

	public function testPolarBearHostileWhenAttacked() : void{
		$bear = $this->createPolarBear();
		$player = $this->createPlayer("Attacker");

		$damageEv = $this->createDamageByEntityEvent($player, $bear, 5.0);
		$bear->attack($damageEv);

		self::assertTrue($bear->isAngry());
		self::assertSame($player->getId(), $bear->getTargetEntityId());

		$bear->calmDown();
		self::assertFalse($bear->isAngry());
		self::assertNull($bear->getTargetEntityId());
	}

	public function testPolarBearBabyAlertsAdults() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$baby = $this->createPolarBear($world);
		$baby->setBaby(true);

		$adult = $this->createPolarBear($world);
		$world->method("getNearbyEntities")->willReturn([$adult]);

		$player = $this->createPlayer("Attacker", null, null, $world);
		$damageEv = $this->createDamageByEntityEvent($player, $baby, 2.0);
		$baby->attack($damageEv);

		self::assertFalse($baby->isAngry()); // Babies never get angry
		self::assertTrue($adult->isAngry()); // Adult becomes angry on baby's behalf!
		self::assertSame($player->getId(), $adult->getTargetEntityId());
	}

	public function testPolarBearDrops() : void{
		$bear = $this->createPolarBear();
		$drops = $bear->getDrops();
		self::assertIsArray($drops);

		$baby = $this->createPolarBear();
		$baby->setBaby(true);
		self::assertEmpty($baby->getDrops());
	}

	public function testPolarBearNbtSerialization() : void{
		$bear = $this->createPolarBear();
		$bear->setAngry(350);
		$bear->setAge(-10000);

		$nbt = $bear->saveNBT();
		$loadedBear = new PolarBear($bear->getLocation(), $nbt);
		$this->createdEntities[] = $loadedBear;

		self::assertTrue($loadedBear->isBaby());
		self::assertSame(-10000, $loadedBear->getAge());
	}
}
