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
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\entity\animation\FireworkParticlesAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\FireworkRocket as FireworkItem;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\CompoundTagMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\sound\FireworkExplosionSound;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class DummyFireworkTarget extends Living{
	public static function getNetworkTypeId() : string{ return "minecraft:dummy"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	public function getName() : string{ return "Dummy"; }
	public ?EntityDamageEvent $lastDamageEvent = null;
	public function attack(EntityDamageEvent $source) : void{
		$this->lastDamageEvent = $source;
	}
}

final class FireworkRocketTest extends TestCase{

	protected function setUp() : void{
		\pocketmine\timings\Timings::init();
	}

	private function createLivingTarget(int $id, Vector3 $pos, ?World $world = null) : DummyFireworkTarget{
		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}
		$target = (new ReflectionClass(DummyFireworkTarget::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($target, $id);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($target, new AxisAlignedBB($pos->x - 0.5, $pos->y, $pos->z - 0.5, $pos->x + 0.5, $pos->y + 1.0, $pos->z + 0.5));
		(new ReflectionProperty(Entity::class, "location"))->setValue($target, new Location($pos->x, $pos->y, $pos->z, $world, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "health"))->setValue($target, 20.0);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($target, new \pocketmine\inventory\ArmorInventory($target));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($target, new \pocketmine\entity\effect\EffectManager($target));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($target, new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($target, new \pocketmine\entity\AttributeMap());
		return $target;
	}

	private function createRocket(?World $world = null, ?Server $server = null) : FireworkRocket{
		$rocket = (new ReflectionClass(FireworkRocket::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, false);
		(new ReflectionProperty(Entity::class, "needsDespawn"))->setValue($rocket, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($rocket, 150);
		(new ReflectionProperty(Entity::class, "size"))->setValue($rocket, new EntitySizeInfo(0.25, 0.25));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($rocket, new Vector3(1.0, 0.0, 0.0));
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($rocket, new AxisAlignedBB(0, 0, 0, 0.25, 0.25, 0.25));
		(new ReflectionProperty(Entity::class, "ticksLived"))->setValue($rocket, 10);
		(new ReflectionProperty(Entity::class, "isCollided"))->setValue($rocket, false);
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($rocket, new \pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($rocket, []);
		(new ReflectionProperty(FireworkRocket::class, "maxFlightTimeTicks"))->setValue($rocket, 60);
		(new ReflectionProperty(FireworkRocket::class, "explosions"))->setValue($rocket, []);
		(new ReflectionProperty(FireworkRocket::class, "shotFromCrossbow"))->setValue($rocket, false);
		(new ReflectionProperty(FireworkRocket::class, "flightTimeMultiplier"))->setValue($rocket, 1);

		if($server === null){
			$server = $this->createMock(Server::class);
		}
		(new ReflectionProperty(Entity::class, "server"))->setValue($rocket, $server);

		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("getCollidingEntities")->willReturn([]);
		}
		(new ReflectionProperty(Entity::class, "location"))->setValue($rocket, new Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		return $rocket;
	}

	public function testCrossbowFlagAccessors() : void{
		$rocket = $this->createRocket();
		self::assertFalse($rocket->isShotFromCrossbow());

		$rocket->setShotFromCrossbow(true);
		self::assertTrue($rocket->isShotFromCrossbow());
	}

	public function testFlightTimeMultiplierAccessors() : void{
		$rocket = $this->createRocket();
		self::assertSame(1, $rocket->getFlightTimeMultiplier());

		$rocket->setFlightTimeMultiplier(3);
		self::assertSame(3, $rocket->getFlightTimeMultiplier());
	}

	public function testSyncNetworkDataExposesFireworksAtMetadataRoot() : void{
		$rocket = $this->createRocket();
		$rocket->setFlightTimeMultiplier(2);
		$explosion = new FireworkRocketExplosion(
			FireworkRocketType::BURST,
			[DyeColor::BLUE],
			[DyeColor::WHITE],
			true,
			false
		);
		$rocket->setExplosions([$explosion]);

		$syncMethod = new ReflectionMethod(FireworkRocket::class, "syncNetworkData");
		$properties = $rocket->getNetworkProperties();
		$syncMethod->invoke($rocket, $properties);

		$allProps = $properties->getAll();
		self::assertArrayHasKey(EntityMetadataProperties::FIREWORK_ITEM, $allProps);

		$prop = $allProps[EntityMetadataProperties::FIREWORK_ITEM];
		self::assertInstanceOf(CompoundTagMetadataProperty::class, $prop);

		$root = $prop->getValue()->getRoot();
		$fireworks = $root->getCompoundTag(FireworkItem::TAG_FIREWORK_DATA);
		self::assertNotNull($fireworks);
		self::assertSame(2, $fireworks->getByte("Flight"));

		$explosionsList = $fireworks->getListTag(FireworkItem::TAG_EXPLOSIONS);
		self::assertNotNull($explosionsList);
		self::assertCount(1, $explosionsList);
		self::assertEquals($explosion->toCompoundTag(), $explosionsList->get(0));
	}

	public static function networkFireworksProvider() : \Generator{
		foreach(\pocketmine\network\mcpe\protocol\ProtocolInfo::ACCEPTED_PROTOCOL as $protocol){
			foreach([false, true] as $crossbow){
				foreach([false, true] as $stars){
					yield "$protocol/crossbow=$crossbow/stars=$stars" => [$protocol, $crossbow, $stars];
				}
			}
		}
	}

	/** @dataProvider networkFireworksProvider */
	public function testSpawnAndExplosionThroughNetwork(int $protocol, bool $crossbow, bool $stars) : void{
		$server = $this->createMock(Server::class);
		$broadcaster = new \pocketmine\network\mcpe\StandardPacketBroadcaster($server, $protocol);
		$session = $this->createMock(\pocketmine\network\mcpe\NetworkSession::class);
		$session->method("getBroadcaster")->willReturn($broadcaster);
		$session->method("getCompressor")->willReturn($this->createMock(\pocketmine\network\mcpe\compression\Compressor::class));
		$player = $this->createMock(\pocketmine\player\Player::class);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(\pocketmine\player\Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$player->method("isConnected")->willReturn(true);
		$player->method("getNetworkSession")->willReturn($session);
		$spawnPackets = [];
		$events = [];
		$session->method("sendDataPacket")->willReturnCallback(function($packet) use ($protocol, &$spawnPackets) : bool{
			$writer = new \pmmp\encoding\ByteBufferWriter();
			$packet->encode($writer, $protocol);
			$decoded = new \pocketmine\network\mcpe\protocol\AddActorPacket();
			$decoded->decode(new \pmmp\encoding\ByteBufferReader($writer->getData()), $protocol);
			$spawnPackets[] = $decoded;
			return true;
		});
		$session->method("addToSendBuffer")->willReturnCallback(function(string $buffer) use ($protocol, &$events) : void{
			$decoded = new ActorEventPacket();
			$decoded->decode(new \pmmp\encoding\ByteBufferReader($buffer), $protocol);
			$events[] = $decoded;
		});
		$explosion = new FireworkRocketExplosion(FireworkRocketType::BURST, [DyeColor::BLUE, DyeColor::RED], [DyeColor::WHITE], true, true);
		// Three independent entities also exercise the metadata/event IDs used by Multishot.
		for($id = 150; $id < 153; ++$id){
			$rocket = $this->createRocket(server: $server);
			(new ReflectionProperty(Entity::class, "id"))->setValue($rocket, $id);
			(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($rocket, new \pocketmine\entity\AttributeMap());
			(new ReflectionProperty(Entity::class, "networkPropertiesDirty"))->setValue($rocket, true);
			(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($rocket, [$player]);
			$rocket->setShotFromCrossbow($crossbow)->setFlightTimeMultiplier(3)->setExplosions($stars ? [$explosion] : []);
			(new ReflectionMethod(Entity::class, "sendSpawnPacket"))->invoke($rocket, $player);
			$spawn = $spawnPackets[count($spawnPackets) - 1];
			self::assertSame($id, $spawn->actorRuntimeId);
			self::assertSame(FireworkRocket::getNetworkTypeId(), $spawn->type);
			$root = $spawn->metadata[EntityMetadataProperties::FIREWORK_ITEM]->getValue()->getRoot();
			$fireworks = $root->getCompoundTag("Fireworks");
			self::assertNotNull($fireworks, "Bedrock reads Fireworks directly from FIREWORK_ITEM");
			self::assertSame(3, $fireworks->getByte("Flight"));
			$list = $fireworks->getListTag("Explosions");
			self::assertNotNull($list);
			self::assertCount($stars ? 1 : 0, $list);
			if($stars){
				self::assertEquals(CompoundTag::create()
					->setByte("FireworkType", 4)
					->setByteArray("FireworkColor", "\x04\x01")
					->setByteArray("FireworkFade", "\x0f")
					->setByte("FireworkFlicker", 1)
					->setByte("FireworkTrail", 1), $list->get(0));
			}
			$rocket->explode();
			$rocket->explode(); // impacts must not emit duplicate explosions
			self::assertFalse($rocket->isFlaggedForDespawn());
			if($stars){
				$event = $events[count($events) - 1];
				self::assertSame($id, $event->actorRuntimeId);
				self::assertSame(ActorEvent::FIREWORK_PARTICLES, $event->eventId);
				self::assertSame(0, $event->eventData);
				self::assertEquals($protocol >= \pocketmine\network\mcpe\protocol\ProtocolInfo::PROTOCOL_1_26_20 ? $rocket->getPosition()->asVector3() : null, $event->firePosition);
			}
			(new ReflectionMethod(FireworkRocket::class, "entityBaseTick"))->invoke($rocket, 1);
			self::assertTrue($rocket->isFlaggedForDespawn());
		}
		self::assertCount(3, $spawnPackets);
		self::assertCount($stars ? 3 : 0, $events);
	}

	public function testExplodesOnCollisionWhenShotFromCrossbow() : void{
		$rocket = $this->createRocket();
		$rocket->setShotFromCrossbow(true);
		(new ReflectionProperty(Entity::class, "isCollided"))->setValue($rocket, true);

		$baseTick = new ReflectionMethod(FireworkRocket::class, "entityBaseTick");
		$baseTick->invoke($rocket, 1);

		self::assertTrue($rocket->hasExploded());
		self::assertFalse($rocket->isFlaggedForDespawn());

		$baseTick->invoke($rocket, 1);
		self::assertTrue($rocket->isFlaggedForDespawn());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, true);
	}

	public function testExplodesOnLivingEntityCollisionWhenShotFromCrossbow() : void{
		$target = (new ReflectionClass(DummyFireworkTarget::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);
		(new ReflectionProperty(Entity::class, "id"))->setValue($target, 501);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([$target]);

		$rocket = $this->createRocket($world);
		$rocket->setShotFromCrossbow(true);

		$baseTick = new ReflectionMethod(FireworkRocket::class, "entityBaseTick");
		$baseTick->invoke($rocket, 1);

		self::assertTrue($rocket->hasExploded());
		self::assertFalse($rocket->isFlaggedForDespawn());

		$baseTick->invoke($rocket, 1);
		self::assertTrue($rocket->isFlaggedForDespawn());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($rocket, true);
	}

	public function testCrossbowRocketRaytracesBlockCollision() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([]);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			if($x === 2 && $y === 0 && $z === 0){
				$block = VanillaBlocks::STONE();
				$block->position($world, 2, 0, 0);
				return $block;
			}
			$air = VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$rocket = $this->createRocket($world);
		$rocket->setShotFromCrossbow(true);

		$moveMethod = new ReflectionMethod(FireworkRocket::class, "move");
		$moveMethod->invoke($rocket, 4.0, 0.0, 0.0);

		self::assertTrue($rocket->isCollided);
		self::assertEquals(2.0, $rocket->getLocation()->x);
	}

	public function testCrossbowRocketRaytracesEntityCollision() : void{
		$targetPos = new Vector3(2, 0, 0);
		$target = $this->createLivingTarget(502, $targetPos);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([$target]);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$air = VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$rocket = $this->createRocket($world);
		$rocket->setShotFromCrossbow(true);

		$moveMethod = new ReflectionMethod(FireworkRocket::class, "move");
		$moveMethod->invoke($rocket, 4.0, 0.0, 0.0);

		self::assertTrue($rocket->isCollided);
		self::assertLessThan(2.0, $rocket->getLocation()->x);
		self::assertGreaterThanOrEqual(1.0, $rocket->getLocation()->x);
	}

	public function testCrossbowRocketIgnoresShooterDuringFirstTicks() : void{
		$shooter = $this->createLivingTarget(99, new Vector3(0.5, 0, 0));

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([$shooter]);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$air = VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$rocket = $this->createRocket($world);
		$rocket->setOwningEntity($shooter);
		$rocket->setShotFromCrossbow(true);
		(new ReflectionProperty(Entity::class, "ticksLived"))->setValue($rocket, 2);

		$moveMethod = new ReflectionMethod(FireworkRocket::class, "move");
		$moveMethod->invoke($rocket, 4.0, 0.0, 0.0);

		self::assertFalse($rocket->isCollided);
		self::assertEquals(4.0, $rocket->getLocation()->x);
	}

	public function testExplodeAttributionToCrossbowShooter() : void{
		$rocket = null;
		$worldManager = $this->createMock(WorldManager::class);
		$server = $this->createMock(Server::class);
		$server->method("getWorldManager")->willReturn($worldManager);

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlock")->willReturn(VanillaBlocks::AIR());
		$world->method("getServer")->willReturn($server);

		$shooter = $this->createLivingTarget(99, new Vector3(0, 0, 0), $world);
		$target = $this->createLivingTarget(503, new Vector3(1, 0, 0), $world);

		$world->method("getCollidingEntities")->willReturn([$target]);

		$worldManager->method("findEntity")->willReturnCallback(function(int $id) use ($shooter, $target, &$rocket) : ?Entity{
			if($id === $shooter->getId()){
				return $shooter;
			}
			if($id === $target->getId()){
				return $target;
			}
			if($rocket !== null && $id === $rocket->getId()){
				return $rocket;
			}
			return null;
		});

		$explosion = new FireworkRocketExplosion(
			FireworkRocketType::SMALL_BALL,
			[DyeColor::RED],
			[],
			false,
			false
		);

		$rocket = $this->createRocket($world, $server);
		$rocket->setOwningEntity($shooter);
		$rocket->setExplosions([$explosion]);

		$rocket->explode();

		self::assertNotNull($target->lastDamageEvent);
		self::assertInstanceOf(EntityDamageByChildEntityEvent::class, $target->lastDamageEvent);
		self::assertSame($shooter, $target->lastDamageEvent->getDamager());
		self::assertSame($rocket, $target->lastDamageEvent->getChild());
		self::assertSame(EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $target->lastDamageEvent->getCause());
		self::assertGreaterThan(0.0, $target->lastDamageEvent->getBaseDamage());
	}

	public function testStarlessCrossbowRocketDealsNoDamage() : void{
		$shooter = $this->createLivingTarget(99, new Vector3(0, 0, 0));
		$target = $this->createLivingTarget(504, new Vector3(1, 0, 0));

		$server = $this->createMock(Server::class);
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([$target]);
		$world->method("getBlock")->willReturn(VanillaBlocks::AIR());
		$world->method("getServer")->willReturn($server);

		$rocket = $this->createRocket($world, $server);
		$rocket->setOwningEntity($shooter);
		$rocket->setExplosions([]);

		$rocket->explode();

		self::assertNull($target->lastDamageEvent);
	}

	public function testFireworkParticlesAnimationEncodesEntityPosition() : void{
		$pos = new Vector3(12.5, 64.0, -8.25);
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);

		$rocket = $this->createRocket($world);
		(new ReflectionProperty(Entity::class, "location"))->setValue($rocket, new Location($pos->x, $pos->y, $pos->z, $world, 0.0, 0.0));

		$anim = new FireworkParticlesAnimation($rocket);
		$packets = $anim->encode();

		self::assertCount(1, $packets);
		self::assertInstanceOf(ActorEventPacket::class, $packets[0]);
		self::assertSame($rocket->getId(), $packets[0]->actorRuntimeId);
		self::assertSame(ActorEvent::FIREWORK_PARTICLES, $packets[0]->eventId);
		self::assertNotNull($packets[0]->firePosition);
		self::assertTrue($pos->equals($packets[0]->firePosition));
	}

	public function testStarlessRocketStopsAndPlaysSound() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getCollidingEntities")->willReturn([]);
		$world->expects(self::once())
			->method("addSound")
			->with(
				self::callback(fn(Vector3 $pos) => true),
				self::isInstanceOf(FireworkExplosionSound::class)
			);

		$rocket = $this->createRocket($world);
		$rocket->setExplosions([]);

		self::assertFalse($rocket->hasExploded());
		$rocket->explode();
		self::assertTrue($rocket->hasExploded());
		self::assertEquals(new Vector3(0, 0, 0), $rocket->getMotion());
	}
}
