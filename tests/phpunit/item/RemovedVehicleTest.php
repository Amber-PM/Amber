<?php

declare(strict_types=1);

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\EntityFactory;
use pocketmine\inventory\CreativeInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\world\World;

final class RemovedVehicleTest extends TestCase{

	public function testBoatEntitiesAreNotLoaded() : void{
		$world = $this->createMock(World::class);
		$world->expects(self::never())->method("addEntity");
		foreach(["Boat", "ChestBoat", "minecraft:boat", "minecraft:chest_boat"] as $id){
			self::assertNull(EntityFactory::getInstance()->createFromData($world, CompoundTag::create()->setString("identifier", $id)));
		}
	}

	public function testBoatsAreNotAvailableFromParserOrCreative() : void{
		$parser = StringToItemParser::getInstance();
		foreach(["boat", "oak_boat", "oak_chest_boat", "bamboo_raft", "bamboo_chest_raft"] as $alias){
			self::assertNull($parser->parse($alias));
		}
		foreach([ProtocolInfo::PROTOCOL_1_20_60, ProtocolInfo::PROTOCOL_1_26_44, ProtocolInfo::CURRENT_PROTOCOL] as $protocol){
			foreach(CreativeInventory::getInstance($protocol)->getAll() as $item){
				self::assertDoesNotMatchRegularExpression('/\b(?:Boat|Raft)\b/i', $item->getName());
			}
		}
	}
}
