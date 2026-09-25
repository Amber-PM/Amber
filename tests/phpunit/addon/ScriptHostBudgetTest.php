<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use ReflectionClass;

final class ScriptHostBudgetTest extends TestCase {

	public function testAsyncWorkBudgetExhaustionDefersRemaining() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("tickBudgetMs")->setValue($host, 50);

		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());

		$stdin = fopen("php://memory", "r+");
		$stdout = fopen("php://memory", "r+");
		$rc->getProperty("stdin")->setValue($host, $stdin);
		$rc->getProperty("stdout")->setValue($host, $stdout);

		if ($rc->hasProperty("asyncWorkBudget")) {
			$rc->getProperty("asyncWorkBudget")->setValue($host, 2);
		}

		$buffer = "";
		for ($i = 1; $i <= 5; $i++) {
			$buffer .= json_encode(["t" => "p", "op" => "log", "a" => ["lvl" => "info", "msg" => "msg$i"]]) . "\n";
		}
		$buffer .= json_encode(["t" => "done"]) . "\n";
		$rc->getProperty("buffer")->setValue($host, $buffer);

		$method = $rc->getMethod("pumpUntil");

		$result1 = $method->invoke($host, ["done"], 50);
		self::assertNull($result1, "pumpUntil should return null when work budget is exhausted");

		$remainingBuffer = $rc->getProperty("buffer")->getValue($host);
		self::assertStringContainsString("msg3", $remainingBuffer, "Remaining async messages must be deferred in buffer");
		self::assertStringContainsString('"t":"done"', $remainingBuffer);

		$result2 = $method->invoke($host, ["done"], 50);
		self::assertNull($result2, "pumpUntil should return null again after next budget chunk");

		$result3 = $method->invoke($host, ["done"], 50);
		self::assertNotNull($result3, "pumpUntil should finally reach done message");
		self::assertSame("done", $result3["t"]);
	}

	public function testTimeBudgetExhaustionStopsReadingBufferedFrames() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("tickBudgetMs")->setValue($host, 0);

		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);

		$buffer = json_encode(["t" => "p", "op" => "log", "a" => ["lvl" => "info", "msg" => "expired"]]) . "\n";
		$buffer .= json_encode(["t" => "done"]) . "\n";
		$rc->getProperty("buffer")->setValue($host, $buffer);

		$method = $rc->getMethod("pumpUntil");

		$result = $method->invoke($host, ["done"], 0);
		self::assertNull($result, "pumpUntil should immediately return null when time budget is 0 / expired");

		$remainingBuffer = $rc->getProperty("buffer")->getValue($host);
		self::assertStringContainsString("expired", $remainingBuffer);
	}
}
