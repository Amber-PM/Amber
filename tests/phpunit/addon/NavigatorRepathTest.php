<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\MovementProfile;
use pocketmine\addon\entity\ai\Navigator;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use ReflectionProperty;

final class NavigatorRepathTest extends TestCase{
	public function testMovingGoalRepathsAfterCumulativeDisplacement() : void{
		AddonTimings::init();
		$queries = 0;
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getChunk", "getMinY", "getMaxY"])->getMock();
		$world->method("getChunk")->willReturnCallback(static function(int $x, int $z) use (&$queries) : ?Chunk{ ++$queries; return null; });
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		$entity = $this->getMockBuilder(AddonEntity::class)->disableOriginalConstructor()->onlyMethods(["getWorld", "getPosition", "getSize", "getId", "__destruct"])->getMock();
		$entity->method("getWorld")->willReturn($world);
		$entity->method("getPosition")->willReturn(new Position(0, 1, 0, $world));
		$entity->method("getSize")->willReturn(new EntitySizeInfo(1.8, 0.6));
		$entity->method("getId")->willReturn(901);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$navigator = new Navigator($entity, new MovementProfile(0.2, false, false, false, 3, 24, false));
		(new ReflectionProperty(Navigator::class, "path"))->setValue($navigator, [new Vector3(10, 1, 0)]);
		(new ReflectionProperty(Navigator::class, "goal"))->setValue($navigator, new Vector3(10, 1, 0));
		(new ReflectionProperty(Navigator::class, "lastPlan"))->setValue($navigator, 100);
		for($tick = 101; $tick <= 110; ++$tick){
			$navigator->moveTo(new Vector3(10 + ($tick - 100) * 0.2, 1, 0), 1, $tick);
		}
		self::assertGreaterThan(0, $queries, "Cumulative movement must trigger a new path search");
		self::assertSame(0, (new ReflectionProperty(\pocketmine\addon\entity\ai\Pathfinder::class, "workUsed"))->getValue(), "An early null search must refund its unused node reservation");
	}
}
