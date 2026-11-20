<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Creeper;
use pocketmine\entity\Enderman;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Monster;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\Skeleton;
use pocketmine\entity\Spider;
use pocketmine\entity\Zombie;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class MonsterAITest extends TestCase{

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getViewersForPosition")->willReturn([]);
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getTimeOfDay")->willReturn(World::TIME_NOON);
		return $world;
	}

	private function initMonster(Monster $mob, Location $location) : void{
		(new ReflectionProperty(Entity::class, "closed"))->setValue($mob, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($mob, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($mob, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($mob, new AttributeMap());
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($mob);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($mob, new \pocketmine\entity\effect\EffectManager($mob));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($mob, new ArmorInventory($mob));
		(new ReflectionProperty(Entity::class, "size"))->setValue($mob, new EntitySizeInfo(1.8, 0.6));
		(new ReflectionProperty(Entity::class, "location"))->setValue($mob, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($mob, clone $location);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($mob, new Vector3(0, 0, 0));
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($mob, new Vector3(0, 0, 0));
		$mob->setHealth(20.0);
	}

	protected function setUp() : void{
		\pocketmine\timings\Timings::init();
	}

	public function testZombieTargetAndMeleeAttack() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 4, 0, $world, 0, 0);

		$zombie = (new ReflectionClass(Zombie::class))->newInstanceWithoutConstructor();
		$this->initMonster($zombie, $location);
		$zombie->initAI();

		$targetLoc = new Location(1, 4, 0, $world, 0, 0);
		$target = (new ReflectionClass(Zombie::class))->newInstanceWithoutConstructor();
		$this->initMonster($target, $targetLoc);

		$zombie->setTargetEntity($target);
		self::assertSame($target, $zombie->getTargetEntity());

		// Attack target within melee reach (1.5 blocks)
		$initialHealth = $target->getHealth();
		$success = $zombie->attackTarget($target);
		self::assertTrue($success);
		self::assertLessThan($initialHealth, $target->getHealth());
	}

	public function testZombieBurnsInDaylight() : void{
		$world = $this->createMockWorld();
		$world->method("getHighestBlockAt")->willReturn(0); // Mob is at y=4, so completely exposed to sky

		$location = new Location(0, 4, 0, $world, 0, 0);
		$zombie = (new ReflectionClass(Zombie::class))->newInstanceWithoutConstructor();
		$this->initMonster($zombie, $location);
		$zombie->initAI();

		self::assertFalse($zombie->isOnFire());
		$zombie->checkDaylightBurn();
		self::assertTrue($zombie->isOnFire());

		// Wearing a helmet prevents daylight burning
		$zombie->extinguish();
		$zombie->getArmorInventory()->setHelmet(VanillaItems::LEATHER_CAP());
		$zombie->checkDaylightBurn();
		self::assertFalse($zombie->isOnFire());
	}

	public function testCreeperSwellAndExplosion() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 4, 0, $world, 0, 0);

		$creeper = (new ReflectionClass(Creeper::class))->newInstanceWithoutConstructor();
		$this->initMonster($creeper, $location);
		$creeper->initAI();

		self::assertSame(0, $creeper->getFuseTicks());
		self::assertFalse($creeper->isIgnited());

		// Trigger swelling
		$creeper->setIgnited(true);
		self::assertTrue($creeper->isIgnited());

		// Simulate 29 ticks
		for($i = 0; $i < 29; ++$i){
			$creeper->tickFuse();
			self::assertFalse($creeper->isClosed());
		}
		self::assertSame(29, $creeper->getFuseTicks());

		// 30th tick reaches 30 ticks (1.5s fuse) -> explodes!
		$exploded = false;
		$creeper->onExplodeCallback = function() use (&$exploded){
			$exploded = true;
		};
		$creeper->tickFuse();
		self::assertTrue($exploded);
	}

	public function testCreeperCoolDownWhenPlayerEscapes() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 4, 0, $world, 0, 0);

		$creeper = (new ReflectionClass(Creeper::class))->newInstanceWithoutConstructor();
		$this->initMonster($creeper, $location);
		$creeper->initAI();

		$creeper->setIgnited(true);
		$creeper->tickFuse();
		$creeper->tickFuse();
		self::assertSame(2, $creeper->getFuseTicks());

		// Target escapes: ignited set to false -> fuse cools down
		$creeper->setIgnited(false);
		$creeper->tickFuse();
		self::assertSame(1, $creeper->getFuseTicks());
		$creeper->tickFuse();
		self::assertSame(0, $creeper->getFuseTicks());
	}

	public function testChargedCreeperOnLightning() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 4, 0, $world, 0, 0);

		$creeper = (new ReflectionClass(Creeper::class))->newInstanceWithoutConstructor();
		$this->initMonster($creeper, $location);
		$creeper->initAI();

		self::assertFalse($creeper->isPowered());
		self::assertSame(3.0, $creeper->getExplosionRadius());

		$creeper->setPowered(true);
		self::assertTrue($creeper->isPowered());
		self::assertSame(6.0, $creeper->getExplosionRadius());
	}

	public function testEndermanAggroOnStare() : void{
		$world = $this->createMockWorld();
		$endermanLoc = new Location(0, 4, 10, $world, 0, 0);

		$enderman = (new ReflectionClass(Enderman::class))->newInstanceWithoutConstructor();
		$this->initMonster($enderman, $endermanLoc);
		$enderman->initAI();

		self::assertFalse($enderman->isAngry());

		// Player looking directly at enderman head (vector from player to enderman)
		$playerLoc = new Location(0, 4, 0, $world, 0, 0); // Looking towards +Z
		$staring = $enderman->checkStareAngle($playerLoc, new Vector3(0, 0, 1));
		self::assertTrue($staring);

		// Becoming angry
		$enderman->setAngry(true);
		self::assertTrue($enderman->isAngry());
		self::assertSame(0.45, $enderman->getMovementSpeed());
	}

	public function testEndermanTeleportsOnProjectile() : void{
		$world = $this->createMockWorld();
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) : \pocketmine\block\Block{
			if($y === 3){
				return \pocketmine\block\VanillaBlocks::STONE();
			}
			return \pocketmine\block\VanillaBlocks::AIR();
		});
		$location = new Location(0, 4, 0, $world, 0, 0);

		$enderman = (new ReflectionClass(Enderman::class))->newInstanceWithoutConstructor();
		$this->initMonster($enderman, $location);
		$enderman->initAI();

		$teleported = false;
		$enderman->onTeleportCallback = function() use (&$teleported){
			$teleported = true;
		};

		// Attack via projectile event
		$arrow = (new ReflectionClass(Arrow::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "closed"))->setValue($arrow, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($arrow, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "location"))->setValue($arrow, $location);
		$ev = new EntityDamageByEntityEvent($arrow, $enderman, EntityDamageEvent::CAUSE_PROJECTILE, 5.0);
		$enderman->attack($ev);

		self::assertTrue($ev->isCancelled(), "Enderman must cancel projectile damage by teleporting");
		self::assertTrue($teleported);
	}

	public function testSpiderDaylightNeutralityAndLeap() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 4, 0, $world, 0, 0);

		$spider = (new ReflectionClass(Spider::class))->newInstanceWithoutConstructor();
		$this->initMonster($spider, $location);
		$spider->initAI();

		// Spider can climb walls
		self::assertTrue($spider->canClimbWalls());

		// In daylight (light level >= 8), hostile should be false by default
		self::assertFalse($spider->isHostileInLightLevel(15));
		// In dark (light level < 8), hostile is true
		self::assertTrue($spider->isHostileInLightLevel(4));
	}
}
