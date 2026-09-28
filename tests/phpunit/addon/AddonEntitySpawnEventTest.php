<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\script\CommandBridge;
use pocketmine\addon\script\ScriptHost;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AddonEntitySpawnEventTest extends TestCase{
	private static bool $registered = false;
	private function entity() : AddonEntity{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity"])->getMock();
		$world->method("getServer")->willReturn($server);
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:spawn"],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10]],
			"component_groups" => ["test:normal" => ["minecraft:variant" => ["value" => 1]], "test:explicit" => ["minecraft:variant" => ["value" => 2]]],
			"events" => ["minecraft:entity_spawned" => ["add" => ["component_groups" => ["test:normal"]]], "test:chosen" => ["add" => ["component_groups" => ["test:explicit"]]]],
		]], "spawn.json", "test");
		if(!self::$registered){
			EntityFactory::getInstance()->register(AddonEntity::class, static fn(World $w, \pocketmine\nbt\tag\CompoundTag $nbt) : AddonEntity => new AddonEntity(new Location(0, 0, 0, $w, 0, 0), $definition, $nbt), ["test:spawn_addon"]);
			self::$registered = true;
		}
		return new AddonEntity(new Location(0, 0, 0, $world, 0, 0), $definition);
	}

	public function testSpawnIntentAndReloadDoNotReplayEvents() : void{
		foreach([[false, null, 1], [true, null, 0], [true, "test:chosen", 2]] as [$manual, $event, $expected]){
			$entity = $this->entity();
			if($manual){
				$entity->setSpawnEvent($event);
			}
			$firstUpdate = new ReflectionMethod(AddonEntity::class, "onFirstUpdate");
			$commit = new ReflectionMethod(AddonEntity::class, "commitPendingMutations");
			$firstUpdate->invoke($entity, 1);
			$commit->invoke($entity);
			self::assertSame($expected, $entity->getVariant());
			$firstUpdate->invoke($entity, 2);
			$commit->invoke($entity);
			self::assertSame($expected, $entity->getVariant());
			$saved = $entity->saveNBT();
			$entity->close();
			$reloaded = $this->entity();
			(new ReflectionMethod(AddonEntity::class, "initEntity"))->invoke($reloaded, $saved);
			$groups = $reloaded->getActiveComponentGroups();
			$firstUpdate->invoke($reloaded, 3);
			$commit->invoke($reloaded);
			self::assertSame($groups, $reloaded->getActiveComponentGroups());
			$reloaded->close();
		}
	}

	public function testSummonCommandPreservesManualEventIntent() : void{
		$seed = $this->entity();
		$definition = $seed->getAddonDefinition();
		$seed->close();
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$spawned = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity", "getViewersForPosition"])->getMock();
		$world->method("getServer")->willReturn($server);
		$world->method("getViewersForPosition")->willReturn([]);
		$world->method("addEntity")->willReturnCallback(static function(AddonEntity $entity) use (&$spawned) : void{ $spawned[] = $entity; });
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "entityDefinitions"))->setValue($manager, ["test:spawn" => $definition]);
		$host = (new ReflectionClass(ScriptHost::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(ScriptHost::class, "manager"))->setValue($host, $manager);
		(new ReflectionProperty(AddonManager::class, "scriptHost"))->setValue($manager, $host);
		$bridge = new CommandBridge($server, $manager);
		foreach([["summon test:spawn", 0], ["summon test:spawn test:chosen", 2]] as [$command, $expected]){
			self::assertSame(1, $bridge->run($command, null, $world, new Vector3(0, 0, 0)));
			$entity = array_pop($spawned);
			self::assertInstanceOf(AddonEntity::class, $entity);
			(new ReflectionMethod(AddonEntity::class, "onFirstUpdate"))->invoke($entity, 1);
			(new ReflectionMethod(AddonEntity::class, "commitPendingMutations"))->invoke($entity);
			self::assertSame($expected, $entity->getVariant());
			$entity->close();
		}
	}
}
