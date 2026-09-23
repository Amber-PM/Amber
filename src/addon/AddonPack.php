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

use function implode;
use function is_array;
use function is_int;
use function is_string;

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
	 */
	public function __construct(
		private string $uuid,
		private string $name,
		private array $version,
		private string $type,
		private string $path,
		private string $source
	){}

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
		$type = null;
		$hasScript = false;
		foreach(is_array($manifest["modules"] ?? null) ? $manifest["modules"] : [] as $module){
			$moduleType = is_array($module) ? ($module["type"] ?? null) : null;
			if($moduleType === self::TYPE_RESOURCES || $moduleType === self::TYPE_DATA){
				$type = $moduleType;
				break;
			}
			if($moduleType === "script" || $moduleType === "javascript"){
				$hasScript = true;
			}
		}
		//newer behavior packs may declare only a script module: they are still behavior packs
		$type ??= $hasScript ? self::TYPE_DATA : null;
		if($type === null){
			throw new AddonException("$source: manifest.json has no resources or data module");
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
			$source
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
}
