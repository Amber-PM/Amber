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

use pocketmine\utils\Git;
use pocketmine\utils\VersionString;
use function is_array;
use function is_int;
use function str_repeat;

final class VersionInfo{
	public const NAME = "AmberPM";
	public const BASE_VERSION = "1.6.0";
	public const COMPATIBLE_API_VERSION = "5.44.2";
	public const IS_DEVELOPMENT_BUILD = true;
	public const BUILD_CHANNEL = "stable";
	public const GITHUB_URL = "https://github.com/Amber-PM/Amber";

	/**
	 * PocketMine-MP-specific version ID for world data. Used to determine what fixes need to be applied to old world
	 * data (e.g. stuff saved wrongly by past versions).
	 * This version supplements the Minecraft vanilla world version.
	 *
	 * This should be bumped if any **non-Mojang** BC-breaking change or bug fix is made to world save data of any kind
	 * (entities, tiles, blocks, biomes etc.). For example, if PM accidentally saved a block with its facing value
	 * swapped, we would bump this, but not if Mojang did the same change.
	 */
	public const WORLD_DATA_VERSION = 1;
	/**
	 * Name of the NBT tag used to store the world data version.
	 */
	public const TAG_WORLD_DATA_VERSION = "PMMPDataVersion"; //TAG_Long

	private function __construct(){
		//NOOP
	}

	/** @var array<string, mixed>|null */
	private static ?array $pharMetadata = null;

	/**
	 * @return array<string, mixed>|null
	 */
	private static function getPharMetadata() : ?array{
		if(self::$pharMetadata === null && \Phar::running(true) !== ""){
			$pharPath = \Phar::running(false);
			$phar = \Phar::isValidPharFilename($pharPath) ? new \Phar($pharPath) : new \PharData($pharPath);
			$meta = $phar->getMetadata();
			if(is_array($meta)){
				self::$pharMetadata = $meta;
			}
		}

		return self::$pharMetadata;
	}

	/**
	 * Overrides artifact metadata for build tools and tests.
	 * @internal
	 * @param array<string, mixed>|null $metadata
	 */
	public static function setPharMetadataOverride(?array $metadata) : void{
		self::reset();
		self::$pharMetadata = $metadata;
	}

	/**
	 * @internal
	 */
	public static function reset() : void{
		self::$pharMetadata = null;
		self::$gitHash = null;
		self::$buildNumber = null;
		self::$isDev = null;
		self::$channel = null;
		self::$effectiveBaseVersion = null;
		self::$fullVersion = null;
	}

	private static ?string $gitHash = null;

	public static function GIT_HASH() : string{
		if(self::$gitHash === null){
			$gitHash = str_repeat("00", 20);

			$meta = self::getPharMetadata();
			if(is_array($meta) && isset($meta["git"]) && is_string($meta["git"])){
				$gitHash = $meta["git"];
			}elseif(\Phar::running(true) === ""){
				$gitHash = Git::getRepositoryStatePretty(\pocketmine\PATH);
			}

			self::$gitHash = $gitHash;
		}

		return self::$gitHash;
	}

	private static ?int $buildNumber = null;

	public static function BUILD_NUMBER() : int{
		if(self::$buildNumber === null){
			self::$buildNumber = 0;
			$meta = self::getPharMetadata();
			if(is_array($meta) && isset($meta["build"]) && is_int($meta["build"])){
				self::$buildNumber = $meta["build"];
			}
		}

		return self::$buildNumber;
	}

	private static ?bool $isDev = null;

	public static function IS_DEVELOPMENT_BUILD() : bool{
		if(self::$isDev === null){
			self::$isDev = true;
			$meta = self::getPharMetadata();
			if(is_array($meta) && isset($meta["isDev"]) && is_bool($meta["isDev"])){
				self::$isDev = $meta["isDev"];
			}
		}

		return self::$isDev;
	}

	public static function isDevelopmentBuild() : bool{
		return self::IS_DEVELOPMENT_BUILD();
	}

	private static ?string $channel = null;

	public static function BUILD_CHANNEL() : string{
		if(self::$channel === null){
			self::$channel = self::BUILD_CHANNEL;
			$meta = self::getPharMetadata();
			if(is_array($meta) && isset($meta["channel"]) && is_string($meta["channel"])){
				self::$channel = $meta["channel"];
			}
		}

		return self::$channel;
	}

	public static function getChannel() : string{
		return self::BUILD_CHANNEL();
	}

	private static ?string $effectiveBaseVersion = null;

	public static function BASE_VERSION() : string{
		if(self::$effectiveBaseVersion === null){
			self::$effectiveBaseVersion = self::BASE_VERSION;
			$meta = self::getPharMetadata();
			if(is_array($meta) && isset($meta["baseVersion"]) && is_string($meta["baseVersion"])){
				self::$effectiveBaseVersion = $meta["baseVersion"];
			}
		}

		return self::$effectiveBaseVersion;
	}

	public static function getCompatibleApiVersion() : string{
		return self::COMPATIBLE_API_VERSION;
	}

	private static ?VersionString $fullVersion = null;

	public static function VERSION() : VersionString{
		if(self::$fullVersion === null){
			self::$fullVersion = new VersionString(self::BASE_VERSION(), self::IS_DEVELOPMENT_BUILD(), self::BUILD_NUMBER());
		}
		return self::$fullVersion;
	}
}
