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

namespace pocketmine\block;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\inventory\DispenserInventory;
use pocketmine\block\inventory\DropperInventory;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\tile\TileFactory;
use pocketmine\entity\Entity;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class DispenserDropperBlockTest extends TestCase{

	public function testBlockTypeIds() : void{
		self::assertSame(10828, BlockTypeIds::DISPENSER);
		self::assertSame(10829, BlockTypeIds::DROPPER);
	}

	public function testVanillaBlocksAccessors() : void{
		$dispenser = VanillaBlocks::DISPENSER();
		self::assertInstanceOf(Dispenser::class, $dispenser);
		self::assertSame(BlockTypeIds::DISPENSER, $dispenser->getTypeId());
		self::assertSame("Dispenser", $dispenser->getName());

		$dropper = VanillaBlocks::DROPPER();
		self::assertInstanceOf(Dropper::class, $dropper);
		self::assertSame(BlockTypeIds::DROPPER, $dropper->getTypeId());
		self::assertSame("Dropper", $dropper->getName());
	}

	public function testFacingAndPoweredState() : void{
		$dispenser = VanillaBlocks::DISPENSER();
		self::assertSame(Facing::NORTH, $dispenser->getFacing());
		self::assertFalse($dispenser->isPowered());

		$dispenser->setFacing(Facing::UP);
		self::assertSame(Facing::UP, $dispenser->getFacing());

		$dispenser->setPowered(true);
		self::assertTrue($dispenser->isPowered());

		$dropper = VanillaBlocks::DROPPER();
		self::assertSame(Facing::NORTH, $dropper->getFacing());
		self::assertFalse($dropper->isPowered());

		$dropper->setFacing(Facing::DOWN);
		self::assertSame(Facing::DOWN, $dropper->getFacing());

		$dropper->setPowered(true);
		self::assertTrue($dropper->isPowered());
	}

	public function testStringToItemParser() : void{
		$parser = StringToItemParser::getInstance();

		$dispenserItem = $parser->parse("dispenser");
		self::assertNotNull($dispenserItem);
		self::assertSame(BlockTypeIds::DISPENSER, $dispenserItem->getBlock()->getTypeId());

		$dropperItem = $parser->parse("dropper");
		self::assertNotNull($dropperItem);
		self::assertSame(BlockTypeIds::DROPPER, $dropperItem->getBlock()->getTypeId());
	}

	public function testTileFactoryRegistration() : void{
		$tileFactory = TileFactory::getInstance();
		$refClass = new ReflectionClass(TileFactory::class);
		$knownTilesProp = $refClass->getProperty("knownTiles");
		/** @var array<string, class-string> $knownTiles */
		$knownTiles = $knownTilesProp->getValue($tileFactory);

		self::assertArrayHasKey("Dispenser", $knownTiles);
		self::assertSame(TileDispenser::class, $knownTiles["Dispenser"]);
		self::assertArrayHasKey("Trap", $knownTiles);
		self::assertSame(TileDispenser::class, $knownTiles["Trap"]);
		self::assertArrayHasKey("minecraft:dispenser", $knownTiles);
		self::assertSame(TileDispenser::class, $knownTiles["minecraft:dispenser"]);

		self::assertArrayHasKey("Dropper", $knownTiles);
		self::assertSame(TileDropper::class, $knownTiles["Dropper"]);
		self::assertArrayHasKey("minecraft:dropper", $knownTiles);
		self::assertSame(TileDropper::class, $knownTiles["minecraft:dropper"]);
	}

	public function testInventorySizes() : void{
		$dispenserInv = new DispenserInventory(new Position(0, 0, 0, null));
		self::assertSame(9, $dispenserInv->getSize());

		$dropperInv = new DropperInventory(new Position(0, 0, 0, null));
		self::assertSame(9, $dropperInv->getSize());
	}

	public function testWindowTypes() : void{
		self::assertSame(6, WindowTypes::DISPENSER);
		self::assertSame(7, WindowTypes::DROPPER);
	}

	public static function containerKeys() : array{
		$cases = [];
		foreach(["Dispenser", "Dropper"] as $type){
			$cases[$type . " unlocked"] = [$type, null, "", true];
			$cases[$type . " empty key"] = [$type, "secret", "", false];
			$cases[$type . " wrong key"] = [$type, "secret", "wrong", false];
			$cases[$type . " matching key"] = [$type, "secret", "secret", true];
		}
		return $cases;
	}

	#[DataProvider("containerKeys")]
	public function testInteractionRespectsSavedContainerLock(string $type, ?string $lock, string $key, bool $opens) : void{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getTile", "removeTile"])->getMock();
		$nbt = CompoundTag::create()->setString("id", $type)->setInt("x", 8)->setInt("y", 64)->setInt("z", 8);
		if($lock !== null){
			$nbt->setString("Lock", $lock);
		}
		$tile = TileFactory::getInstance()->createFromData($world, $nbt);
		self::assertTrue($tile instanceof TileDispenser || $tile instanceof TileDropper);
		$world->method("getTile")->willReturn($tile);
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods(["setCurrentWindow"])->getMock();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->expects(self::exactly($opens ? 1 : 0))->method("setCurrentWindow")->with($tile->getInventory())->willReturn(true);
		$block = $type === "Dispenser" ? VanillaBlocks::DISPENSER() : VanillaBlocks::DROPPER();
		$block->position($world, 8, 64, 8);
		try{
			$item = VanillaItems::STICK()->setCustomName($key);
			self::assertTrue($block->onInteract($item, Facing::UP, Vector3::zero(), $player));
		}finally{
			$tile->close();
		}
	}
}
