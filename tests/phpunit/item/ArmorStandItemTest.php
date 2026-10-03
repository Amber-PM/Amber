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

use PHPUnit\Framework\TestCase;

final class ArmorStandItemTest extends TestCase{

	private ?ArmorStand $item = null;

	protected function setUp() : void{
		$this->item = VanillaItems::ARMOR_STAND();
	}

	protected function tearDown() : void{
		$this->item = null;
		parent::tearDown();
	}

	public function testArmorStandInstance() : void{
		self::assertInstanceOf(ArmorStand::class, $this->item);
	}

	public function testArmorStandName() : void{
		self::assertSame("Armor Stand", $this->item?->getName());
	}

	public function testArmorStandMaxStackSize() : void{
		self::assertSame(16, $this->item?->getMaxStackSize());
	}

	public function testArmorStandTypeId() : void{
		self::assertSame(ItemTypeIds::ARMOR_STAND, $this->item?->getTypeId());
	}
}
