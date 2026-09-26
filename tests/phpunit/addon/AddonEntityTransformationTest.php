<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\entity\trade\TradeOffer;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\Server;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AddonEntityTransformationTest extends TestCase{
	private function transform(array $options, bool $persistTrades = false, bool $withItems = false) : array{
		$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		$created = [];
		$dropped = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["getServer", "addEntity", "removeEntity", "getViewersForPosition", "dropItem"])->getMock();
		$world->method("getServer")->willReturn($server);
		$world->method("getViewersForPosition")->willReturn([]);
		$world->method("addEntity")->willReturnCallback(static function(AddonEntity $entity) use (&$created) : void{ $created[] = $entity; });
		$world->method("dropItem")->willReturnCallback(static function($position, Item $item) use (&$dropped){ $dropped[] = $item; return null; });
		$sourceDefinition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:source"],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10], "minecraft:transformation" => ["into" => "test:target"] + $options, "minecraft:economy_trade_table" => ["persist_trades" => $persistTrades]] + ($withItems ? ["minecraft:inventory" => ["inventory_size" => 5]] : []),
		]], "source.json", "test");
		$targetDefinition = AddonEntityDefinition::fromJson(["minecraft:entity" => [
			"description" => ["identifier" => "test:target"],
			"components" => ["minecraft:health" => ["value" => 10, "max" => 10], "minecraft:economy_trade_table" => []] + ($withItems ? ["minecraft:inventory" => ["inventory_size" => 5]] : []),
			"component_groups" => ["test:transformed" => ["minecraft:variant" => ["value" => 3]]],
			"events" => ["minecraft:entity_transformed" => ["add" => ["component_groups" => ["test:transformed"]]]],
		]], "target.json", "test");
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "entityDefinitions"))->setValue($manager, ["test:target" => $targetDefinition]);
		$previous = AddonManager::getInstance();
		(new ReflectionProperty(AddonManager::class, "instance"))->setValue(null, $manager);
		try{
			$source = new AddonEntity(new Location(0, 0, 0, $world, 0, 0), $sourceDefinition);
			(new ReflectionProperty(AddonEntity::class, "tamed"))->setValue($source, true);
			(new ReflectionProperty(AddonEntity::class, "ownerName"))->setValue($source, "owner");
			$item = new Item(new ItemIdentifier(100));
			if($withItems){
				$source->getInventory()?->setItem(0, $item);
				$source->setMainHandItem($item);
			}
			$offer = new TradeOffer(1, $item, null, $item, 0, 10, 1, true, 3);
			(new ReflectionProperty(AddonEntity::class, "tradeOffers"))->setValue($source, [$offer]);
			(new ReflectionProperty(AddonEntity::class, "tradeExp"))->setValue($source, 9);
			(new ReflectionMethod(AddonEntity::class, "transform"))->invoke($source);
			return [$source, $created[1], $dropped, $offer];
		}finally{
			(new ReflectionProperty(AddonManager::class, "instance"))->setValue(null, $previous);
		}
	}

	public function testDefaultAndExplicitOwnerAndTradeOptions() : void{
		foreach([[[], false, false], [["keep_owner" => false], false, false], [["keep_owner" => true, "keep_level" => true], true, true]] as [$options, $persist, $keep]){
			[$source, $target, , $offer] = $this->transform($options, $persist);
			self::assertSame($keep, (new ReflectionProperty(AddonEntity::class, "tamed"))->getValue($target));
			self::assertSame($keep ? "owner" : null, (new ReflectionProperty(AddonEntity::class, "ownerName"))->getValue($target));
			self::assertSame($persist ? 9 : 0, (new ReflectionProperty(AddonEntity::class, "tradeExp"))->getValue($target));
			self::assertSame($persist ? 3 : null, (new ReflectionProperty(AddonEntity::class, "tradeOffers"))->getValue($target)[0]->uses ?? null);
			self::assertTrue($source->isFlaggedForDespawn());
			$source->close();
			$target->close();
		}
	}

	public function testInventoryAndEquipmentTransferOrDropWithoutDuplication() : void{
		if(!class_exists(\pmmp\encoding\BE::class)){
			self::markTestSkipped("PocketMine native encoding extension is required for item registry initialization");
		}
		[$source, $target, $dropped] = $this->transform(["preserve_equipment" => true], false, true);
		self::assertCount(0, $dropped);
		self::assertCount(1, $target->getInventory()?->getContents() ?? []);
		self::assertFalse($target->getMainHandItem()->isNull());
		$source->close();
		$target->close();

		[$source, $target, $dropped] = $this->transform(["drop_inventory" => true, "drop_equipment" => true], false, true);
		self::assertCount(2, $dropped);
		self::assertCount(0, $target->getInventory()?->getContents() ?? []);
		self::assertTrue($target->getMainHandItem()->isNull());
		$source->close();
		$target->close();
	}

	public function testTransformedSpawnEventRunsOnce() : void{
		[$source, $target] = $this->transform([]);
		$first = new ReflectionMethod(AddonEntity::class, "onFirstUpdate");
		$commit = new ReflectionMethod(AddonEntity::class, "commitPendingMutations");
		$first->invoke($target, 1);
		$commit->invoke($target);
		self::assertSame(3, $target->getVariant());
		self::assertSame(["test:transformed"], $target->getActiveComponentGroups());
		$first->invoke($target, 2);
		$commit->invoke($target);
		self::assertSame(["test:transformed"], $target->getActiveComponentGroups());
		$source->close();
		$target->close();
	}
}
