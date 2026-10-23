<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\build\server_phar;

use InvalidArgumentException;
use function addslashes;
use function is_bool;
use function is_string;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strcmp;
use function strlen;
use function str_repeat;
use function strtolower;
use function trim;
use const PHP_INT_MAX;

final class BuildMetadataResolver{

	private function __construct(){
		//NOOP
	}

	/**
	 * @param list<string> $args
	 * @param list<string> $allowed
	 * @return array<string, string>
	 */
	public static function parseOptions(array $args, array $allowed) : array{
		$result = [];
		for($i = 0, $count = count($args); $i < $count; ++$i){
			if(!str_starts_with($args[$i], "--")){
				throw new InvalidArgumentException("Unexpected argument: " . $args[$i]);
			}
			$parts = explode("=", substr($args[$i], 2), 2);
			$key = $parts[0];
			if(!in_array($key, $allowed, true) || isset($result[$key])){
				throw new InvalidArgumentException("Unknown or repeated option --$key");
			}
			$value = $parts[1] ?? ($args[++$i] ?? "");
			if(trim($value) === "" || str_starts_with($value, "--")){
				throw new InvalidArgumentException("--$key requires a non-empty value");
			}
			$result[$key] = $value;
		}
		return $result;
	}

	/**
	 * Verifies the constants actually packaged in the artifact, without executing its code.
	 * @param array{git: string, build: int, isDev: bool, channel: string, baseVersion: string} $metadata
	 */
	public static function assertPharConstantsMatch(string $pharPath, array $metadata, string $name, string $apiVersion) : void{
		$phar = new \Phar($pharPath);
		if(!isset($phar["src/VersionInfo.php"])){
			throw new InvalidArgumentException("PHAR is missing src/VersionInfo.php");
		}
		$source = $phar["src/VersionInfo.php"]->getContent();
		if(self::injectConstants($source, $metadata) !== $source){
			throw new InvalidArgumentException("PHAR VersionInfo constants contradict artifact metadata");
		}
		foreach(["NAME" => $name, "COMPATIBLE_API_VERSION" => $apiVersion] as $constant => $expected){
			if(preg_match_all('/public const ' . $constant . ' = "([^"]*)";/', $source, $matches) !== 1 || $matches[1][0] !== $expected){
				throw new InvalidArgumentException("PHAR VersionInfo $constant does not match expected software identity");
			}
		}
	}

	/**
	 * Injects resolved artifact metadata into VersionInfo.php source text for packaging into the PHAR.
	 *
	 * @param array<string, mixed> $metadata
	 */
	public static function injectConstants(string $sourceCode, array $metadata) : string{
		if(!isset($metadata["isDev"], $metadata["channel"], $metadata["baseVersion"]) ||
			!is_bool($metadata["isDev"]) || !is_string($metadata["channel"]) || !is_string($metadata["baseVersion"])){
			throw new InvalidArgumentException("Incomplete or invalid version metadata for PHAR injection");
		}

		$replacements = [
			'IS_DEVELOPMENT_BUILD' => [
				'/public const IS_DEVELOPMENT_BUILD = (true|false);/',
				"public const IS_DEVELOPMENT_BUILD = " . ($metadata["isDev"] ? "true" : "false") . ";",
			],
			'BUILD_CHANNEL' => [
				'/public const BUILD_CHANNEL = "[^"]*";/',
				'public const BUILD_CHANNEL = "' . addslashes($metadata["channel"]) . '";',
			],
			'BASE_VERSION' => [
				'/public const BASE_VERSION = "[^"]*";/',
				'public const BASE_VERSION = "' . addslashes($metadata["baseVersion"]) . '";',
			],
		];

		foreach($replacements as $name => [$pattern, $replacement]){
			if(preg_match_all($pattern, $sourceCode) !== 1){
				throw new InvalidArgumentException("Expected exactly one $name constant in VersionInfo.php");
			}
			$sourceCode = preg_replace_callback($pattern, static fn() : string => $replacement, $sourceCode, 1) ??
				throw new InvalidArgumentException("Failed to inject $name constant");
		}
		return $sourceCode;
	}

	/**
	 * Reads a PHAR only when the caller explicitly requests artifact verification.
	 *
	 * @return array{git: string, build: int, isDev: bool, channel: string, baseVersion: string}
	 */
	public static function readPharMetadata(string $pharPath) : array{
		if(!is_file($pharPath)){
			throw new InvalidArgumentException("PHAR not found: $pharPath");
		}
		try{
			$meta = (new \Phar($pharPath))->getMetadata(["allowed_classes" => false]);
		}catch(\Throwable $e){
			throw new InvalidArgumentException("Cannot read PHAR metadata: $pharPath", 0, $e);
		}
		if(!is_array($meta) ||
			!isset($meta["git"], $meta["build"], $meta["isDev"], $meta["channel"], $meta["baseVersion"]) ||
			!is_string($meta["git"]) || !is_int($meta["build"]) || !is_bool($meta["isDev"]) ||
			!is_string($meta["channel"]) || !is_string($meta["baseVersion"])){
			throw new InvalidArgumentException("PHAR has missing or invalid version metadata: $pharPath");
		}
		if(preg_match('/^(\d+\.\d+\.\d+)(?:-(?:beta|alpha)\d+)?$/D', $meta["baseVersion"], $version) !== 1){
			throw new InvalidArgumentException("PHAR has invalid base version");
		}
		$resolved = self::resolve($version[1], [
			"git" => $meta["git"], "build" => $meta["build"],
			"is-dev" => $meta["isDev"], "channel" => $meta["channel"],
			...($meta["isDev"] ? [] : ["tag" => "v" . $meta["baseVersion"]]),
		]);
		self::assertPharMetadataMatches($meta, $resolved, true);
		return [
			"git" => $meta["git"],
			"build" => $meta["build"],
			"isDev" => $meta["isDev"],
			"channel" => $meta["channel"],
			"baseVersion" => $meta["baseVersion"],
		];
	}

	/**
	 * @param array{git: string, build: int, isDev: bool, channel: string, baseVersion: string} $actual
	 * @param array{git: string, build: int, isDev: bool, channel: string, baseVersion: string} $expected
	 */
	public static function assertPharMetadataMatches(array $actual, array $expected, bool $checkBuildIdentity = false) : void{
		$fields = ["isDev", "channel", "baseVersion"];
		if($checkBuildIdentity){
			$fields[] = "git";
			$fields[] = "build";
		}
		foreach($fields as $field){
			if($actual[$field] !== $expected[$field]){
				throw new InvalidArgumentException("PHAR metadata mismatch for $field: artifact does not match requested release");
			}
		}
	}

	/**
	 * @param array<string, mixed>  $opts
	 * @param array<string, string> $env
	 * @return array{git: string, build: int, isDev: bool, channel: string, baseVersion: string}
	 */
	public static function resolve(
		string $declaredBaseVersion,
		array $opts = [],
		array $env = [],
		?string $detectedGitHash = null
	) : array{
		foreach($opts as $key => $value){
			if(!in_array($key, ["git", "build", "tag", "channel", "is-dev"], true) ||
				(!is_string($value) && !($key === "build" && is_int($value)) && !($key === "is-dev" && is_bool($value))) ||
				(is_string($value) && trim($value) === "")){
				throw new InvalidArgumentException("Invalid metadata option --$key");
			}
		}
		$gitHash = self::resolveGitHash($opts, $env, $detectedGitHash);
		$buildNumber = self::resolveBuildNumber($opts, $env);
		$tag = self::resolveTag($opts, $env);
		$explicitIsDev = self::resolveExplicitIsDev($opts);
		$explicitChannel = self::resolveExplicitChannel($opts);

		if($tag !== null){
			return self::resolveFromTag($declaredBaseVersion, $tag, $gitHash, $buildNumber, $explicitIsDev, $explicitChannel);
		}

		if($explicitIsDev === false){
			throw new InvalidArgumentException("Cannot create a stable release build without a valid matching release tag");
		}

		if($explicitChannel === "beta" || $explicitChannel === "alpha"){
			throw new InvalidArgumentException("Channel '$explicitChannel' requires a matching numbered release tag");
		}
		return [
			"git" => $gitHash,
			"build" => $buildNumber,
			"isDev" => true,
			"channel" => $explicitChannel ?? "stable",
			"baseVersion" => $declaredBaseVersion,
		];
	}

	/**
	 * @param array<string, mixed>  $opts
	 * @param array<string, string> $env
	 */
	private static function resolveGitHash(array $opts, array $env, ?string $detectedGitHash) : string{
		if(isset($opts["git"]) && is_string($opts["git"]) && trim($opts["git"]) !== ""){
			return trim($opts["git"]);
		}
		if(isset($env["GITHUB_SHA"]) && trim($env["GITHUB_SHA"]) !== ""){
			return trim($env["GITHUB_SHA"]);
		}
		if($detectedGitHash !== null && trim($detectedGitHash) !== ""){
			return trim($detectedGitHash);
		}
		return str_repeat("00", 20);
	}

	/**
	 * @param array<string, mixed>  $opts
	 * @param array<string, string> $env
	 */
	private static function resolveBuildNumber(array $opts, array $env) : int{
		$value = $opts["build"] ?? ($env["GITHUB_RUN_NUMBER"] ?? "");
		if($value === ""){
			return 0;
		}
		if(!is_string($value) && !is_int($value)){
			throw new InvalidArgumentException("Invalid build number; expected a non-negative integer within platform range");
		}
		$valueString = (string) $value;
		$maxIntString = (string) PHP_INT_MAX;
		if(preg_match('/^(0|[1-9]\d*)$/D', $valueString) !== 1 ||
			strlen($valueString) > strlen($maxIntString) ||
			(strlen($valueString) === strlen($maxIntString) && strcmp($valueString, $maxIntString) > 0)){
			throw new InvalidArgumentException("Invalid build number; expected a non-negative integer within platform range");
		}
		return (int) $value;
	}

	/**
	 * @param array<string, mixed>  $opts
	 * @param array<string, string> $env
	 */
	private static function resolveTag(array $opts, array $env) : ?string{
		if(isset($opts["tag"]) && is_string($opts["tag"]) && trim($opts["tag"]) !== ""){
			return trim($opts["tag"]);
		}
		if(($env["GITHUB_REF_TYPE"] ?? "") === "tag"){
			if(trim($env["GITHUB_REF_NAME"] ?? "") === ""){
				throw new InvalidArgumentException("Tag context requires a non-empty GITHUB_REF_NAME");
			}
			return trim($env["GITHUB_REF_NAME"]);
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $opts
	 */
	private static function resolveExplicitIsDev(array $opts) : ?bool{
		if(!isset($opts["is-dev"])){
			return null;
		}
		$val = $opts["is-dev"];
		if(is_bool($val)){
			return $val;
		}
		if(is_string($val)){
			$lowered = strtolower(trim($val));
			if($lowered === "true" || $lowered === "1"){
				return true;
			}
			if($lowered === "false" || $lowered === "0"){
				return false;
			}
		}
		throw new InvalidArgumentException("Invalid --is-dev value; expected true or false");
	}

	/**
	 * @param array<string, mixed> $opts
	 */
	private static function resolveExplicitChannel(array $opts) : ?string{
		if(isset($opts["channel"]) && is_string($opts["channel"]) && trim($opts["channel"]) !== ""){
			$channel = strtolower(trim($opts["channel"]));
			if(!in_array($channel, ["stable", "beta", "alpha", "development"], true)){
				throw new InvalidArgumentException("Unknown build channel '$channel'");
			}
			return $channel;
		}
		return null;
	}

	/**
	 * @return array{git: string, build: int, isDev: bool, channel: string, baseVersion: string}
	 */
	private static function resolveFromTag(
		string $declaredBaseVersion,
		string $tag,
		string $gitHash,
		int $buildNumber,
		?bool $explicitIsDev,
		?string $explicitChannel
	) : array{
		if(preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $matches) === 1){
			$tagVersion = $matches[1];
			if($tagVersion !== $declaredBaseVersion){
				throw new InvalidArgumentException(sprintf(
					"Release tag '%s' does not match software base version '%s'",
					$tag,
					$declaredBaseVersion
				));
			}

			if($explicitIsDev === true || ($explicitChannel !== null && $explicitChannel !== "stable")){
				throw new InvalidArgumentException("Release tag '$tag' conflicts with requested development flag or channel");
			}

			return [
				"git" => $gitHash,
				"build" => $buildNumber,
				"isDev" => false,
				"channel" => "stable",
				"baseVersion" => $declaredBaseVersion,
			];
		}

		if(preg_match('/^v?(\d+\.\d+\.\d+)-(beta|alpha)\.?(\d+)$/i', $tag, $matches) === 1){
			$tagVersion = $matches[1];
			if($tagVersion !== $declaredBaseVersion){
				throw new InvalidArgumentException(sprintf(
					"Pre-release tag '%s' does not match software base version '%s'",
					$tag,
					$declaredBaseVersion
				));
			}

			$channelType = strtolower($matches[2]);
			$suffixNum = $matches[3] ?? "";
			$normalizedSuffix = $channelType . $suffixNum;

			if($explicitIsDev === true || ($explicitChannel !== null && $explicitChannel !== $channelType)){
				throw new InvalidArgumentException("Pre-release tag '$tag' conflicts with requested development flag or channel");
			}

			return [
				"git" => $gitHash,
				"build" => $buildNumber,
				"isDev" => false,
				"channel" => $channelType,
				"baseVersion" => $declaredBaseVersion . "-" . $normalizedSuffix,
			];
		}

		throw new InvalidArgumentException(sprintf("Cannot produce a build with unrecognized tag format '%s'", $tag));
	}
}
