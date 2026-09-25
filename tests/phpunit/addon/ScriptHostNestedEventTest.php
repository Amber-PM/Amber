<?php
declare(strict_types=1);
namespace pocketmine\addon;
use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use ReflectionClass;

final class ScriptHostNestedEventTest extends TestCase {
	public function testNestedBeforeEvent() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("subscriptions")->setValue($host, ["before:effectAdd" => true]);
		$rc->getProperty("tickBudgetMs")->setValue($host, 50);

		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());

		$stdin = fopen("php://memory", "r+");
		$stdout = fopen("php://memory", "r+");
		$rc->getProperty("stdin")->setValue($host, $stdin);
		$rc->getProperty("stdout")->setValue($host, $stdout);

		// simulate handling a 'p' message (production path: node sends p("eff"), php sets servicing = "p")
		$rc->getProperty("servicing")->setValue($host, "p");

		try {
			$host->before("effectAdd", []);
		} catch (\ValueError $e) {
			// xpected in mock
		}

		rewind($stdin);
		$written = stream_get_contents($stdin);
		self::assertStringContainsString('"t":"before"', $written, "before() should have called syncRequest and written to stdin");
	}

	public function testNestedRecursionBound() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("subscriptions")->setValue($host, ["before:effectAdd" => true]);
		$rc->getProperty("tickBudgetMs")->setValue($host, 50);

		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());

		$stdin = fopen("php://memory", "r+");
		$stdout = fopen("php://memory", "r+");
		$rc->getProperty("stdin")->setValue($host, $stdin);
		$rc->getProperty("stdout")->setValue($host, $stdout);

		// set nested depth to MAX_NESTED_DEPTH (5)
		$rc->getProperty("nestedDepth")->setValue($host, 5);
		$rc->getProperty("servicing")->setValue($host, "p");

		$result = $host->before("effectAdd", []);
		self::assertSame(["cancel" => false], $result);

		rewind($stdin);
		$written = stream_get_contents($stdin);
		self::assertSame("", $written, "before() should have short-circuited due to recursion depth limit");
	}
}
