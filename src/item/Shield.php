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

namespace pocketmine\item;

use pocketmine\block\utils\BannerPatternLayer;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\BannerPatternTypeIdMap;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use function array_values;
use function count;

class Shield extends Durable{
	public const TAG_BLOCK_ENTITY_TAG = "BlockEntityTag";
	public const TAG_BASE = "Base";
	public const TAG_PATTERNS = "Patterns";
	public const TAG_PATTERN_COLOR = "Color";
	public const TAG_PATTERN_NAME = "Pattern";

	private ?DyeColor $baseColor = null;

	/**
	 * @var BannerPatternLayer[]
	 * @phpstan-var list<BannerPatternLayer>
	 */
	private array $patterns = [];

	public function getMaxDurability() : int{
		return 336;
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getFuelTime() : int{
		return 300;
	}

	public function getCooldownTicks() : int{
		return 100;
	}

	public function getCooldownTag() : ?string{
		return ItemCooldownTags::SHIELD;
	}

	public function getBaseColor() : ?DyeColor{
		return $this->baseColor;
	}

	public function setBaseColor(?DyeColor $color) : self{
		$this->baseColor = $color;
		return $this;
	}

	/**
	 * @return BannerPatternLayer[]
	 * @phpstan-return list<BannerPatternLayer>
	 */
	public function getPatterns() : array{
		return $this->patterns;
	}

	/**
	 * @param BannerPatternLayer[] $patterns
	 * @phpstan-param list<BannerPatternLayer> $patterns
	 */
	public function setPatterns(array $patterns) : self{
		$this->patterns = array_values($patterns);
		return $this;
	}

	protected function deserializeCompoundTag(CompoundTag $tag) : void{
		parent::deserializeCompoundTag($tag);
		$this->patterns = [];
		$this->baseColor = null;

		$bet = $tag->getCompoundTag(self::TAG_BLOCK_ENTITY_TAG);
		if($bet !== null){
			if($bet->getTag(self::TAG_BASE) !== null){
				$this->baseColor = DyeColorIdMap::getInstance()->fromInvertedId($bet->getInt(self::TAG_BASE));
			}
			$patterns = $bet->getListTag(self::TAG_PATTERNS, CompoundTag::class);
			if($patterns !== null){
				$colorIdMap = DyeColorIdMap::getInstance();
				$patternIdMap = BannerPatternTypeIdMap::getInstance();
				foreach($patterns as $t){
					$color = $colorIdMap->fromInvertedId($t->getInt(self::TAG_PATTERN_COLOR)) ?? DyeColor::BLACK;
					$type = $patternIdMap->fromId($t->getString(self::TAG_PATTERN_NAME));
					if($type !== null){
						$this->patterns[] = new BannerPatternLayer($type, $color);
					}
				}
			}
		}
	}

	protected function serializeCompoundTag(CompoundTag $tag) : void{
		parent::serializeCompoundTag($tag);

		if($this->baseColor !== null || count($this->patterns) > 0){
			$bet = CompoundTag::create();
			if($this->baseColor !== null){
				$bet->setInt(self::TAG_BASE, DyeColorIdMap::getInstance()->toInvertedId($this->baseColor));
			}
			if(count($this->patterns) > 0){
				$patterns = new ListTag();
				$colorIdMap = DyeColorIdMap::getInstance();
				$patternIdMap = BannerPatternTypeIdMap::getInstance();
				foreach($this->patterns as $pattern){
					$patterns->push(CompoundTag::create()
						->setString(self::TAG_PATTERN_NAME, $patternIdMap->toId($pattern->getType()))
						->setInt(self::TAG_PATTERN_COLOR, $colorIdMap->toInvertedId($pattern->getColor()))
					);
				}
				$bet->setTag(self::TAG_PATTERNS, $patterns);
			}
			$tag->setTag(self::TAG_BLOCK_ENTITY_TAG, $bet);
		}else{
			$tag->removeTag(self::TAG_BLOCK_ENTITY_TAG);
		}
	}
}
