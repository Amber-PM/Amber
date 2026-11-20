<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Zombie;
use pocketmine\inventory\ArmorInventory;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;
use function microtime;

final class MobAiScaleBenchmarkTest extends TestCase{

	protected function setUp() : void{
		\pocketmine\timings\Timings::init();
	}

	public function test500ActiveMobsTickingPerformance() : void{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getViewersForPosition")->willReturn([]);
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getTimeOfDay")->willReturn(World::TIME_NIGHT);

		$mobs = [];
		for($i = 0; $i < 500; ++$i){
			$loc = new Location(($i % 50) * 2, 4, (int)($i / 50) * 2, $world, 0, 0);
			$zombie = (new ReflectionClass(Zombie::class))->newInstanceWithoutConstructor();
			(new ReflectionProperty(Entity::class, "closed"))->setValue($zombie, false);
			(new ReflectionProperty(Entity::class, "id"))->setValue($zombie, Entity::nextRuntimeId());
			(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($zombie, new EntityMetadataCollection());
			(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($zombie, new AttributeMap());
			(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($zombie);
			(new ReflectionProperty(Living::class, "effectManager"))->setValue($zombie, new \pocketmine\entity\effect\EffectManager($zombie));
			(new ReflectionProperty(Living::class, "armorInventory"))->setValue($zombie, new ArmorInventory($zombie));
			(new ReflectionProperty(Entity::class, "size"))->setValue($zombie, new \pocketmine\entity\EntitySizeInfo(1.8, 0.6));
			(new ReflectionProperty(Entity::class, "location"))->setValue($zombie, $loc);
			(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($zombie, clone $loc);
			(new ReflectionProperty(Entity::class, "motion"))->setValue($zombie, new Vector3(0, 0, 0));
			(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($zombie, new Vector3(0, 0, 0));
			$zombie->setHealth(20.0);
			$zombie->initAI();
			$mobs[] = $zombie;
		}

		// Warmup 5 ticks
		for($tick = 0; $tick < 5; ++$tick){
			foreach($mobs as $mob){
				$mob->tickAI(1);
			}
		}

		// Benchmark 20 ticks (1 full simulated second of 500 mobs)
		$start = microtime(true);
		for($tick = 5; $tick < 25; ++$tick){
			foreach($mobs as $mob){
				$mob->tickAI(1);
			}
		}
		$elapsed = microtime(true) - $start;

		// 20 ticks for 500 mobs = 10,000 mob-ticks.
		// Sustaining 20 TPS means 1 second real-time budget for 20 ticks (50ms per tick).
		// In test environments and shared CI runners, ensure 10,000 mob-ticks complete well within budget (< 2.5s).
		self::assertLessThan(2.5, $elapsed, "500 mobs across 20 ticks should take under 2.5s on test runners (took " . round($elapsed * 1000, 2) . "ms)");
	}
}
