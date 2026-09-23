<?php

declare(strict_types=1);

namespace pocketmine\inventory;

use PHPUnit\Framework\TestCase;
use pocketmine\lang\Language;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\cache\CreativeInventoryCache;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\Server;

final class CreativeInventoryTest extends TestCase{
	private const EDUCATION_KEYS = [
		"itemGroup.name.element" => true,
		"itemGroup.name.chemistrytable" => true,
		"itemGroup.name.compounds" => true,
		"itemGroup.name.products" => true
	];

	protected function setUp() : void{
		CreativeInventory::reset();
	}

	protected function tearDown() : void{
		CreativeInventory::reset();
	}

	public function testRemovesOnlyBuiltInEducationGroupsWithOneNotification() : void{
		$inventory = CreativeInventory::getInstance();
		$originalCounts = array_fill_keys(array_keys(self::EDUCATION_KEYS), 0);
		foreach($inventory->getAllEntries() as $entry){
			$name = $entry->getGroup()?->getName();
			if($name instanceof Translatable && isset($originalCounts[$name->getText()])){
				++$originalCounts[$name->getText()];
			}
		}
		foreach($originalCounts as $key => $count){
			if($key !== "itemGroup.name.products"){
				self::assertGreaterThan(0, $count, $key);
			}
		}
		self::assertNotSame(-1, $inventory->getItemIndex(VanillaItems::BLEACH()));
		self::assertNotSame(-1, $inventory->getItemIndex(VanillaItems::ICE_BOMB()));

		$rawStringGroup = new CreativeGroup("itemGroup.name.compounds", VanillaItems::APPLE());
		$unrelatedGroup = new CreativeGroup("Custom tools", VanillaItems::APPLE());
		$inventory->add(VanillaItems::DIAMOND(), group: $rawStringGroup);
		$inventory->add(VanillaItems::EMERALD(), group: $unrelatedGroup);
		$inventory->add(VanillaItems::STICK());
		$ungroupedIndex = array_key_last($inventory->getAllEntries());
		self::assertIsInt($ungroupedIndex);
		$ungroupedEntry = $inventory->getEntry($ungroupedIndex);
		self::assertNotNull($ungroupedEntry);
		$beforeCount = count($inventory->getAllEntries());
		$notifications = 0;
		$inventory->getContentChangedCallbacks()->add(static function() use (&$notifications) : void{
			++$notifications;
		});

		$inventory->removeEducationEditionContent();

		//The locked BedrockData revision contributes 119 elements, 4 tables, 35 compounds, and 47 supported products.
		self::assertSame($beforeCount - 205, count($inventory->getAllEntries()));
		self::assertSame(1, $notifications);
		self::assertSame(-1, $inventory->getItemIndex(VanillaItems::BLEACH()));
		self::assertSame(-1, $inventory->getItemIndex(VanillaItems::ICE_BOMB()));
		$keptGroups = [];
		foreach($inventory->getAllEntries() as $entry){
			$name = $entry->getGroup()?->getName();
			self::assertFalse($name instanceof Translatable && isset(self::EDUCATION_KEYS[$name->getText()]));
			if($entry->getGroup() === $rawStringGroup || $entry->getGroup() === $unrelatedGroup || $entry->getGroup() === null){
				$keptGroups[] = $entry->getGroup();
			}
		}
		self::assertContains($rawStringGroup, $keptGroups);
		self::assertContains($unrelatedGroup, $keptGroups);
		self::assertSame($ungroupedEntry, $inventory->getEntry($ungroupedIndex));

		$inventory->removeEducationEditionContent();
		self::assertSame(1, $notifications);
	}

	public function testLeavesContentAddedAfterStartupFilterUntouched() : void{
		$inventory = CreativeInventory::getInstance();
		$inventory->removeEducationEditionContent();
		$lateGroup = new CreativeGroup(new Translatable("itemGroup.name.products"), VanillaItems::APPLE());
		$inventory->add(VanillaItems::DIAMOND(), group: $lateGroup);

		$lateIndex = array_key_last($inventory->getAllEntries());
		self::assertIsInt($lateIndex);
		$lateEntry = $inventory->getEntry($lateIndex);
		self::assertNotNull($lateEntry);
		$inventory->removeEducationEditionContent();
		self::assertSame($lateEntry, $inventory->getEntry($lateIndex));
	}

	public function testFirstCreativePacketAfterFilteringUsesUpdatedInventory() : void{
		$inventory = CreativeInventory::getInstance();
		$server = $this->createMock(Server::class);
		$server->method("isLanguageForced")->willReturn(true);
		$player = new class($server) extends Player{
			public function __construct(private Server $testServer){}
			public function __destruct(){}
			public function getServer() : Server{ return $this->testServer; }
			public function getLanguage() : Language{ return new Language("eng"); }
		};
		$session = $this->createMock(NetworkSession::class);
		$session->method("getPlayer")->willReturn($player);
		$session->method("getTypeConverter")->willReturn(TypeConverter::getInstance(ProtocolInfo::PROTOCOL_1_26_0));
		$cache = CreativeInventoryCache::getInstance(ProtocolInfo::PROTOCOL_1_26_0);

		$unfilteredPacket = $cache->buildPacket($inventory, $session);
		$inventory->removeEducationEditionContent();
		$firstPlayerPacket = $cache->buildPacket($inventory, $session);

		self::assertCount(count($unfilteredPacket->getItems()) - 205, $firstPlayerPacket->getItems());
		self::assertCount(count($inventory->getAllEntries()), $firstPlayerPacket->getItems());
	}
}
