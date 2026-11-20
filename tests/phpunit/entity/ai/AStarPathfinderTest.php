<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\ai\path\AStarPathfinder;
use pocketmine\entity\ai\path\Node;
use pocketmine\entity\ai\path\Path;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class AStarPathfinderTest extends TestCase{

	/** @var array<string, Block> */
	private array $blocks = [];

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) : Block{
			$key = "$x,$y,$z";
			return $this->blocks[$key] ?? VanillaBlocks::AIR();
		});
		return $world;
	}

	private function setBlock(int $x, int $y, int $z, Block $block) : void{
		$this->blocks["$x,$y,$z"] = $block;
	}

	private function buildFlatFloor(int $minX, int $maxX, int $minZ, int $maxZ, int $y = 0) : void{
		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				$this->setBlock($x, $y, $z, VanillaBlocks::STONE());
			}
		}
	}

	protected function setUp() : void{
		$this->blocks = [];
	}

	public function testDirectLinePathFlatFloor() : void{
		$world = $this->createMockWorld();
		$this->buildFlatFloor(-1, 10, -1, 5, 0);

		$pathfinder = new AStarPathfinder($world, maxNodes: 300);
		$start = new Vector3(0, 1, 0);
		$target = new Vector3(5, 1, 0);

		$path = $pathfinder->findPath($start, $target, entityHeight: 2, entityWidth: 0.6);
		self::assertNotNull($path);
		self::assertGreaterThanOrEqual(5, count($path->getNodes()));
		$endNode = $path->getEndNode();
		self::assertNotNull($endNode);
		self::assertSame(5, $endNode->x);
		self::assertSame(1, $endNode->y);
		self::assertSame(0, $endNode->z);
	}

	public function testStepUpOneBlock() : void{
		$world = $this->createMockWorld();
		// Floor at y=0 from x=0 to 2
		for($x = 0; $x <= 2; ++$x){
			$this->setBlock($x, 0, 0, VanillaBlocks::STONE());
		}
		// Step up at x=3: floor at y=1 from x=3 to 5
		for($x = 3; $x <= 5; ++$x){
			$this->setBlock($x, 1, 0, VanillaBlocks::STONE());
		}

		$pathfinder = new AStarPathfinder($world);
		$path = $pathfinder->findPath(new Vector3(0, 1, 0), new Vector3(5, 2, 0), entityHeight: 2, entityWidth: 0.6);

		self::assertNotNull($path);
		$endNode = $path->getEndNode();
		self::assertNotNull($endNode);
		self::assertSame(5, $endNode->x);
		self::assertSame(2, $endNode->y);
		self::assertSame(0, $endNode->z);
	}

	public function testStepDownUpToThreeBlocks() : void{
		$world = $this->createMockWorld();
		// High floor at y=3 from x=0 to 2
		for($x = 0; $x <= 2; ++$x){
			$this->setBlock($x, 3, 0, VanillaBlocks::STONE());
		}
		// Low floor at y=0 from x=3 to 5 (drop of 3 blocks)
		for($x = 3; $x <= 5; ++$x){
			$this->setBlock($x, 0, 0, VanillaBlocks::STONE());
		}

		$pathfinder = new AStarPathfinder($world);
		$path = $pathfinder->findPath(new Vector3(0, 4, 0), new Vector3(5, 1, 0), entityHeight: 2, entityWidth: 0.6);

		self::assertNotNull($path);
		$endNode = $path->getEndNode();
		self::assertNotNull($endNode);
		self::assertSame(5, $endNode->x);
		self::assertSame(1, $endNode->y);
	}

	public function testObstacleWallNavigation() : void{
		$world = $this->createMockWorld();
		$this->buildFlatFloor(-2, 10, -5, 5, 0);

		// Wall at x=3 from z=-2 to z=2, height 3
		for($z = -2; $z <= 2; ++$z){
			for($y = 1; $y <= 3; ++$y){
				$this->setBlock(3, $y, $z, VanillaBlocks::STONE());
			}
		}

		$pathfinder = new AStarPathfinder($world);
		$path = $pathfinder->findPath(new Vector3(0, 1, 0), new Vector3(6, 1, 0), entityHeight: 2, entityWidth: 0.6);

		self::assertNotNull($path);
		$endNode = $path->getEndNode();
		self::assertNotNull($endNode);
		self::assertSame(6, $endNode->x);
		self::assertSame(1, $endNode->y);
		self::assertSame(0, $endNode->z);

		// Verify path did not walk through the wall at x=3, z=0
		foreach($path->getNodes() as $node){
			if($node->x === 3){
				self::assertTrue(abs($node->z) > 2, "Path must detour around wall at z in [-2, 2]");
			}
		}
	}

	public function testHeadroomRestriction() : void{
		$world = $this->createMockWorld();
		$this->buildFlatFloor(-1, 8, -1, 1, 0);

		// Ceiling at y=2 from x=2 to 4 across all z (only 1 block headroom at y=1)
		for($x = 2; $x <= 4; ++$x){
			for($z = -1; $z <= 1; ++$z){
				$this->setBlock($x, 2, $z, VanillaBlocks::STONE());
			}
		}

		$pathfinder = new AStarPathfinder($world);

		// 2-block tall mob (e.g. Zombie) should not be able to path through
		$pathTall = $pathfinder->findPath(new Vector3(0, 1, 0), new Vector3(6, 1, 0), entityHeight: 2, entityWidth: 0.6);
		self::assertNull($pathTall);

		// 1-block tall mob (e.g. Chicken/Spider) can pass through
		$pathShort = $pathfinder->findPath(new Vector3(0, 1, 0), new Vector3(6, 1, 0), entityHeight: 1, entityWidth: 0.6);
		self::assertNotNull($pathShort);
	}

	public function testAvoidHazardLavaAndFire() : void{
		$world = $this->createMockWorld();
		$this->buildFlatFloor(-2, 8, -3, 3, 0);

		// Place lava at x=2, y=1, z=0
		$this->setBlock(2, 1, 0, VanillaBlocks::LAVA());

		$pathfinder = new AStarPathfinder($world);
		$path = $pathfinder->findPath(new Vector3(0, 1, 0), new Vector3(4, 1, 0), entityHeight: 2, entityWidth: 0.6);

		self::assertNotNull($path);
		foreach($path->getNodes() as $node){
			self::assertFalse($node->x === 2 && $node->y === 1 && $node->z === 0, "Path must not step directly into lava");
		}
	}

	public function testUnreachableTargetTerminatesGracefully() : void{
		$world = $this->createMockWorld();
		$this->buildFlatFloor(0, 5, 0, 5, 0);

		// Encase target in completely sealed stone box
		for($x = 8; $x <= 10; ++$x){
			for($y = 0; $y <= 4; ++$y){
				for($z = 8; $z <= 10; ++$z){
					$this->setBlock($x, $y, $z, VanillaBlocks::BEDROCK());
				}
			}
		}

		$pathfinder = new AStarPathfinder($world, maxNodes: 100);
		$path = $pathfinder->findPath(new Vector3(1, 1, 1), new Vector3(9, 2, 9), entityHeight: 2, entityWidth: 0.6);
		self::assertNull($path);
	}
}
