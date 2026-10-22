<?php

declare(strict_types=1);

use pocketmine\build\server_phar\BuildMetadataResolver;
use pocketmine\utils\Git;
use pocketmine\VersionInfo;

require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/BuildMetadataResolver.php';

try{
	$opts = BuildMetadataResolver::parseOptions(array_slice($argv, 1), ["phar", "git", "build", "tag", "channel", "is-dev"]);
	$pharPath = $opts["phar"] ?? throw new InvalidArgumentException("--phar requires the built artifact path");
	unset($opts["phar"]);
	$env = [];
	foreach(["GITHUB_REF_TYPE", "GITHUB_REF_NAME", "GITHUB_RUN_NUMBER", "GITHUB_SHA"] as $key){
		$env[$key] = (string) (getenv($key) ?: "");
	}
	$expected = BuildMetadataResolver::resolve(VersionInfo::BASE_VERSION, $opts, $env, Git::getRepositoryStatePretty(dirname(__DIR__)));
	$actual = BuildMetadataResolver::readPharMetadata($pharPath);
	BuildMetadataResolver::assertPharMetadataMatches($actual, $expected, true);
	BuildMetadataResolver::assertPharConstantsMatch($pharPath, $actual, VersionInfo::NAME, VersionInfo::COMPATIBLE_API_VERSION);
	echo json_encode(["name" => VersionInfo::NAME, "api_version" => VersionInfo::COMPATIBLE_API_VERSION, ...$actual], JSON_THROW_ON_ERROR) . PHP_EOL;
}catch(Throwable $e){
	fwrite(STDERR, "PHAR metadata error: " . $e->getMessage() . PHP_EOL);
	exit(1);
}
