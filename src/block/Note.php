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

namespace pocketmine\block;

use pocketmine\block\tile\Note as TileNote;
use pocketmine\block\utils\RedstoneReceiver;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\redstone\RedstoneEngine;
use pocketmine\world\sound\MobHeadSound;
use pocketmine\world\sound\NoteInstrument;
use pocketmine\world\sound\NoteSound;
use function assert;

class Note extends Opaque implements RedstoneReceiver{
	public const MIN_PITCH = 0;
	public const MAX_PITCH = 24;

	private int $pitch = self::MIN_PITCH;

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TileNote){
			$this->pitch = $tile->getPitch();
		}else{
			$this->pitch = self::MIN_PITCH;
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		assert($tile instanceof TileNote);
		$tile->setPitch($this->pitch);
	}

	public function getFuelTime() : int{
		return 300;
	}

	public function getPitch() : int{
		return $this->pitch;
	}

	/** @return $this */
	public function setPitch(int $pitch) : self{
		if($pitch < self::MIN_PITCH || $pitch > self::MAX_PITCH){
			throw new \InvalidArgumentException("Pitch must be in range " . self::MIN_PITCH . " - " . self::MAX_PITCH);
		}
		$this->pitch = $pitch;
		return $this;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->pitch = ($this->pitch + 1) % (self::MAX_PITCH + 1);
		$this->position->getWorld()->setBlock($this->position, $this);
		$this->play();
		return true;
	}

	public function onAttack(Item $item, int $face, ?Player $player = null) : bool{
		$this->play();
		return false;
	}

	public function play() : void{
		$world = $this->position->getWorld();
		$above = $this->getSide(Facing::UP);
		if($above instanceof MobHead){
			$sound = MobHeadSound::forHead($above->getMobHeadType());
			if($sound !== null){
				$world->addSound($this->position->add(0.5, 0.5, 0.5), $sound);
			}
			return;
		}
		if($above->getTypeId() !== BlockTypeIds::AIR){
			return;
		}
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new NoteSound(self::instrumentFor($this->getSide(Facing::DOWN)), $this->pitch));
	}

	public static function instrumentFor(Block $below) : NoteInstrument{
		$instrument = match($below->getTypeId()){
			BlockTypeIds::GOLD => NoteInstrument::BELL,
			BlockTypeIds::CLAY => NoteInstrument::FLUTE,
			BlockTypeIds::PACKED_ICE => NoteInstrument::CHIME,
			BlockTypeIds::WOOL => NoteInstrument::GUITAR,
			BlockTypeIds::BONE_BLOCK => NoteInstrument::XYLOPHONE,
			BlockTypeIds::IRON => NoteInstrument::IRON_XYLOPHONE,
			BlockTypeIds::SOUL_SAND => NoteInstrument::COW_BELL,
			BlockTypeIds::PUMPKIN => NoteInstrument::DIDGERIDOO,
			BlockTypeIds::EMERALD => NoteInstrument::BIT,
			BlockTypeIds::HAY_BALE => NoteInstrument::BANJO,
			BlockTypeIds::GLOWSTONE => NoteInstrument::PLING,
			default => null,
		};
		if($instrument !== null){
			return $instrument;
		}
		if($below instanceof Glass || $below instanceof GlassPane || $below instanceof HardenedGlass || $below instanceof HardenedGlassPane || $below instanceof TintedGlass || $below instanceof SeaLantern || $below instanceof Beacon){
			return NoteInstrument::CLICKS_AND_STICKS;
		}
		if($below->hasTypeTag(BlockTypeTags::SAND) || $below instanceof Gravel || $below instanceof ConcretePowder){
			return NoteInstrument::SNARE;
		}
		$tool = $below->getBreakInfo()->getToolType();
		if(($tool & BlockToolType::AXE) !== 0){
			return NoteInstrument::DOUBLE_BASS;
		}
		if(($tool & BlockToolType::PICKAXE) !== 0){
			return NoteInstrument::BASS_DRUM;
		}
		return NoteInstrument::PIANO;
	}

	/** Plays when power is switched on. */
	public function onRedstoneUpdate(RedstoneEngine $engine) : void{
		$powered = $engine->getReceivedPower($this->position) > 0;
		if($engine->powerChanged($this->position, $powered) && $powered){
			$this->play();
		}
	}
}
