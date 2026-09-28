<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginManager;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\WorldManager;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class ScriptHostTickCompletionTest extends TestCase{
	private ScriptHost $host;
	private Living $entity;
	private EffectManager $effects;
	private RegisteredListener $effectListener;
	/** @var list<resource> */
	private array $streams = [];

	protected function setUp() : void{
		$this->host = (new ReflectionClass(ScriptHost::class))->newInstanceWithoutConstructor();
		$server = $this->createMock(Server::class);
		$worldManager = $this->createMock(WorldManager::class);
		$this->entity = $this->getMockBuilder(Living::class)->disableOriginalConstructor()->onlyMethods([
			"getEffects", "getName", "getInitialSizeInfo", "getNetworkTypeId", "onDispose"
		])->getMock();
		(new ReflectionProperty(Entity::class, "id"))->setValue($this->entity, 123);
		$this->effects = new EffectManager($this->entity);
		$this->entity->method("getEffects")->willReturn($this->effects);
		$worldManager->method("findEntity")->willReturn($this->entity);
		$server->method("getWorldManager")->willReturn($worldManager);

		$effectHandler = null;
		$pluginManager = $this->getMockBuilder(PluginManager::class)->disableOriginalConstructor()->onlyMethods(["registerEvent"])->getMock();
		$pluginManager->method("registerEvent")->willReturnCallback(function(string $event, \Closure $handler, int $priority) use (&$effectHandler) : RegisteredListener{
			if($event === EntityEffectAddEvent::class && $priority === EventPriority::LOW){
				$effectHandler = $handler;
			}
			return $this->createMock(RegisteredListener::class);
		});
		$server->method("getPluginManager")->willReturn($pluginManager);
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "scriptHost"))->setValue($manager, $this->host);
		$runtime = (new ReflectionClass(AddonRuntime::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(PluginBase::class, "server"))->setValue($runtime, $server);
		$runtime->attach($manager);
		(new ReflectionMethod(AddonRuntime::class, "onEnable"))->invoke($runtime);
		$this->effectListener = new RegisteredListener($effectHandler, EventPriority::LOW, $this->createMock(Plugin::class), true, new TimingsHandler("ScriptHost tick completion test"));
		HandlerListManager::global()->getListFor(EntityEffectAddEvent::class)->register($this->effectListener);

		foreach(["running" => true, "subscriptions" => ["before:effectAdd" => true], "tickBudgetMs" => 100, "logger" => $this->createMock(\Logger::class), "server" => $server, "packs" => []] as $name => $value){
			(new ReflectionProperty(ScriptHost::class, $name))->setValue($this->host, $value);
		}
		$domain = PHP_OS_FAMILY === "Windows" ? STREAM_PF_INET : STREAM_PF_UNIX;
		$protocol = PHP_OS_FAMILY === "Windows" ? STREAM_IPPROTO_IP : 0;
		$input = stream_socket_pair($domain, STREAM_SOCK_STREAM, $protocol);
		$output = stream_socket_pair($domain, STREAM_SOCK_STREAM, $protocol);
		$this->streams = [$input[0], $input[1], $output[0], $output[1]];
		stream_set_blocking($input[0], false);
		(new ReflectionProperty(ScriptHost::class, "stdin"))->setValue($this->host, $input[1]);
		(new ReflectionProperty(ScriptHost::class, "stdout"))->setValue($this->host, $output[0]);
	}

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll($this->effectListener);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($this->entity, true);
		foreach($this->streams as $stream){
			fclose($stream);
		}
	}

	/** @param list<array<string, mixed>> $frames */
	private function frames(array $frames) : void{
		(new ReflectionProperty(ScriptHost::class, "buffer"))->setValue($this->host, implode("\n", array_map("json_encode", $frames)) . "\n");
	}

	private function effectOperation() : array{
		return ["t" => "p", "op" => "eff", "a" => ["id" => "123", "effect" => "minecraft:speed", "dur" => 20, "amp" => 0, "particles" => true]];
	}

	private function busy() : bool{
		return (new ReflectionProperty(ScriptHost::class, "busy"))->getValue($this->host);
	}

	/** @return list<array<string, mixed>> */
	private function written() : array{
		$written = trim(stream_get_contents($this->streams[0]));
		return $written === "" ? [] : array_map(static fn(string $line) : array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), explode("\n", $written));
	}

	public function testAsyncBeforeEventPreservesOuterCompletionAndNextTick() : void{
		$this->frames([$this->effectOperation(), ["t" => "done"], ["t" => "bres", "reqId" => 1, "cancel" => true]]);
		$this->host->tick(1);
		self::assertFalse($this->effects->has(VanillaEffects::SPEED()), "The real before-event must still cancel effect application");
		self::assertFalse($this->busy(), "A nested pump already observed this tick's completion");
		self::assertSame("", (new ReflectionProperty(ScriptHost::class, "buffer"))->getValue($this->host));
		$this->frames([["t" => "done"]]);
		$this->host->tick(2);
		self::assertFalse($this->busy());
		$written = $this->written();
		self::assertSame(["tick", "before", "tick"], array_column($written, "t"));
		self::assertSame(1, $written[1]["reqId"]);
		self::assertSame(2, $written[2]["n"]);
	}

	public function testNestedCompletionWinsOverAsyncWorkBudget() : void{
		(new ReflectionProperty(ScriptHost::class, "asyncWorkBudget"))->setValue($this->host, 1);
		$this->frames([$this->effectOperation(), ["t" => "done"], ["t" => "bres", "reqId" => 1, "cancel" => true]]);
		$this->host->tick(1);
		self::assertFalse($this->effects->has(VanillaEffects::SPEED()));
		self::assertFalse($this->busy());
	}

	public function testLateBeforeResponseCannotSatisfyCurrentRequest() : void{
		(new ReflectionProperty(ScriptHost::class, "nextReqId"))->setValue($this->host, 2);
		$unrelated = ["t" => "p", "op" => "log", "a" => ["lvl" => "info", "msg" => "next operation"]];
		$this->frames([$this->effectOperation(), ["t" => "done"], ["t" => "bres", "reqId" => 1, "cancel" => false], ["t" => "bres", "reqId" => 2, "cancel" => true], $unrelated]);
		$this->host->tick(1);
		self::assertFalse($this->effects->has(VanillaEffects::SPEED()), "Only the matching cancellation response may apply");
		self::assertFalse($this->busy());
		self::assertSame(2, $this->written()[1]["reqId"]);
		self::assertSame(json_encode($unrelated) . "\n", (new ReflectionProperty(ScriptHost::class, "buffer"))->getValue($this->host), "Completion accounting must not replay messages or consume unrelated frames");
	}

	public function testPreviousCompletionDoesNotFinishAnOverBudgetTick() : void{
		$this->frames([["t" => "done"]]);
		$this->host->tick(1);
		self::assertFalse($this->busy());
		(new ReflectionProperty(ScriptHost::class, "asyncWorkBudget"))->setValue($this->host, 1);
		$this->frames([["t" => "p", "op" => "log", "a" => ["lvl" => "info", "msg" => "unfinished"]], ["t" => "done"]]);
		$this->host->tick(2);
		self::assertTrue($this->busy(), "An unobserved completion must leave the tick busy");
		self::assertSame(2, (new ReflectionProperty(ScriptHost::class, "busySince"))->getValue($this->host));
		$this->host->tick(3);
		self::assertTrue($this->busy(), "Consuming the old completion must not finish the newly sent tick");
		self::assertSame(3, (new ReflectionProperty(ScriptHost::class, "busySince"))->getValue($this->host));
		self::assertSame([1, 2, 3], array_column($this->written(), "n"));
	}
}
