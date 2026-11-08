<?php

declare(strict_types=1);

namespace pocketmine\world\generator\executor;

use PHPUnit\Framework\TestCase;
use function dirname;
use function fclose;
use function php_ini_loaded_file;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use const PHP_BINARY;

final class AsyncGeneratorExecutorNativeTest extends TestCase{

	/** @return iterable<string, array{list<string>}> */
	public static function workerCases() : iterable{
		yield "retired worker0" => [[]];
		yield "live worker2 with a gap" => [["--sparse"]];
	}

	/**
	 * @param list<string> $arguments
	 * @dataProvider workerCases
	 */
	public function testWorkerLifecycle(array $arguments) : void{
		$command = [PHP_BINARY, "-c", php_ini_loaded_file(), dirname(__DIR__, 4) . "/support/optimization/worker-regression.php", ...$arguments];
		$process = proc_open($command, [1 => ["pipe", "w"], 2 => ["redirect", 1]], $pipes);
		self::assertIsResource($process);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		self::assertSame(0, proc_close($process), $output);
	}
}
