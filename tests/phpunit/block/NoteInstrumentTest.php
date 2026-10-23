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

use PHPUnit\Framework\TestCase;
use pocketmine\world\sound\NoteInstrument;

final class NoteInstrumentTest extends TestCase{

	/**
	 * @return \Generator<int, array{Block, NoteInstrument}, void, void>
	 */
	public static function instrumentProvider() : \Generator{
		yield [VanillaBlocks::OAK_PLANKS(), NoteInstrument::DOUBLE_BASS];
		yield [VanillaBlocks::STONE(), NoteInstrument::BASS_DRUM];
		yield [VanillaBlocks::SAND(), NoteInstrument::SNARE];
		yield [VanillaBlocks::GRAVEL(), NoteInstrument::SNARE];
		yield [VanillaBlocks::GLASS(), NoteInstrument::CLICKS_AND_STICKS];
		yield [VanillaBlocks::GOLD(), NoteInstrument::BELL];
		yield [VanillaBlocks::CLAY(), NoteInstrument::FLUTE];
		yield [VanillaBlocks::PACKED_ICE(), NoteInstrument::CHIME];
		yield [VanillaBlocks::WOOL(), NoteInstrument::GUITAR];
		yield [VanillaBlocks::BONE_BLOCK(), NoteInstrument::XYLOPHONE];
		yield [VanillaBlocks::IRON(), NoteInstrument::IRON_XYLOPHONE];
		yield [VanillaBlocks::SOUL_SAND(), NoteInstrument::COW_BELL];
		yield [VanillaBlocks::PUMPKIN(), NoteInstrument::DIDGERIDOO];
		yield [VanillaBlocks::EMERALD(), NoteInstrument::BIT];
		yield [VanillaBlocks::HAY_BALE(), NoteInstrument::BANJO];
		yield [VanillaBlocks::GLOWSTONE(), NoteInstrument::PLING];
		yield [VanillaBlocks::DIRT(), NoteInstrument::PIANO];
		yield [VanillaBlocks::AIR(), NoteInstrument::PIANO];
	}

	/**
	 * @dataProvider instrumentProvider
	 */
	public function testInstrumentFromBlockBelow(Block $below, NoteInstrument $expected) : void{
		self::assertSame($expected, Note::instrumentFor($below), $below->getName());
	}
}
