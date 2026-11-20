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

namespace pmmp\encoding {
	if(!class_exists(BE::class, false)){
		class BE{
			public static function unpackSignedLong(string $bytes) : int{
				return (int) unpack('J', $bytes)[1];
			}
		}
		class LE{
			public static function packSignedLong(int $val) : string{
				return pack('P', $val);
			}
		}
	}
}

namespace pocketmine\world\portal {

use PHPUnit\Framework\TestCase;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\World;

final class NetherPortalRouterTest extends TestCase{

	public function testOverworldToNetherCoordinateScaling() : void{
		$source = new Vector3(800.0, 64.0, -1600.0);
		$netherPos = NetherPortalRouter::translateCoordinates($source, DimensionIds::OVERWORLD, DimensionIds::NETHER);

		self::assertEquals(100.0, $netherPos->x);
		self::assertEquals(64.0, $netherPos->y);
		self::assertEquals(-200.0, $netherPos->z);
	}

	public function testNetherToOverworldCoordinateScaling() : void{
		$source = new Vector3(100.0, 70.0, -200.0);
		$overworldPos = NetherPortalRouter::translateCoordinates($source, DimensionIds::NETHER, DimensionIds::OVERWORLD);

		self::assertEquals(800.0, $overworldPos->x);
		self::assertEquals(70.0, $overworldPos->y);
		self::assertEquals(-1600.0, $overworldPos->z);
	}

	public function testCoordinateClampingNetherY() : void{
		// Extremely high Y in Overworld (Y = 320) clamped to Nether maximum safe ceiling (120)
		$highPos = new Vector3(0.0, 320.0, 0.0);
		$clamped = NetherPortalRouter::translateCoordinates($highPos, DimensionIds::OVERWORLD, DimensionIds::NETHER);
		self::assertEquals(120.0, $clamped->y);

		// Extremely low Y in Overworld (Y = -64) clamped to Nether minimum safe floor (32)
		$lowPos = new Vector3(0.0, -64.0, 0.0);
		$clampedLow = NetherPortalRouter::translateCoordinates($lowPos, DimensionIds::OVERWORLD, DimensionIds::NETHER);
		self::assertEquals(32.0, $clampedLow->y);
	}

	public function testPortalFrameGenerationStructure() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getMinY")->willReturn(0);
		$world->method("getMaxY")->willReturn(256);

		$blocksPlaced = [];
		$world->method("setBlockAt")->willReturnCallback(function(int $x, int $y, int $z, $block) use (&$blocksPlaced) : bool{
			$blocksPlaced["$x,$y,$z"] = $block->getTypeId();
			return true;
		});

		$center = new Vector3(10, 64, 10);
		$portalPos = NetherPortalRouter::generatePortal($world, $center, Axis::X);

		// Frame is 4 wide x 5 high
		// Bottom row: (10,64,10) to (13,64,10) must be obsidian
		for($x = 10; $x <= 13; ++$x){
			self::assertSame(BlockTypeIds::OBSIDIAN, $blocksPlaced["$x,64,10"]);
			self::assertSame(BlockTypeIds::OBSIDIAN, $blocksPlaced["$x,68,10"]);
		}

		// Interior portal blocks (columns 11 and 12, Y 65..67)
		for($x = 11; $x <= 12; ++$x){
			for($y = 65; $y <= 67; ++$y){
				self::assertSame(BlockTypeIds::NETHER_PORTAL, $blocksPlaced["$x,$y,10"]);
			}
		}

		self::assertSame(11.5, $portalPos->x);
		self::assertSame(65.0, $portalPos->y);
		self::assertSame(10.5, $portalPos->z);
	}
}
}
