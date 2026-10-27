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
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/build/BuildMetadataResolver.php';

final class BuildMetadataResolverTest extends TestCase{

	public function testLocalBuildDefaultsToDev() : void{
		$meta = BuildMetadataResolver::resolve("1.7.0", [], [], "abcdef012345");

		self::assertTrue($meta["isDev"]);
		self::assertSame("stable", $meta["channel"]);
		self::assertSame("1.7.0", $meta["baseVersion"]);
		self::assertSame("abcdef012345", $meta["git"]);
		self::assertSame(0, $meta["build"]);
	}

	public function testCiBranchBuildIsDevWithRunNumber() : void{
		$env = [
			"GITHUB_REF_TYPE" => "branch",
			"GITHUB_REF_NAME" => "major-next",
			"GITHUB_RUN_NUMBER" => "142",
			"GITHUB_SHA" => "11223344556677889900aabbccddeeff11223344",
		];
		$meta = BuildMetadataResolver::resolve("1.7.0", [], $env);

		self::assertTrue($meta["isDev"]);
		self::assertSame("stable", $meta["channel"]);
		self::assertSame("1.7.0", $meta["baseVersion"]);
		self::assertSame("11223344556677889900aabbccddeeff11223344", $meta["git"]);
		self::assertSame(142, $meta["build"]);
	}

	public function testOfficialReleaseTagProducesStable() : void{
		$env = [
			"GITHUB_REF_TYPE" => "tag",
			"GITHUB_REF_NAME" => "v1.7.0",
			"GITHUB_RUN_NUMBER" => "200",
			"GITHUB_SHA" => "deadbeefdeadbeefdeadbeefdeadbeefdeadbeef",
		];
		$meta = BuildMetadataResolver::resolve("1.7.0", [], $env);

		self::assertFalse($meta["isDev"]);
		self::assertSame("stable", $meta["channel"]);
		self::assertSame("1.7.0", $meta["baseVersion"]);
		self::assertSame(200, $meta["build"]);
	}

	public function testValidBetaTagProducesBetaArtifact() : void{
		$env = [
			"GITHUB_REF_TYPE" => "tag",
			"GITHUB_REF_NAME" => "v1.7.0-beta1",
			"GITHUB_RUN_NUMBER" => "199",
			"GITHUB_SHA" => "feedfacefeedfacefeedfacefeedfacefeedface",
		];
		$meta = BuildMetadataResolver::resolve("1.7.0", [], $env);

		self::assertFalse($meta["isDev"]);
		self::assertSame("beta", $meta["channel"]);
		self::assertSame("1.7.0-beta1", $meta["baseVersion"]);
		self::assertSame(199, $meta["build"]);
	}

	public function testCliTagOverridesEnvironment() : void{
		$opts = [
			"tag" => "v1.7.0",
			"build" => "300",
			"git" => "override-git-hash",
		];
		$meta = BuildMetadataResolver::resolve("1.7.0", $opts, []);

		self::assertFalse($meta["isDev"]);
		self::assertSame("stable", $meta["channel"]);
		self::assertSame("1.7.0", $meta["baseVersion"]);
		self::assertSame("override-git-hash", $meta["git"]);
		self::assertSame(300, $meta["build"]);
	}

	public function testMismatchedReleaseTagThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Release tag 'v1.5.0' does not match software base version '1.7.0'");

		BuildMetadataResolver::resolve("1.7.0", ["tag" => "v1.5.0"]);
	}

	public function testStableFlagWithoutTagThrows() : void{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Cannot create a stable release build without a valid matching release tag");

		BuildMetadataResolver::resolve("1.7.0", ["is-dev" => "false"]);
	}

	public function testInjectConstantsForStableRelease() : void{
		$source = <<<'PHP'
	public const BASE_VERSION = "1.7.0";
	public const IS_DEVELOPMENT_BUILD = true;
	public const BUILD_CHANNEL = "stable";
PHP;
		$injected = BuildMetadataResolver::injectConstants($source, [
			"isDev" => false,
			"channel" => "stable",
			"baseVersion" => "1.7.0",
		]);

		self::assertStringContainsString('public const IS_DEVELOPMENT_BUILD = false;', $injected);
		self::assertStringContainsString('public const BUILD_CHANNEL = "stable";', $injected);
		self::assertStringContainsString('public const BASE_VERSION = "1.7.0";', $injected);
	}

	public function testInjectConstantsForBetaRelease() : void{
		$source = <<<'PHP'
	public const BASE_VERSION = "1.7.0";
	public const IS_DEVELOPMENT_BUILD = true;
	public const BUILD_CHANNEL = "stable";
PHP;
		$injected = BuildMetadataResolver::injectConstants($source, [
			"isDev" => false,
			"channel" => "beta",
			"baseVersion" => "1.7.0-beta1",
		]);

		self::assertStringContainsString('public const IS_DEVELOPMENT_BUILD = false;', $injected);
		self::assertStringContainsString('public const BUILD_CHANNEL = "beta";', $injected);
		self::assertStringContainsString('public const BASE_VERSION = "1.7.0-beta1";', $injected);
	}

	public function testGenerateBuildInfoJsonReleaseAndBetaMetadata() : void{
		$parseJson = function(string $raw) : array{
			$lines = array_filter(explode("\n", str_replace("\r", "", $raw)), fn($l) => !str_starts_with(trim($l), "PHP Warning"));
			$clean = implode("\n", $lines);
			$data = json_decode($clean, true);
			self::assertIsArray($data);
			return $data;
		};

		[$stableExit, $outputStable] = $this->runPhp([
			"build/generate-build-info-json.php", "abc12345", "v1.7.0",
			"Amber-PM/Amber", "10", "100", "https://example.com",
		]);
		self::assertSame(0, $stableExit, $outputStable);
		$jsonStable = $parseJson($outputStable);
		self::assertFalse($jsonStable["is_dev"]);
		self::assertSame("stable", $jsonStable["channel"]);
		self::assertSame("1.7.0", $jsonStable["base_version"]);

		[$betaExit, $outputBeta] = $this->runPhp([
			"build/generate-build-info-json.php", "abc12345", "v1.7.0-beta1",
			"Amber-PM/Amber", "11", "101", "https://example.com",
		]);
		self::assertSame(0, $betaExit, $outputBeta);
		$jsonBeta = $parseJson($outputBeta);
		self::assertFalse($jsonBeta["is_dev"]);
		self::assertSame("beta", $jsonBeta["channel"]);
		self::assertSame("1.7.0-beta1", $jsonBeta["base_version"]);
	}

	public function testDumpVersionInfoReleaseAndBetaMetadata() : void{
		$cleanOutput = function(string $raw) : string{
			$lines = array_filter(explode("\n", str_replace("\r", "", $raw)), fn($l) => !str_starts_with(trim($l), "PHP Warning") && trim($l) !== "");
			return trim(end($lines) ?: "");
		};

		[$exit, $raw] = $this->runPhp(["build/dump-version-info.php", "is_dev"]);
		self::assertSame(0, $exit, $raw);
		$devOutput = $cleanOutput($raw);
		self::assertSame("true", $devOutput);

		[$exit, $raw] = $this->runPhp(["build/dump-version-info.php", "is_dev", "--tag=v1.7.0"]);
		self::assertSame(0, $exit, $raw);
		$stableDevOutput = $cleanOutput($raw);
		self::assertSame("false", $stableDevOutput);

		[$exit, $raw] = $this->runPhp(["build/dump-version-info.php", "channel", "--tag=v1.7.0-beta1"]);
		self::assertSame(0, $exit, $raw);
		$betaChannelOutput = $cleanOutput($raw);
		self::assertSame("beta", $betaChannelOutput);

		[$exit, $raw] = $this->runPhp(["build/dump-version-info.php", "base_version", "--tag=v1.7.0-beta1"]);
		self::assertSame(0, $exit, $raw);
		$betaBaseVersionOutput = $cleanOutput($raw);
		self::assertSame("1.7.0-beta1", $betaBaseVersionOutput);
	}

	public function testReleaseTagOverridesCannotConflict() : void{
		foreach([
			["tag" => "v1.7.0", "is-dev" => true],
			["tag" => "v1.7.0", "channel" => "beta"],
			["tag" => "v1.7.0-beta1", "is-dev" => true],
			["tag" => "v1.7.0-beta1", "channel" => "stable"],
		] as $opts){
			try{
				BuildMetadataResolver::resolve("1.7.0", $opts);
				self::fail("Conflicting tag flags must fail");
			}catch(InvalidArgumentException $e){
				self::assertStringContainsString("conflicts", $e->getMessage());
			}
		}
	}

	public function testUnknownTagAndInvalidDevFlagAreRejected() : void{
		foreach([
			["tag" => "v1.7.0-rc1"],
			["tag" => "random-tag"],
			["is-dev" => "maybe"],
		] as $opts){
			try{
				BuildMetadataResolver::resolve("1.7.0", $opts);
				self::fail("Invalid version metadata must fail");
			}catch(InvalidArgumentException){
				self::assertTrue(true);
			}
		}
	}

	public function testInjectionKeepsReplacementTextLiteral() : void{
		$source = <<<'PHP'
	public const BASE_VERSION = "1.7.0";
	public const IS_DEVELOPMENT_BUILD = true;
	public const BUILD_CHANNEL = "stable";
PHP;
		$injected = BuildMetadataResolver::injectConstants($source, [
			"isDev" => true,
			"channel" => 'dev$1',
			"baseVersion" => "1.7.0",
		]);
		self::assertStringContainsString('public const BUILD_CHANNEL = "dev$1";', $injected);
	}

	public function testInjectionRejectsMissingOrDuplicateConstants() : void{
		$metadata = ["isDev" => false, "channel" => "stable", "baseVersion" => "1.7.0"];
		$source = <<<'PHP'
	public const BASE_VERSION = "1.7.0";
	public const IS_DEVELOPMENT_BUILD = true;
	public const BUILD_CHANNEL = "stable";
PHP;
		foreach([
			str_replace('public const IS_DEVELOPMENT_BUILD = true;', '', $source),
			$source . "\n" . 'public const BASE_VERSION = "1.7.0";',
		] as $invalidSource){
			try{
				BuildMetadataResolver::injectConstants($invalidSource, $metadata);
				self::fail("Missing or duplicate metadata constants must fail");
			}catch(InvalidArgumentException $e){
				self::assertStringContainsString("Expected exactly one", $e->getMessage());
			}
		}
	}

	/**
	 * @param list<string> $args
	 * @return array{int, string}
	 */
	private function runPhp(array $args) : array{
		$command = [PHP_BINARY, "-c", (string) php_ini_loaded_file(), ...$args];
		$process = proc_open($command, [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, dirname(__DIR__, 2));
		self::assertIsResource($process);
		$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return [proc_close($process), $output];
	}

	public function testInvalidReleaseTagsFailClosed() : void{
		[$exit, $output] = $this->runPhp(["build/dump-version-info.php", "is_dev", "--tag=v1.5.0"]);
		self::assertNotSame(0, $exit);
		self::assertStringContainsString("does not match", $output);

		[$exit, $output] = $this->runPhp([
			"build/generate-build-info-json.php", "abc123", "v1.5.0", "Amber-PM/Amber", "10", "100", "https://example.com",
		]);
		self::assertNotSame(0, $exit);
		self::assertStringContainsString("does not match", $output);

		[$exit, $output] = $this->runPhp([
			"build/generate-build-info-json.php", "abc123", "not-a-release", "Amber-PM/Amber", "10", "100", "https://example.com",
		]);
		self::assertNotSame(0, $exit);
		self::assertStringContainsString("unrecognized tag", $output);
	}

	public function testExplicitPharMustMatchRequestedRelease() : void{
		$temporary = tempnam(sys_get_temp_dir(), "amber-version-");
		self::assertNotFalse($temporary);
		unlink($temporary);
		$pharPath = $temporary . ".phar";

		try{
			[$exit, $output] = $this->runPhp([
				"-dphar.readonly=0", "-r",
				'$phar = new Phar($argv[1]); $phar->startBuffering(); $phar->addFromString("index.php", "<?php echo 1;"); $phar->addFile("src/VersionInfo.php", "src/VersionInfo.php"); $phar->setStub("<?php __HALT_COMPILER();"); $phar->setMetadata(["git" => "abc123", "build" => 10, "isDev" => true, "channel" => "stable", "baseVersion" => "1.7.0"]); $phar->stopBuffering();',
				$pharPath,
			]);
			self::assertSame(0, $exit, $output);

			[$exit, $output] = $this->runPhp(["build/dump-version-info.php", "is_dev", "--phar=$pharPath"]);
			self::assertSame(0, $exit, $output);
			self::assertSame("true", trim($output));

			[$exit, $output] = $this->runPhp(["build/dump-version-info.php", "is_dev", "--tag=v1.7.0", "--phar=$pharPath"]);
			self::assertNotSame(0, $exit);
			self::assertStringContainsString("PHAR metadata mismatch", $output);

			[$exit, $output] = $this->runPhp([
				"build/generate-build-info-json.php", "abc123", "v1.7.0", "Amber-PM/Amber", "10", "100", "https://example.com", "--phar=$pharPath",
			]);
			self::assertNotSame(0, $exit);
			self::assertStringContainsString("PHAR metadata mismatch", $output);
		}finally{
			if(file_exists($pharPath)){
				unlink($pharPath);
			}
		}
	}
	public function testStrictTagsAndChannels() : void{
		foreach([
			["tag" => "v1.7.0-beta"], ["tag" => "v1.7.0-alpha"],
			["channel" => "bogus"], ["channel" => "beta"], ["channel" => "alpha"],
			["tag" => ""], ["tag" => true], ["tag" => ["v1.7.0", "v1.7.0-beta1"]],
			["channel" => ""], ["channel" => true],
			["build" => "abc"], ["build" => "1foo"], ["build" => "-1"],
			["build" => "1.5"], ["build" => "999999999999999999999999"],
			["git" => ""], ["git" => true],
		] as $opts){
			try{
				BuildMetadataResolver::resolve("1.7.0", $opts);
				self::fail("Invalid metadata accepted: " . json_encode($opts));
			}catch(InvalidArgumentException $e){
				self::assertNotSame("", $e->getMessage());
			}
		}
	}

	public function testAlphaReleaseAndValidSuffixes() : void{
		foreach(["stable" => "", "beta" => "-beta1", "alpha" => "-alpha1"] as $channel => $suffix){
			$tag = "v1.7.0" . $suffix;
			$meta = BuildMetadataResolver::resolve("1.7.0", ["tag" => $tag]);
			self::assertFalse($meta["isDev"]);
			self::assertSame($channel, $meta["channel"]);
			self::assertSame("1.7.0" . $suffix, $meta["baseVersion"]);
			[$exit, $output] = $this->runPhp(["build/dump-version-info.php", "suffix_valid", "--tag=$tag"]);
			self::assertSame(0, $exit, $output);
			self::assertSame("true", trim($output));
		}
	}

	public function testMalformedCliMetadataFails() : void{
		foreach(["--tag=v1.7.0-beta", "--tag=v1.7.0-alpha", "--channel=bogus", "--tag=", "--tag", "--build=abc", "--build=", "--is-dev", "--chanel=stable"] as $arg){
			[$exit, $output] = $this->runPhp(["build/dump-version-info.php", "is_dev", $arg]);
			self::assertNotSame(0, $exit, $arg . ": " . $output);
			self::assertStringContainsString("metadata error", $output);
		}
	}

	public function testBuiltArtifactVerifierRejectsTampering() : void{
		$temporary = tempnam(sys_get_temp_dir(), "amber-verify-");
		self::assertNotFalse($temporary);
		unlink($temporary);
		$path = $temporary . ".phar";
		$source = file_get_contents(dirname(__DIR__, 2) . "/src/VersionInfo.php");
		self::assertNotFalse($source);
		$metadata = BuildMetadataResolver::resolve("1.7.0", ["git" => "abc123", "build" => "10"]);
		$cases = [
			"valid" => [$metadata, $source, 0],
			"wrong git" => [array_replace($metadata, ["git" => "stale"]), $source, 1],
			"wrong build" => [array_replace($metadata, ["build" => 11]), $source, 1],
			"wrong channel" => [array_replace($metadata, ["channel" => "bogus"]), $source, 1],
			"wrong version" => [array_replace($metadata, ["baseVersion" => "1.5.0"]), $source, 1],
			"wrong dev flag" => [array_replace($metadata, ["isDev" => false]), $source, 1],
			"wrong name" => [$metadata, str_replace('NAME = "AmberPM"', 'NAME = "Other"', $source), 1],
			"wrong API" => [$metadata, str_replace('COMPATIBLE_API_VERSION = "5.44.2"', 'COMPATIBLE_API_VERSION = "1.7.0"', $source), 1],
			"contradictory constant" => [$metadata, str_replace('IS_DEVELOPMENT_BUILD = true', 'IS_DEVELOPMENT_BUILD = false', $source), 1],
			"missing constant" => [$metadata, str_replace('public const BUILD_CHANNEL = "stable";', '', $source), 1],
			"duplicate constant" => [$metadata, $source . '\npublic const BUILD_CHANNEL = "stable";', 1],
		];
		try{
			foreach($cases as $label => [$meta, $code, $expectedExit]){
				[$exit, $output] = $this->runPhp(["-dphar.readonly=0", "-r",
					'$p = new Phar($argv[1]); $p->startBuffering(); $p->setStub("<?php __HALT_COMPILER();"); $p->addFromString("src/VersionInfo.php", base64_decode($argv[2])); $p->setMetadata(json_decode(base64_decode($argv[3]), true)); $p->stopBuffering();',
					$path, base64_encode($code), base64_encode(json_encode($meta, JSON_THROW_ON_ERROR)),
				]);
				self::assertSame(0, $exit, $label . ": " . $output);
				[$exit, $output] = $this->runPhp(["build/verify-phar.php", "--phar=$path", "--git=abc123", "--build=10"]);
				self::assertSame($expectedExit, $exit, $label . ": " . $output);
			}
		}finally{
			if(file_exists($path)){
				unlink($path);
			}
		}
	}

	public function testReleaseJsonUsesResolvedIdentity() : void{
		[$exit, $output] = $this->runPhp([
			"build/generate-build-info-json.php", " abc123 ", " v1.7.0 ", "Amber-PM/Amber", "10", "100", "https://example.com",
		]);
		self::assertSame(0, $exit, $output);
		$data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
		self::assertSame("abc123", $data["git_commit"]);
		self::assertSame("https://github.com/Amber-PM/Amber/releases/tag/v1.7.0", $data["details_url"]);
	}

}
