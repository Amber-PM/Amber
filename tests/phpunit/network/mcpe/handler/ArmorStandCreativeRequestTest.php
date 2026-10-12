<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\handler;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\ArmorStand;
use pocketmine\network\mcpe\cache\CreativeInventoryCache;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerUIIds;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CreativeCreateStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestSlotInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\TakeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\UIInventorySlotOffset;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;

final class ArmorStandCreativeRequestTest extends TestCase{

	public function testAdvertisedArmorStandCreativeIdCanBeTakenAcrossProtocols() : void{
		foreach([ProtocolInfo::PROTOCOL_1_20_60, ProtocolInfo::PROTOCOL_1_26_30, ProtocolInfo::PROTOCOL_1_26_44, ProtocolInfo::PROTOCOL_1_26_50, ProtocolInfo::CURRENT_PROTOCOL] as $protocol){
			foreach([true, false] as $education){
				$creative = clone CreativeInventory::getInstance($protocol);
				if(!$education){
					$creative->removeEducationEditionContent();
				}
				$cache = CreativeInventoryCache::getInstance($protocol);
				$entry = (new ReflectionMethod($cache, "buildCacheEntry"))->invoke($cache, $creative);
				$converter = TypeConverter::getInstance($protocol);
				$id = null;
				foreach($entry->items as $item){
					if($creative->getItem($item->getEntryId()) instanceof ArmorStand){
						self::assertInstanceOf(ArmorStand::class, $converter->netItemStackToCore($item->getItem()));
						$id = $item->getEntryId();
						break;
					}
				}
				self::assertNotNull($id);
				foreach([1, 16, 64] as $count){
					[$transaction, $inventory] = $this->createTransaction($creative, $id, $count);
					$transaction->execute();
					self::assertInstanceOf(ArmorStand::class, $inventory->getItem(0));
					self::assertSame($count, $inventory->getItem(0)->getCount());
				}
			}
		}
	}

	public function testOversizedCreativeStackDoesNotModifyInventory() : void{
		$creative = CreativeInventory::getInstance();
		[$transaction, $inventory] = $this->createTransaction($creative, $creative->getItemIndex(\pocketmine\item\VanillaItems::ARMOR_STAND()), 65);
		try{
			$transaction->execute();
			self::fail("Oversized stack was accepted");
		}catch(TransactionValidationException){
			self::assertTrue($inventory->getItem(0)->isNull());
		}
	}

	public function testSurvivalCannotCreateArmorStandFromCreative() : void{
		$creative = CreativeInventory::getInstance();
		[$transaction, $inventory] = $this->createTransaction($creative, $creative->getItemIndex(\pocketmine\item\VanillaItems::ARMOR_STAND()), 1, true);
		try{
			$transaction->execute();
			self::fail("Survival creative request was accepted");
		}catch(TransactionValidationException){
			self::assertTrue($inventory->getItem(0)->isNull());
		}
	}

	private function createTransaction(CreativeInventory $creative, int $id, int $count, bool $finiteResources = false) : array{
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods(["hasFiniteResources", "getCreativeInventory"])->getMock();
		$player->method("hasFiniteResources")->willReturn($finiteResources);
		$player->method("getCreativeInventory")->willReturn($creative);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$inventory = new PlayerInventory($player);
		$manager = $this->createMock(InventoryManager::class);
		$manager->method("locateWindowAndSlot")->willReturn([$inventory, 0]);
		$request = new ItemStackRequest(-1, [
			new CreativeCreateStackRequestAction($id, 1),
			new TakeStackRequestAction($count, new ItemStackRequestSlotInfo(new FullContainerName(ContainerUIIds::CREATED_OUTPUT), UIInventorySlotOffset::CREATED_ITEM_OUTPUT, -1), new ItemStackRequestSlotInfo(new FullContainerName(ContainerUIIds::HOTBAR), 0, -1))
		], [], 0);
		$transaction = (new ItemStackRequestExecutor($player, $manager, $request))->generateInventoryTransaction();
		self::assertInstanceOf(InventoryTransaction::class, $transaction);
		return [$transaction, $inventory];
	}
}
