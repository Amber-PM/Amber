<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\entity\Location;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;

final class AddonEntityEventMutationTest extends TestCase{
	private function entity() : AddonEntity{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity"])->getMock();
		$world->method("getServer")->willReturn($server);
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:events", "properties" => ["test:state" => ["type" => "int", "range" => [0, 2], "default" => 0]]],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10]],
			"component_groups" => ["test:baby" => ["minecraft:is_baby" => []], "test:seen" => ["minecraft:variant" => ["value" => 1]]],
			"events" => [
				"test:group" => ["sequence" => [["add" => ["component_groups" => ["test:baby"]]], ["filters" => ["test" => "is_baby", "value" => true], "add" => ["component_groups" => ["test:seen"]]]]],
				"test:property" => ["sequence" => [["set_property" => ["test:state" => 1]], ["filters" => ["test" => "int_property", "domain" => "test:state", "value" => 1], "add" => ["component_groups" => ["test:seen"]]]]],
				"test:conflict" => ["sequence" => [["add" => ["component_groups" => ["test:baby"]]], ["remove" => ["component_groups" => ["test:baby"]]], ["add" => ["component_groups" => ["test:baby"]]]]],
				"test:nested" => ["sequence" => [["add" => ["component_groups" => ["test:baby"]]], ["trigger" => "test:check"]]],
				"test:check" => ["filters" => ["test" => "is_baby", "value" => true], "add" => ["component_groups" => ["test:seen"]]],
			],
		]], "events.json", "test");
		return new AddonEntity(new Location(0, 0, 0, $world, 0, 0), $definition);
	}

	public function testGroupAndPropertyFiltersSeePreEventStateUntilUpdate() : void{
		$group = $this->entity();
		$group->triggerEvent("test:group");
		self::assertFalse($group->hasComponent("minecraft:is_baby"));
		self::assertSame(0, $group->getVariant());
		(new ReflectionMethod(AddonEntity::class, "commitPendingMutations"))->invoke($group);
		self::assertTrue($group->hasComponent("minecraft:is_baby"));
		self::assertSame(0, $group->getVariant());
		$group->close();

		$property = $this->entity();
		$property->triggerEvent("test:property");
		self::assertSame(0, $property->getProperty("test:state"));
		self::assertSame(0, $property->getVariant());
		(new ReflectionMethod(AddonEntity::class, "commitPendingMutations"))->invoke($property);
		self::assertSame(1, $property->getProperty("test:state"));
		self::assertSame(0, $property->getVariant());
		$property->close();
	}

	public function testNestedEventCannotObservePendingGroupAndConflictsCommitInOrder() : void{
		$entity = $this->entity();
		$entity->triggerEvent("test:nested");
		self::assertSame(0, $entity->getVariant());
		(new ReflectionMethod(AddonEntity::class, "commitPendingMutations"))->invoke($entity);
		self::assertTrue($entity->hasComponent("minecraft:is_baby"));
		self::assertSame(0, $entity->getVariant());
		$entity->close();
		$entity = $this->entity();
		$entity->triggerEvent("test:conflict");
		(new ReflectionMethod(AddonEntity::class, "commitPendingMutations"))->invoke($entity);
		self::assertTrue($entity->hasComponent("minecraft:is_baby"));
		$entity->close();
	}
}
