<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AddonEntityReloadStateTest extends TestCase{
	private function roundTrip(?float $health = null) : array{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Server::class, "tickCounter"))->setValue($server, 100);
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity"])->getMock();
		$world->method("getServer")->willReturn($server);
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:reload_state"],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10]],
			"component_groups" => ["test:loaded" => [
				"minecraft:variant" => ["value" => 7],
				"minecraft:health" => ["value" => 30, "max" => 30],
				"minecraft:inventory" => ["inventory_size" => 6],
				"minecraft:transformation" => ["into" => "test:other", "delay" => 200],
				"minecraft:scheduler" => ["min_delay_secs" => 5, "max_delay_secs" => 5, "scheduled_events" => [["event" => "test:scheduled"]]],
			], "test:fired" => ["minecraft:variant" => ["value" => 9]]],
			"events" => ["test:scheduled" => ["add" => ["component_groups" => ["test:fired"]]]],
		]], "reload-state.json", "test");
		EntityFactory::getInstance()->register(AddonEntity::class, static fn(World $w, CompoundTag $nbt) : AddonEntity => new AddonEntity(new Location(0, 0, 0, $w, 0, 0), $definition, $nbt), [AddonEntity::SAVE_ID]);
		$original = new AddonEntity(new Location(0, 0, 0, $world, 0, 0), $definition);
		$original->addComponentGroup("test:loaded");
		if($health !== null){
			$original->setHealth($health);
		}
		$original->getInventory()?->setItem(2, VanillaItems::DIAMOND());
		$saved = $original->saveNBT();
		$original->close();
		$reloaded = EntityFactory::getInstance()->createFromData($world, $saved);
		self::assertInstanceOf(AddonEntity::class, $reloaded);
		return [$reloaded, $server];
	}

	public function testSavedGroupsRebuildStructuralAndNetworkState() : void{
		[$entity] = $this->roundTrip();
		try{
			self::assertSame(["test:loaded"], $entity->getActiveComponentGroups());
			self::assertTrue($entity->hasComponent("minecraft:inventory"));
			self::assertSame(6, $entity->getInventory()?->getSize());
			self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $entity->getInventory()?->getItem(2)->getTypeId());
			self::assertSame(30, $entity->getMaxHealth());
			self::assertSame(7, $entity->getVariant());
			$data = (new ReflectionMethod($entity, "getAllNetworkData"))->invoke($entity);
			self::assertInstanceOf(IntMetadataProperty::class, $data[EntityMetadataProperties::VARIANT]);
			self::assertSame(7, $data[EntityMetadataProperties::VARIANT]->getValue());
		}finally{
			$entity->close();
		}
	}

	public function testReloadDoesNotReplayGroupOnlyTransformation() : void{
		[$entity] = $this->roundTrip();
		try{
			self::assertTrue($entity->hasComponent("minecraft:transformation"));
			self::assertNull((new ReflectionProperty(AddonEntity::class, "transformAt"))->getValue($entity));
		}finally{
			$entity->close();
		}
	}

	public function testSavedCurrentHealthUsesRestoredGroupMaximum() : void{
		foreach([27.0, 30.0] as $savedHealth){
			[$entity] = $this->roundTrip($savedHealth);
			try{
				self::assertSame(30, $entity->getMaxHealth());
				self::assertSame($savedHealth, $entity->getHealth());
			}finally{
				$entity->close();
			}
		}
	}

	public function testReloadedSchedulerWaitsForItsConfiguredDelay() : void{
		[$entity, $server] = $this->roundTrip();
		try{
			$commit = new ReflectionMethod(AddonEntity::class, "commitPendingMutations");
			foreach([100, 199] as $tick){
				(new ReflectionProperty(Server::class, "tickCounter"))->setValue($server, $tick);
				$entity->getFeatures()->tick($tick);
				$commit->invoke($entity);
				self::assertSame(["test:loaded"], $entity->getActiveComponentGroups());
			}
			(new ReflectionProperty(Server::class, "tickCounter"))->setValue($server, 200);
			$entity->getFeatures()->tick(200);
			$commit->invoke($entity);
			self::assertSame(["test:loaded", "test:fired"], $entity->getActiveComponentGroups());
			self::assertSame(9, $entity->getVariant());
		}finally{
			$entity->close();
		}
	}
}
