<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\ai\Pathfinder;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class PathfinderTraversalTest extends TestCase{
	/** @param array<int, Block> $blocks */
	private function path(array $blocks, Vector3 $from, Vector3 $to) : ?array{
		$chunk = new Chunk([], true);
		foreach($blocks as $packed => $block){
			$x = $packed & 15;
			$y = ($packed >> 4) & 15;
			$z = ($packed >> 8) & 15;
			$chunk->setBlockStateId($x, $y, $z, $block->getStateId());
		}
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getChunk", "getMinY", "getMaxY"])->getMock();
		$world->method("getChunk")->willReturnCallback(static fn(int $x, int $z) : ?Chunk => $x === 0 && $z === 0 ? $chunk : null);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);
		return (new Pathfinder($world, 2, false, false))->find($from, $to);
	}

	private static function key(int $x, int $y, int $z) : int{ return $x | ($y << 4) | ($z << 8); }

	public function testFenceAndWallCannotBecomeStepWaypoints() : void{
		foreach([VanillaBlocks::OAK_FENCE(), VanillaBlocks::COBBLESTONE_WALL()] as $barrier){
			$blocks = [];
			for($x = 0; $x <= 2; ++$x){ $blocks[self::key($x, 0, 0)] = VanillaBlocks::STONE(); }
			$blocks[self::key(1, 1, 0)] = $barrier;
			$path = $this->path($blocks, new Vector3(0.5, 1, 0.5), new Vector3(2.5, 1, 0.5));
			self::assertTrue($path === null || array_filter($path, static fn(Vector3 $node) : bool => $node->x >= 1.5) === [], $barrier->getName());
		}
	}

	public function testStepUnderSourceCeilingIsRejected() : void{
		$blocks = [
			self::key(0, 0, 0) => VanillaBlocks::STONE(),
			self::key(1, 1, 0) => VanillaBlocks::STONE(),
			self::key(0, 3, 0) => VanillaBlocks::STONE(),
		];
		$path = $this->path($blocks, new Vector3(0.5, 1, 0.5), new Vector3(1.5, 2, 0.5));
		self::assertNull($path);
	}
}
