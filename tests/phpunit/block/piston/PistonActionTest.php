<?php

/*
 *
 *     _             _               
 *    / \   _ __ ___ | |__   ___ _ __ 
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |   
 * /_/   \_\_| |_| |_|_.__/ \___|_|   
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block\piston;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\Piston;
use pocketmine\block\PistonHead;
use pocketmine\block\StickyPiston;
use pocketmine\block\Torch;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\item\StringToItemParser;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class TestPistonEntity extends Entity{
	public static function getNetworkTypeId() : string{ return "minecraft:test"; }
	protected function getInitialGravity() : float{ return 0.04; }
	protected function getInitialDragMultiplier() : float{ return 0.02; }
	protected function getInitialSizeInfo() : \pocketmine\entity\EntitySizeInfo{ return new \pocketmine\entity\EntitySizeInfo(1.0, 1.0); }

	public ?Vector3 $teleportedTo = null;

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		$this->teleportedTo = clone $pos;
		return true;
	}
}

final class PistonActionTest extends TestCase{

	/**
	 * @param Entity[] $entities
	 * @return array{World, array<string, Block>}
	 */
	private function createTestWorld(array $entities = []) : array{
		/** @var array<string, Block> $blocks */
		$blocks = [];
		$world = $this->getMockBuilder(World::class)
			->disableOriginalConstructor()
			->onlyMethods(["getBlockAt", "setBlockAt", "getBlock", "setBlock", "isInWorld", "isChunkLoaded", "isLoaded", "useBreakOn", "addSound", "getNearbyEntities"])
			->getMock();

		$world->method("isInWorld")->willReturn(true);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("isLoaded")->willReturn(true);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use (&$blocks, $world) : Block{
			$key = "$x:$y:$z";
			if(isset($blocks[$key])){
				$b = clone $blocks[$key];
				$b->position($world, $x, $y, $z);
				return $b;
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("getBlock")->willReturnCallback(function(Vector3 $pos) use ($world) : Block{
			return $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block) use (&$blocks, $world) : bool{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$blocks[$key] = $clone;
			return true;
		});

		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) use (&$blocks, $world) : bool{
			$key = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
			$clone = clone $block;
			$clone->position($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
			$blocks[$key] = $clone;
			return true;
		});

		$world->method("useBreakOn")->willReturnCallback(function(Vector3 $pos) use (&$blocks) : bool{
			$key = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
			unset($blocks[$key]);
			return true;
		});

		$world->method("addSound");

		$world->method("getNearbyEntities")->willReturnCallback(function(AxisAlignedBB $bb) use (&$entities) : array{
			$result = [];
			foreach($entities as $entity){
				if($entity->getBoundingBox()->intersectsWith($bb)){
					$result[] = $entity;
				}
			}
			return $result;
		});

		return [$world, $blocks];
	}

	private function setBlock(World $world, Vector3 $pos, Block $block) : void{
		$block->position($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $block);
	}

	public function testPistonExtendPushSingleBlock() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		self::assertTrue($piston->extend());
		self::assertTrue($piston->isExtended());

		// (0, 64, 1) should now have PistonHead
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertInstanceOf(PistonHead::class, $head);
		self::assertSame(Facing::SOUTH, $head->getFacing());
		self::assertFalse($head->isSticky());

		// (0, 64, 2) should now have Stone
		$stone = $world->getBlock(new Vector3(0, 64, 2));
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $stone->getTypeId());
	}

	public function testPistonRetractNonSticky() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		$piston->extend();
		self::assertTrue($piston->retract());
		self::assertFalse($piston->isExtended());

		// Head should be gone
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $head->getTypeId());

		// Stone remains at (0, 64, 2)
		$stone = $world->getBlock(new Vector3(0, 64, 2));
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $stone->getTypeId());
	}

	public function testStickyPistonExtendAndRetractPull() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$sticky = VanillaBlocks::STICKY_PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $sticky);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		self::assertTrue($sticky->extend());

		// Head is sticky
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertInstanceOf(PistonHead::class, $head);
		self::assertTrue($head->isSticky());

		// Stone moved to (0, 64, 2)
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlock(new Vector3(0, 64, 2))->getTypeId());

		// Retract pulls stone back to (0, 64, 1)
		self::assertTrue($sticky->retract());
		self::assertFalse($sticky->isExtended());

		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $world->getBlock(new Vector3(0, 64, 1))->getTypeId());
		self::assertSame(VanillaBlocks::AIR()->getTypeId(), $world->getBlock(new Vector3(0, 64, 2))->getTypeId());
	}

	public function testPistonBreaksFlowableOnPush() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::TORCH());

		self::assertTrue($piston->extend());
		$head = $world->getBlock(new Vector3(0, 64, 1));
		self::assertInstanceOf(PistonHead::class, $head);
	}

	public function testPistonBlockedByImmovable() : void{
		[$world, $blocks] = $this->createTestWorld();
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::BEDROCK());

		self::assertFalse($piston->extend());
		self::assertFalse($piston->isExtended());
		self::assertSame(VanillaBlocks::BEDROCK()->getTypeId(), $world->getBlock(new Vector3(0, 64, 1))->getTypeId());
	}

	public function testEntityDisplacementOnExtend() : void{
		$entityPos = new Vector3(0.5, 64.0, 2.5);
		$entityBB = new AxisAlignedBB(0.2, 64.0, 2.2, 0.8, 65.8, 2.8);

		$mockEntity = (new \ReflectionClass(TestPistonEntity::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($mockEntity, true);
		(new \ReflectionProperty(Entity::class, "id"))->setValue($mockEntity, 999);
		(new \ReflectionProperty(Entity::class, "boundingBox"))->setValue($mockEntity, $entityBB);
		(new \ReflectionProperty(Entity::class, "location"))->setValue($mockEntity, new \pocketmine\entity\Location($entityPos->x, $entityPos->y, $entityPos->z, null, 0.0, 0.0));

		[$world, $blocks] = $this->createTestWorld([$mockEntity]);
		$pos = new Vector3(0, 64, 0);

		$piston = VanillaBlocks::PISTON()->setFacing(Facing::SOUTH);
		$this->setBlock($world, $pos, $piston);
		$this->setBlock($world, new Vector3(0, 64, 1), VanillaBlocks::STONE());

		self::assertTrue($piston->extend());

		// Entity should have been displaced by +1 on Z (SOUTH)
		self::assertNotNull($mockEntity->teleportedTo);
		self::assertEqualsWithDelta(0.5, $mockEntity->teleportedTo->x, 0.001);
		self::assertEqualsWithDelta(64.0, $mockEntity->teleportedTo->y, 0.001);
		self::assertEqualsWithDelta(3.5, $mockEntity->teleportedTo->z, 0.001);
	}

	public function testStringToItemParser() : void{
		$parser = StringToItemParser::getInstance();

		$pistonItem = $parser->parse("piston");
		self::assertNotNull($pistonItem);
		self::assertSame(VanillaBlocks::PISTON()->getTypeId(), $pistonItem->getBlock()->getTypeId());

		$stickyPistonItem = $parser->parse("sticky_piston");
		self::assertNotNull($stickyPistonItem);
		self::assertSame(VanillaBlocks::STICKY_PISTON()->getTypeId(), $stickyPistonItem->getBlock()->getTypeId());
	}

	public function testVanillaBlocksMethods() : void{
		self::assertInstanceOf(Piston::class, VanillaBlocks::PISTON());
		self::assertInstanceOf(StickyPiston::class, VanillaBlocks::STICKY_PISTON());
		self::assertInstanceOf(PistonHead::class, VanillaBlocks::PISTON_HEAD());
	}
}
