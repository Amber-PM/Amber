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

namespace pocketmine\event\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\TameableAnimal;
use pocketmine\event\Cancellable;
use pocketmine\player\Player;
use ReflectionClass;

final class PetEventsTest extends TestCase{

	public function testEventsInheritanceAndCancellable() : void{
		self::assertTrue(is_subclass_of(EntityTameEvent::class, EntityEvent::class));
		self::assertTrue(is_subclass_of(EntityTameEvent::class, Cancellable::class));

		self::assertTrue(is_subclass_of(PetSitChangeEvent::class, EntityEvent::class));
		self::assertTrue(is_subclass_of(PetSitChangeEvent::class, Cancellable::class));

		self::assertTrue(is_subclass_of(PetCollarColorChangeEvent::class, EntityEvent::class));
		self::assertTrue(is_subclass_of(PetCollarColorChangeEvent::class, Cancellable::class));
	}

	private function createPetMock() : TameableAnimal{
		$entity = $this->createMock(TameableAnimal::class);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($entity, true);
		return $entity;
	}

	private function createPlayerMock() : Player{
		$player = $this->createMock(Player::class);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		return $player;
	}

	public function testEntityTameEventContract() : void{
		$entity = $this->createPetMock();
		$player = $this->createPlayerMock();

		$ev = new EntityTameEvent($entity, $player);
		self::assertSame($entity, $ev->getEntity());
		self::assertSame($player, $ev->getPlayer());
		self::assertFalse($ev->isCancelled());

		$ev->cancel();
		self::assertTrue($ev->isCancelled());
		$ev->uncancel();
		self::assertFalse($ev->isCancelled());
	}

	public function testPetSitChangeEventContract() : void{
		$entity = $this->createPetMock();
		$player = $this->createPlayerMock();

		$ev = new PetSitChangeEvent($entity, $player, true);
		self::assertSame($entity, $ev->getEntity());
		self::assertSame($player, $ev->getPlayer());
		self::assertTrue($ev->isSitting());

		$ev->setSitting(false);
		self::assertFalse($ev->isSitting());

		$ev->cancel();
		self::assertTrue($ev->isCancelled());
		$ev->uncancel();
		self::assertFalse($ev->isCancelled());
	}

	public function testPetCollarColorChangeEventContract() : void{
		$entity = $this->createPetMock();
		$player = $this->createPlayerMock();

		$ev = new PetCollarColorChangeEvent($entity, $player, DyeColor::RED, DyeColor::BLUE);
		self::assertSame($entity, $ev->getEntity());
		self::assertSame($player, $ev->getPlayer());
		self::assertSame(DyeColor::RED, $ev->getOldColor());
		self::assertSame(DyeColor::BLUE, $ev->getNewColor());

		$ev->setNewColor(DyeColor::GREEN);
		self::assertSame(DyeColor::GREEN, $ev->getNewColor());

		$ev->cancel();
		self::assertTrue($ev->isCancelled());
		$ev->uncancel();
		self::assertFalse($ev->isCancelled());
	}

	public function testReflectionSignatures() : void{
		$tameRef = new ReflectionClass(EntityTameEvent::class);
		$tameCtor = $tameRef->getConstructor();
		self::assertNotNull($tameCtor);
		self::assertCount(2, $tameCtor->getParameters());
		self::assertSame(TameableAnimal::class, $tameCtor->getParameters()[0]->getType()?->getName());
		self::assertSame(Player::class, $tameCtor->getParameters()[1]->getType()?->getName());

		$sitRef = new ReflectionClass(PetSitChangeEvent::class);
		$sitCtor = $sitRef->getConstructor();
		self::assertNotNull($sitCtor);
		self::assertCount(3, $sitCtor->getParameters());
		self::assertSame(TameableAnimal::class, $sitCtor->getParameters()[0]->getType()?->getName());
		self::assertSame(Player::class, $sitCtor->getParameters()[1]->getType()?->getName());
		self::assertSame("bool", $sitCtor->getParameters()[2]->getType()?->getName());

		$colorRef = new ReflectionClass(PetCollarColorChangeEvent::class);
		$colorCtor = $colorRef->getConstructor();
		self::assertNotNull($colorCtor);
		self::assertCount(4, $colorCtor->getParameters());
		self::assertSame(TameableAnimal::class, $colorCtor->getParameters()[0]->getType()?->getName());
		self::assertSame(Player::class, $colorCtor->getParameters()[1]->getType()?->getName());
		self::assertSame(DyeColor::class, $colorCtor->getParameters()[2]->getType()?->getName());
		self::assertSame(DyeColor::class, $colorCtor->getParameters()[3]->getType()?->getName());
	}
}
