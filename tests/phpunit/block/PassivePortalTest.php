<?php

declare(strict_types=1);

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\world\World;
use ReflectionProperty;

final class PassivePortalTest extends TestCase{

	public function testExistingPortalBlocksDoNotHandleEntityMovement() : void{
		$entity = $this->createMock(Entity::class);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$entity->expects(self::never())->method("teleport");
		foreach([VanillaBlocks::NETHER_PORTAL(), VanillaBlocks::END_PORTAL()] as $portal){
			self::assertFalse($portal->hasEntityCollision());
			self::assertTrue($portal->onEntityInside($entity));
		}
	}

	public function testFireDoesNotActivateObsidianFrame() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::OBSIDIAN());
		$world->expects(self::never())->method("setBlock");
		$world->expects(self::once())->method("scheduleDelayedBlockUpdate");
		$fire = VanillaBlocks::FIRE();
		$fire->position($world, 0, 1, 0);
		$fire->onNearbyBlockChange();
	}
}
