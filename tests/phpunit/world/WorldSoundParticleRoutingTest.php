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

namespace pocketmine\world;

use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\player\Player;
use pocketmine\timings\Timings;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\ProtocolParticle;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\ProtocolSound;
use ReflectionClass;

final class WorldSoundParticleRoutingTest extends TestCase{

	private World $world;
	private Vector3 $position;
	private Player $viewer;
	private Player $other;

	protected function setUp() : void{
		Timings::init();
		$this->position = new Vector3(0, 0, 0);
		$this->viewer = self::createPlayer();
		$this->other = self::createPlayer();
		$this->world = (new ReflectionClass(World::class))->newInstanceWithoutConstructor();
		$this->setProperty('playerChunkListeners', [World::chunkHash(0, 0) => [spl_object_id($this->viewer) => $this->viewer]]);
	}

	private static function createPlayer() : Player{
		return new class extends Player{
			public function __construct(){}

			public function __destruct(){}
		};
	}

	private function setProperty(string $name, mixed $value) : void{
		(new ReflectionClass(World::class))->getProperty($name)->setValue($this->world, $value);
	}

	private function getProperty(string $name) : mixed{
		return (new ReflectionClass(World::class))->getProperty($name)->getValue($this->world);
	}

	private function viewers() : array{
		return $this->world->getViewersForPosition($this->position);
	}

	private static function protocolSound() : ProtocolSound{
		return new class extends ProtocolSound{
			public function encode(Vector3 $pos) : array{
				return [];
			}
		};
	}

	private static function protocolParticle() : ProtocolParticle{
		return new class extends ProtocolParticle{
			public function encode(Vector3 $pos) : array{
				return [];
			}
		};
	}

	private static function plainParticle() : Particle{
		return new class implements Particle{
			public function encode(Vector3 $pos) : array{
				return [LevelEventPacket::create(0, 0, $pos)];
			}
		};
	}

	private function translatorBuffer() : array{
		return $this->getProperty('packetBuffersByChunkTypeConverter')[World::chunkHash(0, 0)] ?? [];
	}

	private function plainBuffer() : array{
		return $this->getProperty('packetBuffersByChunk')[World::chunkHash(0, 0)] ?? [];
	}

	public function testSoundWithTranslatorAndNullPlayersIsBufferedByChunk() : void{
		$this->world->addSound($this->position, self::protocolSound());
		self::assertCount(1, $this->translatorBuffer());
	}

	public function testSoundWithTranslatorAndExplicitViewersIsBufferedByChunk() : void{
		$this->world->addSound($this->position, self::protocolSound(), $this->viewers());
		self::assertCount(1, $this->translatorBuffer());
	}

	public function testSoundWithTranslatorAndOtherPlayersIsNotBufferedByChunk() : void{
		$this->world->addSound($this->position, self::protocolSound(), [spl_object_id($this->other) => $this->other]);
		self::assertCount(0, $this->translatorBuffer());
	}

	public function testPlainSoundAndNullPlayersIsBufferedByChunk() : void{
		$this->world->addSound($this->position, new ClickSound());
		self::assertCount(1, $this->plainBuffer());
	}

	public function testPlainSoundAndExplicitViewersIsBufferedByChunk() : void{
		$this->world->addSound($this->position, new ClickSound(), $this->viewers());
		self::assertCount(1, $this->plainBuffer());
	}

	public function testPlainSoundAndOtherPlayersIsNotBufferedByChunk() : void{
		$this->world->addSound($this->position, new ClickSound(), [spl_object_id($this->other) => $this->other]);
		self::assertCount(0, $this->plainBuffer());
	}

	public function testParticleWithTranslatorAndNullPlayersIsBufferedByChunk() : void{
		$this->world->addParticle($this->position, self::protocolParticle());
		self::assertCount(1, $this->translatorBuffer());
	}

	public function testParticleWithTranslatorAndExplicitViewersIsBufferedByChunk() : void{
		$this->world->addParticle($this->position, self::protocolParticle(), $this->viewers());
		self::assertCount(1, $this->translatorBuffer());
	}

	public function testParticleWithTranslatorAndOtherPlayersIsNotBufferedByChunk() : void{
		$this->world->addParticle($this->position, self::protocolParticle(), [spl_object_id($this->other) => $this->other]);
		self::assertCount(0, $this->translatorBuffer());
	}

	public function testPlainParticleAndNullPlayersIsBufferedByChunk() : void{
		$this->world->addParticle($this->position, self::plainParticle());
		self::assertCount(1, $this->plainBuffer());
	}

	public function testPlainParticleAndExplicitViewersIsBufferedByChunk() : void{
		$this->world->addParticle($this->position, self::plainParticle(), $this->viewers());
		self::assertCount(1, $this->plainBuffer());
	}

	public function testPlainParticleAndOtherPlayersIsNotBufferedByChunk() : void{
		$this->world->addParticle($this->position, self::plainParticle(), [spl_object_id($this->other) => $this->other]);
		self::assertCount(0, $this->plainBuffer());
	}
}
