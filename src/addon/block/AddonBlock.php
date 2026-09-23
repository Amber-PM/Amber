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

namespace pocketmine\addon\block;

use pocketmine\addon\AddonManager;
use pocketmine\block\Block;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function array_search;
use function count;
use function max;

/**
 * A custom block. One instance is registered per add-on block; its state is an index into the block's
 * permutation list (see AddonBlockDefinition::getPermutationValues()), so any number of add-on states fit
 * without a class per block.
 */
class AddonBlock extends Block{
	/** @var list<array<string, int|string|bool>> */
	private array $permutations;
	private int $stateIndex = 0;

	public function __construct(BlockIdentifier $idInfo, BlockTypeInfo $typeInfo, private AddonBlockDefinition $addonDefinition){
		$this->permutations = $addonDefinition->getPermutationValues();
		parent::__construct($idInfo, $addonDefinition->getDisplayName(), $typeInfo);
	}

	public function getAddonDefinition() : AddonBlockDefinition{ return $this->addonDefinition; }

	/** The add-on identifier, e.g. "example:ruby_block". */
	public function getAddonIdentifier() : string{ return $this->addonDefinition->getIdentifier(); }

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		if(count($this->permutations) > 1){
			$w->boundedIntAuto(0, count($this->permutations) - 1, $this->stateIndex);
		}
	}

	/** @return array<string, int|string|bool> the current value of every state */
	public function getStateValues() : array{
		return $this->permutations[$this->stateIndex];
	}

	public function getStateValue(string $name) : int|string|bool|null{
		return $this->permutations[$this->stateIndex][$name] ?? null;
	}

	/**
	 * Returns a copy of this block with one state changed. Unknown states or values leave it unchanged.
	 */
	public function withStateValue(string $name, int|string|bool $value) : static{
		$wanted = $this->permutations[$this->stateIndex];
		if(!isset($wanted[$name])){
			return clone $this;
		}
		$wanted[$name] = $value;
		$index = array_search($wanted, $this->permutations, true);
		$block = clone $this;
		if($index !== false){
			$block->stateIndex = $index;
		}
		return $block;
	}

	/** @internal used by the block state deserializer */
	public function setStateIndex(int $index) : static{
		$this->stateIndex = max(0, $index);
		return $this;
	}

	public function getStateIndex() : int{ return $this->stateIndex; }

	public function getLightLevel() : int{
		return $this->addonDefinition->getLightEmission();
	}

	public function getLightFilter() : int{
		return $this->addonDefinition->getLightDampening();
	}

	public function isTransparent() : bool{
		return !$this->addonDefinition->isOpaqueCube();
	}

	public function isSolid() : bool{
		return $this->addonDefinition->getCollisionBox()["enabled"];
	}

	public function isFullCube() : bool{
		return $this->addonDefinition->isOpaqueCube();
	}

	public function getSupportType(int $facing) : SupportType{
		return $this->addonDefinition->isOpaqueCube() ? SupportType::FULL : SupportType::NONE;
	}

	public function getFrictionFactor() : float{
		return $this->addonDefinition->getFriction();
	}

	protected function recalculateCollisionBoxes() : array{
		$box = $this->addonDefinition->getCollisionBox();
		if(!$box["enabled"]){
			return [];
		}
		//add-on boxes are in pixels, origin relative to the bottom centre of the block
		[$ox, $oy, $oz] = $box["origin"];
		[$sx, $sy, $sz] = $box["size"];
		$minX = ($ox + 8) / 16;
		$minY = $oy / 16;
		$minZ = ($oz + 8) / 16;
		return [new AxisAlignedBB($minX, $minY, $minZ, $minX + $sx / 16, $minY + $sy / 16, $minZ + $sz / 16)];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$block = $this;
		$definition = $this->addonDefinition;
		if($player !== null && $definition->hasState("minecraft:cardinal_direction")){
			$block = $block->withStateValue("minecraft:cardinal_direction", self::facingName(Facing::opposite($player->getHorizontalFacing())));
		}
		if($player !== null && $definition->hasState("minecraft:facing_direction")){
			$pitch = $player->getLocation()->getPitch();
			$facing = $pitch > 45 ? Facing::UP : ($pitch < -45 ? Facing::DOWN : Facing::opposite($player->getHorizontalFacing()));
			$block = $block->withStateValue("minecraft:facing_direction", self::facingName($facing));
		}
		if($definition->hasState("minecraft:block_face")){
			$block = $block->withStateValue("minecraft:block_face", self::facingName($face));
		}
		if($definition->hasState("minecraft:vertical_half")){
			$top = $face === Facing::DOWN || ($face !== Facing::UP && $clickVector->y > 0.5);
			$block = $block->withStateValue("minecraft:vertical_half", $top ? "top" : "bottom");
		}
		$tx->addBlock($blockReplace->position, $block);
		return true;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null && (AddonManager::getInstance()?->dispatchBlockInteract($player, $this, $item) ?? false)){
			return true;
		}
		return parent::onInteract($item, $face, $clickVector, $player, $returnedItems);
	}

	private static function facingName(int $facing) : string{
		return match($facing){
			Facing::DOWN => "down",
			Facing::UP => "up",
			Facing::NORTH => "north",
			Facing::SOUTH => "south",
			Facing::WEST => "west",
			default => "east",
		};
	}
}
