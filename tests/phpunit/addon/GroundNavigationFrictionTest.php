<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\ai\MobBrain;
use pocketmine\addon\entity\ai\Navigator;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use ReflectionMethod;
use ReflectionProperty;

final class GroundNavigationFrictionTest extends TestCase{
	private function movedOn(Block $surface) : float{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getBlockAt"])->getMock();
		$world->method("getBlockAt")->willReturn($surface);
		$mob = $this->getMockBuilder(AddonEntity::class)->disableOriginalConstructor()->onlyMethods(["getId", "__destruct"])->getMock();
		$mob->method("getId")->willReturn(812);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, true);
		(new ReflectionProperty(Entity::class, "location"))->setValue($mob, new Location(0.5, 1, 0.5, $world, 0, 0));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($mob, new Vector3(0.4, 0, 0));
		(new ReflectionProperty(Entity::class, "drag"))->setValue($mob, 0.02);
		(new ReflectionProperty(Entity::class, "gravity"))->setValue($mob, 0.08);
		$mob->onGround = true;
		$brain = new MobBrain($mob, []);
		(new ReflectionProperty(AddonEntity::class, "brain"))->setValue($mob, $brain);
		$navigator = $brain->getNavigator();
		(new ReflectionProperty(Navigator::class, "moving"))->setValue($navigator, true);
		(new ReflectionProperty(Navigator::class, "wantX"))->setValue($navigator, 0.2);
		(new ReflectionMethod(AddonEntity::class, "tryChangeMovement"))->invoke($mob);
		return $mob->getMotion()->x;
	}

	public function testGroundSteeringRetainsSurfaceFriction() : void{
		self::assertGreaterThan($this->movedOn(VanillaBlocks::STONE()), $this->movedOn(VanillaBlocks::ICE()));
	}
}
