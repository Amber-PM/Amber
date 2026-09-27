<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\CommandBridge;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class CommandBridgeDepthTest extends TestCase{
	private string $root;
	/** @var list<string> */
	private array $files = [];

	protected function setUp() : void{
		$this->root = sys_get_temp_dir() . "/amber-functions-" . bin2hex(random_bytes(8));
		mkdir($this->root);
		mkdir($this->root . "/functions");
	}

	protected function tearDown() : void{
		foreach($this->files as $file){
			unlink($file);
		}
		rmdir($this->root . "/functions");
		rmdir($this->root);
	}

	private function bridge() : CommandBridge{
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "behaviorRoots"))->setValue($manager, [$this->root]);
		return new CommandBridge((new ReflectionClass(Server::class))->newInstanceWithoutConstructor(), $manager);
	}

	private function functionFile(string $name, string $line) : void{
		$file = $this->root . "/functions/$name.mcfunction";
		file_put_contents($file, $line . "\n");
		$this->files[] = $file;
	}

	private function world() : World{
		return $this->getMockBuilder(World::class)->disableOriginalConstructor()->getMock();
	}

	public function testFunctionLineCannotBypassExistingDepthLimit() : void{
		$this->functionFile("once", "execute");
		$bridge = $this->bridge();
		(new ReflectionProperty(CommandBridge::class, "depth"))->setValue($bridge, 16);
		$world = $this->world();
		$origin = new Vector3(0, 0, 0);
		self::assertSame(0, $bridge->run("function once", null, $world, $origin));
		self::assertSame(16, (new ReflectionProperty(CommandBridge::class, "depth"))->getValue($bridge));
		self::assertSame(1, $bridge->run("execute", null, $world, $origin));
	}

	public function testSelfRecursiveFunctionAndExecuteRunRestoreDepth() : void{
		$this->functionFile("self", "function self");
		$this->functionFile("via_execute", "execute run function via_execute");
		$bridge = $this->bridge();
		$world = $this->world();
		$origin = new Vector3(0, 0, 0);
		self::assertSame(0, $bridge->run("function self", null, $world, $origin));
		self::assertSame(0, $bridge->run("function via_execute", null, $world, $origin));
		self::assertSame(0, (new ReflectionProperty(CommandBridge::class, "depth"))->getValue($bridge));
		self::assertSame(1, $bridge->run("execute", null, $world, $origin));
	}

	public function testTopLevelDepthRejectionNeedsNoWorldContext() : void{
		$bridge = $this->bridge();
		(new ReflectionProperty(CommandBridge::class, "depth"))->setValue($bridge, 17);
		self::assertSame(0, $bridge->run("execute", null));
		self::assertSame(17, (new ReflectionProperty(CommandBridge::class, "depth"))->getValue($bridge));
	}
}
