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

namespace pocketmine\player;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Location;
use pocketmine\event\player\PlayerToggleCrawlEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\world\World;

final class PlayerCrawlTest extends TestCase{

	public function testPlayerCrawlingSizeAndNetworkFlags() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$location = new Location(0, 64, 0, $world, 0, 0);

		$player = new class($location) extends Player{
			public function __construct(Location $location){
				// Skip full player socket init in unit test
				$this->location = $location;
				$this->crawling = false;
				$this->networkPropertiesDirty = false;
			}

			protected function onDispose() : void{}
		};

		self::assertFalse($player->isCrawling());

		$player->setCrawling(true);
		self::assertTrue($player->isCrawling());
		self::assertEqualsWithDelta(0.6, $player->getSize()->getHeight(), 0.01);
		self::assertEqualsWithDelta(0.4, $player->getSize()->getEyeHeight(), 0.01);

		$player->setCrawling(false);
		self::assertFalse($player->isCrawling());
		self::assertEqualsWithDelta(1.8, $player->getSize()->getHeight(), 0.01);
		self::assertEqualsWithDelta(1.62, $player->getSize()->getEyeHeight(), 0.01);
	}

	public function testToggleCrawlRespectsClearance() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$location = new Location(0, 64, 0, $world, 0, 0);

		$player = new class($location) extends Player{
			public bool $mockClearance = true;

			public function __construct(Location $location){
				$this->location = $location;
				$this->crawling = false;
			}

			public function hasStandingClearance() : bool{
				return $this->mockClearance;
			}

			protected function onDispose() : void{}
		};

		// Start crawl
		self::assertTrue($player->toggleCrawl(true));
		self::assertTrue($player->isCrawling());

		// Blocked ceiling above (clearance = false)
		$player->mockClearance = false;
		self::assertFalse($player->toggleCrawl(false));
		self::assertTrue($player->isCrawling()); // Still crawling

		// Ceiling clears
		$player->mockClearance = true;
		self::assertTrue($player->toggleCrawl(false));
		self::assertFalse($player->isCrawling());
	}

	public function testToggleCrawlEventCancellable() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$location = new Location(0, 64, 0, $world, 0, 0);

		$player = new class($location) extends Player{
			public function __construct(Location $location){
				$this->location = $location;
				$this->crawling = false;
			}

			public function hasStandingClearance() : bool{
				return true;
			}

			protected function onDispose() : void{}
		};

		$ev = new PlayerToggleCrawlEvent($player, true);
		self::assertFalse($ev->isCancelled());
		$ev->cancel();
		self::assertTrue($ev->isCancelled());
	}
}
