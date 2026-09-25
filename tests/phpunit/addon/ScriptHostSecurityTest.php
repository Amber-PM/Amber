<?php

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use pocketmine\utils\Filesystem;

final class ScriptHostSecurityTest extends TestCase{

	private string $tempDir;
	private $process;
	private $pipes;

	protected function setUp() : void{
		$this->tempDir = sys_get_temp_dir() . "/amber_addon_test_" . bin2hex(random_bytes(4));
		mkdir($this->tempDir);
	}

	protected function tearDown() : void{
		if(is_resource($this->process)){
			foreach($this->pipes as $pipe){
				if(is_resource($pipe)) fclose($pipe);
			}
			$status = proc_get_status($this->process);
			if($status["running"]){
				proc_terminate($this->process);
			}
			proc_close($this->process);
		}
		Filesystem::recursiveUnlink($this->tempDir);
	}

	private function startNode(string $jsCode) : void{
		$packDir = Path::join($this->tempDir, "pack");
		mkdir($packDir);
		file_put_contents(Path::join($packDir, "index.js"), $jsCode);

		$hostMjs = realpath(__DIR__ . "/../../../resources/addon_scripts/host.mjs");
		$hostDir = dirname($hostMjs);

		$command = [
			"node",
			"--permission",
			"--allow-worker",
			"--allow-fs-read=" . $hostDir . "/*",
			"--allow-fs-read=" . Path::canonicalize($packDir) . "/*",
			$hostMjs
		];
		$cmdStr = implode(" ", array_map('escapeshellarg', $command));

		$this->process = proc_open($cmdStr, [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"],
			2 => ["pipe", "w"]
		], $this->pipes, $hostDir, null, ["bypass_shell" => true]);

		self::assertTrue(is_resource($this->process));

		$init = json_encode([
			"t" => "init",
			"packs" => [
				["name" => "testpack", "root" => $packDir, "entry" => Path::join($packDir, "index.js"), "api" => 2]
			],
			"dims" => [],
			"tick" => 1
		]) . "\n";

		fwrite($this->pipes[0], $init);
	}

	private function readUntilDone() : array {
		$errs = "";
		$type = "unknown";
		while(!feof($this->pipes[1])){
			$line = fgets($this->pipes[1]);
			if($line === false) break;
			$decoded = json_decode($line, true);
			if($decoded === null) continue;
			if(isset($decoded["t"]) && $decoded["t"] === "done"){
				$type = "done";
				break;
			}
			if($decoded["t"] === "forged"){
				$type = "forged";
				break;
			}
			if($decoded["t"] === "p" && isset($decoded["op"]) && $decoded["op"] === "log"){
				$errs .= ($decoded["a"]["msg"] ?? "") . "\n";
			}
		}

		return [$type, $errs];
	}

	public function testCapabilityLeak() : void{
		$this->startNode('console.error("ENV_LEAK:" + (process.env.PATH !== undefined));');

		[$type, $err] = $this->readUntilDone();
		self::assertSame("done", $type);

		// it should NOT leak env vars
		// if env is stripped process.env.PATH is undefined, so output is "ENV_LEAK:false"
		self::assertStringContainsString("ENV_LEAK:false", $err, "Environment variables leaked to script.");
	}

	public function testIpcForgery() : void{
		$this->startNode('process.stdout.write("{\"t\":\"forged\"}\n");');

		[$type, $err] = $this->readUntilDone();

		self::assertNotSame("forged", $type, "Worker stdout is not isolated; forged IPC frame leaked.");
		self::assertSame("done", $type);
	}

	public function testCrossPackImport() : void{
		$pack1 = Path::join($this->tempDir, "pack1");
		$pack2 = Path::join($this->tempDir, "pack2");
		mkdir($pack1);
		mkdir($pack2);
		file_put_contents(Path::join($pack1, "index.js"), 'import "../pack2/secret.js";');
		file_put_contents(Path::join($pack2, "secret.js"), 'console.error("LEAKED_SECRET");');
		file_put_contents(Path::join($pack2, "index2.js"), '');

		$hostMjs = realpath(__DIR__ . "/../../../resources/addon_scripts/host.mjs");
		$hostDir = dirname($hostMjs);

		$command = [
			"node",
			"--permission",
			"--allow-worker",
			"--allow-fs-read=" . $hostDir . "/*",
			"--allow-fs-read=" . Path::canonicalize($pack1) . "/*",
			"--allow-fs-read=" . Path::canonicalize($pack2) . "/*",
			$hostMjs
		];
		$cmdStr = implode(" ", array_map('escapeshellarg', $command));

		$this->process = proc_open($cmdStr, [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"],
			2 => ["pipe", "w"]
		], $this->pipes, $hostDir, null, ["bypass_shell" => true]);

		$init = json_encode([
			"t" => "init",
			"packs" => [
				["name" => "pack1", "root" => $pack1, "entry" => Path::join($pack1, "index.js"), "api" => 2],
				["name" => "pack2", "root" => $pack2, "entry" => Path::join($pack2, "index2.js"), "api" => 2]
			],
			"dims" => [],
			"tick" => 1
		]) . "\n";

		fwrite($this->pipes[0], $init);

		[$type, $err] = $this->readUntilDone();
		self::assertSame("done", $type);
		self::assertStringNotContainsString("LEAKED_SECRET", $err);
		self::assertStringContainsString("leaves its behavior pack", $err);
	}

	public function testCrossPackPrefixCollision() : void{
		$pack1 = Path::join($this->tempDir, "pack1");
		$pack10 = Path::join($this->tempDir, "pack10");
		mkdir($pack1);
		mkdir($pack10);
		file_put_contents(Path::join($pack1, "index.js"), 'import "../pack10/secret.js";');
		file_put_contents(Path::join($pack10, "secret.js"), 'console.error("PREFIX_ESCAPE");');
		file_put_contents(Path::join($pack10, "index.js"), '');

		$hostMjs = realpath(__DIR__ . "/../../../resources/addon_scripts/host.mjs");
		$hostDir = dirname($hostMjs);

		$command = [
			"node",
			"--permission",
			"--allow-worker",
			"--allow-fs-read=" . $hostDir . "/*",
			"--allow-fs-read=" . Path::canonicalize($pack1) . "/*",
			"--allow-fs-read=" . Path::canonicalize($pack10) . "/*",
			$hostMjs
		];
		$cmdStr = implode(" ", array_map('escapeshellarg', $command));

		$this->process = proc_open($cmdStr, [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"],
			2 => ["pipe", "w"]
		], $this->pipes, $hostDir, null, ["bypass_shell" => true]);

		$init = json_encode([
			"t" => "init",
			"packs" => [
				["name" => "pack1", "root" => $pack1, "entry" => Path::join($pack1, "index.js"), "api" => 2],
				["name" => "pack10", "root" => $pack10, "entry" => Path::join($pack10, "index.js"), "api" => 2]
			],
			"dims" => [],
			"tick" => 1
		]) . "\n";

		fwrite($this->pipes[0], $init);

		[$type, $err] = $this->readUntilDone();
		self::assertSame("done", $type);
		self::assertStringNotContainsString("PREFIX_ESCAPE", $err);
		self::assertStringContainsString("leaves its behavior pack", $err);
	}
}
