<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;

final class EntityDynamicPropertyPersistenceTest extends TestCase{
	public function testOrdinaryEntityPropertiesRoundTripThroughNbt() : void{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity"])->getMock();
		$world->method("getServer")->willReturn($server);
		EntityFactory::getInstance()->register(DynamicPropertyTestEntity::class, static fn(World $w, CompoundTag $nbt) : DynamicPropertyTestEntity => new DynamicPropertyTestEntity(new Location(0, 0, 0, $w, 0, 0), $nbt), ["test:dynamic_entity"]);

		$first = new DynamicPropertyTestEntity(new Location(0, 0, 0, $world, 0, 0));
		$first->setAddonDynamicProperties(["uuid-a:value" => 12, "uuid-b:value" => "other", "uuid-a:deleted" => true]);
		$first->setAddonDynamicProperties(["uuid-a:value" => 12, "uuid-b:value" => "other"]);
		$nbt = $first->saveNBT();
		$first->close();

		$second = EntityFactory::getInstance()->createFromData($world, $nbt);
		self::assertInstanceOf(DynamicPropertyTestEntity::class, $second);
		self::assertSame(["uuid-a:value" => 12, "uuid-b:value" => "other"], $second->getAddonDynamicProperties());
		$second->setAddonDynamicProperties([]);
		self::assertNull($second->saveNBT()->getTag("AddonDynamicProperties"));
		$second->close();
	}
}

final class DynamicPropertyTestEntity extends Entity{
	public static function getNetworkTypeId() : string{ return "test:dynamic_entity"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.0, 1.0); }
	protected function getInitialDragMultiplier() : float{ return 0.02; }
	protected function getInitialGravity() : float{ return 0.08; }
}
