<?php

declare(strict_types=1);

namespace pocketmine\updater;

use PHPUnit\Framework\TestCase;
use pocketmine\scheduler\AsyncPool;
use pocketmine\Server;
use pocketmine\ServerConfigGroup;
use pocketmine\thread\log\AttachableThreadSafeLogger;
use pocketmine\utils\Config;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class UpdateCheckerTest extends TestCase{
	public function testUpdaterIsDisabledWhenTheSettingIsMissing() : void{
		$this->assertTaskCount(null, 0);
	}

	public function testUpdaterCanBeExplicitlyDisabled() : void{
		$this->assertTaskCount(false, 0);
	}

	public function testUpdaterCanBeExplicitlyEnabled() : void{
		$this->assertTaskCount(true, 1);
	}

	private function assertTaskCount(?bool $enabled, int $expected) : void{
		$path = tempnam(sys_get_temp_dir(), "amber-updater-");
		self::assertNotFalse($path);
		file_put_contents($path, "{}");
		try{
			$config = new Config($path, Config::JSON);
			if($enabled !== null){
				$config->setNested("auto-updater.enabled", $enabled);
			}
			$pool = $this->createMock(AsyncPool::class);
			$pool->expects(self::exactly($expected))->method("submitTask")->with(self::isInstanceOf(UpdateCheckTask::class));
			$server = $this->createMock(Server::class);
			$server->method("getConfigGroup")->willReturn(new ServerConfigGroup($config, $config));
			$server->method("getAsyncPool")->willReturn($pool);
			$server->method("getLogger")->willReturn(new class extends AttachableThreadSafeLogger{
				public function emergency($message) : void{}
				public function alert($message) : void{}
				public function critical($message) : void{}
				public function error($message) : void{}
				public function warning($message) : void{}
				public function notice($message) : void{}
				public function info($message) : void{}
				public function debug($message) : void{}
				public function log($level, $message) : void{}
				public function logException(\Throwable $e, $trace = null) : void{}
			});
			new UpdateChecker($server, "update.pmmp.io");
		}finally{
			unlink($path);
		}
	}
}
