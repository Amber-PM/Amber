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

namespace pocketmine;

use PHPUnit\Framework\TestCase;
use pocketmine\plugin\ApiVersion;
use pocketmine\utils\VersionString;

final class VersionInfoTest extends TestCase{

	protected function tearDown() : void{
		VersionInfo::reset();
	}

	public function testSoftwareVersionSeparatedFromApiVersion() : void{
		self::assertSame("1.6.0", VersionInfo::BASE_VERSION);
		self::assertSame("5.44.2", VersionInfo::COMPATIBLE_API_VERSION);
		self::assertSame(VersionInfo::COMPATIBLE_API_VERSION, VersionInfo::getCompatibleApiVersion());

		self::assertTrue(ApiVersion::isCompatible(VersionInfo::COMPATIBLE_API_VERSION, ["5.0.0"]));
		self::assertFalse(ApiVersion::isCompatible(VersionInfo::BASE_VERSION, ["5.0.0"]));
	}

	public function testLocalBuildIdentifiedAsDev() : void{
		VersionInfo::reset();

		self::assertTrue(VersionInfo::IS_DEVELOPMENT_BUILD());
		self::assertTrue(VersionInfo::isDevelopmentBuild());
		self::assertSame("stable", VersionInfo::BUILD_CHANNEL());
		self::assertSame("stable", VersionInfo::getChannel());
		self::assertSame("1.6.0", VersionInfo::BASE_VERSION());
		self::assertSame("1.6.0+dev", VersionInfo::VERSION()->getFullVersion(true));
	}

	public function testOfficialStableBuildMetadata() : void{
		VersionInfo::setPharMetadataForTesting([
			"git" => "1122334455667788990011223344556677889900",
			"build" => 55,
			"isDev" => false,
			"channel" => "stable",
			"baseVersion" => "1.6.0",
		]);

		self::assertFalse(VersionInfo::IS_DEVELOPMENT_BUILD());
		self::assertFalse(VersionInfo::isDevelopmentBuild());
		self::assertSame("stable", VersionInfo::BUILD_CHANNEL());
		self::assertSame("1.6.0", VersionInfo::BASE_VERSION());
		self::assertSame("1.6.0", VersionInfo::VERSION()->getFullVersion(true));
		self::assertSame("1122334455667788990011223344556677889900", VersionInfo::GIT_HASH());
		self::assertSame(55, VersionInfo::BUILD_NUMBER());
	}

	public function testBetaBuildMetadata() : void{
		VersionInfo::setPharMetadataForTesting([
			"git" => "aabbccddeeff00112233445566778899aabbccdd",
			"build" => 56,
			"isDev" => false,
			"channel" => "beta",
			"baseVersion" => "1.6.0-beta1",
		]);

		self::assertFalse(VersionInfo::IS_DEVELOPMENT_BUILD());
		self::assertSame("beta", VersionInfo::BUILD_CHANNEL());
		self::assertSame("1.6.0-beta1", VersionInfo::BASE_VERSION());
		self::assertSame("1.6.0-beta1", VersionInfo::VERSION()->getFullVersion(true));
		self::assertSame("beta1", VersionInfo::VERSION()->getSuffix());
	}

	public function testCiBranchBuildWithRunNumber() : void{
		VersionInfo::setPharMetadataForTesting([
			"git" => "00112233445566778899aabbccddeeff00112233",
			"build" => 77,
			"isDev" => true,
			"channel" => "stable",
			"baseVersion" => "1.6.0",
		]);

		self::assertTrue(VersionInfo::IS_DEVELOPMENT_BUILD());
		self::assertSame("1.6.0+dev.77", VersionInfo::VERSION()->getFullVersion(true));
		self::assertSame("1.6.0+dev", VersionInfo::VERSION()->getFullVersion(false));
	}

	public function testVersionStringParsing() : void{
		$version = new VersionString("1.6.0", true, 0);
		self::assertSame(1, $version->getMajor());
		self::assertSame(6, $version->getMinor());
		self::assertSame(0, $version->getPatch());
		self::assertSame("", $version->getSuffix());
		self::assertSame("1.6.0+dev", $version->getFullVersion());

		$betaVersion = new VersionString("1.6.0-beta1", false, 0);
		self::assertSame("beta1", $betaVersion->getSuffix());
		self::assertSame("1.6.0-beta1", $betaVersion->getFullVersion());
	}

	public function testDevBuildWarningTranslation() : void{
		$warning = \pocketmine\lang\KnownTranslationFactory::pocketmine_server_devBuild_warning1(VersionInfo::NAME);
		self::assertSame([VersionInfo::NAME], $warning->getParameters());

		$language = new \pocketmine\lang\Language(\pocketmine\lang\Language::FALLBACK_LANGUAGE, \pocketmine\LOCALE_DATA_PATH);
		$translated = $language->translate($warning);
		self::assertStringContainsString(VersionInfo::NAME, $translated);
	}

	public function testServerPropertiesEnableDevBuildsKeyRetained() : void{
		self::assertSame("settings.enable-dev-builds", \pocketmine\YmlServerProperties::SETTINGS_ENABLE_DEV_BUILDS);
	}
}
