<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\Server;
use ReflectionClass;
use ReflectionProperty;

final class AddonEntityBreedCooldownTest extends TestCase{
	/** @return array{AddonEntity, AddonEntity} */
	private function parents(?float $firstCooldown, ?float $secondCooldown) : array{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Server::class, "tickCounter"))->setValue($server, 100);
		$parents = [];
		foreach([1 => $firstCooldown, 2 => $secondCooldown] as $id => $cooldown){
			$parent = $this->getMockBuilder(AddonEntity::class)
				->disableOriginalConstructor()
				->onlyMethods(["getId", "getLocation", "getAddonIdentifier", "__destruct"])
				->getMock();
			$parent->method("getId")->willReturn($id);
			$parent->method("getLocation")->willReturn(new Location(0, 0, 0, null, 0, 0));
			$parent->method("getAddonIdentifier")->willReturn("test:mob");
			$breedable = $cooldown === null ? [] : ["breed_cooldown" => $cooldown];
			(new ReflectionProperty(AddonEntity::class, "components"))->setValue($parent, ["minecraft:breedable" => $breedable]);
			(new ReflectionProperty(AddonEntity::class, "server"))->setValue($parent, $server);
			(new ReflectionProperty(Entity::class, "closed"))->setValue($parent, true);
			$parents[] = $parent;
		}
		return $parents;
	}

	public function testEachParentUsesItsOwnConfiguredCooldown() : void{
		foreach([[null, null, 1300, 1300], [2.5, 9.0, 150, 280], [0.0, 120.0, 100, 2500]] as [$first, $second, $expectedFirst, $expectedSecond]){
			[$one, $two] = $this->parents($first, $second);
			$one->breedWith($two);
			self::assertSame($expectedFirst, (new ReflectionProperty(AddonEntity::class, "breedCooldownUntil"))->getValue($one));
			self::assertSame($expectedSecond, (new ReflectionProperty(AddonEntity::class, "breedCooldownUntil"))->getValue($two));
			$gate = new \ReflectionMethod(AddonEntity::class, "canEnterLoveMode");
			if($expectedFirst > 100){
				self::assertFalse($gate->invoke($one, $expectedFirst - 1));
			}
			if($expectedSecond > 100){
				self::assertFalse($gate->invoke($two, $expectedSecond - 1));
			}
			self::assertTrue($gate->invoke($one, $expectedFirst));
			self::assertTrue($gate->invoke($two, $expectedSecond));
		}
	}
}
