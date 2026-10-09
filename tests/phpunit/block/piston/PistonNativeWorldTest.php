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

namespace pocketmine\block\piston;

use PHPUnit\Framework\TestCase;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use pocketmine\world\WorldTimings;
use function array_map;
use function array_sum;
use function array_values;
use function count;
use function spl_object_id;

final class NativePistonCollisionEntity extends \pocketmine\entity\Entity{
	public int $pistonMoves = 0;
	public function __destruct(){}
	public static function getNetworkTypeId() : string{ return "minecraft:test"; }
	protected function getInitialSizeInfo() : \pocketmine\entity\EntitySizeInfo{ return new \pocketmine\entity\EntitySizeInfo(1.8, 0.6); }
	protected function getInitialDragMultiplier() : float{ return 0.02; }
	protected function getInitialGravity() : float{ return 0.08; }
	protected function checkBlockIntersections() : void{}
	protected function updateMovement(bool $teleport = false) : void{
		if($teleport){ throw new \LogicException("Piston movement teleported an entity"); }
	}
	public function initialize(World $world, \pocketmine\math\Vector3 $pos, int $id) : void{
		$this->closed = false;
		$this->id = $id;
		$this->size = $this->getInitialSizeInfo();
		$this->location = \pocketmine\entity\Location::fromObject($pos, $world);
		$this->lastLocation = clone $this->location;
		$this->boundingBox = new \pocketmine\math\AxisAlignedBB($pos->x - 0.3, $pos->y, $pos->z - 0.3, $pos->x + 0.3, $pos->y + 1.8, $pos->z + 0.3);
		$this->motion = $this->lastMotion = new \pocketmine\math\Vector3(0, 0, 0);
	}
	public function moveByPiston(\pocketmine\math\Vector3 $offset) : void{
		++$this->pistonMoves;
		parent::moveByPiston($offset);
	}
}

final class PistonNativeWorldTest extends TestCase{

	private array $worlds = [];
	private array $closeListeners = [];

	protected function tearDown() : void{
		foreach($this->closeListeners as $listener){
			\pocketmine\event\HandlerListManager::global()->getListFor(\pocketmine\event\inventory\InventoryCloseEvent::class)->unregister($listener);
		}
		$this->closeListeners = [];
		foreach($this->worlds as $world){
			foreach($world->getEntities() as $entity){
				$entity->close();
			}
			foreach([0, 1] as $x){
				foreach($world->getChunk($x, 0)->getTiles() as $tile){
					$tile->close();
				}
			}
		}
		$this->worlds = [];
	}

	private function world() : World{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()
			->onlyMethods(["updateAllLight", "scheduleDelayedBlockUpdate", "addSound", "getNearbyEntities", "getFolderName", "getDisplayName", "addParticle", "dropItem", "isSpawnChunk", "getRealBlockSkyLightAt", "getSunAnglePercentage"])->getMock();
		$world->method("getFolderName")->willReturn("piston-regression");
		$world->method("getDisplayName")->willReturn("piston-regression");
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("isSpawnChunk")->willReturn(false);
		$world->method("getRealBlockSkyLightAt")->willReturn(15);
		$world->method("getSunAnglePercentage")->willReturn(0.0);
		foreach([
			"minY" => -64, "maxY" => 320, "server" => $this->createMock(\pocketmine\Server::class), "logger" => $this->createMock(\Logger::class),
			"blockStateRegistry" => RuntimeBlockStateRegistry::getInstance(),
			"chunks" => [World::chunkHash(0, 0) => new Chunk([], true), World::chunkHash(1, 0) => new Chunk([], true)],
			"neighbourBlockUpdateQueue" => new \SplQueue()
		] as $name => $value){
			(new \ReflectionProperty(World::class, $name))->setValue($world, $value);
		}
		$world->timings = new WorldTimings($world);
		$this->worlds[] = $world;
		return $world;
	}

	private function finishMovement(World $world, \pocketmine\math\Vector3 $pos) : void{
		$arm = $world->getTile($pos);
		for($tick = 0; $tick < 2 && $arm->isMoving(); ++$tick){
			$world->getBlock($pos)->onScheduledUpdate();
		}
		self::assertFalse($arm->isMoving());
	}

	private function networkViewer(\pocketmine\inventory\Inventory $inventory, int $windowId = 12) : array{
		$player = $this->getMockBuilder(\pocketmine\player\Player::class)->disableOriginalConstructor()->onlyMethods(["getNetworkSession"])->getMock();
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		$session = $this->createMock(\pocketmine\network\mcpe\NetworkSession::class);
		$connected = (object) ["value" => true];
		$session->method("isConnected")->willReturnCallback(static fn() => $connected->value);
		$session->method("getLogger")->willReturn($this->createMock(\Logger::class));
		$session->method("sendDataPacket")->willReturn(true);
		$manager = (new \ReflectionClass(\pocketmine\network\mcpe\InventoryManager::class))->newInstanceWithoutConstructor();
		foreach(["session" => $session, "player" => $player, "lastInventoryNetworkId" => $windowId, "networkIdToInventoryMap" => [$windowId => $inventory]] as $name => $value){
			(new \ReflectionProperty(\pocketmine\network\mcpe\InventoryManager::class, $name))->setValue($manager, $value);
		}
		$session->method("getInvManager")->willReturn($manager);
		$player->method("getNetworkSession")->willReturn($session);
		foreach(["logger" => $this->createMock(\Logger::class), "currentWindow" => $inventory, "craftingGrid" => new \pocketmine\inventory\PlayerCraftingInventory($player), "cursorInventory" => new \pocketmine\inventory\PlayerCursorInventory($player)] as $name => $value){
			(new \ReflectionProperty(\pocketmine\player\Player::class, $name))->setValue($player, $value);
		}
		$inventory->onOpen($player);
		return [$player, $manager, $connected];
	}

	public function testMissingCloseAckDisconnectsClientAndReleasesPiston() : void{
		$world = $this->world();
		$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(9, 64, 8, VanillaBlocks::CHEST());
		[$viewer, $manager, $connected] = $this->networkViewer($world->getTileAt(9, 64, 8)->getInventory());
		$viewer->getNetworkSession()->method("disconnect")->willReturnCallback(static function() use ($connected) : void{ $connected->value = false; });
		self::assertFalse($world->getBlockAt(8, 64, 8)->extend());
		(new \ReflectionProperty($manager, "pendingCloseDeadline"))->setValue($manager, 0);
		$manager->onClientRemoveWindow(99);
		$manager->flushPendingUpdates();
		self::assertFalse($connected->value);
		self::assertTrue($world->getBlockAt(8, 64, 8)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(8, 64, 8));
	}

	public function testClosingDeferredWindowCancelsItsOpenCallback() : void{
		$world = $this->world();
		$world->setBlockAt(9, 64, 8, VanillaBlocks::CHEST());
		[$viewer, $manager] = $this->networkViewer($world->getTileAt(9, 64, 8)->getInventory());
		$viewer->removeCurrentWindow();
		$opened = false;
		(new \ReflectionProperty($manager, "pendingOpenWindowCallback"))->setValue($manager, static function() use (&$opened) : void{ $opened = true; });
		$manager->onCurrentWindowRemove();
		$manager->onClientRemoveWindow(12);
		self::assertFalse($opened);
	}

	public function testOpenDoubleChestWaitsForEveryCloseAckBeforePushAndPull() : void{
		$world = $this->world();
		$world->setBlockAt(8, 64, 8, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		foreach([9, 10] as $x){
			$world->setBlockAt($x, 64, 8, VanillaBlocks::CHEST()->setFacing(Facing::SOUTH));
		}
		self::assertTrue($world->getTileAt(9, 64, 8)->pairWith($world->getTileAt(10, 64, 8)));
		for($slot = 0; $slot < 27; ++$slot){
			$world->getTileAt(9, 64, 8)->getRealInventory()->setItem($slot, VanillaItems::DIAMOND()->setCount($slot + 1));
			$world->getTileAt(10, 64, 8)->getRealInventory()->setItem($slot, VanillaItems::GOLD_INGOT()->setCount($slot + 1));
		}
		foreach([true, false] as $extending){
			$x = $extending ? 9 : 10;
			$near = $world->getTileAt($x, 64, 8);
			$far = $world->getTileAt($x + 1, 64, 8);
			if(!$near->isPaired()){
				self::assertTrue($near->pairWith($far));
			}
			$inventory = $near->getInventory();
			[$first, $firstManager] = $this->networkViewer($inventory, 12);
			[$second, $secondManager] = $this->networkViewer($inventory, 13);
			$piston = $world->getBlockAt(8, 64, 8);
			self::assertFalse($extending ? $piston->extend() : $piston->retract());
			self::assertNull($first->getCurrentWindow());
			self::assertNull($second->getCurrentWindow());
			self::assertSame($near, $world->getTileAt($x, 64, 8));
			self::assertSame($far, $world->getTileAt($x + 1, 64, 8));
			self::assertTrue($near->isPaired());
			self::assertFalse($world->getTileAt(8, 64, 8)->isMoving());
			$firstManager->onClientRemoveWindow(12);
			for($retry = 0; $retry < 3; ++$retry){
				self::assertFalse($extending ? $piston->extend() : $piston->retract());
			}
			$secondManager->onClientRemoveWindow(99);
			self::assertFalse($extending ? $piston->extend() : $piston->retract());
			$secondManager->onClientRemoveWindow(13);
			self::assertTrue($extending ? $piston->extend() : $piston->retract());
			$this->finishMovement($world, new \pocketmine\math\Vector3(8, 64, 8));
			foreach([[$extending ? 10 : 9, VanillaItems::DIAMOND()], [11, VanillaItems::GOLD_INGOT()]] as [$dest, $item]){
				for($slot = 0; $slot < 27; ++$slot){
					self::assertTrue($world->getTileAt($dest, 64, 8)->getRealInventory()->getItem($slot)->equalsExact($item->setCount($slot + 1)));
				}
			}
		}
	}

	public function testDeferredContainerMovementRechecksPowerAndObstructions() : void{
		foreach(["ack", "disconnect", "unpowered", "obstructed"] as $mode){
			$world = $this->world();
			$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 2000);
			(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
			$world->setBlockAt(8, 64, 8, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(9, 64, 8, VanillaBlocks::CHEST());
			$chest = $world->getTileAt(9, 64, 8);
			$item = VanillaItems::DIAMOND()->setCount(37);
			$chest->getRealInventory()->setItem(26, $item);
			[$viewer, $manager, $connected] = $this->networkViewer($chest->getInventory());
			$world->setBlockAt(8, 63, 8, VanillaBlocks::REDSTONE());
			$world->getBlockAt(8, 64, 8)->onRedstoneUpdate($engine);
			for($tick = 1; $tick <= 3; ++$tick){
				$engine->tick($tick);
				self::assertSame($chest, $world->getTileAt(9, 64, 8));
				self::assertFalse($world->getTileAt(8, 64, 8)->isMoving());
			}
			self::assertNull($viewer->getCurrentWindow());
			if($mode === "unpowered"){
				$world->setBlockAt(8, 63, 8, VanillaBlocks::AIR());
			}elseif($mode === "obstructed"){
				$world->setBlockAt(10, 64, 8, VanillaBlocks::OBSIDIAN());
			}
			if($mode === "disconnect"){
				$connected->value = false;
			}else{
				$manager->onClientRemoveWindow(12);
			}
			for($tick = 4; $tick <= 8; ++$tick){
				$engine->tick($tick);
			}
			$moved = $mode === "ack" || $mode === "disconnect";
			self::assertSame($moved, $world->getBlockAt(8, 64, 8)->isExtended());
			self::assertFalse($world->getTileAt(8, 64, 8)->isMoving());
			self::assertTrue($world->getTileAt($moved ? 10 : 9, 64, 8)->getRealInventory()->getItem(26)->equalsExact($item));
		}
	}

	public function testAnotherPistonCannotBypassThePairedChestCloseWait() : void{
		$world = $this->world();
		$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(10, 64, 9, VanillaBlocks::PISTON()->setFacing(Facing::NORTH));
		foreach([9, 10] as $x){
			$world->setBlockAt($x, 64, 8, VanillaBlocks::CHEST()->setFacing(Facing::SOUTH));
		}
		$near = $world->getTileAt(9, 64, 8);
		$far = $world->getTileAt(10, 64, 8);
		self::assertTrue($near->pairWith($far));
		$item = VanillaItems::GOLD_INGOT()->setCount(41);
		$far->getRealInventory()->setItem(26, $item);
		[$viewer, $manager] = $this->networkViewer($near->getInventory());
		self::assertFalse($world->getBlockAt(8, 64, 8)->extend());
		self::assertFalse($world->getBlockAt(10, 64, 9)->extend());
		self::assertSame($far, $world->getTileAt(10, 64, 8));
		self::assertNull($viewer->getCurrentWindow());
		$manager->onClientRemoveWindow(12);
		self::assertTrue($world->getBlockAt(10, 64, 9)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(10, 64, 9));
		self::assertTrue($world->getTileAt(10, 64, 7)->getRealInventory()->getItem(26)->equalsExact($item));
		self::assertSame($near, $world->getTileAt(9, 64, 8));
	}

	public function testNativeChunkUnloadAndReloadResumesPushAndPullInEitherOrder() : void{
		foreach([[0, 1], [1, 0]] as $order){
			$world = $this->world();
			$saved = [];
			$loaded = [];
			$provider = $this->createMock(\pocketmine\world\format\io\WritableWorldProvider::class);
			$provider->method("saveChunk")->willReturnCallback(function(int $x, int $z, \pocketmine\world\format\io\ChunkData $data, int $flags) use (&$saved) : void{
				$serializer = new \pocketmine\nbt\LittleEndianNbtSerializer();
				$tiles = array_map(fn($tag) => $serializer->read($serializer->write(new \pocketmine\nbt\TreeRoot($tag)))->mustGetCompoundTag(), $data->getTileNBT());
				$saved[World::chunkHash($x, $z)] = new \pocketmine\world\format\io\ChunkData($data->getSubChunks(), $data->isPopulated(), $data->getEntityNBT(), $tiles);
			});
			$provider->method("loadChunk")->willReturnCallback(function(int $x, int $z) use (&$saved, &$loaded) : ?\pocketmine\world\format\io\LoadedChunkData{
				$loaded[] = [$x, $z];
				return isset($saved[$hash = World::chunkHash($x, $z)]) ? new \pocketmine\world\format\io\LoadedChunkData($saved[$hash], false, 0) : null;
			});
			(new \ReflectionProperty(World::class, "provider"))->setValue($world, $provider);
			$world->setBlockAt(14, 64, 1, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
			$item = VanillaItems::DIAMOND()->setCount(37)->setCustomName("chunk roundtrip");
			$world->getTileAt(15, 64, 1)->getInventory()->setItem(26, $item);
			foreach([true, false] as $extending){
				$piston = $world->getBlockAt(14, 64, 1);
				self::assertTrue($extending ? $piston->extend() : $piston->retract());
				$piston->onScheduledUpdate();
				$oldArm = $world->getTileAt(14, 64, 1);
				self::assertSame(0.5, $oldArm->getProgress());
				self::assertTrue($world->unloadChunk(0, 0));
				self::assertTrue($world->unloadChunk(1, 0));
				self::assertTrue($oldArm->isClosed());
				self::assertSame([], (new \ReflectionProperty(World::class, "movingBlocksByChunk"))->getValue($world));
				$before = count($loaded);
				self::assertNotNull($world->loadChunk($order[0], 0));
				foreach($world->getChunk($order[0], 0)->getTiles() as $tile){
					$world->getBlock($tile->getPosition())->onScheduledUpdate();
				}
				self::assertFalse($world->isChunkLoaded($order[1], 0));
				self::assertCount($before + 1, $loaded);
				self::assertNotNull($world->loadChunk($order[1], 0));
				$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
				$chest = $world->getTileAt($extending ? 16 : 15, 64, 1);
				self::assertInstanceOf(Chest::class, $chest);
				self::assertTrue($chest->getInventory()->getItem(26)->equalsExact($item));
				self::assertCount($before + 2, $loaded);
			}
		}
	}

	public function testMaximumStickyPullCanReloadItsFarthestAttachment() : void{
		$world = $this->world();
		$world->setBlockAt(1, 64, 8, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		for($x = 2; $x <= 13; ++$x){
			$world->setBlockAt($x, 64, 8, VanillaBlocks::SLIME());
		}
		self::assertTrue($world->getBlockAt(1, 64, 8)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(1, 64, 8));
		self::assertTrue($world->getBlockAt(1, 64, 8)->retract());
		$arm = $world->getTileAt(1, 64, 8);
		$saved = $arm->saveNBT();
		$arm->close();
		$arm = TileFactory::getInstance()->createFromData($world, $saved);
		self::assertInstanceOf(\pocketmine\block\tile\PistonArm::class, $arm);
		$world->addTile($arm);
		$this->finishMovement($world, new \pocketmine\math\Vector3(1, 64, 8));
		for($x = 2; $x <= 13; ++$x){
			self::assertSame(VanillaBlocks::SLIME()->getStateId(), $world->getBlockAt($x, 64, 8)->getStateId());
		}
		self::assertSame(VanillaBlocks::AIR()->getStateId(), $world->getBlockAt(14, 64, 8)->getStateId());
	}

	private function addCollisionEntity(World $world, \pocketmine\math\Vector3 $position, int $id) : NativePistonCollisionEntity{
		$entity = (new \ReflectionClass(NativePistonCollisionEntity::class))->newInstanceWithoutConstructor();
		$entity->initialize($world, $position, $id);
		$property = new \ReflectionProperty(World::class, "entitiesByChunk");
		$chunks = $property->getValue($world);
		$chunks[World::chunkHash($position->getFloorX() >> 4, $position->getFloorZ() >> 4)][$id] = $entity;
		$property->setValue($world, $chunks);
		return $entity;
	}

	private function onInventoryClose(\pocketmine\inventory\Inventory $inventory, \Closure $callback) : void{
		$player = $this->getMockBuilder(\pocketmine\player\Player::class)->disableOriginalConstructor()->onlyMethods(["getNetworkSession"])->getMock();
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		$session = $this->createMock(\pocketmine\network\mcpe\NetworkSession::class);
		$session->method("getInvManager")->willReturn(null);
		$player->method("getNetworkSession")->willReturn($session);
		foreach(["logger" => $this->createMock(\Logger::class), "currentWindow" => $inventory, "craftingGrid" => new \pocketmine\inventory\PlayerCraftingInventory($player), "cursorInventory" => new \pocketmine\inventory\PlayerCursorInventory($player)] as $name => $value){
			(new \ReflectionProperty(\pocketmine\player\Player::class, $name))->setValue($player, $value);
		}
		$inventory->onOpen($player);
		$listener = new \pocketmine\event\RegisteredListener($callback, \pocketmine\event\EventPriority::NORMAL, $this->createMock(\pocketmine\plugin\Plugin::class), true, new \pocketmine\timings\TimingsHandler("piston close event regression"));
		\pocketmine\event\HandlerListManager::global()->getListFor(\pocketmine\event\inventory\InventoryCloseEvent::class)->register($listener);
		$this->closeListeners[] = $listener;
	}

	public function testNativePistonPushesInStepsAndRespectsWalls() : void{
		foreach([false, true] as $wall){
			$world = $this->world();
			$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(9, 64, 8, VanillaBlocks::STONE());
			if($wall){ $world->setBlockAt(11, 64, 8, VanillaBlocks::STONE()); }
			$entity = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(10.3, 64, 8.5), 101);
			self::assertTrue($world->getBlockAt(8, 64, 8)->extend());
			self::assertSame(0, $entity->pistonMoves);
			$world->getBlockAt(8, 64, 8)->onScheduledUpdate();
			self::assertSame(1, $entity->pistonMoves);
			self::assertLessThanOrEqual(10.810001, $entity->getPosition()->x);
			$world->getBlockAt(8, 64, 8)->onScheduledUpdate();
			self::assertSame(2, $entity->pistonMoves);
			if($wall){
				self::assertLessThanOrEqual(11.000001, $entity->getBoundingBox()->maxX);
			}else{
				self::assertEqualsWithDelta(11.31, $entity->getPosition()->x, 0.00001);
			}
		}
	}

	public function testHoneyCarriesStandingEntityExactlyOneBlock() : void{
		$world = $this->world();
		$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(9, 64, 8, VanillaBlocks::HONEY_BLOCK());
		$entity = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(9.5, 64.9375, 8.5), 102);
		self::assertTrue($world->getBlockAt(8, 64, 8)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(8, 64, 8));
		self::assertEqualsWithDelta(10.5, $entity->getPosition()->x, 0.00001);
		self::assertEqualsWithDelta(64.9375, $entity->getPosition()->y, 0.00001);
	}

	public function testHoneyCarriesSideContactOnPushAndPullInEveryDirection() : void{
		foreach(Facing::ALL as $direction){
			$world = $this->world();
			$base = new \pocketmine\math\Vector3(8, 64, 8);
			$source = $base->getSide($direction, 2);
			[$dx, $dy, $dz] = Facing::OFFSET[$direction];
			$world->setBlock($base, VanillaBlocks::STICKY_PISTON()->setFacing($direction));
			$world->setBlock($base->getSide($direction), VanillaBlocks::HONEY_BLOCK());
			$world->setBlock($source, VanillaBlocks::HONEY_BLOCK());
			$position = $source->add($dz !== 0 ? 1.2375 : 0.5, $direction === Facing::DOWN ? -0.9 : 0.1, $dz !== 0 ? 0.5 : 1.2375);
			$entity = $this->addCollisionEntity($world, $position, 200 + $direction);
			self::assertTrue($world->getBlock($base)->extend());
			$this->finishMovement($world, $base);
			self::assertEqualsWithDelta($position->x + $dx, $entity->getPosition()->x, 0.00001);
			self::assertEqualsWithDelta($position->y + $dy, $entity->getPosition()->y, 0.00001);
			self::assertEqualsWithDelta($position->z + $dz, $entity->getPosition()->z, 0.00001);
			self::assertSame(2, $entity->pistonMoves);
			self::assertTrue($world->getBlock($base)->retract());
			$this->finishMovement($world, $base);
			self::assertEqualsWithDelta($position->x, $entity->getPosition()->x, 0.00001);
			self::assertEqualsWithDelta($position->y, $entity->getPosition()->y, 0.00001);
			self::assertEqualsWithDelta($position->z, $entity->getPosition()->z, 0.00001);
			self::assertSame(4, $entity->pistonMoves);
		}
	}

	public function testHoneySideCarryRequiresContactAndRespectsWalls() : void{
		foreach([false, true] as $wall){
			$world = $this->world();
			$base = new \pocketmine\math\Vector3(8, 64, 8);
			$world->setBlock($base, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(9, 64, 8, VanillaBlocks::HONEY_BLOCK());
			$world->setBlockAt(10, 64, 8, VanillaBlocks::HONEY_BLOCK());
			if($wall){
				$world->setBlockAt(11, 64, 9, VanillaBlocks::STONE());
			}
			$touching = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(10.5, 64.1, 9.2375), 220);
			$separated = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(10.5, 64.1, 9.2875), 221);
			self::assertTrue($world->getBlock($base)->extend());
			$this->finishMovement($world, $base);
			self::assertEqualsWithDelta($wall ? 10.7 : 11.5, $touching->getPosition()->x, 0.00001);
			self::assertEqualsWithDelta(10.5, $separated->getPosition()->x, 0.00001);
			self::assertSame(0, $separated->pistonMoves);
		}
	}

	public function testEntityEnumerationIsBudgetedAndEventuallyCompletes() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 1);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(8, 63, 8, VanillaBlocks::REDSTONE());
		$world->setBlockAt(9, 64, 8, VanillaBlocks::STONE());
		$entities = [];
		for($id = 0; $id < 80; ++$id){
			$entities[] = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(10.3, 64, 8.5), 1000 + $id);
		}
		$world->getBlockAt(8, 64, 8)->onRedstoneUpdate($engine);
		$arm = $world->getTileAt(8, 64, 8);
		$engine->tick(1);
		self::assertSame(0.0, $arm->getProgress());
		self::assertLessThanOrEqual(16, array_sum(array_map(fn($entity) => $entity->pistonMoves, $entities)));
		for($tick = 2; $tick <= 12; ++$tick){ $engine->tick($tick); }
		self::assertFalse($arm->isMoving());
		foreach($entities as $entity){
			self::assertSame(2, $entity->pistonMoves);
			self::assertEqualsWithDelta(11.31, $entity->getPosition()->x, 0.00001);
		}
	}

	public function testInventoryCloseEventChangesAreCapturedAndReentrantMovementIsRejected() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$inventory = $world->getTileAt(15, 64, 1)->getInventory();
		$item = VanillaItems::DIAMOND()->setCount(23);
		$called = 0;
		$this->onInventoryClose($inventory, function(\pocketmine\event\inventory\InventoryCloseEvent $event) use ($world, $inventory, $item, &$called) : void{
			++$called;
			$inventory->setItem(0, $item);
			self::assertFalse($world->getBlockAt(14, 64, 1)->extend());
		});
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		self::assertSame(1, $called);
		self::assertTrue($world->getTileAt(16, 64, 1)->getInventory()->getItem(0)->equalsExact($item));
	}

	public function testCloseEventBaseReplacementIsPreserved() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$inventory = $world->getTileAt(15, 64, 1)->getInventory();
		$item = VanillaItems::DIAMOND()->setCount(23);
		$inventory->setItem(0, $item);
		$this->onInventoryClose($inventory, function(\pocketmine\event\inventory\InventoryCloseEvent $event) use ($world) : void{
			$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::SOUTH));
		});
		self::assertFalse($world->getBlockAt(14, 64, 1)->extend());
		self::assertSame(Facing::SOUTH, $world->getBlockAt(14, 64, 1)->getFacing());
		self::assertFalse($world->getTileAt(14, 64, 1)->isMoving());
		self::assertTrue($world->getTileAt(15, 64, 1)->getInventory()->getItem(0)->equalsExact($item));
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlockAt(16, 64, 1)->getTypeId());
	}

	public function testMovingFenceCollisionIncludesDiagonalOverflowAndSurvivesReload() : void{
		foreach([Facing::EAST, Facing::DOWN] as $facing){
			$world = $this->world();
			$base = new \pocketmine\math\Vector3(8, 64, 8);
			$source = $base->getSide($facing);
			$dest = $source->getSide($facing);
			$world->setBlock($base, VanillaBlocks::PISTON()->setFacing($facing));
			$world->setBlock($source, VanillaBlocks::OAK_FENCE());
			$world->setBlock($source->getSide(Facing::NORTH), VanillaBlocks::OAK_FENCE());
			self::assertTrue($world->getBlock($base)->extend());
			$area = new \pocketmine\math\AxisAlignedBB($source->x + 0.4, $source->y + 1.1, $source->z + 0.4, $source->x + 0.49, $source->y + 1.2, $source->z + 0.6);
			self::assertTrue($world->getBlock($dest)->collidesWithBB($area));
			self::assertNotEmpty($world->getBlockCollisionBoxes($area));
			self::assertNotEmpty($world->getCollisionBlocks($area));
			$before = $world->getBlock($dest)->getCollisionBoxes();
			$tile = $world->getTile($dest);
			$saved = $tile->saveNBT();
			$tile->close();
			$world->addTile(TileFactory::getInstance()->createFromData($world, $saved));
			$world->invalidateBlockCache($dest);
			self::assertEquals($before, $world->getBlock($dest)->getCollisionBoxes());
			self::assertNotEmpty($world->getBlockCollisionBoxes($area));
			$world->getBlock($base)->onScheduledUpdate();
			self::assertFalse($world->getBlock($dest)->collidesWithBB($area));
			self::assertNotContainsEquals($before[0], $world->getBlockCollisionBoxes($area));
		}
	}

	public function testExplosionIgnitesMovingTntInEitherDestructionOrder() : void{
		foreach([false, true] as $baseFirst){
			$world = $this->world();
			$world->expects(self::never())->method("dropItem");
			$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(15, 64, 1, VanillaBlocks::TNT());
			self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
			$moving = $world->getBlockAt(16, 64, 1);
			if($baseFirst){ $world->getTileAt(14, 64, 1)->onBlockDestroyed(); }
			$explosion = new \pocketmine\world\Explosion(new \pocketmine\world\Position(16.5, 64.5, 1.5, $world), 1.0);
			$explosion->affectedBlocks = [World::blockHash(16, 64, 1) => $moving];
			self::assertTrue($explosion->explodeB());
			self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlockAt(16, 64, 1)->getTypeId());
			self::assertCount(1, $world->getEntities());
			$tnt = array_values($world->getEntities())[0];
			self::assertInstanceOf(\pocketmine\entity\object\PrimedTNT::class, $tnt);
			self::assertGreaterThanOrEqual(10, $tnt->getFuse());
			self::assertLessThanOrEqual(30, $tnt->getFuse());
		}
	}

	public function testMovedContainersKeepItemsThroughNativeWorldAndSaveReload() : void{
		foreach([VanillaBlocks::CHEST(), VanillaBlocks::BARREL(), VanillaBlocks::HOPPER(), VanillaBlocks::DISPENSER(), VanillaBlocks::DROPPER(), VanillaBlocks::FURNACE()] as $container){
			$world = $this->world();
			$world->setBlockAt(14, 64, 1, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(15, 64, 1, $container);
			$tile = $world->getTileAt(15, 64, 1);
			self::assertInstanceOf(Container::class, $tile);
			$items = VanillaItems::DIAMOND()->setCount(37)->setCustomName("keep me");
			$tile->getRealInventory()->setItem(0, $items);
			for($i = 0; $i < 3; ++$i){
				$piston = $world->getBlockAt(14, 64, 1);
				self::assertTrue($piston->extend());
				$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
				self::assertTrue($tile->isClosed());
				$moved = $world->getTileAt(16, 64, 1);
				self::assertInstanceOf(Container::class, $moved);
				self::assertTrue($moved->getRealInventory()->getItem(0)->equalsExact($items), $container->getName());
				$saved = $moved->saveNBT();
				$moved->close();
				$reloaded = TileFactory::getInstance()->createFromData($world, $saved);
				$world->addTile($reloaded);
				self::assertInstanceOf(Container::class, $reloaded);
				self::assertTrue($reloaded->getRealInventory()->getItem(0)->equalsExact($items));
				self::assertTrue($world->getBlockAt(14, 64, 1)->retract());
				$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
				$tile = $world->getTileAt(15, 64, 1);
				self::assertInstanceOf(Container::class, $tile);
				self::assertTrue($tile->getRealInventory()->getItem(0)->equalsExact($items));
				self::assertNull($world->getTileAt(16, 64, 1));
			}
		}
	}

	public function testBothHalvesMoveAndRemainPaired() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::HONEY_BLOCK());
		$world->setBlockAt(15, 64, 2, VanillaBlocks::HONEY_BLOCK());
		foreach([1, 2] as $z){
			$world->setBlockAt(15, 65, $z, VanillaBlocks::CHEST()->setFacing(Facing::WEST));
		}
		$left = $world->getTileAt(15, 65, 1);
		$right = $world->getTileAt(15, 65, 2);
		self::assertInstanceOf(Chest::class, $left);
		self::assertInstanceOf(Chest::class, $right);
		self::assertTrue($left->pairWith($right));
		$diamond = VanillaItems::DIAMOND()->setCount(17);
		$gold = VanillaItems::GOLD_INGOT()->setCount(43);
		$left->getRealInventory()->setItem(26, $diamond);
		$right->getRealInventory()->setItem(26, $gold);
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		$left = $world->getTileAt(16, 65, 1);
		$right = $world->getTileAt(16, 65, 2);
		self::assertInstanceOf(Chest::class, $left);
		self::assertInstanceOf(Chest::class, $right);
		self::assertTrue($left->getRealInventory()->getItem(26)->equalsExact($diamond));
		self::assertTrue($right->getRealInventory()->getItem(26)->equalsExact($gold));
		self::assertTrue($left->isPaired());
		self::assertSame($right, $left->getPair());
		self::assertSame(54, $left->getInventory()->getSize());
		self::assertTrue($world->getBlockAt(14, 64, 1)->retract());
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		$left = $world->getTileAt(15, 65, 1);
		$right = $world->getTileAt(15, 65, 2);
		self::assertInstanceOf(Chest::class, $left);
		self::assertInstanceOf(Chest::class, $right);
		self::assertSame($right, $left->getPair());
		self::assertTrue($left->getRealInventory()->getItem(26)->equalsExact($diamond));
		self::assertTrue($right->getRealInventory()->getItem(26)->equalsExact($gold));
	}

	public function testUnpairClosesDoubleInventoryViewers() : void{
		$world = $this->world();
		$world->setBlockAt(15, 65, 1, VanillaBlocks::CHEST());
		$world->setBlockAt(15, 65, 2, VanillaBlocks::CHEST());
		$left = $world->getTileAt(15, 65, 1);
		$right = $world->getTileAt(15, 65, 2);
		self::assertInstanceOf(Chest::class, $left);
		self::assertInstanceOf(Chest::class, $right);
		self::assertTrue($left->pairWith($right));
		$inventory = $left->getInventory();
		$viewer = $this->getMockBuilder(\pocketmine\player\Player::class)->disableOriginalConstructor()->onlyMethods(["getCurrentWindow", "removeCurrentWindow"])->getMock();
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($viewer, true);
		(new \ReflectionProperty(\pocketmine\player\Player::class, "logger"))->setValue($viewer, $this->createMock(\Logger::class));
		$viewer->method("getCurrentWindow")->willReturn($inventory);
		$viewer->expects(self::once())->method("removeCurrentWindow");
		(new \ReflectionProperty(\pocketmine\inventory\BaseInventory::class, "viewers"))->setValue($inventory, [spl_object_id($viewer) => $viewer]);
		self::assertTrue($left->unpair());
		self::assertCount(0, $inventory->getViewers());
	}

	public function testFullDoubleChestKeepsEverySlotWhenPushAndPullSeparateIt() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		foreach([15, 16] as $x){
			$world->setBlockAt($x, 64, 1, VanillaBlocks::CHEST()->setFacing(Facing::SOUTH));
		}
		$near = $world->getTileAt(15, 64, 1);
		$far = $world->getTileAt(16, 64, 1);
		self::assertInstanceOf(Chest::class, $near);
		self::assertInstanceOf(Chest::class, $far);
		self::assertTrue($near->pairWith($far));
		for($slot = 0; $slot < 27; ++$slot){
			$near->getRealInventory()->setItem($slot, VanillaItems::DIAMOND()->setCount($slot + 1)->setCustomName("near-$slot"));
			$far->getRealInventory()->setItem($slot, VanillaItems::GOLD_INGOT()->setCount($slot + 1)->setCustomName("far-$slot"));
		}
		$nearContents = $near->getRealInventory()->getContents();
		$farContents = $far->getRealInventory()->getContents();
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		$near = $world->getTileAt(16, 64, 1);
		$far = $world->getTileAt(17, 64, 1);
		self::assertInstanceOf(Chest::class, $near);
		self::assertInstanceOf(Chest::class, $far);
		self::assertSame($far, $near->getPair());
		self::assertSame(54, $near->getInventory()->getSize());
		foreach($nearContents as $slot => $item){
			self::assertTrue($near->getRealInventory()->getItem($slot)->equalsExact($item));
			self::assertTrue($far->getRealInventory()->getItem($slot)->equalsExact($farContents[$slot]));
		}
		self::assertTrue($world->getBlockAt(14, 64, 1)->retract());
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		$near = $world->getTileAt(15, 64, 1);
		self::assertInstanceOf(Chest::class, $near);
		self::assertFalse($near->isPaired());
		self::assertFalse($far->isPaired());
		foreach($nearContents as $slot => $item){
			self::assertTrue($near->getRealInventory()->getItem($slot)->equalsExact($item));
			self::assertTrue($far->getRealInventory()->getItem($slot)->equalsExact($farContents[$slot]));
		}
	}

	public function testPistonSendsClientStateAndRenderRefresh() : void{
		$world = $this->world();
		foreach([VanillaBlocks::PISTON(), VanillaBlocks::STICKY_PISTON()] as $prototype){
			foreach(Facing::ALL as $facing){
				$world->setBlockAt(8, 64, 8, $prototype->setFacing($facing));
				$tile = $world->getTileAt(8, 64, 8);
				self::assertInstanceOf(\pocketmine\block\tile\PistonArm::class, $tile);
				foreach([false, true, false] as $extended){
					$piston = $world->getBlockAt(8, 64, 8);
					if($extended){
						self::assertTrue($piston->extend());
						$this->finishMovement($world, new \pocketmine\math\Vector3(8, 64, 8));
					}else{
						self::assertTrue($piston->retract());
						$this->finishMovement($world, new \pocketmine\math\Vector3(8, 64, 8));
					}
					foreach([\pocketmine\network\mcpe\protocol\ProtocolInfo::PROTOCOL_1_20_60, \pocketmine\network\mcpe\protocol\ProtocolInfo::CURRENT_PROTOCOL] as $protocol){
						$converter = \pocketmine\network\mcpe\convert\TypeConverter::getInstance($protocol);
						$packets = $world->createBlockUpdatePackets($converter, [new \pocketmine\math\Vector3(8, 64, 8)]);
						self::assertCount(3, $packets);
						self::assertInstanceOf(\pocketmine\network\mcpe\protocol\UpdateBlockPacket::class, $packets[0]);
						self::assertInstanceOf(\pocketmine\network\mcpe\protocol\UpdateBlockPacket::class, $packets[1]);
						self::assertNotSame($packets[0]->blockRuntimeId, $packets[1]->blockRuntimeId);
						self::assertInstanceOf(\pocketmine\network\mcpe\protocol\BlockActorDataPacket::class, $packets[2]);
						$nbt = $tile->getSpawnCompound($converter);
						self::assertSame("PistonArm", $nbt->getString("id"));
						self::assertSame($extended ? 2 : 0, $nbt->getByte("State"));
						self::assertSame($extended ? 1.0 : 0.0, $nbt->getFloat("Progress"));
						self::assertSame($prototype->isSticky() ? 1 : 0, $nbt->getByte("Sticky"));
					}
				}
			}
		}
	}

	public function testShortPulseFinishesBothAnimations() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 2000);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(8, 63, 8, VanillaBlocks::REDSTONE());
		$piston = $world->getBlockAt(8, 64, 8);
		$piston->onRedstoneUpdate($engine);
		$tile = $world->getTileAt(8, 64, 8);
		self::assertInstanceOf(\pocketmine\block\tile\PistonArm::class, $tile);
		self::assertTrue($tile->isMoving());
		self::assertSame(1, $tile->saveNBT()->getByte("State"));
		self::assertSame(0.0, $tile->saveNBT()->getFloat("Progress"));
		self::assertTrue($world->getBlockAt(8, 64, 8)->isExtended());
		self::assertTrue(PistonMoveRules::isImmovable($world->getBlockAt(8, 64, 8)));
		$world->setBlockAt(8, 63, 8, VanillaBlocks::AIR());
		$world->getBlockAt(8, 64, 8)->onRedstoneUpdate($engine);
		$engine->tick(1);
		self::assertSame(1, $tile->saveNBT()->getByte("State"));
		self::assertSame(0.5, $tile->saveNBT()->getFloat("Progress"));
		$engine->tick(2);
		self::assertSame(3, $tile->saveNBT()->getByte("State"));
		self::assertFalse($world->getBlockAt(8, 64, 8)->isExtended());
		self::assertTrue(PistonMoveRules::isImmovable($world->getBlockAt(8, 64, 8)));
		$engine->tick(3);
		$engine->tick(4);
		self::assertFalse($tile->isMoving());
		self::assertSame(0, $tile->saveNBT()->getByte("State"));
		self::assertSame(0.0, $tile->saveNBT()->getFloat("Progress"));
		self::assertFalse(PistonMoveRules::isImmovable($world->getBlockAt(8, 64, 8)));
	}

	public function testDaylightSensorKeepsItsBoundedTimerAfterInversion() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 1);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::DAYLIGHT_SENSOR());
		$world->getBlockAt(8, 64, 8)->onScheduledUpdate();
		self::assertSame(15, $world->getBlockAt(8, 64, 8)->getOutputSignalStrength());
		self::assertTrue($engine->isScheduled(new \pocketmine\math\Vector3(8, 64, 8)));
		$world->getBlockAt(8, 64, 8)->onInteract(VanillaItems::AIR(), Facing::UP, new \pocketmine\math\Vector3(0, 0, 0));
		self::assertSame(0, $world->getBlockAt(8, 64, 8)->getOutputSignalStrength());
		self::assertTrue($engine->isScheduled(new \pocketmine\math\Vector3(8, 64, 8)));
		for($tick = 1; $tick <= 40; ++$tick){ $engine->tick($tick); }
		self::assertTrue($engine->isScheduled(new \pocketmine\math\Vector3(8, 64, 8)));
	}

	public function testPressurePlateEventRetainsAllActivatingEntities() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 8);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::WEIGHTED_PRESSURE_PLATE_LIGHT());
		for($id = 0; $id < 23; ++$id){
			$inside = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(8.5, 64, 8.5), 4000 + $id);
		}
		$count = 0;
		$listener = new \pocketmine\event\RegisteredListener(function(\pocketmine\event\block\PressurePlateUpdateEvent $event) use (&$count) : void{
			$count = count($event->getActivatingEntities());
		}, \pocketmine\event\EventPriority::NORMAL, $this->createMock(\pocketmine\plugin\Plugin::class), true, new \pocketmine\timings\TimingsHandler("pressure plate population regression"));
		$handlers = \pocketmine\event\HandlerListManager::global()->getListFor(\pocketmine\event\block\PressurePlateUpdateEvent::class);
		$handlers->register($listener);
		try{
			$world->getBlockAt(8, 64, 8)->onEntityInside($inside);
			for($tick = 1; $tick <= 30; ++$tick){ $engine->tick($tick); }
			self::assertSame(23, $count);
			self::assertSame(15, $world->getBlockAt(8, 64, 8)->getOutputSignalStrength());
		}finally{
			$handlers->unregister($listener);
		}
	}

	public function testButtonReleaseUsesRedstoneBudgetAndRejectsReplacedTimer() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 1);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		for($x = 4; $x <= 6; ++$x){
			$world->setBlockAt($x, 64, 8, VanillaBlocks::STONE_BUTTON());
			$world->getBlockAt($x, 64, 8)->onInteract(VanillaItems::AIR(), Facing::UP, new \pocketmine\math\Vector3(0, 0, 0));
			self::assertTrue($engine->isScheduled(new \pocketmine\math\Vector3($x, 64, 8)));
		}
		for($tick = 1; $tick < 20; ++$tick){ $engine->tick($tick); }
		$engine->tick(20);
		$pressed = 0;
		for($x = 4; $x <= 6; ++$x){ $pressed += (int) $world->getBlockAt($x, 64, 8)->isPressed(); }
		self::assertSame(2, $pressed);
		$world->setBlockAt(6, 64, 8, VanillaBlocks::STONE_BUTTON());
		self::assertFalse($engine->isScheduled(new \pocketmine\math\Vector3(6, 64, 8)));
		$engine->tick(21);
		$engine->tick(22);
		for($x = 4; $x <= 6; ++$x){ self::assertFalse($world->getBlockAt($x, 64, 8)->isPressed()); }
	}

	public function testPressurePlateWorkIsBudgetedAndRetainsWeightedSignal() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 10);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::WEIGHTED_PRESSURE_PLATE_HEAVY());
		for($id = 0; $id < 130; ++$id){
			$this->addCollisionEntity($world, new \pocketmine\math\Vector3(10.5, 64, 8.5), 2000 + $id);
		}
		for($id = 0; $id < 20; ++$id){
			$inside = $this->addCollisionEntity($world, new \pocketmine\math\Vector3(8.5, 64, 8.5), 3000 + $id);
		}
		$plate = $world->getBlockAt(8, 64, 8);
		$plate->onEntityInside($inside);
		self::assertTrue($engine->isScheduled($plate->getPosition()));
		$engine->tick(1);
		self::assertSame(0, $world->getBlockAt(8, 64, 8)->getOutputSignalStrength());
		for($tick = 2; $tick <= 80; ++$tick){
			$engine->tick($tick);
		}
		self::assertSame(2, $world->getBlockAt(8, 64, 8)->getOutputSignalStrength());
	}

	public function testComparatorWatchIsReleasedWithItsTileAndRestoredOnReplacement() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 100);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_COMPARATOR()->setFacing(Facing::EAST));
		$world->setBlockAt(9, 64, 8, VanillaBlocks::CHEST());
		$world->getBlockAt(8, 64, 8)->onRedstoneUpdate($engine);
		self::assertSame(1, $engine->getContainerWatch()->getWatchedCount());
		$world->getTileAt(8, 64, 8)->close();
		self::assertSame(0, $engine->getContainerWatch()->getWatchedCount());
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_COMPARATOR()->setFacing(Facing::EAST));
		$engine->tick(1);
		self::assertSame(1, $engine->getContainerWatch()->getWatchedCount());
	}

	public function testInvalidSavedComparatorAndContainerSignalsStayInRange() : void{
		$world = $this->world();
		$world->setBlockAt(8, 64, 8, VanillaBlocks::REDSTONE_COMPARATOR());
		$tile = $world->getTileAt(8, 64, 8);
		foreach([-100 => 0, 100 => 15] as $saved => $expected){
			$tile->readSaveData(\pocketmine\nbt\tag\CompoundTag::create()->setInt("OutputSignal", $saved));
			self::assertSame($expected, $tile->getSignalStrength());
		}
		$world->setBlockAt(9, 64, 8, VanillaBlocks::CHEST());
		$chest = $world->getTileAt(9, 64, 8);
		for($slot = 0; $slot < $chest->getInventory()->getSize(); ++$slot){
			$chest->getInventory()->setItem($slot, VanillaItems::DIAMOND()->setCount(127));
		}
		self::assertSame(15, \pocketmine\world\redstone\ContainerWatch::containerSignal($chest));
	}

	public function testStalledPistonCannotStarveOtherRedstone() : void{
		foreach([1, 2, 4] as $budget){
			$world = $this->world();
			$engine = new \pocketmine\world\redstone\RedstoneEngine($world, $budget);
			(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
			$world->setBlockAt(15, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
			$world->setBlockAt(15, 63, 8, VanillaBlocks::REDSTONE());
			$world->getBlockAt(15, 64, 8)->onRedstoneUpdate($engine);
			$chunks = new \ReflectionProperty(World::class, "chunks");
			$loaded = $chunks->getValue($world);
			$chunks->setValue($world, [World::chunkHash(0, 0) => $loaded[World::chunkHash(0, 0)]]);
			$world->clearCache(true);
			$world->setBlockAt(8, 64, 3, VanillaBlocks::REDSTONE_LAMP());
			$world->setBlockAt(8, 63, 3, VanillaBlocks::REDSTONE());
			$world->setBlockAt(4, 64, 3, VanillaBlocks::REDSTONE_COMPARATOR()->setFacing(Facing::EAST));
			$world->setBlockAt(5, 64, 3, VanillaBlocks::CHEST());
			$world->getBlockAt(4, 64, 3)->onRedstoneUpdate($engine);
			$engine->request(8, 64, 3);
			for($tick = 1; $tick <= 12; ++$tick){
				$engine->tick($tick);
			}
			self::assertTrue($world->getBlockAt(8, 64, 3)->isLit(), "Pending animation starved the lamp at budget $budget");
			self::assertSame(1, $engine->getContainerWatch()->getWatchedCount());
			$chunks->setValue($world, $loaded);
			$world->clearCache(true);
		}
	}

	public function testAnimationKeepsEndpointWhenHeadChunkIsUnloaded() : void{
		$world = $this->world();
		$engine = new \pocketmine\world\redstone\RedstoneEngine($world, 2000);
		(new \ReflectionProperty(World::class, "redstone"))->setValue($world, $engine);
		$world->setBlockAt(15, 64, 8, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 63, 8, VanillaBlocks::REDSTONE());
		$world->getBlockAt(15, 64, 8)->onRedstoneUpdate($engine);
		$tile = $world->getTileAt(15, 64, 8);
		self::assertInstanceOf(\pocketmine\block\tile\PistonArm::class, $tile);
		$chunks = new \ReflectionProperty(World::class, "chunks");
		$loaded = $chunks->getValue($world);
		$chunks->setValue($world, [World::chunkHash(0, 0) => $loaded[World::chunkHash(0, 0)]]);
		$world->clearCache(true);
		$engine->tick(1);
		self::assertSame(1, $tile->saveNBT()->getByte("State"));
		self::assertTrue($world->getBlockAt(15, 64, 8)->isExtended());
		$chunks->setValue($world, $loaded);
		$world->clearCache(true);
		$engine->tick(2);
		$engine->tick(3);
		self::assertTrue($world->getBlockAt(15, 64, 8)->isExtended());
		self::assertSame(2, $tile->saveNBT()->getByte("State"));
	}
	public function testMovingCollisionAdvancesInAllDirectionsAndInvalidatesCachedCells() : void{
		foreach(Facing::ALL as $facing){
			$world = $this->world();
			$base = new \pocketmine\math\Vector3(8, 64, 8);
			$source = $base->getSide($facing);
			$dest = $source->getSide($facing);
			$world->setBlock($base, VanillaBlocks::PISTON()->setFacing($facing));
			$world->setBlock($source, VanillaBlocks::STONE());
			self::assertTrue($world->getBlock($base)->extend());
			$area = new \pocketmine\math\AxisAlignedBB(5, 61, 5, 12, 68, 12);
			$world->getBlockCollisionBoxes($area);
			$before = $world->getBlock($dest)->getCollisionBoxes()[0];
			self::assertEqualsWithDelta($source->x, $before->minX, 0.00001);
			self::assertEqualsWithDelta($source->y, $before->minY, 0.00001);
			self::assertEqualsWithDelta($source->z, $before->minZ, 0.00001);
			$world->getBlock($base)->onScheduledUpdate();
			$after = $world->getBlock($dest)->getCollisionBoxes()[0];
			[$dx, $dy, $dz] = Facing::OFFSET[$facing];
			self::assertEqualsWithDelta($source->x + $dx * 0.5, $after->minX, 0.00001);
			self::assertEqualsWithDelta($source->y + $dy * 0.5, $after->minY, 0.00001);
			self::assertEqualsWithDelta($source->z + $dz * 0.5, $after->minZ, 0.00001);
			$matches = 0;
			foreach($world->getBlockCollisionBoxes($area) as $box){
				if($box == $after){
					++$matches;
				}
			}
			self::assertGreaterThan(0, $matches);
			$world->getBlock($base)->onScheduledUpdate();
			self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlock($dest)->getTypeId());
		}
	}

	public function testMovingPayloadUsesEachClientPaletteWithoutLeakingInventory() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$world->getTileAt(15, 64, 1)->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(64));
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$tile = $world->getTileAt(16, 64, 1);
		foreach([\pocketmine\network\mcpe\protocol\ProtocolInfo::PROTOCOL_1_20_60, \pocketmine\network\mcpe\protocol\ProtocolInfo::CURRENT_PROTOCOL] as $protocol){
			$converter = \pocketmine\network\mcpe\convert\TypeConverter::getInstance($protocol);
			$spawn = $tile->getSpawnCompound($converter);
			self::assertSame("MovingBlock", $spawn->getString("id"));
			self::assertSame("minecraft:chest", $spawn->getCompoundTag("movingBlock")->getString("name"));
			self::assertNull($spawn->getCompoundTag("movingEntity")->getTag("Items"));
			self::assertNull($spawn->getTag("AmberMovementId"));
			self::assertSame(14, $spawn->getInt("pistonPosX"));
			$packets = $world->createBlockUpdatePackets($converter, [new \pocketmine\math\Vector3(16, 64, 1)]);
			self::assertCount(2, $packets);
			self::assertSame($converter->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::MOVING_BLOCK()->getStateId()), $packets[0]->blockRuntimeId);
		}
		self::assertCount(1, $tile->saveNBT()->getCompoundTag("movingEntity")->getListTag("Items"));
	}

	public function testBaseDestructionFinishesCarriedChestExactlyOnce() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$item = VanillaItems::DIAMOND()->setCount(29);
		$world->getTileAt(15, 64, 1)->getInventory()->setItem(0, $item);
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$moving = $world->getTileAt(16, 64, 1);
		$world->getTileAt(14, 64, 1)->onBlockDestroyed();
		self::assertInstanceOf(Chest::class, $world->getTileAt(16, 64, 1));
		self::assertTrue($world->getTileAt(16, 64, 1)->getInventory()->getItem(0)->equalsExact($item));
		self::assertFalse($moving->finish());
		$moving->close();
		self::assertInstanceOf(Chest::class, $world->getTileAt(16, 64, 1));
	}

	public function testOrphanedMovingBlockRestoresAfterBaseReplacement() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$item = VanillaItems::DIAMOND()->setCount(13);
		$world->getTileAt(15, 64, 1)->getInventory()->setItem(0, $item);
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$world->setBlockAt(14, 64, 1, VanillaBlocks::STONE());
		$world->getBlockAt(16, 64, 1)->onScheduledUpdate();
		self::assertTrue($world->getTileAt(16, 64, 1)->getInventory()->getItem(0)->equalsExact($item));
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlockAt(14, 64, 1)->getTypeId());
	}

	public function testDestinationReplacementIsNeverResurrectedByOldMovement() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::STONE());
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$moving = $world->getTileAt(16, 64, 1);
		$world->setBlockAt(16, 64, 1, VanillaBlocks::BEDROCK());
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		self::assertSame(VanillaBlocks::BEDROCK()->getTypeId(), $world->getBlockAt(16, 64, 1)->getTypeId());
		self::assertFalse($moving->finish());
	}

	public function testCrossChunkMotionWaitsForDestinationThenResumesWithContent() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$item = VanillaItems::DIAMOND()->setCount(47);
		$world->getTileAt(15, 64, 1)->getInventory()->setItem(0, $item);
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		$chunks = new \ReflectionProperty(World::class, "chunks");
		$loaded = $chunks->getValue($world);
		$chunks->setValue($world, [World::chunkHash(0, 0) => $loaded[World::chunkHash(0, 0)]]);
		$world->clearCache(true);
		for($i = 0; $i < 8; ++$i){
			$world->getBlockAt(14, 64, 1)->onScheduledUpdate();
		}
		self::assertFalse($world->isChunkLoaded(1, 0));
		self::assertSame(0.0, $world->getTileAt(14, 64, 1)->getProgress());
		$chunks->setValue($world, $loaded);
		$world->clearCache(true);
		$this->finishMovement($world, new \pocketmine\math\Vector3(14, 64, 1));
		self::assertTrue($world->getTileAt(16, 64, 1)->getInventory()->getItem(0)->equalsExact($item));
	}

	public function testMovingContainerIsTemporaryAndPreservesPayloadOnReload() : void{
		$world = $this->world();
		$world->setBlockAt(14, 64, 1, VanillaBlocks::STICKY_PISTON()->setFacing(Facing::EAST));
		$world->setBlockAt(15, 64, 1, VanillaBlocks::CHEST());
		$chest = $world->getTileAt(15, 64, 1);
		$item = VanillaItems::DIAMOND()->setCount(51)->setCustomName("in transit");
		$chest->getInventory()->setItem(7, $item);
		self::assertTrue($world->getBlockAt(14, 64, 1)->extend());
		self::assertInstanceOf(\pocketmine\block\MovingBlock::class, $world->getBlockAt(16, 64, 1));
		$moving = $world->getTileAt(16, 64, 1);
		self::assertInstanceOf(\pocketmine\block\tile\MovingBlock::class, $moving);
		self::assertFalse($moving instanceof Container);
		self::assertFalse($world->getBlockAt(14, 64, 1)->extend());
		$arm = $world->getTileAt(14, 64, 1);
		foreach([$arm, $moving] as $tile){
			$saved = $tile->saveNBT();
			$tile->close();
			$world->addTile(TileFactory::getInstance()->createFromData($world, $saved));
		}
		$world->getBlockAt(14, 64, 1)->onScheduledUpdate();
		self::assertInstanceOf(\pocketmine\block\MovingBlock::class, $world->getBlockAt(16, 64, 1));
		$world->getBlockAt(14, 64, 1)->onScheduledUpdate();
		$restored = $world->getTileAt(16, 64, 1);
		self::assertInstanceOf(Chest::class, $restored);
		self::assertTrue($restored->getInventory()->getItem(7)->equalsExact($item));
		self::assertNull($world->getTileAt(15, 64, 1));
	}

}
