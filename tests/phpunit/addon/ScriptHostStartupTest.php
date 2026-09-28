<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Filesystem;

final class ScriptHostStartupTest extends TestCase{
	private function runStartup(string $mode, float $limit) : array{
		$dir = sys_get_temp_dir() . "/amber-startup-" . bin2hex(random_bytes(8));
		mkdir($dir);
		$output = $dir . "/result";
		$process = proc_open([PHP_BINARY, __DIR__ . "/fixtures/script-host-startup.php", $mode, $dir], [
			0 => ["file", PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null", "r"],
			1 => ["file", $output, "w"], 2 => ["redirect", 1]
		], $pipes);
		self::assertIsResource($process);
		$deadline = hrtime(true) + (int) ($limit * 1e9);
		try{
			do{
				$status = proc_get_status($process);
				if(!$status["running"]){
					self::assertSame(0, $status["exitcode"], (string) file_get_contents($output));
					return json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
				}
				usleep(10000);
			}while(hrtime(true) < $deadline);
			self::fail("ScriptHost startup exceeded its external watchdog ($limit seconds)");
		}finally{
			if(proc_get_status($process)["running"]){
				if(PHP_OS_FAMILY === "Windows"){
					exec("taskkill /PID " . $status["pid"] . " /T /F > NUL 2>&1");
				}else{
					proc_terminate($process);
				}
			}
			proc_close($process);
			Filesystem::recursiveUnlink($dir);
		}
	}

	public function testStartupDoneAndSubsequentRequestsDoNotBlockOnQuietStderr() : void{
		$result = $this->runStartup("done", 5);
		self::assertTrue($result["started"]);
		self::assertTrue($result["before"]["cancel"]);
		self::assertFalse($result["busy"]);
		self::assertNull($result["idle"]);
		self::assertLessThan(0.2, $result["idleElapsed"]);
		self::assertTrue($result["outputsRemoved"]);
		self::assertTrue($result["restarted"]);
	}

	public function testUnfinishedStartupStillTimesOut() : void{
		$result = $this->runStartup("timeout", 20);
		self::assertFalse($result["started"]);
		self::assertGreaterThanOrEqual(15, $result["elapsed"]);
		self::assertLessThan(18, $result["elapsed"]);
		self::assertContains("Add-on scripts: the script host did not finish starting within 15 seconds", $result["logs"]);
	}
}
