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

namespace pocketmine\addon;

use Ramsey\Uuid\Uuid;

use function explode;
use function file;
use function file_get_contents;
use function implode;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function ltrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;
use const FILE_IGNORE_NEW_LINES;

/**
 * One pack inside an add-on: a resource pack ("resources") or a behavior pack ("data"), read from its
 * manifest.json and unpacked into a directory.
 */
final class AddonPack{
	public const TYPE_RESOURCES = "resources";
	public const TYPE_DATA = "data";

	/**
	 * @param int[] $version
	 * @phpstan-param list<int> $version
	 * @throws AddonException
	 */
	public function __construct(
		private string $uuid,
		private string $name,
		private array $version,
		private string $type,
		private string $path,
		private string $source,
		private ?string $scriptEntry = null,
		private int $scriptApiMajor = 2
	){
		if(!Uuid::isValid($this->uuid)){
			throw new AddonException("$source: pack UUID '$this->uuid' is not a valid UUID");
		}
	}

	/**
	 * @throws AddonException
	 */
	public static function fromDirectory(string $path, string $source) : self{
		$manifestPath = $path . "/manifest.json";
		if(!is_file($manifestPath)){
			throw new AddonException("$source: $path has no manifest.json");
		}
		$manifest = AddonJson::decode((string) file_get_contents($manifestPath), "$source/manifest.json");
		$header = $manifest["header"] ?? null;
		if(!is_array($header) || !is_string($header["uuid"] ?? null)){
			throw new AddonException("$source: manifest.json has no header uuid");
		}
		if(!Uuid::isValid($header["uuid"])){
			throw new AddonException("$source: manifest.json header uuid is not a valid UUID");
		}
		$type = null;
		$hasScript = false;
		$scriptEntry = null;
		foreach(is_array($manifest["modules"] ?? null) ? $manifest["modules"] : [] as $module){
			$moduleType = is_array($module) ? ($module["type"] ?? null) : null;
			if(($moduleType === self::TYPE_RESOURCES || $moduleType === self::TYPE_DATA) && $type === null){
				$type = $moduleType;
			}
			if($moduleType === "script" || $moduleType === "javascript"){
				$hasScript = true;
				$entry = $module["entry"] ?? null;
				if(is_string($entry) && $entry !== "" && !str_contains($entry, "..") && is_file($path . "/" . $entry)){
					$scriptEntry = $entry;
				}
			}
		}
		//newer behavior packs may declare only a script module: they are still behavior packs
		$type ??= $hasScript ? self::TYPE_DATA : null;
		if($type === null){
			throw new AddonException("$source: manifest.json has no resources or data module");
		}
		$apiMajor = 2;
		foreach(is_array($manifest["dependencies"] ?? null) ? $manifest["dependencies"] : [] as $dependency){
			if(is_array($dependency) && ($dependency["module_name"] ?? null) === "@minecraft/server"){
				$declared = $dependency["version"] ?? "2.0.0";
				$apiMajor = is_array($declared) ? (int) ($declared[0] ?? 2) : (int) explode(".", (string) $declared, 2)[0];
			}
		}
		$version = [];
		foreach(is_array($header["version"] ?? null) ? $header["version"] : [] as $part){
			$version[] = is_int($part) ? $part : (int) $part;
		}
		$name = is_string($header["name"] ?? null) ? $header["name"] : $header["uuid"];
		return new self(
			$header["uuid"],
			self::localise($path, $name),
			$version,
			$type,
			$path,
			$source,
			$type === self::TYPE_DATA ? $scriptEntry : null,
			$apiMajor
		);
	}

	/** Resolves a translation key such as "pack.name" from the pack's texts/en_US.lang. */
	private static function localise(string $path, string $name) : string{
		$lang = $path . "/texts/en_US.lang";
		if(!str_starts_with($name, "pack.") || !is_file($lang)){
			return $name;
		}
		$lines = file($lang, FILE_IGNORE_NEW_LINES);
		foreach($lines === false ? [] : $lines as $line){
			$line = trim(ltrim($line, "\xEF\xBB\xBF"));
			if(str_starts_with($line, $name . "=")){
				$value = trim(explode("#", substr($line, strlen($name) + 1), 2)[0]);
				return $value !== "" ? $value : $name;
			}
		}
		return $name;
	}

	public function getUuid() : string{ return $this->uuid; }

	public function getName() : string{ return $this->name; }

	/** @return int[] */
	public function getVersion() : array{ return $this->version; }

	public function getVersionString() : string{ return implode(".", $this->version); }

	public function getType() : string{ return $this->type; }

	public function isResourcePack() : bool{ return $this->type === self::TYPE_RESOURCES; }

	public function isBehaviorPack() : bool{ return $this->type === self::TYPE_DATA; }

	/** Directory the pack was unpacked to (contains manifest.json). */
	public function getPath() : string{ return $this->path; }

	/** The file or folder in the addons directory this pack came from. */
	public function getSource() : string{ return $this->source; }

	/** The script module's entry file, relative to the pack (e.g. "scripts/main.js"), or null. */
	public function getScriptEntry() : ?string{ return $this->scriptEntry; }

	/** Major version of @minecraft/server the pack's scripts were written for (1 or 2); defaults to 2. */
	public function getScriptApiMajor() : int{ return $this->scriptApiMajor; }
}
