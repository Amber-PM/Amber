<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\spawn\AddonSpawner;
use pocketmine\addon\spawn\SpawnRule;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AddonSpawnerCapacityTest extends TestCase{
	private function fixture() : array{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$entities = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity", "getViewersForPosition", "getHighestBlockAt", "getBlockAt", "getEntities"])->getMock();
		$world->method("getServer")->willReturn($server);
		$world->method("addEntity")->willReturnCallback(static function(AddonEntity $entity) use (&$entities) : void{ $entities[] = $entity; });
		$world->method("getViewersForPosition")->willReturn([]);
		$world->method("getHighestBlockAt")->willReturn(64);
		$solid = $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods(["isSolid"])->getMock();
		$solid->method("isSolid")->willReturn(true);
		$air = $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods(["isSolid"])->getMock();
		$air->method("isSolid")->willReturn(false);
		$world->method("getBlockAt")->willReturnCallback(static fn(int $x, int $y, int $z) => $y === 64 ? $solid : $air);
		$world->method("getEntities")->willReturnCallback(static function() use (&$entities) : array{ return $entities; });
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => ["description" => ["identifier" => "test:mob"], "components" => ["minecraft:health" => ["value" => 10, "max" => 10]]]], "mob.json", "test");
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "entityDefinitions"))->setValue($manager, ["test:mob" => $definition]);
		$rule = SpawnRule::fromJson(["minecraft:spawn_rules" => ["description" => ["identifier" => "test:mob", "population_control" => "animal"], "conditions" => [["minecraft:spawns_on_surface" => [], "minecraft:herd" => ["min_size" => 3, "max_size" => 3]]]]], "rule.json");
		$spawner = new AddonSpawner($server, $manager, [$rule], 40, ["animal" => 5], []);
		return [$spawner, $manager, $world, $rule, &$entities];
	}

	public function testHerdStopsAtRemainingCategoryCapacity() : void{
		[$spawner, , $world, $rule, $entities] = $this->fixture();
		$condition = $rule->getConditions()[0];
		$spawned = (new ReflectionMethod(AddonSpawner::class, "spawnHerd"))->invoke($spawner, $world, $rule, $condition, SpawnRule::SURFACE, 0, 65, 0, 1);
		self::assertSame(1, $spawned);
		foreach($entities as $entity){ $entity->close(); }
	}

	public function testHerdStopsAtRemainingDensityCapacity() : void{
		[$spawner, $manager, $world, $rule, $entities] = $this->fixture();
		$existing = $manager->createEntity("test:mob", new Location(0.5, 65, 0.5, $world, 0, 0));
		self::assertInstanceOf(AddonEntity::class, $existing);
		$condition = $rule->getConditions()[0];
		$condition["minecraft:density_limit"] = ["surface" => 2];
		$spawned = (new ReflectionMethod(AddonSpawner::class, "spawnHerd"))->invoke($spawner, $world, $rule, $condition, SpawnRule::SURFACE, 0, 65, 0, 3);
		self::assertSame(1, $spawned);
		foreach($entities as $entity){ $entity->close(); }
	}
}
