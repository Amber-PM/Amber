<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\entity\ai\MovementProfile;
use pocketmine\addon\entity\ai\Pathfinder;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class NavigatorBudgetCancellationTest extends TestCase{
	protected function setUp() : void{
		foreach(['budgetTick' => -1, 'budgetUsed' => 0, 'workUsed' => 0, 'waiting' => [], 'waitQueue' => null, 'reserved' => [], 'reservationAttempted' => [], 'outstanding' => []] as $name => $value){
			(new ReflectionProperty(Pathfinder::class, $name))->setValue(null, $value);
		}
	}

	private function entity() : AddonEntity{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(['getServer', 'addEntity', 'removeEntity', 'getNearbyEntities'])->getMock();
		$world->method('getServer')->willReturn($server);
		$world->method('getNearbyEntities')->willReturn([]);
		$definition = AddonEntityDefinition::fromJson(['minecraft:entity' => [
			'description' => ['identifier' => 'test:budget_cancellation'],
			'components' => ['minecraft:health' => ['value' => 10, 'max' => 10], 'minecraft:movement' => ['value' => 0.2]],
		]], 'budget-cancellation.json', 'test');
		return new AddonEntity(new Location(5.5, 64, 5.5, $world, 0, 0), $definition);
	}

	private function queue(AddonEntity $entity) : void{
		for($id = -4; $id < 0; ++$id){
			self::assertTrue(Pathfinder::takeBudget(10000, $id));
			Pathfinder::completeBudget(10000, $id, 400);
		}
		self::assertFalse($entity->getBrain()->getNavigator()->moveTo(new Vector3(20.5, 64, 20.5), 1, 10000));
		self::assertArrayHasKey($entity->getId(), (new ReflectionProperty(Pathfinder::class, 'waiting'))->getValue());
	}

	private function assertCancelled(AddonEntity $entity) : void{
		foreach(['waiting', 'reserved', 'reservationAttempted'] as $name){
			self::assertArrayNotHasKey($entity->getId(), (new ReflectionProperty(Pathfinder::class, $name))->getValue());
		}
		self::assertTrue(Pathfinder::takeBudget(10001, -11));
	}

	public function testStoppingNavigatorCancelsQueuedRequester() : void{
		$entity = $this->entity();
		try{
			$this->queue($entity);
			$entity->getBrain()->getNavigator()->stop();
			$this->assertCancelled($entity);
		}finally{
			$entity->close();
		}
	}

	public function testDisposingEntityCancelsReservedNavigatorRequester() : void{
		$entity = $this->entity();
		try{
			$this->queue($entity);
			self::assertTrue(Pathfinder::takeBudget(10001, -10));
			self::assertArrayHasKey($entity->getId(), (new ReflectionProperty(Pathfinder::class, 'reserved'))->getValue());
			$entity->close();
			$this->assertCancelled($entity);
		}finally{
			$entity->close();
		}
	}

	public function testSwitchingToDirectMovementAbandonsQueuedSearch() : void{
		$entity = $this->entity();
		try{
			$this->queue($entity);
			$navigator = $entity->getBrain()->getNavigator();
			$navigator->setProfile(new MovementProfile(0.2, true, false, false, 3, 24, false));
			self::assertTrue($navigator->moveTo(new Vector3(20.5, 64, 20.5), 1, 10000));
			$this->assertCancelled($entity);
		}finally{
			$entity->close();
		}
	}
}
