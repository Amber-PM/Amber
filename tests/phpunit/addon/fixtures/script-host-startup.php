<?php
declare(strict_types=1);

require dirname(__DIR__, 4) . "/vendor/autoload.php";

use pocketmine\addon\AddonPack;
use pocketmine\addon\script\ScriptHost;
use pocketmine\Server;
use pocketmine\world\WorldManager;

[$script, $mode, $dir] = $argv;
$packDir = $dir . "/pack";
mkdir($packDir);
file_put_contents($packDir . "/main.js", $mode === "timeout" ? "while(true){}" : <<<'JS'
import { world } from '@minecraft/server';
world.beforeEvents.chatSend.subscribe(event => { event.cancel = true; });
JS);
$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Server::class, "worldManager"))->setValue($server, (new ReflectionClass(WorldManager::class))->newInstanceWithoutConstructor());
$logger = new class extends SimpleLogger{
	public array $messages = [];
	public function log($level, $message){ $this->messages[] = $message; }
};
$host = (new ReflectionClass(ScriptHost::class))->newInstanceWithoutConstructor();
foreach([
	"server" => $server,
	"packs" => [new AddonPack("00000000-0000-4000-8000-000000000001", "startup test", [1, 0, 0], AddonPack::TYPE_DATA, $packDir, "test", "main.js")],
	"runtimeDir" => $dir . "/runtime", "nodeBinary" => "node", "tickBudgetMs" => 1000, "memoryMb" => 128, "logger" => $logger
] as $name => $value){
	(new ReflectionProperty(ScriptHost::class, $name))->setValue($host, $value);
}
$startedAt = hrtime(true);
try{
	$started = $host->start();
	$result = ["started" => $started, "elapsed" => (hrtime(true) - $startedAt) / 1e9];
	if($started){
		$host->tick(1);
		$result["before"] = $host->before("chatSend", ["message" => "hello"]);
		$host->tick(2);
		$result["busy"] = (new ReflectionProperty(ScriptHost::class, "busy"))->getValue($host);
		$idleAt = hrtime(true);
		$result["idle"] = (new ReflectionMethod(ScriptHost::class, "pumpUntil"))->invoke($host, ["done"], 20);
		$result["idleElapsed"] = (hrtime(true) - $idleAt) / 1e9;
	}
	$outputPaths = [];
	if(PHP_OS_FAMILY === "Windows"){
		foreach(["stdout", "stderr"] as $name){
			$outputStream = (new ReflectionProperty(ScriptHost::class, $name))->getValue($host);
			if(is_resource($outputStream)){
				$outputPaths[] = stream_get_meta_data($outputStream)["uri"];
			}
		}
	}
}finally{
	$host->stop();
}
$result["outputsRemoved"] = array_filter($outputPaths, "file_exists") === [];
if($started){
	$result["restarted"] = $host->start();
	$host->stop();
}
$result["logs"] = $logger->messages;
echo json_encode($result, JSON_THROW_ON_ERROR);
