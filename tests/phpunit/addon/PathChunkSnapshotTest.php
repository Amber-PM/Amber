<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\ai\PathChunkSnapshot;
use pocketmine\addon\entity\ai\Pathfinder;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class PathChunkSnapshotTest extends TestCase{

	public function testSnapshotSearchMatchesWorldSearch() : void{
		$chunks = [];
		$stone = VanillaBlocks::STONE()->getStateId();
		$fence = VanillaBlocks::OAK_FENCE()->getStateId();
		for($chunkX = 0; $chunkX <= 1; ++$chunkX){
			for($chunkZ = 0; $chunkZ <= 1; ++$chunkZ){
				$chunk = new Chunk([], true);
				for($x = 0; $x < 16; ++$x){
					for($z = 0; $z < 16; ++$z){
						$chunk->setBlockStateId($x, 0, $z, $stone);
						//a wall of fences with a gap, crossing both chunks, so the path has to detour
						if($chunkX === 0 && $x === 10 && !($chunkZ === 0 && $z === 8)){
							$chunk->setBlockStateId($x, 1, $z, $fence);
						}
					}
				}
				$chunks[World::chunkHash($chunkX, $chunkZ)] = $chunk;
			}
		}
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getChunk", "getMinY", "getMaxY"])->getMock();
		$world->method("getChunk")->willReturnCallback(static fn(int $x, int $z) : ?Chunk => $chunks[World::chunkHash($x, $z)] ?? null);
		$world->method("getMinY")->willReturn(-64);
		$world->method("getMaxY")->willReturn(320);

		$from = new Vector3(2.5, 1, 2.5);
		$to = new Vector3(20.5, 1, 4.5);
		$direct = (new Pathfinder($world, 2, false, false))->find($from, $to);
		self::assertNotNull($direct);

		$snapshot = PathChunkSnapshot::fromPayload(PathChunkSnapshot::capture($world, -24, -24, 30, 30));
		$fromSnapshot = (new Pathfinder($snapshot, 2, false, false))->find($from, $to);
		self::assertEquals($direct, $fromSnapshot);
		self::assertEquals(new Vector3(20.5, 1, 4.5), $fromSnapshot[count($fromSnapshot) - 1]);
	}
}
