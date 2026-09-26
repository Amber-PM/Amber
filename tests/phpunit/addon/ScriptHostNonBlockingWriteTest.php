<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use ReflectionClass;

final class ScriptHostNonBlockingWriteTest extends TestCase {

	private mixed $process = null;
	/** @var resource[] */
	private array $pipes = [];

	protected function tearDown() : void {
		foreach ($this->pipes as $pipe) {
			if (is_resource($pipe)) {
				fclose($pipe);
			}
		}
		if (is_resource($this->process)) {
			proc_terminate($this->process);
			proc_close($this->process);
		}
	}

	public function testLargePayloadWriteDoesNotBlockAndBuffersOverflow() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$domain = PHP_OS_FAMILY === "Windows" ? STREAM_PF_INET : STREAM_PF_UNIX;
		$proto = PHP_OS_FAMILY === "Windows" ? STREAM_IPPROTO_IP : 0;
		$pair = stream_socket_pair($domain, STREAM_SOCK_STREAM, $proto);
		self::assertIsArray($pair);
		$this->pipes[] = $pair[0];
		$this->pipes[] = $pair[1];

		$cmd = [PHP_BINARY, "-r", "sleep(10);"];
		$this->process = proc_open($cmd, [
			0 => $pair[1],
			1 => ["pipe", "w"],
			2 => ["pipe", "w"]
		], $subPipes, null, null, ["bypass_shell" => true]);

		self::assertIsResource($this->process);
		foreach ($subPipes as $p) {
			$this->pipes[] = $p;
		}

		$stdin = $pair[0];
		stream_set_blocking($stdin, false);

		while (@fwrite($stdin, str_repeat("A", 65536)) > 0) {
		}

		$rc->getProperty("stdin")->setValue($host, $stdin);
		$rc->getProperty("writeBuffer")->setValue($host, "");

		$writeMethod = $rc->getMethod("write");

		// large ~1 mb payload
		$largeData = str_repeat("X", 1024 * 1024);
		$payload = ["t" => "p", "op" => "test", "data" => $largeData];

		$start = hrtime(true);
		$writeMethod->invoke($host, $payload);
		$elapsedMs = (hrtime(true) - $start) / 1_000_000;

		// write() must return promptly (far less than the 10s sleep)
		self::assertLessThan(500, $elapsedMs, "write() took too long; may have blocked on OS pipe buffer");

		// unsent data must remain buffered in writeBuffer once pipe capacity is reached
		$buffered = $rc->getProperty("writeBuffer")->getValue($host);
		self::assertGreaterThan(0, strlen($buffered), "Unsent overflow must remain in writeBuffer");
	}
}
