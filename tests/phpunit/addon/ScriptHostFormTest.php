<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use ReflectionClass;

final class ScriptHostFormTest extends TestCase {

	public function testStaleFormResponseIsRejectedAfterRestart() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$rc->getProperty("epoch")->setValue($host, 2);
		$rc->getProperty("queue")->setValue($host, []);

		$host->formResponse(1, ["old"], 1);

		self::assertSame(
			[],
			$rc->getProperty("queue")->getValue($host),
			"Stale form response belonging to an older epoch must be rejected"
		);

		$host->formResponse(2, ["new"], 2);

		$queue = $rc->getProperty("queue")->getValue($host);

		self::assertCount(1, $queue, "Current form response must be queued");
		self::assertSame("__form", $queue[0][0]);
		self::assertSame(2, $queue[0][1]["fid"]);
		self::assertSame(["new"], $queue[0][1]["data"]);
	}
}
