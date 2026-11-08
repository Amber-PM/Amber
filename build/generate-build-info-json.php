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

if(!isset($argv) || (count($argv) !== 7 && count($argv) !== 8) ||
	(isset($argv[7]) && !str_starts_with($argv[7], "--phar="))){
	fwrite(STDERR, "required args: <git hash> <tag name> <github repo (owner/name)> <build number> <github actions run ID> <PHP binary download URL> [--phar=<path>]\n");
	exit(1);
}

try{
	// The release tag is authoritative; a stale PHAR in the working directory must never override it.
	$metadata = BuildMetadataResolver::resolve(
		VersionInfo::BASE_VERSION,
		[
			"tag" => $argv[2],
			"git" => $argv[1],
			"build" => $argv[4],
			"is-dev" => false,
		]
	);

	// An explicitly supplied artifact must describe this exact release, including build identity.
	if(isset($argv[7])){
		$pharPath = substr($argv[7], strlen("--phar="));
		BuildMetadataResolver::assertPharMetadataMatches(
			BuildMetadataResolver::readPharMetadata($pharPath),
			$metadata,
			true
		);
		BuildMetadataResolver::assertPharConstantsMatch($pharPath, $metadata, VersionInfo::NAME, VersionInfo::COMPATIBLE_API_VERSION);
	}
}catch(\InvalidArgumentException $e){
	fwrite(STDERR, "Version metadata error: " . $e->getMessage() . PHP_EOL);
	exit(1);
}

$tag = trim($argv[2]);

echo json_encode([
	"php_version" => sprintf("%d.%d", PHP_MAJOR_VERSION, PHP_MINOR_VERSION), //deprecated
	"base_version" => $metadata["baseVersion"],
	"api_version" => VersionInfo::COMPATIBLE_API_VERSION,
	"build" => $metadata["build"],
	"is_dev" => $metadata["isDev"],
	"channel" => $metadata["channel"],
	"git_commit" => $metadata["git"],
	"mcpe_version" => ProtocolInfo::MINECRAFT_VERSION_NETWORK,
	"date" => time(), //TODO: maybe we should embed this in VersionInfo?
	"details_url" => "https://github.com/$argv[3]/releases/tag/$tag",
	"download_url" => "https://github.com/$argv[3]/releases/download/$tag/PocketMine-MP.phar",
	"source_url" => "https://github.com/$argv[3]/tree/$tag",
	"build_log_url" => "https://github.com/$argv[3]/actions/runs/$argv[5]",
	"php_download_url" => $argv[6],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
