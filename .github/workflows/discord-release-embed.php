<?php

declare(strict_types=1);

namespace pocketmine;

use pocketmine\utils\Internet;
use function count;
use function dirname;
use function fwrite;
use function is_array;
use function json_decode;
use function json_encode;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * @phpstan-return array<string, mixed>
 */
function generateDiscordEmbed(
	string $version,
	string $channel,
	string $description,
	string $detailsUrl,
	string $sourceUrl,
	string $pharDownloadUrl,
	string $buildLogUrl,
	int $newsPingRoleId,
	?string $phpDownloadUrl,
	string $releaseName = VersionInfo::NAME
) : array{
	$phpEmbedLink = $phpDownloadUrl !== null ? " | [PHP Binaries]($phpDownloadUrl)" : "";
	$pingPrefix = $newsPingRoleId > 0 ? "<@&$newsPingRoleId> " : "";
	$titleText = "New $releaseName release: $version ($channel)";

	return [
		"content" => "$pingPrefix$titleText",
		"embeds" => [
			[
				"title" => $titleText,
				"description" => <<<DESCRIPTION
$description

[Details]($detailsUrl) | [Source Code]($sourceUrl) | [Build Log]($buildLogUrl) | [Download]($pharDownloadUrl)$phpEmbedLink
DESCRIPTION,
				"url" => $detailsUrl,
				"color" => $channel === "stable" ? 0x57ab5a : 0xc69026
			]
		]
	];
}

if(count($argv) < 5 || count($argv) > 6){
	fwrite(STDERR, "Required arguments: <github repo> <version tag> <API token> <webhook URL> [news ping role ID]\n");
	exit(1);
}

$repo = $argv[1];
$tagName = $argv[2];
$token = $argv[3];
$hookURL = $argv[4];
$newsPingRoleId = isset($argv[5]) ? (int) $argv[5] : 0;

$result = Internet::getURL('https://api.github.com/repos/' . $repo . '/releases/tags/' . $tagName, extraHeaders: [
	'Authorization: token ' . $token,
	'User-Agent: PocketmineMV-Release-Notifier'
]);
if($result === null || $result->getCode() !== 200){
	fwrite(STDERR, "Error accessing GitHub API for release $tagName (Code: " . ($result?->getCode() ?? 0) . ")\n");
	if($result !== null){
		fwrite(STDERR, $result->getBody() . "\n");
	}
	exit(1);
}

$releaseInfoJson = json_decode($result->getBody(), true, JSON_THROW_ON_ERROR);
if(!is_array($releaseInfoJson)){
	fwrite(STDERR, "Invalid release JSON returned from GitHub API\n");
	exit(1);
}

$detailsUrl = $releaseInfoJson["html_url"] ?? "https://github.com/$repo/releases/tag/$tagName";
$sourceUrl = "https://github.com/$repo/tree/$tagName";
$pharDownloadUrl = "https://github.com/$repo/releases/download/$tagName/PocketMine-MP.phar";
$buildLogUrl = "https://github.com/$repo/actions";
$phpBinaryUrl = null;
$baseVersion = $releaseInfoJson["name"] ?? $tagName;
$channel = ($releaseInfoJson["prerelease"] ?? false) ? "beta" : "stable";

$buildInfoPath = 'https://github.com/' . $repo . '/releases/download/' . $tagName . '/build_info.json';
$buildInfoResult = Internet::getURL($buildInfoPath, extraHeaders: [
	'Authorization: token ' . $token,
	'User-Agent: PocketmineMV-Release-Notifier'
]);
if($buildInfoResult !== null && $buildInfoResult->getCode() === 200){
	$buildInfoJson = json_decode($buildInfoResult->getBody(), true, JSON_THROW_ON_ERROR);
	if(is_array($buildInfoJson)){
		$detailsUrl = $buildInfoJson["details_url"] ?? $detailsUrl;
		$sourceUrl = $buildInfoJson["source_url"] ?? $sourceUrl;
		$pharDownloadUrl = $buildInfoJson["download_url"] ?? $pharDownloadUrl;
		$buildLogUrl = $buildInfoJson["build_log_url"] ?? $buildLogUrl;
		$phpBinaryUrl = $buildInfoJson["php_download_url"] ?? null;
		$baseVersion = $buildInfoJson["base_version"] ?? $baseVersion;
		$channel = $buildInfoJson["channel"] ?? $channel;
	}
}

$description = $releaseInfoJson["body"] ?? "";

$discordPayload = generateDiscordEmbed(
	$baseVersion,
	$channel,
	$description,
	$detailsUrl,
	$sourceUrl,
	$pharDownloadUrl,
	$buildLogUrl,
	$newsPingRoleId,
	$phpBinaryUrl
);

$response = Internet::postURL(
	$hookURL,
	json_encode($discordPayload, JSON_THROW_ON_ERROR),
	extraHeaders: ['Content-Type: application/json']
);
if($response?->getCode() !== 204){
	fwrite(STDERR, "Failed to send Discord webhook (Code: " . ($response?->getCode() ?? 0) . ")\n");
	fwrite(STDERR, $response?->getBody() ?? "No response body\n");
	exit(1);
}

echo "Successfully sent release notification to Discord webhook!\n";
