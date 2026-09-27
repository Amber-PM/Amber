<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\CommandBridge;
use pocketmine\addon\script\CommandContext;
use pocketmine\addon\world\AddonScoreboard;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class CommandBridgeSelectorTest extends TestCase{
	private function setupBridge(float $bobYaw = 80.0) : array{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getEntities"])->getMock();
		$players = [];
		foreach([["Alice", 1, 5, 10.0, 20.0], ["Bob", 2, 15, 40.0, $bobYaw]] as [$name, $id, $level, $pitch, $yaw]){
			$xp = $this->getMockBuilder(ExperienceManager::class)->disableOriginalConstructor()->onlyMethods(["getXpLevel"])->getMock();
			$xp->method("getXpLevel")->willReturn($level);
			$player = new SelectorFixturePlayer($name, $id, new Location($id, 64, 0, $world, $yaw, $pitch), $xp);
			$players[] = $player;
		}
		$world->method("getEntities")->willReturn($players);
		(new ReflectionProperty(Server::class, "playerList"))->setValue($server, [1 => $players[0], 2 => $players[1]]);
		$board = new AddonScoreboard($server, sys_get_temp_dir() . "/amber-unused-board-" . bin2hex(random_bytes(8)));
		$board->addObjective("ready", "Ready");
		$board->setScore("ready", "Alice", 1);
		$board->setScore("ready", "Bob", 0);
		$board->addObjective("touched", "Touched");
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "scoreboard"))->setValue($manager, $board);
		$bridge = new CommandBridge($server, $manager);
		$ctx = new CommandContext(null, $world, new Vector3(0, 64, 0), 0, 0);
		return [$bridge, $ctx, $board, $players];
	}

	public function testScoresRestrictSelectionAndMutation() : void{
		[$bridge, $ctx, $board, $players] = $this->setupBridge();
		self::assertSame([$players[0]], $bridge->select("@e[scores={ready=1}]", $ctx));
		self::assertSame([$players[1]], $bridge->select("@e[scores={ready=..0}]", $ctx));
		self::assertSame([$players[1]], $bridge->select("@e[scores={ready=!1}]", $ctx));
		self::assertSame([], $bridge->select("@e[scores={ready=1,missing=0}]", $ctx));
		self::assertSame(1, $bridge->run("scoreboard players set @e[scores={ready=1}] touched 7", null, $ctx->world, $ctx->origin));
		self::assertSame(7, $board->getScore("touched", "Alice"));
		self::assertNull($board->getScore("touched", "Bob"));
	}

	public function testLevelAndRotationRangesRestrictSelection() : void{
		[$bridge, $ctx, , $players] = $this->setupBridge();
		self::assertSame([$players[0]], $bridge->select("@a[l=10,lm=5]", $ctx));
		self::assertSame([$players[1]], $bridge->select("@a[lm=10]", $ctx));
		self::assertSame([$players[1]], $bridge->select("@e[rxm=30,rx=50,rym=70,ry=90]", $ctx));
	}

	public function testYawSelectorsUseBedrockSignedRangeWithoutChangingEntityRotation() : void{
		[$bridge, $ctx, , $players] = $this->setupBridge(350.0);
		self::assertSame([$players[1]], $bridge->select("@a[rym=-20,ry=0]", $ctx));
		self::assertSame([], $bridge->select("@a[name=Bob,rym=0,ry=20]", $ctx));
		self::assertSame(350.0, $players[1]->getLocation()->yaw);
	}

	public function testUnsupportedRestrictionsFailClosed() : void{
		[$bridge, $ctx] = $this->setupBridge();
		self::assertSame([], $bridge->select("@e[hasitem={item=minecraft:stone}]", $ctx));
		self::assertSame([], $bridge->select("@e[unknown=1]", $ctx));
		self::assertSame([], $bridge->select("@e[unknown]", $ctx));
		self::assertSame([], $bridge->select("@e[type=minecraft:player,unknown]", $ctx));
		self::assertSame([], $bridge->select("@e[scores={}]", $ctx));
		self::assertSame([], $bridge->select("@a[l=20,l=0]", $ctx));
	}
}

final class SelectorFixturePlayer extends Player{
	public function __construct(private string $fixtureName, private int $fixtureId, private Location $fixtureLocation, private ExperienceManager $fixtureXp){}
	public function __destruct(){}
	public function getName() : string{ return $this->fixtureName; }
	public function getId() : int{ return $this->fixtureId; }
	public function getLocation() : Location{ return $this->fixtureLocation; }
	public function getPosition() : \pocketmine\world\Position{ return $this->fixtureLocation; }
	public function isClosed() : bool{ return false; }
	public function isAlive() : bool{ return true; }
	public function getXpManager() : ExperienceManager{ return $this->fixtureXp; }
}
