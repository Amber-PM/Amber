<?php
declare(strict_types=1);

namespace pocketmine\addon;

use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\script\ScriptHost;
use pocketmine\entity\Location;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;

final class AddonEntityScriptPropertyTest extends TestCase{
	private function entity() : AddonEntity{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity"])->getMock();
		$world->method("getServer")->willReturn($server);
		$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:properties", "properties" => [
				"test:bool" => ["type" => "bool", "default" => false],
				"test:int" => ["type" => "int", "range" => [0, 2], "default" => 0],
				"test:float" => ["type" => "float", "range" => [0.0, 2.0], "default" => 0.0],
				"test:enum" => ["type" => "enum", "values" => ["old", "new"], "default" => "old"],
			]],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10]],
		]], "properties.json", "test");
		return new AddonEntity(new Location(0, 0, 0, $world, 0, 0), $definition);
	}

	public function testInvalidAssignmentsThrowWithoutMutation() : void{
		$entity = $this->entity();
		foreach([
			["test:missing", 1, InvalidArgumentException::class],
			["test:bool", 1, InvalidArgumentException::class],
			["test:int", 3, OutOfRangeException::class],
			["test:float", 2.1, OutOfRangeException::class],
			["test:enum", "invalid", InvalidArgumentException::class],
		] as [$name, $value, $exception]){
			try{
				$entity->setScriptProperty($name, $value);
				self::fail("Expected $exception for $name");
			}catch(InvalidArgumentException|OutOfRangeException $error){
				self::assertInstanceOf($exception, $error);
			}
		}
		self::assertSame(["test:bool" => false, "test:int" => 0, "test:float" => 0.0, "test:enum" => "old"], $entity->getProperties());
		$entity->close();
	}

	public function testValidAssignmentsCommitAtUpdateBoundary() : void{
		$entity = $this->entity();
		foreach(["test:bool" => true, "test:int" => 2, "test:float" => 1.5, "test:enum" => "new"] as $name => $value){
			$entity->setScriptProperty($name, $value);
		}
		self::assertSame(0, $entity->getProperty("test:int"));
		(new ReflectionMethod(AddonEntity::class, "commitPendingMutations"))->invoke($entity);
		self::assertSame(["test:bool" => true, "test:int" => 2, "test:float" => 1.5, "test:enum" => "new"], $entity->getProperties());
		$entity->close();
	}

	public function testResetReturnsDefaultBeforeThePendingValueCommits() : void{
		$entity = $this->entity();
		$commit = new ReflectionMethod(AddonEntity::class, "commitPendingMutations");
		$entity->setScriptProperty("test:int", 2);
		$commit->invoke($entity);
		self::assertSame(2, $entity->getProperty("test:int"));
		self::assertSame(0, $entity->resetScriptProperty("test:int"));
		self::assertSame(2, $entity->getProperty("test:int"));
		$commit->invoke($entity);
		self::assertSame(0, $entity->getProperty("test:int"));
		$entity->close();
	}

	public function testPropertyFailuresCarryStructuredScriptErrorTypes() : void{
		$type = new ReflectionMethod(ScriptHost::class, "propertyErrorType");
		self::assertSame("InvalidArgumentError", $type->invoke(null, "sprop", new InvalidArgumentException("wrong type")));
		self::assertSame("ArgumentOutOfBoundsError", $type->invoke(null, "sprop", new OutOfRangeException("outside range")));
		self::assertSame("InvalidArgumentError", $type->invoke(null, "rprop", new InvalidArgumentException("missing property")));
		self::assertNull($type->invoke(null, "other", new InvalidArgumentException("unchanged legacy call")));
	}

	public function testResetRejectsUndeclaredPropertyWithoutQueueingMutation() : void{
		$entity = $this->entity();
		try{
			$entity->resetScriptProperty("test:missing");
			self::fail("Resetting an undeclared property must throw");
		}catch(InvalidArgumentException){
			self::assertSame(0, $entity->getProperty("test:int"));
			self::assertSame([], (new \ReflectionProperty(AddonEntity::class, "pendingMutations"))->getValue($entity));
		}finally{
			$entity->close();
		}
	}
}
