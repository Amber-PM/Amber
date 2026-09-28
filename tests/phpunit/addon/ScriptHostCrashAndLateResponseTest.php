<?php
declare(strict_types=1);
namespace pocketmine\addon;
use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use ReflectionClass;

final class ScriptHostCrashAndLateResponseTest extends TestCase {
	public function testLateResponseCorrelation() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();
		
		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("subscriptions")->setValue($host, ["before:A" => true]);
		$rc->getProperty("tickBudgetMs")->setValue($host, 1);
		$rc->getProperty("queue")->setValue($host, []);
		
		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());
		
		$domain = PHP_OS_FAMILY === "Windows" ? STREAM_PF_INET : STREAM_PF_UNIX;
		$proto = PHP_OS_FAMILY === "Windows" ? STREAM_IPPROTO_IP : 0;
		$pairIn = stream_socket_pair($domain, STREAM_SOCK_STREAM, $proto);
		$pairOut = stream_socket_pair($domain, STREAM_SOCK_STREAM, $proto);
		$stdin = $pairIn[1];
		$stdout = $pairOut[0];
		$rc->getProperty("stdin")->setValue($host, $stdin);
		$rc->getProperty("stdout")->setValue($host, $stdout);
		
		$resA = $host->before("A", []);
		self::assertSame(["cancel" => false], $resA, "before A timed out, should return uncancelled");
		
		self::assertFalse(
			$rc->getProperty("busy")->getValue($host),
			"A timed-out synchronous request must not enter tick-busy state"
		);
		
		$jsonLateBres = json_encode(["t" => "bres", "reqId" => 1, "cancel" => true]);
		$jsonDone = json_encode(["t" => "done"]);
		$rc->getProperty("buffer")->setValue($host, $jsonLateBres . "\n" . $jsonDone . "\n");
		
		$host->tick(2);
		
		stream_set_blocking($pairIn[0], false);
		$written = fread($pairIn[0], 65536);
		self::assertStringContainsString('"t":"tick"', $written, "Next normal tick must be written to Node");
		self::assertStringContainsString('"n":2', $written);
		
		self::assertTrue($rc->getProperty("running")->getValue($host));
		self::assertFalse($rc->getProperty("busy")->getValue($host));
	}
	
	public function testCrashCleansWorkerRegistries() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();
		
		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("subscriptions")->setValue($host, ["before:foo" => true]);
		$rc->getProperty("queue")->setValue($host, [["A", []]]);
		$rc->getProperty("customComponents")->setValue($host, ["item:bar" => ["onUse" => true]]);
		$rc->getProperty("scriptFunctions")->setValue($host, ["fn1" => true]);
		$rc->getProperty("customCommands")->setValue($host, ["cmd1" => ["desc", 0]]);
		$rc->getProperty("busy")->setValue($host, true);
		$rc->getProperty("nestedDepth")->setValue($host, 3);
		$rc->getProperty("servicing")->setValue($host, "p");
		$dummyPluginFn = function() : void{};
		$rc->getProperty("pluginFunctions")->setValue($host, ["pfn" => $dummyPluginFn]);
		
		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());
		
		$method = $rc->getMethod("crashed");
		
		$method->invoke($host, "test error");
		
		self::assertSame([], $rc->getProperty("subscriptions")->getValue($host));
		self::assertSame([], $rc->getProperty("queue")->getValue($host));
		self::assertSame([], $rc->getProperty("customComponents")->getValue($host));
		self::assertSame([], $rc->getProperty("scriptFunctions")->getValue($host));
		self::assertSame([], $rc->getProperty("customCommands")->getValue($host));
		self::assertFalse($rc->getProperty("busy")->getValue($host));
		self::assertSame(0, $rc->getProperty("nestedDepth")->getValue($host));
		self::assertSame("", $rc->getProperty("servicing")->getValue($host));
		self::assertSame(["pfn" => $dummyPluginFn], $rc->getProperty("pluginFunctions")->getValue($host));
	}
}
