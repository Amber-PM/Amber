<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\handler;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\TransactionData;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

final class DropItemTransactionTest extends TestCase{
	public static function dropCases() : \Generator{
		foreach([ProtocolInfo::PROTOCOL_1_26_44, ProtocolInfo::PROTOCOL_1_26_50, ProtocolInfo::PROTOCOL_1_26_51] as $protocol){
			foreach([GameMode::SURVIVAL, GameMode::CREATIVE] as $mode){
				foreach(["DISPENSER", "STICKY_PISTON", "LEVER", "orange hardened glass", "DIRT", "iron ingot"] as $name){
					yield "$protocol/$mode->name/$name" => [$protocol, $mode, $name, ""];
				}
			}
		}
		foreach(["id", "meta", "count", "block"] as $mismatch){
			yield "reject $mismatch mismatch" => [ProtocolInfo::PROTOCOL_1_26_51, GameMode::SURVIVAL, "DISPENSER", $mismatch];
		}
	}

	/** @dataProvider dropCases */
	public function testDropThroughDecodedInventoryTransaction(int $protocol, GameMode $mode, string $name, string $mismatch) : void{
		\pocketmine\timings\Timings::init();
		$item = match($name){
			"orange hardened glass" => VanillaBlocks::STAINED_HARDENED_GLASS()->setColor(DyeColor::ORANGE)->asItem(),
			"iron ingot" => VanillaItems::IRON_INGOT(),
			default => VanillaBlocks::$name()->asItem()
		};
		$item->setCount(10)->setCustomName("server-owned NBT");
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()
			->onlyMethods(["getInventory", "dropItem", "setUsingItem"])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		(new \ReflectionProperty(Player::class, "gamemode"))->setValue($player, $mode);
		$inventory = new PlayerInventory($player);
		$inventory->setItem(4, $item);
		$player->method("getInventory")->willReturn($inventory);
		$expectedDrop = (clone $item)->setCount(1);
		$player->expects($mismatch === "" ? self::once() : self::never())->method("dropItem")
			->with(self::callback(fn(Item $dropped) : bool => $dropped->equalsExact($expectedDrop)));
		$converter = TypeConverter::getInstance($protocol);
		$net = $converter->coreItemStackToNet($item);
		$clientStack = new ItemStack(
			$net->getId() + ($mismatch === "id" ? 1 : 0),
			$net->getMeta() + ($mismatch === "meta" ? 1 : 0),
			$net->getCount() + ($mismatch === "count" ? 1 : 0),
			$net->getBlockRuntimeId() + ($mismatch === "block" ? 1 : 0),
			$net->getRawExtraData()
		);
		$source = new NetworkInventoryAction();
		$source->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
		$source->windowId = ContainerIds::INVENTORY;
		$source->inventorySlot = 4;
		$source->oldItem = new ItemStackWrapper(0, $clientStack);
		$source->newItem = new ItemStackWrapper(0, $converter->coreItemStackToNet((clone $item)->setCount(9)));
		$drop = new NetworkInventoryAction();
		$drop->sourceType = NetworkInventoryAction::SOURCE_WORLD;
		$drop->sourceFlags = 0;
		$drop->inventorySlot = NetworkInventoryAction::ACTION_MAGIC_SLOT_DROP_ITEM;
		$drop->oldItem = new ItemStackWrapper(0, ItemStack::null());
		$drop->newItem = new ItemStackWrapper(0, $converter->coreItemStackToNet($expectedDrop));
		$data = new NormalTransactionData();
		(new \ReflectionProperty(TransactionData::class, "actions"))->setValue($data, [$source, $drop]);
		$packet = InventoryTransactionPacket::create(0, null, $data);
		$writer = new ByteBufferWriter();
		$packet->encode($writer, $protocol);
		$decoded = new InventoryTransactionPacket();
		$decoded->decode(new ByteBufferReader($writer->getData()), $protocol);
		if($protocol >= ProtocolInfo::PROTOCOL_1_26_50 && $net->getBlockRuntimeId() < 0 && $mismatch === ""){
			self::assertSame($net->getBlockRuntimeId() + 0x100000000, $decoded->trData->getActions()[0]->oldItem->getItemStack()->getBlockRuntimeId());
		}
		$session = $this->createMock(NetworkSession::class);
		$session->method("getTypeConverter")->willReturn($converter);
		$session->method("getLogger")->willReturn($this->createMock(\Logger::class));
		$manager = $this->createMock(InventoryManager::class);
		$handler = new InGamePacketHandler($player, $session, $manager);
		self::assertSame($mismatch === "", $handler->handleInventoryTransaction($decoded));
		self::assertTrue($inventory->getItem(4)->equalsExact((clone $item)->setCount($mismatch === "" ? 9 : 10)));
	}
}
