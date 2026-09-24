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

use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use function array_is_list;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;
use const JSON_BIGINT_AS_STRING;

/**
 * JSON helpers for Bedrock add-on files.
 *
 * Add-on JSON is "Minecraft JSON": comments (both kinds) and trailing commas are allowed. It is converted to
 * NBT with the same rules the client uses for data-driven components: objects become compounds, arrays become
 * lists, booleans become bytes, whole numbers ints and everything else floats.
 */
final class AddonJson{

	private function __construct(){
		//NOOP
	}

	/**
	 * @return mixed[]
	 * @throws AddonException
	 */
	public static function decode(string $contents, string $sourceName) : array{
		$contents = self::cleanJson($contents, $sourceName);

		$decoded = json_decode($contents, true, 512, JSON_BIGINT_AS_STRING);
		if(!is_array($decoded)){
			throw new AddonException("$sourceName is not valid JSON");
		}
		return $decoded;
	}

	/** @throws AddonException */
	private static function cleanJson(string $contents, string $sourceName) : string{
		if(str_starts_with($contents, "\xEF\xBB\xBF")){
			$contents = substr($contents, 3);
		}

		$len = strlen($contents);
		$strippedComments = "";
		$inString = false;
		$escaped = false;
		for($i = 0; $i < $len; ++$i){
			$c = $contents[$i];
			if($inString){
				$strippedComments .= $c;
				if($escaped){
					$escaped = false;
				}elseif($c === "\\"){
					$escaped = true;
				}elseif($c === '"'){
					$inString = false;
				}
				continue;
			}
			if($c === '"'){
				$inString = true;
				$strippedComments .= $c;
			}elseif($c === '/' && $i + 1 < $len && $contents[$i + 1] === '/'){
				$i += 2;
				while($i < $len && $contents[$i] !== "\r" && $contents[$i] !== "\n"){
					++$i;
				}
				if($i < $len){
					$strippedComments .= $contents[$i];
				}
			}elseif($c === '/' && $i + 1 < $len && $contents[$i + 1] === '*'){
				$closed = false;
				$i += 2;
				while($i + 1 < $len){
					if($contents[$i] === '*' && $contents[$i + 1] === '/'){
						$closed = true;
						++$i;
						break;
					}
					++$i;
				}
				if(!$closed){
					throw new AddonException("$sourceName has an unterminated block comment");
				}
			}else{
				$strippedComments .= $c;
			}
		}

		$len = strlen($strippedComments);
		$result = "";
		$inString = false;
		$escaped = false;
		for($i = 0; $i < $len; ++$i){
			$c = $strippedComments[$i];
			if($inString){
				$result .= $c;
				if($escaped){
					$escaped = false;
				}elseif($c === "\\"){
					$escaped = true;
				}elseif($c === '"'){
					$inString = false;
				}
				continue;
			}
			if($c === '"'){
				$inString = true;
				$result .= $c;
			}elseif($c === ','){
				$next = $i + 1;
				while($next < $len && ($strippedComments[$next] === ' ' || $strippedComments[$next] === "\t" || $strippedComments[$next] === "\r" || $strippedComments[$next] === "\n")){
					++$next;
				}
				if($next < $len && ($strippedComments[$next] === '}' || $strippedComments[$next] === ']')){
					continue;
				}
				$result .= $c;
			}else{
				$result .= $c;
			}
		}

		return $result;
	}

	/**
	 * Converts a decoded JSON value into an NBT tag, using the client's data-driven component conventions.
	 */
	public static function toTag(mixed $value) : Tag{
		if(is_bool($value)){
			return new ByteTag($value ? 1 : 0);
		}
		if(is_int($value)){
			return new IntTag($value);
		}
		if(is_float($value)){
			return new FloatTag($value);
		}
		if(is_string($value)){
			return new StringTag($value);
		}
		if(is_array($value)){
			if($value !== [] && array_is_list($value)){
				$tags = [];
				foreach($value as $element){
					$tags[] = self::toTag($element);
				}
				//NBT lists must be homogeneous: numeric lists mixing ints and floats become float lists
				$types = [];
				foreach($tags as $tag){
					$types[$tag->getType()] = true;
				}
				if(isset($types[(new IntTag(0))->getType()], $types[(new FloatTag(0))->getType()]) && count($types) === 2){
					$tags = [];
					foreach($value as $element){
						$tags[] = new FloatTag((float) $element);
					}
				}
				return new ListTag($tags);
			}
			$compound = CompoundTag::create();
			foreach($value as $key => $element){
				$compound->setTag((string) $key, self::toTag($element));
			}
			return $compound;
		}
		return new StringTag("");
	}

	public static function toCompound(mixed $value) : CompoundTag{
		$tag = self::toTag($value);
		return $tag instanceof CompoundTag ? $tag : CompoundTag::create();
	}

	/**
	 * Reads a component that can be written either as a bare value or as {"value": x}.
	 */
	public static function scalar(mixed $component, string $key = "value") : mixed{
		if(is_array($component) && !array_is_list($component)){
			return $component[$key] ?? null;
		}
		return $component;
	}

	public static function isValidIdentifier(string $identifier) : bool{
		return strlen($identifier) <= 128 && preg_match('/^[a-z0-9_\-.]+:[a-z0-9_\-.\/]+$/', $identifier) === 1;
	}
}
