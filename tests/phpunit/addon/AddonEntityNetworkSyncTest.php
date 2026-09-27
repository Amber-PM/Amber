<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AddonEntityNetworkSyncTest extends TestCase{
	private function entity(bool $projectile = false) : AddonEntity{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity", "getNearbyEntities"])->getMock();
		$world->method("getServer")->willReturn($server);
		$world->method("getNearbyEntities")->willReturn([]);
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:network_sync", "properties" => ["test:state" => ["type" => "int", "range" => [0, 2], "default" => 0]]],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10]] + ($projectile ? ["minecraft:projectile" => []] : []),
		]], "network-sync.json", "test");
		$entity = new AddonEntity(new Location(0, 0, 0, $world, 0, 0), $definition);
		(new ReflectionProperty(Server::class, "tickCounter"))->setValue($server, $entity->getId() % 20 + 1);
		return $entity;
	}

	/** @param list<ClientboundPacket> $packets */
	private function viewer(AddonEntity $entity, array &$packets) : Player{
		$converter = $this->getMockBuilder(TypeConverter::class)->disableOriginalConstructor()->onlyMethods(["coreItemStackToNet"])->getMock();
		$converter->method("coreItemStackToNet")->willReturnCallback(static fn(Item $item) : ItemStack => $item->isNull() ? ItemStack::null() : new ItemStack(5, 0, 1, 0, ""));
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->getMock();
		$session->method("getTypeConverter")->willReturn($converter);
		$session->method("getEntityEventBroadcaster")->willReturn($this->createMock(EntityEventBroadcaster::class));
		$session->method("sendDataPacket")->willReturnCallback(static function(ClientboundPacket $packet) use (&$packets) : bool{
			$packets[] = $packet;
			return true;
		});
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods(["getNetworkSession"])->getMock();
		$player->method("getNetworkSession")->willReturn($session);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		(new ReflectionProperty(Entity::class, "hasSpawned"))->setValue($entity, [spl_object_id($player) => $player]);
		return $player;
	}

	public function testClearingLastMainHandItemSendsEmptyEquipment() : void{
		$entity = $this->entity();
		$packets = [];
		$viewer = $this->viewer($entity, $packets);
		try{
			$entity->setMainHandItem(new Item(new ItemIdentifier(100)));
			self::assertCount(2, $packets);
			self::assertInstanceOf(MobEquipmentPacket::class, $packets[0]);
			self::assertSame(ContainerIds::INVENTORY, $packets[0]->windowId);
			self::assertFalse($packets[0]->item->getItemStack()->isNull());
			$entity->setMainHandItem(VanillaItems::AIR());
			self::assertCount(4, $packets);
			self::assertInstanceOf(MobEquipmentPacket::class, $packets[2]);
			self::assertSame(ContainerIds::INVENTORY, $packets[2]->windowId);
			self::assertTrue($packets[2]->item->getItemStack()->isNull());
		}finally{
			$entity->close();
			unset($viewer);
		}
	}

	public function testClearingLastOffHandItemSendsEmptyEquipment() : void{
		$entity = $this->entity();
		$packets = [];
		$viewer = $this->viewer($entity, $packets);
		try{
			$entity->setOffHandItem(new Item(new ItemIdentifier(100)));
			self::assertCount(2, $packets);
			self::assertInstanceOf(MobEquipmentPacket::class, $packets[1]);
			self::assertSame(ContainerIds::OFFHAND, $packets[1]->windowId);
			self::assertFalse($packets[1]->item->getItemStack()->isNull());
			$entity->setOffHandItem(VanillaItems::AIR());
			self::assertCount(4, $packets);
			self::assertInstanceOf(MobEquipmentPacket::class, $packets[3]);
			self::assertSame(ContainerIds::OFFHAND, $packets[3]->windowId);
			self::assertTrue($packets[3]->item->getItemStack()->isNull());
		}finally{
			$entity->close();
			unset($viewer);
		}
	}

	public function testProjectilePropertyMutationReachesExistingViewerOnce() : void{
		$entity = $this->entity(true);
		$packets = [];
		$viewer = $this->viewer($entity, $packets);
		try{
			(new ReflectionMethod($entity, "sendSpawnPacket"))->invoke($entity, $viewer);
			self::assertInstanceOf(AddActorPacket::class, $packets[0]);
			self::assertCount(1, $packets);
			self::assertSame([0 => 0], $packets[0]->syncedProperties->getIntProperties());
			$packets = [];
			$entity->setProperty("test:state", 2);
			(new ReflectionMethod($entity, "runtimeTick"))->invoke($entity, 1);
			self::assertCount(1, $packets);
			self::assertInstanceOf(SetActorDataPacket::class, $packets[0]);
			self::assertSame($entity->getId(), $packets[0]->actorRuntimeId);
			self::assertSame([0 => 2], $packets[0]->syncedProperties->getIntProperties());
			(new ReflectionMethod($entity, "runtimeTick"))->invoke($entity, 1);
			self::assertCount(1, $packets);
		}finally{
			$entity->close();
			unset($viewer);
		}
	}

	public function testDespawningProjectileDoesNotSendStalePropertyMutation() : void{
		$entity = $this->entity(true);
		$packets = [];
		$viewer = $this->viewer($entity, $packets);
		try{
			$entity->setProperty("test:state", 2);
			(new ReflectionProperty(AddonEntity::class, "projectileAge"))->setValue($entity, 1200);
			(new ReflectionMethod($entity, "runtimeTick"))->invoke($entity, 1);
			self::assertTrue($entity->isFlaggedForDespawn());
			self::assertSame([], $packets);
		}finally{
			$entity->close();
			unset($viewer);
		}
	}
}
