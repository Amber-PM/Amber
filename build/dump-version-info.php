<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

use pocketmine\build\server_phar\BuildMetadataResolver;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\VersionInfo;

require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/BuildMetadataResolver.php';

/*
 * Dumps version info in a machine-readable format for use in GitHub Actions workflows
 */

/**
 * @var string[]|Closure[] $options
 * @phpstan-var array<string, string|Closure() : string> $options
 */
$options = [
	"base_version" => fn() : string => VersionInfo::BASE_VERSION(),
	"api_version" => fn() : string => VersionInfo::COMPATIBLE_API_VERSION,
	"major_version" => fn() : string => explode(".", VersionInfo::BASE_VERSION(), limit: 2)[0],
	"mcpe_version" => ProtocolInfo::MINECRAFT_VERSION_NETWORK,
	"mcpe_display_version" => ProtocolInfo::MINECRAFT_VERSION,
	"is_dev" => fn() : bool => VersionInfo::IS_DEVELOPMENT_BUILD(),
	"changelog_file_name" => function() : string{
		$version = VersionInfo::VERSION();
		$result = $version->getMajor() . "." . $version->getMinor();
		$suffix = $version->getSuffix();
		if($suffix !== ""){
			if(preg_match('/^([A-Za-z]+)(\d+)$/', $suffix, $matches) !== 1){
				fwrite(STDERR, "error: invalid current version suffix \"$suffix\"; aborting" . PHP_EOL);
				exit(1);
			}
			$baseSuffix = $matches[1];
			$result .= "-" . strtolower($baseSuffix);
		}
		return $result . ".md";
	},
	"changelog_md_header" => fn() : string => str_replace(".", "", VersionInfo::BASE_VERSION()),
	"prerelease" => fn() : bool => VersionInfo::VERSION()->getSuffix() !== "",
	"channel" => fn() : string => VersionInfo::BUILD_CHANNEL(),
	"suffix_valid" => function() : bool{
		//TODO: maybe this should be put into its own script?
		$suffix = VersionInfo::VERSION()->getSuffix();
		$channel = VersionInfo::BUILD_CHANNEL();
		if($channel === "stable"){
			//stable builds may not have suffixes
			return $suffix === "";
		}
		if($channel === "alpha" || $channel === "beta"){
			$upperChannel = strtoupper($channel);
			$upperSuffix = strtoupper($suffix);
			return str_starts_with($upperSuffix, $upperChannel) && is_numeric(substr($upperSuffix, strlen($upperChannel)));
		}
		return true;
	}
];

$args = array_slice($argv ?? [], 1);
$requestedKey = array_shift($args);

$env = [
	"GITHUB_REF_TYPE" => (string) (getenv("GITHUB_REF_TYPE") ?: ""),
	"GITHUB_REF_NAME" => (string) (getenv("GITHUB_REF_NAME") ?: ""),
	"GITHUB_RUN_NUMBER" => (string) (getenv("GITHUB_RUN_NUMBER") ?: ""),
	"GITHUB_SHA" => (string) (getenv("GITHUB_SHA") ?: ""),
];

try{
	if(!is_string($requestedKey) || !isset($options[$requestedKey])){
		throw new \InvalidArgumentException("Please provide an option (one of: " . implode(", ", array_keys($options)) . ")");
	}
	$cliOpts = BuildMetadataResolver::parseOptions($args, ["phar", "tag", "git", "build", "channel", "is-dev"]);
	$requestedPhar = $cliOpts["phar"] ?? null;
	if($requestedPhar !== null && (!is_string($requestedPhar) || $requestedPhar === "")){
		throw new \InvalidArgumentException("--phar requires a file path");
	}
	unset($cliOpts["phar"]);

	$hasTag = isset($cliOpts["tag"]) ||
		($env["GITHUB_REF_TYPE"] === "tag");
	if($requestedPhar !== null && !$hasTag && $cliOpts === []){
		$metadata = BuildMetadataResolver::readPharMetadata($requestedPhar);
	}else{
		$metadata = BuildMetadataResolver::resolve(VersionInfo::BASE_VERSION, $cliOpts, $env);
		if($requestedPhar !== null){
			BuildMetadataResolver::assertPharMetadataMatches(
				BuildMetadataResolver::readPharMetadata($requestedPhar),
				$metadata,
				true
			);
		}
	}
	if($requestedPhar !== null){
		BuildMetadataResolver::assertPharConstantsMatch($requestedPhar, $metadata, VersionInfo::NAME, VersionInfo::COMPATIBLE_API_VERSION);
	}
}catch(\InvalidArgumentException $e){
	fwrite(STDERR, "Version metadata error: " . $e->getMessage() . PHP_EOL);
	exit(1);
}

VersionInfo::setPharMetadataOverride($metadata);

if($requestedKey === null){
	fwrite(STDERR, "Please provide an option (one of: " . implode(", ", array_keys($options)) . ")" . PHP_EOL);
	exit(1);
}

$result = $options[$requestedKey];
if($result instanceof Closure){
	$result = $result();
}
if(is_bool($result)){
	echo $result ? "true" : "false";
}else{
	echo $result;
}
