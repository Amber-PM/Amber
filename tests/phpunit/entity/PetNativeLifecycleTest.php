<?php

declare(strict_types=1);

namespace pocketmine\tests\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Location;
use pocketmine\entity\Wolf;
use pocketmine\tests\support\entity\NativeEntityTestEnvironment;

require_once __DIR__ . "/../../support/entity/NativeEntityTestEnvironment.php";

final class PetNativeLifecycleTest extends TestCase{

	private NativeEntityTestEnvironment $environment;

	protected function setUp() : void{
		$this->environment = NativeEntityTestEnvironment::create($this);
	}

	protected function tearDown() : void{
		$this->environment->close();
	}

	public function testNewbornAndReloadedWolfAreSmallerThanAdult() : void{
		$location = new Location(8, 10, 8, $this->environment->getWorld(), 0, 0);
		$adult = new Wolf($location);
		$baby = new Wolf($location);
		$baby->setAge(-24000);
		self::assertLessThan($adult->getSize()->getHeight(), $baby->getSize()->getHeight());
		self::assertLessThan($adult->getSize()->getWidth(), $baby->getSize()->getWidth());
		$loaded = new Wolf($location, $baby->saveNBT());
		self::assertSame($baby->getScale(), $loaded->getScale());
		self::assertSame($baby->getSize()->getHeight(), $loaded->getSize()->getHeight());
	}

	public function testGrowthDoesNotMultiplyScale() : void{
		$wolf = new Wolf(new Location(8, 10, 8, $this->environment->getWorld(), 0, 0));
		$wolf->setAge(-100);
		$scale = $wolf->getScale();
		$wolf->setAge(-99);
		$wolf->setAge(-1);
		self::assertSame($scale, $wolf->getScale());
		$wolf->setAge(0);
		self::assertSame(1.0, $wolf->getScale());
		self::assertEqualsWithDelta(0.85, $wolf->getSize()->getHeight(), 0.0001);
	}

	public function testBreedingCreatesFullyInitializedNativeWolf() : void{
		$world = $this->environment->getWorld();
		$first = new Wolf(new Location(8, 10, 8, $world, 0, 0));
		$second = new Wolf(new Location(9, 10, 8, $world, 0, 0));
		foreach([$first, $second] as $parent){
			$parent->setTamed(true);
			$parent->setOwnerUUID("d9802a39-9045-498c-ab22-d7eea8eca246");
			$parent->setInLoveTicks(600);
		}
		$baby = $first->breed($second);
		self::assertInstanceOf(Wolf::class, $baby);
		self::assertSame($baby, $world->getEntity($baby->getId()));
		self::assertSame(20.0, $baby->getHealth());
		self::assertTrue($baby->isBaby());
		self::assertLessThan($first->getSize()->getHeight(), $baby->getSize()->getHeight());
	}
}
