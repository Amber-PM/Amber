<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Animal;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Chicken;
use pocketmine\entity\Cow;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Pig;
use pocketmine\entity\Sheep;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class AnimalAITest extends TestCase{

	/** @var array<string, Block> */
	private array $blocks = [];

	private function createMockWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("isInLoadedTerrain")->willReturn(true);
		$world->method("getDamageY")->willReturn(-64);
		$world->method("getViewersForPosition")->willReturn([]);
		$world->method("getNearbyEntities")->willReturn([]);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) : Block{
			return $this->blocks["$x,$y,$z"] ?? VanillaBlocks::AIR();
		});
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) : bool{
			$this->blocks["{$pos->x},{$pos->y},{$pos->z}"] = $block;
			return true;
		});
		return $world;
	}

	protected function setUp() : void{
		\pocketmine\timings\Timings::init();
	}

	private function initAnimal(Animal $animal, Location $location) : void{
		(new ReflectionProperty(Entity::class, "closed"))->setValue($animal, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($animal, Entity::nextRuntimeId());
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($animal, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($animal, new AttributeMap());
		(new ReflectionClass(Living::class))->getMethod("addAttributes")->invoke($animal);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($animal, new \pocketmine\entity\effect\EffectManager($animal));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($animal, new ArmorInventory($animal));
		(new ReflectionProperty(Entity::class, "size"))->setValue($animal, new EntitySizeInfo(1.0, 1.0));
		(new ReflectionProperty(Entity::class, "location"))->setValue($animal, $location);
		(new ReflectionProperty(Entity::class, "lastLocation"))->setValue($animal, clone $location);
		(new ReflectionProperty(Entity::class, "motion"))->setValue($animal, new Vector3(0, 0, 0));
		(new ReflectionProperty(Entity::class, "lastMotion"))->setValue($animal, new Vector3(0, 0, 0));
		$animal->setHealth(10.0);
	}

	public function testSheepShearingAndEatingGrassRegrowsWool() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 1, 0, $world, 0, 0);

		// Block under sheep at y=0 is Grass
		$this->blocks["0,0,0"] = VanillaBlocks::GRASS();

		$sheep = (new ReflectionClass(Sheep::class))->newInstanceWithoutConstructor();
		$this->initAnimal($sheep, $location);
		$sheep->initAI();

		self::assertFalse($sheep->isSheared());
		self::assertSame(DyeColor::WHITE, $sheep->getColor());

		// Shearing sheep
		$drops = $sheep->shear();
		self::assertTrue($sheep->isSheared());
		self::assertGreaterThanOrEqual(1, count($drops));
		self::assertSame(VanillaBlocks::WOOL()->asItem()->getTypeId(), $drops[0]->getTypeId());

		// Now sheep grazes on grass block below
		$ate = $sheep->eatGrassBlock();
		self::assertTrue($ate);
		self::assertFalse($sheep->isSheared(), "Sheep wool must regrow after eating grass");
		self::assertSame(VanillaBlocks::DIRT()->getTypeId(), $this->blocks["0,0,0"]->getTypeId(), "Grass block must be converted to dirt");
	}

	public function testCowMilkingInteraction() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 1, 0, $world, 0, 0);

		$cow = (new ReflectionClass(Cow::class))->newInstanceWithoutConstructor();
		$this->initAnimal($cow, $location);
		$cow->initAI();

		// Interacting with empty bucket gives milk bucket
		$returned = [];
		$bucket = VanillaItems::BUCKET();
		$success = $cow->interactWithItem($bucket, $returned);

		self::assertTrue($success);
		self::assertCount(1, $returned);
		self::assertSame(VanillaItems::MILK_BUCKET()->getTypeId(), $returned[0]->getTypeId());
	}

	public function testChickenSlowFallNoFallDamage() : void{
		$world = $this->createMockWorld();
		$location = new Location(0, 20, 0, $world, 0, 0);

		$chicken = (new ReflectionClass(Chicken::class))->newInstanceWithoutConstructor();
		$this->initAnimal($chicken, $location);
		$chicken->initAI();

		// Gravity velocity cap on slow falling
		self::assertSame(0.0, $chicken->calculateFallDamage(20.0), "Chickens must not take fall damage");
		self::assertSame(-0.15, $chicken->getMaxFallingSpeed());
	}

	public function testBreedingProducesBabyAndGrantsExperience() : void{
		$world = $this->createMockWorld();
		$loc1 = new Location(0, 1, 0, $world, 0, 0);
		$loc2 = new Location(1, 1, 0, $world, 0, 0);

		$parent1 = (new ReflectionClass(Cow::class))->newInstanceWithoutConstructor();
		$this->initAnimal($parent1, $loc1);
		$parent1->initAI();

		$parent2 = (new ReflectionClass(Cow::class))->newInstanceWithoutConstructor();
		$this->initAnimal($parent2, $loc2);
		$parent2->initAI();

		// Feed wheat
		$parent1->feed(VanillaItems::WHEAT());
		$parent2->feed(VanillaItems::WHEAT());

		self::assertTrue($parent1->isInLove());
		self::assertTrue($parent2->isInLove());

		// Breed them
		$xpSpawned = 0;
		$baby = $parent1->breedWith($parent2, function(int $xp) use (&$xpSpawned){
			$xpSpawned = $xp;
		});

		self::assertNotNull($baby);
		self::assertTrue($baby->isBaby());
		self::assertFalse($parent1->isInLove());
		self::assertFalse($parent2->isInLove());
		self::assertGreaterThanOrEqual(1, $xpSpawned);
		self::assertLessThanOrEqual(7, $xpSpawned);
		self::assertGreaterThan(0, $parent1->getBreedingCooldown());
	}
}
