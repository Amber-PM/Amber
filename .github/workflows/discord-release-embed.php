<?php

declare(strict_types=1);

namespace pocketmine;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\Internet;
use function array_unique;
use function count;
use function dirname;
use function fwrite;
use function is_array;
use function json_decode;
use function json_encode;
use function ltrim;
use function realpath;
use function str_starts_with;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

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

if(isset($argv[0]) && realpath($argv[0]) === __FILE__){
	if(count($argv) < 5 || count($argv) > 6){
		fwrite(STDERR, "Required arguments: <github repo> <version tag> <API token> <webhook URL> [news ping role ID]\n");
		exit(1);
	}

	$repo = $argv[1];
	$rawTagName = $argv[2];
	$token = $argv[3];
	$hookURL = $argv[4];
	$newsPingRoleId = isset($argv[5]) ? (int) $argv[5] : 0;

	if(empty($hookURL) || $hookURL === "none"){
		fwrite(STDERR, "DISCORD_RELEASE_WEBHOOK is empty or not configured. Skipping Discord notification.\n");
		exit(0);
	}

	$extraHeaders = ['User-Agent: PocketmineMV-Release-Notifier'];
	if(!empty($token) && $token !== "none" && !str_starts_with($token, "dummy")){
		$extraHeaders[] = 'Authorization: Bearer ' . $token;
	}

	$candidates = array_unique([$rawTagName, "v" . ltrim($rawTagName, "v"), ltrim($rawTagName, "v")]);
	$releaseInfoJson = null;
	$matchedTag = $rawTagName;

	foreach($candidates as $candidate){
		$res = Internet::getURL('https://api.github.com/repos/' . $repo . '/releases/tags/' . $candidate, extraHeaders: $extraHeaders);
		if($res !== null && $res->getCode() === 200){
			$decoded = json_decode($res->getBody(), true);
			if(is_array($decoded)){
				$releaseInfoJson = $decoded;
				$matchedTag = $candidate;
				break;
			}
		}
	}

	$detailsUrl = "https://github.com/$repo/releases/tag/$matchedTag";
	$sourceUrl = "https://github.com/$repo/tree/$matchedTag";
	$pharDownloadUrl = "https://github.com/$repo/releases/download/$matchedTag/PocketMine-MP.phar";
	$buildLogUrl = "https://github.com/$repo/actions";
	$phpBinaryUrl = "https://github.com/pmmp/PHP-Binaries/releases/tag/pm5-php-8.2-latest";
	$baseVersion = $releaseInfoJson["name"] ?? $matchedTag;
	$channel = ($releaseInfoJson["prerelease"] ?? false) ? "beta" : "stable";

	$mcVersion = ProtocolInfo::MINECRAFT_VERSION_NETWORK;
	$mcDisplayVersion = ProtocolInfo::MINECRAFT_VERSION;

	$defaultDescription = "**For Minecraft: Bedrock Edition $mcVersion (display version $mcDisplayVersion)**\n\n" .
		"Please see the [changelogs]($detailsUrl) for details.\n\n" .
		":information_source: Download the recommended PHP binary [here]($phpBinaryUrl).\n\n" .
		":warning: Found a bug? Report it on our [issue tracker](https://github.com/$repo/issues). **We can't fix bugs if you don't report them.**";

	$description = !empty($releaseInfoJson["body"]) ? $releaseInfoJson["body"] : $defaultDescription;

	$buildInfoPath = 'https://github.com/' . $repo . '/releases/download/' . $matchedTag . '/build_info.json';
	$buildInfoResult = Internet::getURL($buildInfoPath, extraHeaders: $extraHeaders);
	if($buildInfoResult !== null && $buildInfoResult->getCode() === 200){
		$buildInfoJson = json_decode($buildInfoResult->getBody(), true);
		if(is_array($buildInfoJson)){
			$detailsUrl = $buildInfoJson["details_url"] ?? $detailsUrl;
			$sourceUrl = $buildInfoJson["source_url"] ?? $sourceUrl;
			$pharDownloadUrl = $buildInfoJson["download_url"] ?? $pharDownloadUrl;
			$buildLogUrl = $buildInfoJson["build_log_url"] ?? $buildLogUrl;
			$phpBinaryUrl = $buildInfoJson["php_download_url"] ?? $phpBinaryUrl;
			$baseVersion = $buildInfoJson["base_version"] ?? $baseVersion;
			$channel = $buildInfoJson["channel"] ?? $channel;
		}
	}

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
	if($response?->getCode() !== 204 && $response?->getCode() !== 200){
		fwrite(STDERR, "Failed to send Discord webhook (Code: " . ($response?->getCode() ?? 0) . ")\n");
		fwrite(STDERR, $response?->getBody() ?? "No response body\n");
		exit(1);
	}

	echo "Successfully sent release notification to Discord webhook!\n";
}
