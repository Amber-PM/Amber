<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\goal\NearestAttackableTargetGoal;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class GoalOverlappingCanSeeTest extends TestCase{
	public function testOverlappingEntitiesWithMustSeeDoesNotThrowAndCanTarget() : void{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();

		$pos = new Vector3(10.5, 64.0, 10.5);

		$air = $this->getMockBuilder(Block::class)
			->disableOriginalConstructor()
			->onlyMethods(["isSolid", "isTransparent"])
			->getMock();
		$air->method("isSolid")->willReturn(false);
		$air->method("isTransparent")->willReturn(true);

		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods(["getServer", "getBlockAt", "getNearbyEntities"])
			->getMock();
		$world->method("getServer")->willReturn($server);
		$world->method("getBlockAt")->willReturn($air);

		$mobPos = new Position($pos->x, $pos->y, $pos->z, $world);
		$targetPos = new Position($pos->x, $pos->y, $pos->z, $world);

		$target = $this->getMockBuilder(AddonEntity::class)
			->disableOriginalConstructor()
			->onlyMethods(["getPosition", "getEyePos", "isClosed", "isAlive", "__destruct"])
			->getMock();
		$target->method("getPosition")->willReturn($targetPos);
		$target->method("getEyePos")->willReturn($pos);
		$target->method("isClosed")->willReturn(false);
		$target->method("isAlive")->willReturn(true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);

		$world->method("getNearbyEntities")->willReturn([$target]);

		$mob = $this->getMockBuilder(AddonEntity::class)
			->disableOriginalConstructor()
			->onlyMethods(["getPosition", "getEyePos", "getWorld", "getBoundingBox", "isTamed", "__destruct"])
			->getMock();
		$mob->method("getPosition")->willReturn($mobPos);
		$mob->method("getEyePos")->willReturn($pos);
		$mob->method("getWorld")->willReturn($world);
		$mob->method("getBoundingBox")->willReturn(new AxisAlignedBB($pos->x - 0.3, $pos->y, $pos->z - 0.3, $pos->x + 0.3, $pos->y + 1.8, $pos->z + 0.3));
		$mob->method("isTamed")->willReturn(false);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);

		$goal = new NearestAttackableTargetGoal($mob, [
			"entity_types" => [
				[
					"max_dist" => 16.0,
					"must_see" => true,
				],
			],
		], 1);

		self::assertTrue($goal->canStart());
	}

	public function testOverlappingEntitiesInSolidBlockCannotSee() : void{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();

		$pos = new Vector3(10.5, 64.0, 10.5);

		$solid = $this->getMockBuilder(Block::class)
			->disableOriginalConstructor()
			->onlyMethods(["isSolid", "isTransparent"])
			->getMock();
		$solid->method("isSolid")->willReturn(true);
		$solid->method("isTransparent")->willReturn(false);

		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods(["getServer", "getBlockAt", "getNearbyEntities"])
			->getMock();
		$world->method("getServer")->willReturn($server);
		$world->method("getBlockAt")->willReturn($solid);

		$mobPos = new Position($pos->x, $pos->y, $pos->z, $world);
		$targetPos = new Position($pos->x, $pos->y, $pos->z, $world);

		$target = $this->getMockBuilder(AddonEntity::class)
			->disableOriginalConstructor()
			->onlyMethods(["getPosition", "getEyePos", "isClosed", "isAlive", "__destruct"])
			->getMock();
		$target->method("getPosition")->willReturn($targetPos);
		$target->method("getEyePos")->willReturn($pos);
		$target->method("isClosed")->willReturn(false);
		$target->method("isAlive")->willReturn(true);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($target, true);

		$world->method("getNearbyEntities")->willReturn([$target]);

		$mob = $this->getMockBuilder(AddonEntity::class)
			->disableOriginalConstructor()
			->onlyMethods(["getPosition", "getEyePos", "getWorld", "getBoundingBox", "isTamed", "__destruct"])
			->getMock();
		$mob->method("getPosition")->willReturn($mobPos);
		$mob->method("getEyePos")->willReturn($pos);
		$mob->method("getWorld")->willReturn($world);
		$mob->method("getBoundingBox")->willReturn(new AxisAlignedBB($pos->x - 0.3, $pos->y, $pos->z - 0.3, $pos->x + 0.3, $pos->y + 1.8, $pos->z + 0.3));
		$mob->method("isTamed")->willReturn(false);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);

		$goal = new NearestAttackableTargetGoal($mob, [
			"entity_types" => [
				[
					"max_dist" => 16.0,
					"must_see" => true,
				],
			],
		], 1);

		self::assertFalse($goal->canStart());
	}
}
