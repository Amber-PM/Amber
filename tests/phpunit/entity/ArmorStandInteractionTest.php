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
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\event\entity\ArmorStandEquipEvent;
use pocketmine\event\entity\ArmorStandPoseChangeEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;
use function round;

final class ArmorStandInteractionTest extends TestCase{

	/** @var Entity[] */
	private array $createdEntities = [];

	public static function setUpBeforeClass() : void{
		\pocketmine\timings\Timings::init();
	}

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll();
		foreach($this->createdEntities as $entity){
			$this->markClosed($entity);
		}
		$this->createdEntities = [];
		parent::tearDown();
	}

	private function markClosed(Entity $entity) : void{
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
	}

	private function createArmorStand(int $initialPose = 0, bool $locked = false) : ArmorStand{
		$stand = (new ReflectionClass(ArmorStand::class))->newInstanceWithoutConstructor();
		$this->markClosed($stand);
		(new ReflectionProperty(Entity::class, "id"))->setValue($stand, 1);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($stand, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($stand, new EntitySizeInfo(1.975, 0.5));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($stand, new AttributeMap());
		(new ReflectionClass(ArmorStand::class))->getMethod("addAttributes")->invoke($stand);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($stand, new EffectManager($stand));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($stand, new ArmorInventory($stand));
		$world = $this->createMock(\pocketmine\world\World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->updateEntities = [];
		(new ReflectionProperty(Entity::class, "location"))->setValue($stand, new \pocketmine\entity\Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($stand, Vector3::zero());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($stand, false);
		$stand->setPose($initialPose);
		$stand->setLocked($locked);
		$this->createdEntities[] = $stand;
		return $stand;
	}

	private function createPlayer(bool $isSneaking = false, bool $isCreative = false, ?Item $heldItem = null, bool $isSpectator = false) : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["isSneaking", "isCreative", "isSpectator", "getInventory", "hasFiniteResources", "getViewers", "canInteract", "broadcastSound"])
			->getMock();
		$this->markClosed($player);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 2);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		$inventory = new PlayerInventory($player);
		if($heldItem !== null){
			$inventory->setItemInHand($heldItem);
		}
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, $inventory);
		$player->method("isSneaking")->willReturn($isSneaking);
		$player->method("isCreative")->willReturnCallback(function(bool $literal = false) use ($isCreative, $isSpectator) : bool{
			return $isCreative || (!$literal && $isSpectator);
		});
		$player->method("isSpectator")->willReturn($isSpectator);
		$player->method("getInventory")->willReturn($inventory);
		$player->method("hasFiniteResources")->willReturn(!$isCreative && !$isSpectator);
		$player->method("getViewers")->willReturn([]);
		$player->method("canInteract")->willReturn(true);
		$this->createdEntities[] = $player;
		return $player;
	}

	public function testSnappedYawCalculations() : void{
		$testCases = [
			[0.0, 0.0],
			[10.0, 0.0],
			[11.24, 0.0],
			[11.25, 22.5],
			[15.0, 22.5],
			[22.5, 22.5],
			[30.0, 22.5],
			[33.74, 22.5],
			[33.75, 45.0],
			[45.0, 45.0],
			[90.0, 90.0],
			[180.0, 180.0],
			[270.0, 270.0],
			[350.0, 360.0],
			[360.0, 360.0],
			[-10.0, -0.0],
			[-11.25, -22.5],
			[-15.0, -22.5],
			[-22.5, -22.5],
			[-35.0, -45.0],
		];

		foreach($testCases as [$yaw, $expectedSnapped]){
			$calculated = round($yaw / 22.5) * 22.5;
			self::assertEqualsWithDelta($expectedSnapped, $calculated, 0.0001, "Yaw $yaw should snap to $expectedSnapped");
		}
	}

	public function testPoseCyclingProgression() : void{
		$stand = $this->createArmorStand(0);
		$player = $this->createPlayer(isSneaking: true);

		// Cycle through 1 to 12 then back to 0
		for($expected = 1; $expected < ArmorStand::POSE_COUNT; ++$expected){
			$result = $stand->onInteract($player, Vector3::zero());
			self::assertTrue($result);
			self::assertSame($expected, $stand->getPose());
		}

		// 13th cycle wraps back to 0
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertSame(0, $stand->getPose());
	}

	public function testPoseChangeEventCancellation() : void{
		$stand = $this->createArmorStand(0);
		$player = $this->createPlayer(isSneaking: true);

		$listener = new RegisteredListener(
			function(ArmorStandPoseChangeEvent $ev) : void{
				$ev->cancel();
			},
			EventPriority::NORMAL,
			$this->createMock(Plugin::class),
			false,
			new TimingsHandler("pose cancel test")
		);
		HandlerListManager::global()->getListFor(ArmorStandPoseChangeEvent::class)->register($listener);

		$result = $stand->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertSame(0, $stand->getPose());
	}

	public function testPoseChangeEventModification() : void{
		$stand = $this->createArmorStand(0);
		$player = $this->createPlayer(isSneaking: true);

		$listener = new RegisteredListener(
			function(ArmorStandPoseChangeEvent $ev) : void{
				$ev->setNewPose(5);
			},
			EventPriority::NORMAL,
			$this->createMock(Plugin::class),
			false,
			new TimingsHandler("pose modify test")
		);
		HandlerListManager::global()->getListFor(ArmorStandPoseChangeEvent::class)->register($listener);

		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertSame(5, $stand->getPose());
	}

	public function testEquipmentSlotDetermination() : void{
		$stand = $this->createArmorStand();

		// Helmet -> Slot 0
		$playerHelmet = $this->createPlayer(heldItem: VanillaItems::IRON_HELMET());
		$result = $stand->onInteract($playerHelmet, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getArmorInventory()->getHelmet()->equalsExact(VanillaItems::IRON_HELMET()));
		self::assertTrue($playerHelmet->getInventory()->getItemInHand()->isNull());

		// Chestplate -> Slot 1
		$playerChest = $this->createPlayer(heldItem: VanillaItems::DIAMOND_CHESTPLATE());
		$result = $stand->onInteract($playerChest, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getArmorInventory()->getChestplate()->equalsExact(VanillaItems::DIAMOND_CHESTPLATE()));
		self::assertTrue($playerChest->getInventory()->getItemInHand()->isNull());

		// Leggings -> Slot 2
		$playerLegs = $this->createPlayer(heldItem: VanillaItems::GOLDEN_LEGGINGS());
		$result = $stand->onInteract($playerLegs, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getArmorInventory()->getLeggings()->equalsExact(VanillaItems::GOLDEN_LEGGINGS()));
		self::assertTrue($playerLegs->getInventory()->getItemInHand()->isNull());

		// Boots -> Slot 3
		$playerBoots = $this->createPlayer(heldItem: VanillaItems::CHAINMAIL_BOOTS());
		$result = $stand->onInteract($playerBoots, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getArmorInventory()->getBoots()->equalsExact(VanillaItems::CHAINMAIL_BOOTS()));
		self::assertTrue($playerBoots->getInventory()->getItemInHand()->isNull());

		// Sword (weapon) -> Slot 4 (Main Hand)
		$playerSword = $this->createPlayer(heldItem: VanillaItems::DIAMOND_SWORD());
		$result = $stand->onInteract($playerSword, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getMainHandItem()->equalsExact(VanillaItems::DIAMOND_SWORD()));
		self::assertTrue($playerSword->getInventory()->getItemInHand()->isNull());

		// Non-weapon item (Stick) -> Slot 4 (Main Hand)
		$playerStick = $this->createPlayer(heldItem: VanillaItems::STICK());
		$result = $stand->onInteract($playerStick, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getMainHandItem()->equalsExact(VanillaItems::STICK()));
		self::assertTrue($playerStick->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_SWORD()));
	}

	public function testEquipmentSwappingExistingArmor() : void{
		$stand = $this->createArmorStand();
		$stand->getArmorInventory()->setHelmet(VanillaItems::LEATHER_CAP());

		$player = $this->createPlayer(heldItem: VanillaItems::DIAMOND_HELMET());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($stand->getArmorInventory()->getHelmet()->equalsExact(VanillaItems::DIAMOND_HELMET()));
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::LEATHER_CAP()));
	}

	public function testEquipEventCancellation() : void{
		$stand = $this->createArmorStand();
		$player = $this->createPlayer(heldItem: VanillaItems::DIAMOND_CHESTPLATE());

		$listener = new RegisteredListener(
			function(ArmorStandEquipEvent $ev) : void{
				$ev->cancel();
			},
			EventPriority::NORMAL,
			$this->createMock(Plugin::class),
			false,
			new TimingsHandler("equip cancel test")
		);
		HandlerListManager::global()->getListFor(ArmorStandEquipEvent::class)->register($listener);

		$result = $stand->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($stand->getArmorInventory()->getChestplate()->isNull());
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_CHESTPLATE()));
	}

	public function testEmptyHandStrippingPriority() : void{
		$stand = $this->createArmorStand();
		$stand->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());
		$stand->getArmorInventory()->setChestplate(VanillaItems::DIAMOND_CHESTPLATE());
		$stand->getArmorInventory()->setLeggings(VanillaItems::DIAMOND_LEGGINGS());
		$stand->getArmorInventory()->setBoots(VanillaItems::DIAMOND_BOOTS());
		$stand->setMainHandItem(VanillaItems::DIAMOND_SWORD());

		$player = $this->createPlayer(); // empty hand

		// 1. Strip Head (Helmet)
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_HELMET()));
		self::assertTrue($stand->getArmorInventory()->getHelmet()->isNull());
		self::assertFalse($stand->getArmorInventory()->getChestplate()->isNull());

		// 2. Strip Chest (Chestplate)
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_CHESTPLATE()));
		self::assertTrue($stand->getArmorInventory()->getChestplate()->isNull());
		self::assertFalse($stand->getArmorInventory()->getLeggings()->isNull());

		// 3. Strip Legs (Leggings)
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_LEGGINGS()));
		self::assertTrue($stand->getArmorInventory()->getLeggings()->isNull());
		self::assertFalse($stand->getArmorInventory()->getBoots()->isNull());

		// 4. Strip Feet (Boots)
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_BOOTS()));
		self::assertTrue($stand->getArmorInventory()->getBoots()->isNull());
		self::assertFalse($stand->getMainHandItem()->isNull());

		// 5. Strip Main Hand (Sword)
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::DIAMOND_SWORD()));
		self::assertTrue($stand->getMainHandItem()->isNull());

		// 6. Nothing left to strip -> return false
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertTrue($player->getInventory()->getItemInHand()->isNull());
	}

	public function testEmptyHandPartialStrippingSkippingEmptySlots() : void{
		$stand = $this->createArmorStand();
		// Only boots and main hand equipped
		$stand->getArmorInventory()->setBoots(VanillaItems::IRON_BOOTS());
		$stand->setMainHandItem(VanillaItems::IRON_SWORD());

		$player = $this->createPlayer();

		// 1. Should strip Boots directly since Head, Chest, Legs are empty
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::IRON_BOOTS()));
		self::assertTrue($stand->getArmorInventory()->getBoots()->isNull());

		// 2. Should strip Main Hand
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($player->getInventory()->getItemInHand()->equalsExact(VanillaItems::IRON_SWORD()));
		self::assertTrue($stand->getMainHandItem()->isNull());

		// 3. Now completely empty
		$player->getInventory()->setItemInHand(VanillaItems::AIR());
		$result = $stand->onInteract($player, Vector3::zero());
		self::assertFalse($result);
	}

	public function testLockedArmorStandRejection() : void{
		$stand = $this->createArmorStand(initialPose: 2, locked: true);
		$stand->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());

		// Interaction while sneaking rejected
		$sneakingPlayer = $this->createPlayer(isSneaking: true);
		self::assertFalse($stand->onInteract($sneakingPlayer, Vector3::zero()));
		self::assertSame(2, $stand->getPose());

		// Equipment swap rejected
		$equipPlayer = $this->createPlayer(heldItem: VanillaItems::GOLDEN_CHESTPLATE());
		self::assertFalse($stand->onInteract($equipPlayer, Vector3::zero()));
		self::assertTrue($stand->getArmorInventory()->getChestplate()->isNull());
		self::assertTrue($equipPlayer->getInventory()->getItemInHand()->equalsExact(VanillaItems::GOLDEN_CHESTPLATE()));

		// Empty hand strip rejected
		$emptyPlayer = $this->createPlayer();
		self::assertFalse($stand->onInteract($emptyPlayer, Vector3::zero()));
		self::assertFalse($stand->getArmorInventory()->getHelmet()->isNull());

		// Attack rejected
		$damageEv = new EntityDamageEvent($stand, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 2.0);
		$stand->attack($damageEv);
		self::assertTrue($damageEv->isCancelled());
		self::assertSame(6.0, $stand->getHealth());
	}

	public function testDropsArray() : void{
		$stand = $this->createArmorStand();

		// Empty stand drops only the armor stand item
		$emptyDrops = $stand->getDrops();
		self::assertCount(1, $emptyDrops);
		self::assertTrue($emptyDrops[0]->equalsExact(VanillaItems::ARMOR_STAND()));

		// Stand with armor and held item
		$stand->getArmorInventory()->setHelmet(VanillaItems::IRON_HELMET());
		$stand->getArmorInventory()->setBoots(VanillaItems::DIAMOND_BOOTS());
		$stand->setMainHandItem(VanillaItems::DIAMOND_SWORD());

		$drops = $stand->getDrops();
		self::assertCount(4, $drops);

		$expected = [
			VanillaItems::ARMOR_STAND()->getTypeId(),
			VanillaItems::IRON_HELMET()->getTypeId(),
			VanillaItems::DIAMOND_BOOTS()->getTypeId(),
			VanillaItems::DIAMOND_SWORD()->getTypeId(),
		];
		$actual = array_map(fn(Item $i) => $i->getTypeId(), $drops);
		sort($expected);
		sort($actual);
		self::assertSame($expected, $actual);
	}

	public function testCreativePunchDespawnWithoutDrops() : void{
		$stand = $this->createArmorStand();
		$stand->getArmorInventory()->setChestplate(VanillaItems::DIAMOND_CHESTPLATE());
		$creativePlayer = $this->createPlayer(isCreative: true);

		$damageEv = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager"])
			->getMock();
		$damageEv->method("getDamager")->willReturn($creativePlayer);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv);

		self::assertTrue($damageEv->isCancelled());
		self::assertTrue($stand->isFlaggedForDespawn());
	}

	public function testNbtSanitizePoseOnLoad() : void{
		$stand = $this->createArmorStand();

		// Positive overflow wrapping: 15 % 13 = 2
		$nbt = CompoundTag::create()->setInt(ArmorStand::TAG_POSE, 15);
		$stand->readSaveData($nbt);
		self::assertSame(2, $stand->getPose());

		// Exact POSE_COUNT: 13 % 13 = 0
		$nbt = CompoundTag::create()->setInt(ArmorStand::TAG_POSE, 13);
		$stand->readSaveData($nbt);
		self::assertSame(0, $stand->getPose());

		// Multiple wrap: 27 % 13 = 1
		$nbt = CompoundTag::create()->setInt(ArmorStand::TAG_POSE, 27);
		$stand->readSaveData($nbt);
		self::assertSame(1, $stand->getPose());

		// Negative wrapping: -1 -> 12
		$nbt = CompoundTag::create()->setInt(ArmorStand::TAG_POSE, -1);
		$stand->readSaveData($nbt);
		self::assertSame(12, $stand->getPose());

		// Negative exact: -13 -> 0
		$nbt = CompoundTag::create()->setInt(ArmorStand::TAG_POSE, -13);
		$stand->readSaveData($nbt);
		self::assertSame(0, $stand->getPose());

		// Negative multiple: -14 -> 12
		$nbt = CompoundTag::create()->setInt(ArmorStand::TAG_POSE, -14);
		$stand->readSaveData($nbt);
		self::assertSame(12, $stand->getPose());
	}

	public function testItemOnInteractBlockNonUpFaceRejected() : void{
		$item = VanillaItems::ARMOR_STAND();
		$player = $this->createPlayer();
		$replaceBlock = (new ReflectionClass(Block::class))->newInstanceWithoutConstructor();
		$clickedBlock = (new ReflectionClass(Block::class))->newInstanceWithoutConstructor();
		$returned = [];

		foreach([Facing::DOWN, Facing::NORTH, Facing::SOUTH, Facing::EAST, Facing::WEST] as $face){
			$res = $item->onInteractBlock($player, $replaceBlock, $clickedBlock, $face, Vector3::zero(), $returned);
			self::assertSame(ItemUseResult::NONE, $res);
		}
	}

	public function testItemOnInteractBlockNonSolidSupportRejected() : void{
		$item = VanillaItems::ARMOR_STAND();
		$player = $this->createPlayer();
		$replaceBlock = (new ReflectionClass(Block::class))->newInstanceWithoutConstructor();

		$clickedBlock = $this->createMock(Block::class);
		$clickedBlock->method("isSolid")->willReturn(false);

		$returned = [];
		$res = $item->onInteractBlock($player, $replaceBlock, $clickedBlock, Facing::UP, Vector3::zero(), $returned);
		self::assertSame(ItemUseResult::NONE, $res);
	}

	public function testItemOnInteractBlockBlockCollisionRejected() : void{
		$item = VanillaItems::ARMOR_STAND();
		$player = $this->createPlayer();

		$clickedBlock = $this->createMock(Block::class);
		$clickedBlock->method("isSolid")->willReturn(true);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockCollisionBoxes")->willReturn([new AxisAlignedBB(0, 64, 0, 1, 65, 1)]);
		$world->method("getCollidingEntities")->willReturn([]);

		$pos = new Position(0, 64, 0, $world);
		$replaceBlock = $this->createMock(Block::class);
		(new ReflectionProperty(Block::class, "position"))->setValue($replaceBlock, $pos);

		$returned = [];
		$res = $item->onInteractBlock($player, $replaceBlock, $clickedBlock, Facing::UP, Vector3::zero(), $returned);
		self::assertSame(ItemUseResult::NONE, $res);
	}

	public function testItemOnInteractBlockEntityCollisionRejected() : void{
		$item = VanillaItems::ARMOR_STAND();
		$player = $this->createPlayer();

		$clickedBlock = $this->createMock(Block::class);
		$clickedBlock->method("isSolid")->willReturn(true);

		$existingEntity = $this->createArmorStand();
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockCollisionBoxes")->willReturn([]);
		$world->method("getCollidingEntities")->willReturn([$existingEntity]);

		$pos = new Position(0, 64, 0, $world);
		$replaceBlock = $this->createMock(Block::class);
		(new ReflectionProperty(Block::class, "position"))->setValue($replaceBlock, $pos);

		$returned = [];
		$res = $item->onInteractBlock($player, $replaceBlock, $clickedBlock, Facing::UP, Vector3::zero(), $returned);
		self::assertSame(ItemUseResult::NONE, $res);
	}

	public function testEmptyHandStripEventCancellation() : void{
		$stand = $this->createArmorStand();
		$stand->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());

		$player = $this->createPlayer(); // empty hand

		$listener = new RegisteredListener(
			function(ArmorStandEquipEvent $ev) : void{
				$ev->cancel();
			},
			EventPriority::NORMAL,
			$this->createMock(Plugin::class),
			false,
			new TimingsHandler("strip cancel test")
		);
		HandlerListManager::global()->getListFor(ArmorStandEquipEvent::class)->register($listener);

		$result = $stand->onInteract($player, Vector3::zero());
		self::assertFalse($result);
		self::assertFalse($stand->getArmorInventory()->getHelmet()->isNull());
		self::assertTrue($player->getInventory()->getItemInHand()->isNull());
	}

	public function testStartDeathAnimationFlagsForDespawn() : void{
		$stand = $this->createArmorStand();
		$method = (new ReflectionClass(ArmorStand::class))->getMethod("startDeathAnimation");
		$method->invoke($stand);

		self::assertTrue($stand->isFlaggedForDespawn());
	}

	public function testWearableBlocksEquipToHeadSlot() : void{
		$stand = $this->createArmorStand();

		// Carved Pumpkin -> Head slot
		$pumpkinPlayer = $this->createPlayer(heldItem: VanillaBlocks::CARVED_PUMPKIN()->asItem());
		$result = $stand->onInteract($pumpkinPlayer, Vector3::zero());
		self::assertTrue($result);
		self::assertTrue($pumpkinPlayer->getInventory()->getItemInHand()->isNull());
		self::assertTrue($stand->getArmorInventory()->getHelmet()->equalsExact(VanillaBlocks::CARVED_PUMPKIN()->asItem()));

		// Mob Head / Skull -> Swaps with head slot
		$skullPlayer = $this->createPlayer(heldItem: VanillaBlocks::MOB_HEAD()->asItem());
		$result2 = $stand->onInteract($skullPlayer, Vector3::zero());
		self::assertTrue($result2);
		self::assertTrue($stand->getArmorInventory()->getHelmet()->equalsExact(VanillaBlocks::MOB_HEAD()->asItem()));
		self::assertTrue($skullPlayer->getInventory()->getItemInHand()->equalsExact(VanillaBlocks::CARVED_PUMPKIN()->asItem()));
	}

	public function testAttackAndKnockbackApplyZeroKnockback() : void{
		$stand = $this->createArmorStand();
		$player = $this->createPlayer();
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, new \pocketmine\entity\Location(1.0, 0.0, 1.0, $stand->getWorld(), 0.0, 0.0));

		$damageEv = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->setConstructorArgs([$player, $stand, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 2.0])
			->onlyMethods(["getDamager"])
			->getMock();
		$damageEv->method("getDamager")->willReturn($player);

		$stand->attack($damageEv);

		self::assertEquals(0.0, $stand->getMotion()->x);
		self::assertEquals(0.0, $stand->getMotion()->y);
		self::assertEquals(0.0, $stand->getMotion()->z);

		$stand->knockBack(1.0, 1.0, 1.0, 1.0);
		self::assertEquals(0.0, $stand->getMotion()->x);
		self::assertEquals(0.0, $stand->getMotion()->y);
		self::assertEquals(0.0, $stand->getMotion()->z);
	}

	public function testDamageArmorDoesNotDegradeEquippedArmor() : void{
		$stand = $this->createArmorStand();
		$helmet = VanillaItems::DIAMOND_HELMET();
		$stand->getArmorInventory()->setHelmet($helmet);

		$stand->damageArmor(10.0);

		$currentHelmet = $stand->getArmorInventory()->getHelmet();
		self::assertInstanceOf(Durable::class, $currentHelmet);
		self::assertSame(0, $currentHelmet->getDamage());
	}

	public function testSpectatorInteractionRejected() : void{
		$stand = $this->createArmorStand();
		$spectator = $this->createPlayer(isSpectator: true);

		self::assertFalse($stand->onInteract($spectator, Vector3::zero()));
	}

	public function testAlreadyCancelledCreativePunchDoesNotDespawn() : void{
		$stand = $this->createArmorStand();
		$creativePlayer = $this->createPlayer(isCreative: true);

		$damageEv = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager", "isCancelled"])
			->getMock();
		$damageEv->method("getDamager")->willReturn($creativePlayer);
		$damageEv->method("isCancelled")->willReturn(true);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv);
		self::assertFalse($stand->isFlaggedForDespawn());
	}

	public function testSpectatorPunchDoesNotDespawn() : void{
		$stand = $this->createArmorStand();
		$spectator = $this->createPlayer(isSpectator: true);

		$damageEv = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager"])
			->getMock();
		$damageEv->method("getDamager")->willReturn($spectator);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv);
		self::assertFalse($stand->isFlaggedForDespawn());
	}

	public function testNameTagLockedRenamingProtection() : void{
		$stand = $this->createArmorStand(locked: true);
		self::assertFalse($stand->canBeRenamed());

		$stand->setLocked(false);
		self::assertTrue($stand->canBeRenamed());
	}

	public function testNameTagWithCustomNameDoesNotTriggerEquip() : void{
		$stand = $this->createArmorStand();
		$nameTag = VanillaItems::NAME_TAG()->setCustomName("Display Stand");
		$player = $this->createPlayer(heldItem: $nameTag);

		self::assertFalse($stand->onInteract($player, Vector3::zero()));
		self::assertTrue($stand->getMainHandItem()->isNull());
	}

	public function testSurvivalWeaponOneHitBreak() : void{
		$stand = $this->createArmorStand();
		$player = $this->createPlayer(heldItem: VanillaItems::DIAMOND_SWORD());

		$damageEv = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager", "call"])
			->getMock();
		$damageEv->method("getDamager")->willReturn($player);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv);
		self::assertFalse($stand->isAlive());
	}

	public function testSurvivalFistTwoHitBreak() : void{
		$stand = $this->createArmorStand();
		$player = $this->createPlayer(); // bare fist

		// 1st punch wobbles and remains alive
		$damageEv1 = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager", "call"])
			->getMock();
		$damageEv1->method("getDamager")->willReturn($player);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv1, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv1);
		self::assertTrue($stand->isAlive());

		// 2nd punch within 20 ticks breaks the stand
		$damageEv2 = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager", "call"])
			->getMock();
		$damageEv2->method("getDamager")->willReturn($player);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv2, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv2);
		self::assertFalse($stand->isAlive());
	}

	public function testExplosionDropsArmorAndHeldItemButNotStandItem() : void{
		$stand = $this->createArmorStand();
		$stand->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());
		$stand->setMainHandItem(VanillaItems::DIAMOND_SWORD());

		$explosionEv = $this->getMockBuilder(EntityDamageEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getCause", "call"])
			->getMock();
		$explosionEv->method("getCause")->willReturn(EntityDamageEvent::CAUSE_BLOCK_EXPLOSION);

		$stand->attack($explosionEv);
		self::assertFalse($stand->isAlive());

		$drops = $stand->getDrops();
		$dropIds = array_map(fn(Item $i) => $i->getTypeId(), $drops);
		self::assertNotContains(VanillaItems::ARMOR_STAND()->getTypeId(), $dropIds);
		self::assertContains(VanillaItems::DIAMOND_HELMET()->getTypeId(), $dropIds);
		self::assertContains(VanillaItems::DIAMOND_SWORD()->getTypeId(), $dropIds);
	}

	public function testThornsDoesNotDegradeEquippedArmorOnStand() : void{
		$stand = $this->createArmorStand();
		$chestplate = VanillaItems::DIAMOND_CHESTPLATE();
		$chestplate->addEnchantment(new EnchantmentInstance(VanillaEnchantments::THORNS(), 3));
		$stand->getArmorInventory()->setChestplate($chestplate);

		$player = $this->createPlayer();
		$damageEv = $this->getMockBuilder(EntityDamageByEntityEvent::class)
			->disableOriginalConstructor()
			->onlyMethods(["getDamager", "call"])
			->getMock();
		$damageEv->method("getDamager")->willReturn($player);
		(new ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($damageEv, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

		$stand->attack($damageEv);

		/** @var Durable $equipped */
		$equipped = $stand->getArmorInventory()->getChestplate();
		self::assertSame(0, $equipped->getDamage());
	}

	public function testSpectatorInteractEntityRejectsNameTagRenaming() : void{
		$stand = $this->createArmorStand();
		$nameTag = VanillaItems::NAME_TAG()->setCustomName("Display Stand");
		$spectator = $this->createPlayer(heldItem: $nameTag, isSpectator: true);

		$result = $spectator->interactEntity($stand, Vector3::zero());
		self::assertFalse($result, "Player::interactEntity() must return false for spectator");
		self::assertSame("", $stand->getNameTag(), "Armor stand name must not be changed by spectator");
		self::assertSame(1, $spectator->getInventory()->getItemInHand()->getCount(), "Spectator NameTag must not be consumed");
		self::assertTrue($stand->getMainHandItem()->isNull(), "Spectator interaction must not equip the stand");
	}

	public function testSurvivalInteractEntityAppliesNameTagRenaming() : void{
		$stand = $this->createArmorStand();
		$nameTag = VanillaItems::NAME_TAG()->setCustomName("Display Stand");
		$player = $this->createPlayer(heldItem: $nameTag, isSpectator: false);

		$result = $player->interactEntity($stand, Vector3::zero());
		self::assertTrue($result, "Player::interactEntity() should return true when item handles entity interaction");
		self::assertSame("Display Stand", $stand->getNameTag(), "Armor stand name should be updated");
		self::assertSame(0, $player->getInventory()->getItemInHand()->getCount(), "Survival NameTag should be consumed");
		self::assertTrue($stand->getMainHandItem()->isNull(), "Stand main hand must not be equipped after renaming");
	}

	public function testPlayerShotProjectileBreaksStandRegardlessOfHeldItem() : void{
		$arrow = $this->createMock(Entity::class);
		$this->markClosed($arrow);
		$arrow->method("getId")->willReturn(10);

		// Case 1: Player holding AIR
		$stand1 = $this->createArmorStand();
		$playerHoldingAir = $this->createPlayer(heldItem: VanillaItems::AIR());
		$event1 = new EntityDamageByChildEntityEvent($playerHoldingAir, $arrow, $stand1, EntityDamageEvent::CAUSE_PROJECTILE, 4.0);
		$stand1->attack($event1);
		self::assertFalse($stand1->isAlive(), "Stand should be destroyed in 1 hit by arrow when player holds AIR");
		$dropIds1 = array_map(fn(Item $i) => $i->getTypeId(), $stand1->getDrops());
		self::assertContains(VanillaItems::ARMOR_STAND()->getTypeId(), $dropIds1);

		// Case 2: Player holding Diamond Sword
		$stand2 = $this->createArmorStand();
		$playerHoldingSword = $this->createPlayer(heldItem: VanillaItems::DIAMOND_SWORD());
		$event2 = new EntityDamageByChildEntityEvent($playerHoldingSword, $arrow, $stand2, EntityDamageEvent::CAUSE_PROJECTILE, 4.0);
		$stand2->attack($event2);
		self::assertFalse($stand2->isAlive(), "Stand should be destroyed in 1 hit by arrow when player holds Sword");
		$dropIds2 = array_map(fn(Item $i) => $i->getTypeId(), $stand2->getDrops());
		self::assertContains(VanillaItems::ARMOR_STAND()->getTypeId(), $dropIds2);

		// Case 3: Player in Creative mode
		$stand3 = $this->createArmorStand();
		$creativePlayer = $this->createPlayer(isCreative: true);
		$event3 = new EntityDamageByChildEntityEvent($creativePlayer, $arrow, $stand3, EntityDamageEvent::CAUSE_PROJECTILE, 4.0);
		$stand3->attack($event3);
		self::assertFalse($stand3->isAlive(), "Stand should be destroyed in 1 hit by arrow when shooter is creative");
		$dropIds3 = array_map(fn(Item $i) => $i->getTypeId(), $stand3->getDrops());
		self::assertContains(VanillaItems::ARMOR_STAND()->getTypeId(), $dropIds3);
	}

	public function testPlayerShotProjectileEventCancellation() : void{
		$stand = $this->createArmorStand();
		$arrow = $this->createMock(Entity::class);
		$this->markClosed($arrow);
		$arrow->method("getId")->willReturn(11);
		$player = $this->createPlayer(heldItem: VanillaItems::DIAMOND_SWORD());

		$event = new EntityDamageByChildEntityEvent($player, $arrow, $stand, EntityDamageEvent::CAUSE_PROJECTILE, 4.0);
		$event->cancel();

		$stand->attack($event);
		self::assertTrue($stand->isAlive(), "Cancelled projectile event must not destroy the stand");
	}
}
