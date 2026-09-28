<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\goal\MoveTowardsTargetGoal;
use pocketmine\entity\Entity;
use pocketmine\world\Position;
use ReflectionProperty;

final class MoveTowardsTargetGoalTest extends TestCase{
	public function testApproachesOutsideDesiredRadiusAndStopsAtIt() : void{
		$targetPosition = new Position(10, 1, 0, null);
		$target = $this->getMockBuilder(AddonEntity::class)->disableOriginalConstructor()->onlyMethods(["getPosition", "isClosed", "isAlive", "__destruct"])->getMock();
		$target->method("getPosition")->willReturnCallback(static function() use (&$targetPosition) : Position{ return $targetPosition; });
		$target->method("isClosed")->willReturn(false);
		$target->method("isAlive")->willReturn(true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);
		$mob = $this->getMockBuilder(AddonEntity::class)->disableOriginalConstructor()->onlyMethods(["getPosition", "getTargetEntity", "__destruct"])->getMock();
		$mob->method("getPosition")->willReturn(new Position(0, 1, 0, null));
		$mob->method("getTargetEntity")->willReturn($target);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);
		$goal = new MoveTowardsTargetGoal($mob, ["within_radius" => 4], 2);
		self::assertTrue($goal->canStart());
		self::assertTrue($goal->canContinue());
		$targetPosition = new Position(4, 1, 0, null);
		self::assertFalse($goal->canContinue());
	}
}
