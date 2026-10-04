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

namespace pocketmine\block\portal;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\EndPortal;
use pocketmine\block\EndPortalFrame;
use pocketmine\block\utils\SupportType;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\EnderEye;
use pocketmine\item\ItemUseResult;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\portal\EndPortalDetector;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;

final class EndPortalBlockTest extends TestCase{

	/** @var array<string, Block> */
	private array $blocks = [];

	private function createMockWorld() : World{
		$this->blocks = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt",
			"setBlockAt",
			"isInWorld",
			"addSound"
		])->getMock();

		$world->method("isInWorld")->willReturn(true);

		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world) : Block{
			$key = "$x:$y:$z";
			if(isset($this->blocks[$key])){
				$b = clone $this->blocks[$key];
				$b->position($world, $x, $y, $z);
				return $b;
			}
			$air = clone VanillaBlocks::AIR();
			$air->position($world, $x, $y, $z);
			return $air;
		});

		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, Block $block) use ($world) : bool{
			$key = "$x:$y:$z";
			$clone = clone $block;
			$clone->position($world, $x, $y, $z);
			$this->blocks[$key] = $clone;
			return true;
		});

		$world->method("addSound")->willReturnCallback(function(Vector3 $pos, Sound $sound) : void{
			// NOOP
		});

		return $world;
	}

	public function testEndPortalProperties() : void{
		$portal = VanillaBlocks::END_PORTAL();
		self::assertInstanceOf(EndPortal::class, $portal);
		self::assertSame(15, $portal->getLightLevel());
		self::assertFalse($portal->isSolid());
		self::assertEmpty($portal->getCollisionBoxes());
		self::assertSame(SupportType::NONE, $portal->getSupportType(Facing::UP));
		self::assertEmpty($portal->getDrops(VanillaItems::DIAMOND_PICKAXE()));
	}

	private function buildFrame(World $world, Vector3 $center, bool $withEyes = true) : void{
		$cx = $center->getFloorX();
		$cy = $center->getFloorY();
		$cz = $center->getFloorZ();

		// North side (Z = cz - 2): facing SOUTH
		for($x = $cx - 1; $x <= $cx + 1; ++$x){
			$frame = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::SOUTH)->setEye($withEyes);
			$world->setBlockAt($x, $cy, $cz - 2, $frame);
		}

		// South side (Z = cz + 2): facing NORTH
		for($x = $cx - 1; $x <= $cx + 1; ++$x){
			$frame = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::NORTH)->setEye($withEyes);
			$world->setBlockAt($x, $cy, $cz + 2, $frame);
		}

		// West side (X = cx - 2): facing EAST
		for($z = $cz - 1; $z <= $cz + 1; ++$z){
			$frame = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::EAST)->setEye($withEyes);
			$world->setBlockAt($cx - 2, $cy, $z, $frame);
		}

		// East side (X = cx + 2): facing WEST
		for($z = $cz - 1; $z <= $cz + 1; ++$z){
			$frame = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::WEST)->setEye($withEyes);
			$world->setBlockAt($cx + 2, $cy, $z, $frame);
		}
	}

	public function testEndPortalDetectionAndActivation() : void{
		$world = $this->createMockWorld();
		$center = new Vector3(0, 64, 0);

		$this->buildFrame($world, $center, true);

		self::assertTrue(EndPortalDetector::isFrameComplete($world, $center));

		$activated = EndPortalDetector::activatePortal($world, $center);
		self::assertTrue($activated);

		// Assert all 3x3 blocks are EndPortal
		for($x = -1; $x <= 1; ++$x){
			for($z = -1; $z <= 1; ++$z){
				$b = $world->getBlockAt($x, 64, $z);
				self::assertInstanceOf(EndPortal::class, $b);
			}
		}
	}

	public function testIncompleteFrameMissingEye() : void{
		$world = $this->createMockWorld();
		$center = new Vector3(10, 64, 10);

		$this->buildFrame($world, $center, true);

		// Remove eye from one frame
		$missingEyeFrame = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::SOUTH)->setEye(false);
		$world->setBlockAt(10, 64, 8, $missingEyeFrame);

		self::assertFalse(EndPortalDetector::isFrameComplete($world, $center));
		self::assertFalse(EndPortalDetector::activatePortal($world, $center));
	}

	public function testIncompleteFrameWrongFacing() : void{
		$world = $this->createMockWorld();
		$center = new Vector3(0, 64, 0);

		$this->buildFrame($world, $center, true);

		// Change facing of one frame to incorrect direction
		$wrongFacingFrame = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::NORTH)->setEye(true);
		$world->setBlockAt(0, 64, -2, $wrongFacingFrame);

		self::assertFalse(EndPortalDetector::isFrameComplete($world, $center));
	}

	public function testTryActivateFromFramePosition() : void{
		$world = $this->createMockWorld();
		$center = new Vector3(50, 70, 50);

		$this->buildFrame($world, $center, true);

		// Trigger from a frame position
		$framePos = new Vector3(50, 70, 48);
		$activated = EndPortalDetector::tryActivate($world, $framePos);
		self::assertTrue($activated);

		// Verify center was activated
		$b = $world->getBlockAt(50, 70, 50);
		self::assertInstanceOf(EndPortal::class, $b);
	}

	public function testEnderEyeInteraction() : void{
		$world = $this->createMockWorld();
		$center = new Vector3(0, 64, 0);

		$this->buildFrame($world, $center, true);

		// Keep one frame without eye
		$pos = new Vector3(0, 64, -2);
		$frameWithoutEye = VanillaBlocks::END_PORTAL_FRAME()->setFacing(Facing::SOUTH)->setEye(false);
		$frameWithoutEye->position($world, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		$world->setBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $frameWithoutEye);

		// Check before interaction
		self::assertFalse(EndPortalDetector::isFrameComplete($world, $center));

		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getWorld"])
			->getMock();
		$player->method("getWorld")->willReturn($world);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));

		$eye = VanillaItems::ENDER_EYE();
		self::assertSame(64, $eye->getMaxStackSize());

		$returnedItems = [];
		$air = VanillaBlocks::AIR();
		$air->position($world, 0, 65, -2);

		$result = $eye->onInteractBlock($player, $air, $frameWithoutEye, Facing::UP, Vector3::zero(), $returnedItems);
		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertSame(0, $eye->getCount());

		// The frame now has an eye
		$updatedBlock = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
		self::assertInstanceOf(EndPortalFrame::class, $updatedBlock);
		self::assertTrue($updatedBlock->hasEye());

		// And the portal activated!
		$centerBlock = $world->getBlockAt(0, 64, 0);
		self::assertInstanceOf(EndPortal::class, $centerBlock);

		// Trying again on already-filled frame fails
		$eye2 = VanillaItems::ENDER_EYE();
		$result2 = $eye2->onInteractBlock($player, $air, $updatedBlock, Facing::UP, Vector3::zero(), $returnedItems);
		self::assertSame(ItemUseResult::NONE, $result2);
		self::assertSame(1, $eye2->getCount());
	}
}
