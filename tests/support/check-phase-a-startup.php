<?php

declare(strict_types=1);

// Integration regression: use a built PHAR and a NEW isolated data directory.
// Usage: php check-phase-a-startup.php <phar> <data-dir> <enable-dev-builds:true|false> <expect-dev:true|false>
if(count($argv) !== 5 || !in_array($argv[3], ["true", "false"], true) || !in_array($argv[4], ["true", "false"], true)){
	fwrite(STDERR, "Required: <phar> <new-data-dir> <enable-dev-builds:true|false> <expect-dev:true|false>\n");
	exit(1);
}
$pharPath = realpath($argv[1]);
$data = $argv[2];
if($pharPath === false || file_exists($data)){
	fwrite(STDERR, "Artifact must exist and data directory must be new\n");
	exit(1);
}
mkdir($data, 0777, true);
$config = yaml_parse_file(dirname(__DIR__, 2) . "/resources/pocketmine.yml");
$config["settings"]["enable-dev-builds"] = $argv[3] === "true";
$config["settings"]["async-workers"] = 1;
$config["auto-updater"]["enabled"] = false;
file_put_contents($data . "/pocketmine.yml", yaml_emit($config));
file_put_contents($data . "/server.properties", "server-ip=127.0.0.1\nserver-port=0\nenable-ipv6=off\nenable-query=off\nlanguage=eng\nlevel-type=FLAT\nview-distance=2\n");
$log = $data . "/console.log";
$process = proc_open([PHP_BINARY, "-c", (string) php_ini_loaded_file(), $pharPath, "--no-wizard", "--disable-ansi", "--data=$data", "--plugins=$data/plugins"], [0 => ["pipe", "r"], 1 => ["file", $log, "w"], 2 => ["file", $data . "/stderr.log", "w"]], $pipes, $data);
if(!is_resource($process)){
	throw new RuntimeException("Cannot start PHAR");
}
$expectedDev = $argv[4] === "true";
$expectedStart = !$expectedDev || $argv[3] === "true";
$started = false;
$deadline = microtime(true) + 60;
do{
	usleep(100000);
	$output = file_get_contents($log);
	$status = proc_get_status($process);
	if(!$started && str_contains($output, "Done (")){
		$started = true;
		fwrite($pipes[0], "version\nabout\nstop\n");
	}
}while($status["running"] && microtime(true) < $deadline);
if($status["running"]){
	fwrite($pipes[0], "stop\n");
	proc_terminate($process);
}
fclose($pipes[0]);
$exit = proc_close($process);
if($exit === -1 && !$status["running"]){
	$exit = $status["exitcode"];
}
$output = file_get_contents($log);
$errors = file_get_contents($data . "/stderr.log");
$rejected = str_contains($output, "configuration does not permit running development builds");
$warning = str_contains($output, "You are running a AmberPM DEVELOPMENT build.") &&
	str_contains($output, "The API for this version may not be finalized.") &&
	str_contains($output, "The build may have bugs, crash, corrupt your data, or break your plugins.");
$passed = !$status["running"] && $exit === ($expectedStart ? 0 : 1) && $started === $expectedStart &&
	$rejected === !$expectedStart && $warning === ($expectedDev && $expectedStart) &&
	(!$started || (str_contains($output, "PocketMine API 5.44.2") && str_contains($output, "This server is running AmberPM")));
echo json_encode(["pass" => $passed, "exit" => $exit, "started" => $started, "rejected" => $rejected, "warning" => $warning, "log" => $log], JSON_PRETTY_PRINT) . PHP_EOL;
if(!$passed){
	fwrite(STDERR, $output . $errors);
	exit(1);
}
