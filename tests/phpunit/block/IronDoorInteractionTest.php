<?php

declare(strict_types=1);

namespace pocketmine\block;

use PHPUnit\Framework\TestCase;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\World;

final class IronDoorInteractionTest extends TestCase{

	public static function interactionCases() : \Generator{
		foreach(["IRON_DOOR" => false, "IRON_TRAPDOOR" => false, "OAK_DOOR" => true, "OAK_TRAPDOOR" => true] as $name => $manual){
			foreach([false, true] as $open){
				foreach([false, true] as $top){
					yield "$name/open=$open/top=$top" => [$name, $manual, $open, $top];
				}
			}
		}
	}

	/** @dataProvider interactionCases */
	public function testManualInteraction(string $name, bool $manual, bool $open, bool $top) : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->expects($manual ? self::once() : self::never())->method("setBlock");
		$world->expects($manual ? self::once() : self::never())->method("addSound");
		$block = VanillaBlocks::$name()->setOpen($open)->setTop($top);
		$block->position($world, 0, 64, 0);
		self::assertSame($manual, $block->onInteract(VanillaItems::AIR(), Facing::NORTH, Vector3::zero()));
		self::assertSame($manual ? !$open : $open, $block->isOpen());
	}

	public function testIronBlocksStillOpenAndCloseWithRedstone() : void{
		foreach([VanillaBlocks::IRON_DOOR(), VanillaBlocks::IRON_TRAPDOOR()] as $block){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
			$world->method("isInWorld")->willReturn(true);
			$world->method("isChunkLoaded")->willReturn(true);
			$block->position($world, 0, 64, 0);
			$blocks = ["0:64:0" => $block];
			if($block instanceof Door){
				$top = (clone $block)->setTop(true);
				$top->position($world, 0, 65, 0);
				$blocks["0:65:0"] = $top;
			}
			$powered = true;
			$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) use ($world, &$blocks, &$powered) : Block{
				$result = clone ($blocks["$x:$y:$z"] ?? ($powered && $x === 1 && $y === 64 && $z === 0 ? VanillaBlocks::REDSTONE() : VanillaBlocks::AIR()));
				$result->position($world, $x, $y, $z);
				return $result;
			});
			$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $value) use (&$blocks) : void{
				$blocks["$pos->x:$pos->y:$pos->z"] = clone $value;
			});
			$world->method("getBlock")->willReturnCallback(fn(Vector3 $pos) : Block => $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()));
			$engine = new RedstoneEngine($world, 100);
			$block->onRedstoneUpdate($engine);
			foreach($blocks as $part){
				self::assertTrue($part->isOpen());
			}
			$powered = false;
			$blocks["0:64:0"]->onRedstoneUpdate($engine);
			foreach($blocks as $part){
				self::assertFalse($part->isOpen());
			}
		}
	}
}
