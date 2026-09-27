<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\CommandBridge;
use pocketmine\addon\world\AddonScoreboard;
use pocketmine\addon\world\AddonTickingAreas;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use ReflectionClass;
use ReflectionProperty;

final class CommandBridgeScheduleTest extends TestCase{
	private string $root;
	/** @var list<string> */
	private array $files = [];

	protected function setUp() : void{
		$this->root = sys_get_temp_dir() . "/amber-schedule-" . bin2hex(random_bytes(8));
		mkdir($this->root);
		mkdir($this->root . "/functions");
	}

	protected function tearDown() : void{
		foreach($this->files as $file){ unlink($file); }
		rmdir($this->root . "/functions");
		rmdir($this->root);
	}

	private function functionFile(string $name, int $value) : void{
		$file = $this->root . "/functions/$name.mcfunction";
		file_put_contents($file, "scoreboard players set marker done $value\n");
		$this->files[] = $file;
	}

	private function fixture(bool &$loaded) : array{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getFolderName", "isChunkLoaded", "getSpawnLocation"])->getMock();
		$world->method("getFolderName")->willReturn("test-world");
		$world->method("getSpawnLocation")->willReturn(new \pocketmine\world\Position(0, 0, 0, $world));
		$world->method("isChunkLoaded")->willReturnCallback(static function(int $x, int $z) use (&$loaded) : bool{ return $loaded; });
		$worldManager = (new ReflectionClass(WorldManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(WorldManager::class, "worlds"))->setValue($worldManager, [$world]);
		(new ReflectionProperty(Server::class, "worldManager"))->setValue($server, $worldManager);
		(new ReflectionProperty(Server::class, "tickCounter"))->setValue($server, 0);
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "behaviorRoots"))->setValue($manager, [$this->root]);
		$areas = new AddonTickingAreas($server, $this->root . "/unused-areas.json");
		(new ReflectionProperty(AddonManager::class, "tickingAreas"))->setValue($manager, $areas);
		$board = new AddonScoreboard($server, $this->root . "/unused-board.json");
		$board->addObjective("done", "Done");
		(new ReflectionProperty(AddonManager::class, "scoreboard"))->setValue($manager, $board);
		$bridge = new CommandBridge($server, $manager);
		return [$bridge, $world, $areas, $board];
	}

	private static function area(AddonTickingAreas $areas, string $name) : void{
		(new ReflectionProperty(AddonTickingAreas::class, "areas"))->setValue($areas, [$name => ["world" => "test-world", "x1" => 0, "z1" => 0, "x2" => 1, "z2" => 1]]);
	}

	public function testNamedAreaWaitsUntilItExistsAndLoadsThenRunsOnce() : void{
		$loaded = false;
		[$bridge, $world, $areas, $board] = $this->fixture($loaded);
		$this->functionFile("one", 1);
		self::assertSame(1, $bridge->run("schedule on_area_loaded add tickingarea farm one", null, $world, new Vector3(0, 0, 0)));
		$bridge->tick(20);
		self::assertNull($board->getScore("done", "marker"));
		self::area($areas, "farm");
		$bridge->tick(40);
		self::assertNull($board->getScore("done", "marker"));
		$loaded = true;
		$bridge->tick(60);
		self::assertSame(1, $board->getScore("done", "marker"));
		self::assertSame([], (new ReflectionProperty(CommandBridge::class, "scheduled"))->getValue($bridge));
	}

	public function testNamedAreaClearAndFunctionClearSelectOnlyRequestedEntries() : void{
		$loaded = false;
		[$bridge, $world, $areas, $board] = $this->fixture($loaded);
		$this->functionFile("one", 1);
		$this->functionFile("two", 2);
		$origin = new Vector3(0, 0, 0);
		foreach(["one", "two"] as $name){
			$bridge->run("schedule on_area_loaded add tickingarea farm $name", null, $world, $origin);
		}
		self::assertSame(1, $bridge->run("schedule on_area_loaded clear tickingarea farm one", null, $world, $origin));
		self::assertSame(1, $bridge->run("schedule on_area_loaded clear function two", null, $world, $origin));
		self::assertSame([], (new ReflectionProperty(CommandBridge::class, "scheduled"))->getValue($bridge));
		$bridge->run("schedule on_area_loaded add tickingarea farm one", null, $world, $origin);
		$bridge->run("schedule on_area_loaded add tickingarea farm two", null, $world, $origin);
		self::assertSame(2, $bridge->run("schedule on_area_loaded clear tickingarea farm", null, $world, $origin));
		self::area($areas, "farm");
		$loaded = true;
		$bridge->tick(20);
		self::assertNull($board->getScore("done", "marker"));
	}

	public function testClearFunctionRemovesDelayAndAreaSchedulesOnlyForThatFunction() : void{
		$loaded = false;
		[$bridge, $world] = $this->fixture($loaded);
		$this->functionFile("one", 1);
		$this->functionFile("two", 2);
		$origin = new Vector3(0, 0, 0);
		self::assertSame(1, $bridge->run("schedule delay add one 10t", null, $world, $origin));
		self::assertSame(1, $bridge->run("schedule on_area_loaded add tickingarea farm one", null, $world, $origin));
		self::assertSame(1, $bridge->run("schedule on_area_loaded add tickingarea farm two", null, $world, $origin));
		self::assertSame(2, $bridge->run("schedule clear one", null, $world, $origin));
		$remaining = array_values((new ReflectionProperty(CommandBridge::class, "scheduled"))->getValue($bridge));
		self::assertCount(1, $remaining);
		self::assertSame("two", $remaining[0]["function"]);
		self::assertSame(1, $bridge->run("schedule on_area_loaded clear function two", null, $world, $origin));
		self::assertSame(1, $bridge->run("schedule delay add one 10t", null, $world, $origin));
		self::assertSame(1, $bridge->run("schedule delay clear one", null, $world, $origin));
		self::assertSame([], (new ReflectionProperty(CommandBridge::class, "scheduled"))->getValue($bridge));
	}
}
