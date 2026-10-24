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
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\FroglightType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\HungerManager;
use pocketmine\entity\projectile\LlamaSpit;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\LlamaInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\biome\Biome;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class TestSmallSlime extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:slime"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.5, 0.5); }
	public function getName() : string{ return "Slime"; }
}

class TestSmallMagmaCube extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:magma_cube"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.5, 0.5); }
	public function getName() : string{ return "Magma Cube"; }
}

class LlamaAndGoatTest extends TestCase{

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

	private function createPlayer(string $name = "Steve", ?string $uuid = null, ?Item $heldItem = null, bool $sneaking = false, ?World $world = null, ?Vector3 $pos = null) : Player{
		$uuidObj = $uuid !== null ? Uuid::fromString($uuid) : Uuid::uuid4();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "isConnected", "isOnline", "setCurrentWindow"])
			->getMock();

		$player->method("getName")->willReturn($name);
		$player->method("getUniqueId")->willReturn($uuidObj);
		$player->method("isSneaking")->willReturn($sneaking);
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
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
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
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, $inv);
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new \pocketmine\inventory\PlayerOffHandInventory($player));
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

	private function createGoat(?World $world = null, ?Vector3 $pos = null) : Goat{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$goat = new Goat($location);
		$this->createdEntities[] = $goat;
		return $goat;
	}

	private function createLlama(?World $world = null, ?Vector3 $pos = null) : Llama{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$llama = new Llama($location);
		$this->createdEntities[] = $llama;
		return $llama;
	}

	private function createTraderLlama(?World $world = null, ?Vector3 $pos = null) : TraderLlama{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$llama = new TraderLlama($location);
		$this->createdEntities[] = $llama;
		return $llama;
	}

	private function createFrog(?World $world = null, ?Vector3 $pos = null) : Frog{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$frog = new Frog($location);
		$this->createdEntities[] = $frog;
		return $frog;
	}

	private function createTadpole(?World $world = null, ?Vector3 $pos = null) : Tadpole{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$tadpole = new Tadpole($location);
		$this->createdEntities[] = $tadpole;
		return $tadpole;
	}

	private function createAllay(?World $world = null, ?Vector3 $pos = null) : Allay{
		if($world === null){
			$world = $this->getDefaultWorld();
		}
		$location = new Location($pos?->x ?? 0.0, $pos?->y ?? 0.0, $pos?->z ?? 0.0, $world, 0.0, 0.0);
		$allay = new Allay($location);
		$this->createdEntities[] = $allay;
		return $allay;
	}

	// ---------------- GOAT TESTS ---------------- //

	public function testGoatInitializationAndStats() : void{
		$goat = $this->createGoat();
		self::assertSame(EntityIds::GOAT, Goat::getNetworkTypeId());
		self::assertSame("Goat", $goat->getName());
		self::assertSame(10, $goat->getDefaultMaxHealth());
		self::assertSame(Goat::VARIANT_DEFAULT, $goat->getVariant());
		self::assertSame(2, $goat->getHornCount());
		self::assertFalse($goat->isBaby());
		self::assertFalse($goat->isScreamer());
	}

	public function testGoatScreamerVariant() : void{
		$goat = $this->createGoat();
		$goat->setVariant(Goat::VARIANT_SCREAMER);
		self::assertTrue($goat->isScreamer());
		self::assertSame(Goat::VARIANT_SCREAMER, $goat->getVariant());
	}

	public function testGoatMilking() : void{
		$goat = $this->createGoat();
		$player = $this->createPlayer("Milker", null, VanillaItems::BUCKET());

		$interacted = $goat->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertTrue($player->getInventory()->contains(VanillaItems::MILK_BUCKET()));
	}

	public function testGoatFallDamageReduction() : void{
		$goat = $this->createGoat();
		$health = $goat->getHealth();

		// Falling 10 blocks: regular entity takes 7 damage, goat takes 0 damage (10 - 3 - 10 <= 0)
		$goat->fall(10.0);
		self::assertSame($health, $goat->getHealth());

		// Falling 15 blocks: 15 - 3 - 10 = 2 damage
		$goat->fall(15.0);
		self::assertSame(8.0, $goat->getHealth());
	}

	public function testGoatRamAttackAndHornDrop() : void{
		$goat = $this->createGoat();
		$target = $this->createPlayer("Target");

		$goat->ramTarget($target);
		self::assertFalse($goat->isRamming());

		// Collide with solid block while ramming
		$goat->setRamming(true);
		$solidBlock = $this->createMock(Block::class);
		$solidBlock->method("isSolid")->willReturn(true);

		$goat->handleBlockImpact($solidBlock);
		self::assertSame(1, $goat->getHornCount());
		self::assertFalse($goat->isRamming());
	}

	public function testGoatNbtSerialization() : void{
		$goat = $this->createGoat();
		$goat->setVariant(Goat::VARIANT_SCREAMER);
		$goat->setHornCount(1);
		$goat->setAge(-5000);

		$nbt = $goat->saveNBT();
		$loadedGoat = new Goat($goat->getLocation(), $nbt);
		$this->createdEntities[] = $loadedGoat;

		self::assertSame(Goat::VARIANT_SCREAMER, $loadedGoat->getVariant());
		self::assertSame(1, $loadedGoat->getHornCount());
		self::assertSame(-5000, $loadedGoat->getAge());
		self::assertTrue($loadedGoat->isBaby());
	}

	// ---------------- LLAMA TESTS ---------------- //

	public function testLlamaInitializationAndStrength() : void{
		$llama = $this->createLlama();
		self::assertSame(EntityIds::LLAMA, Llama::getNetworkTypeId());
		self::assertSame("Llama", $llama->getName());
		self::assertGreaterThanOrEqual(15, $llama->getDefaultMaxHealth());
		self::assertLessThanOrEqual(30, $llama->getDefaultMaxHealth());
		self::assertGreaterThanOrEqual(1, $llama->getStrength());
		self::assertLessThanOrEqual(5, $llama->getStrength());
		self::assertFalse($llama->isTamed());
		self::assertFalse($llama->isChested());
		self::assertNull($llama->getCarpetColor());
	}

	public function testLlamaChestedAndInventorySize() : void{
		$llama = $this->createLlama();
		$llama->setStrength(4);
		self::assertSame(12, $llama->getInventorySize()); // 3 * 4 = 12 slots

		$player = $this->createPlayer("Owner", null, VanillaBlocks::CHEST()->asItem());
		$interacted = $llama->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertTrue($llama->isChested());
		self::assertNotNull($llama->getInventory());
		self::assertSame(12, $llama->getInventory()->getSize());
	}

	public function testLlamaCarpetEquippingAndShearing() : void{
		$llama = $this->createLlama();
		$redCarpet = VanillaBlocks::CARPET()->setColor(DyeColor::RED)->asItem();

		$player = $this->createPlayer("Decorator", null, $redCarpet);
		$interacted = $llama->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertSame(DyeColor::RED, $llama->getCarpetColor());

		// Shearing carpet
		$shears = VanillaItems::SHEARS();
		$shearPlayer = $this->createPlayer("Shearer", null, $shears);
		$interacted = $llama->interact($shearPlayer, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertNull($llama->getCarpetColor());
	}

	public function testTraderLlamaDefaults() : void{
		$traderLlama = $this->createTraderLlama();
		self::assertSame(EntityIds::TRADER_LLAMA, TraderLlama::getNetworkTypeId());
		self::assertSame("Trader Llama", $traderLlama->getName());
		self::assertTrue($traderLlama->isTamed());
		self::assertSame(DyeColor::BLUE, $traderLlama->getCarpetColor());
	}

	public function testLlamaSpitProjectile() : void{
		$world = $this->getDefaultWorld();
		$spit = new LlamaSpit(new Location(0, 0, 0, $world, 0, 0), null);
		self::assertSame(EntityIds::LLAMA_SPIT, LlamaSpit::getNetworkTypeId());

		$target = $this->createPlayer("Target");
		$hitResult = new RayTraceResult(new AxisAlignedBB(0, 0, 0, 1, 1, 1), 0, new Vector3(0, 0, 0));

		$method = new \ReflectionMethod(LlamaSpit::class, "onHitEntity");
		$method->invoke($spit, $target, $hitResult);

		self::assertTrue($spit->isFlaggedForDespawn());
	}

	public function testLlamaNbtSerialization() : void{
		$llama = $this->createLlama();
		$llama->setVariant(Llama::VARIANT_BROWN);
		$llama->setStrength(5);
		$llama->setTamed(true);
		$llama->setChested(true);
		$llama->setCarpet(VanillaBlocks::CARPET()->setColor(DyeColor::LIME)->asItem());

		$nbt = $llama->saveNBT();
		$loadedLlama = new Llama($llama->getLocation(), $nbt);
		$this->createdEntities[] = $loadedLlama;

		self::assertSame(Llama::VARIANT_BROWN, $loadedLlama->getVariant());
		self::assertSame(5, $loadedLlama->getStrength());
		self::assertTrue($loadedLlama->isTamed());
		self::assertTrue($loadedLlama->isChested());
		self::assertSame(DyeColor::LIME, $loadedLlama->getCarpetColor());
		self::assertNotNull($loadedLlama->getInventory());
		self::assertSame(15, $loadedLlama->getInventory()->getSize());
	}

	// ---------------- FROG & TADPOLE TESTS ---------------- //

	public function testFrogInitializationAndVariants() : void{
		$frog = $this->createFrog();
		self::assertSame(EntityIds::FROG, Frog::getNetworkTypeId());
		self::assertSame("Frog", $frog->getName());
		self::assertSame(10, $frog->getDefaultMaxHealth());
		self::assertSame(Frog::VARIANT_TEMPERATE, $frog->getVariant());
		self::assertTrue($frog->canBreatheInWater());

		$frog->setVariant(Frog::VARIANT_COLD);
		self::assertSame(Frog::VARIANT_COLD, $frog->getVariant());
	}

	public function testFrogFallDamageReduction() : void{
		$frog = $this->createFrog();
		$health = $frog->getHealth();

		$frog->fall(10.0);
		self::assertSame($health, $frog->getHealth());
	}

	public function testFrogEatSmallSlimeAndMagmaCube() : void{
		$world = $this->getDefaultWorld();
		$frog = $this->createFrog($world);
		$frog->setVariant(Frog::VARIANT_COLD);

		$slime = new TestSmallSlime(new Location(0, 0, 0, $world, 0, 0));
		$eaten = $frog->eatSmallMob($slime);
		self::assertTrue($eaten);
		self::assertTrue($slime->isFlaggedForDespawn());

		$magmaCube = new TestSmallMagmaCube(new Location(0, 0, 0, $world, 0, 0));
		$eaten = $frog->eatSmallMob($magmaCube);
		self::assertTrue($eaten);
		self::assertTrue($magmaCube->isFlaggedForDespawn());
	}

	public function testTadpoleGrowthWithSlimeball() : void{
		$tadpole = $this->createTadpole();
		self::assertSame(EntityIds::TADPOLE, Tadpole::getNetworkTypeId());
		self::assertSame("Tadpole", $tadpole->getName());
		self::assertSame(6, $tadpole->getDefaultMaxHealth());
		self::assertTrue($tadpole->isBaby());
		self::assertTrue($tadpole->canBreatheInWater());

		$initialAge = $tadpole->getAge();
		$player = $this->createPlayer("Feeder", null, VanillaItems::SLIMEBALL());
		$interacted = $tadpole->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertSame($initialAge + 2400, $tadpole->getAge());
	}

	// ---------------- ALLAY TESTS ---------------- //

	public function testAllayInitializationAndStats() : void{
		$allay = $this->createAllay();
		self::assertSame(EntityIds::ALLAY, Allay::getNetworkTypeId());
		self::assertSame("Allay", $allay->getName());
		self::assertSame(20, $allay->getDefaultMaxHealth());
		self::assertNull($allay->getHeldItem());
		self::assertNull($allay->getOwnerUuid());
		self::assertFalse($allay->isDancing());
	}

	public function testAllayItemGivingAndRetrieval() : void{
		$allay = $this->createAllay();
		$cookie = VanillaItems::COOKIE();

		// Player gives cookie
		$player = $this->createPlayer("Giver", null, $cookie);
		$interacted = $allay->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertNotNull($allay->getHeldItem());
		self::assertSame($cookie->getTypeId(), $allay->getHeldItem()->getTypeId());
		self::assertSame($player->getUniqueId()->toString(), $allay->getOwnerUuid());

		// Player retrieves with empty hand
		$retriever = $this->createPlayer("Retriever");
		$interacted = $allay->interact($retriever, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertNull($allay->getHeldItem());
		self::assertTrue($retriever->getInventory()->contains($cookie));
	}

	public function testAllayDuplication() : void{
		$allay = $this->createAllay();
		$allay->setDancing(true);
		self::assertTrue($allay->canDuplicate());

		$player = $this->createPlayer("Duplicator", null, VanillaItems::AMETHYST_SHARD());
		$interacted = $allay->interact($player, new Vector3(0, 0, 0));
		self::assertTrue($interacted);
		self::assertFalse($allay->canDuplicate()); // Cooldown active
		self::assertSame(Allay::DUPLICATION_COOLDOWN_TICKS, $allay->getDuplicationCooldownTicks());
	}

	public function testAllayNbtSerialization() : void{
		$allay = $this->createAllay();
		$allay->setHeldItem(VanillaItems::GOLD_INGOT());
		$allay->setOwnerUuid("00000000-0000-0000-0000-000000000002");
		$allay->setDuplicationCooldownTicks(1200);

		$nbt = $allay->saveNBT();
		$loadedAllay = new Allay($allay->getLocation(), $nbt);
		$this->createdEntities[] = $loadedAllay;

		self::assertNotNull($loadedAllay->getHeldItem());
		self::assertSame(VanillaItems::GOLD_INGOT()->getTypeId(), $loadedAllay->getHeldItem()->getTypeId());
		self::assertSame("00000000-0000-0000-0000-000000000002", $loadedAllay->getOwnerUuid());
		self::assertSame(1200, $loadedAllay->getDuplicationCooldownTicks());
	}
}
