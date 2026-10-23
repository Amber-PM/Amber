<?php

declare(strict_types=1);

namespace pocketmine\tests\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\ArmorStand;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\tests\support\entity\NativeEntityTestEnvironment;
use pocketmine\timings\Timings;

require_once __DIR__ . "/../../support/entity/NativeEntityTestEnvironment.php";

final class ArmorStandNativeLifecycleTest extends TestCase{

	private NativeEntityTestEnvironment $environment;

	protected function setUp() : void{
		Timings::init();
		$this->environment = NativeEntityTestEnvironment::create($this);
		$this->environment->getWorld()->setBlockAt(8, 9, 8, VanillaBlocks::STONE());
	}

	protected function tearDown() : void{
		$this->environment->close();
	}

	public function testWobbleExpiresThroughNativeTicks() : void{
		$stand = new ArmorStand(new Location(8.5, 10, 8.5, $this->environment->getWorld(), 0, 0));
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods(["isSpectator", "isCreative", "getInventory"])->getMock();
		$player->method("isSpectator")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$inventory = $this->createMock(\pocketmine\inventory\PlayerInventory::class);
		$inventory->method("getItemInHand")->willReturn(VanillaItems::AIR());
		$player->method("getInventory")->willReturn($inventory);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		$event = $this->getMockBuilder(EntityDamageByEntityEvent::class)->disableOriginalConstructor()->onlyMethods(["getDamager", "call"])->getMock();
		$event->method("getDamager")->willReturn($player);
		(new \ReflectionProperty(EntityDamageEvent::class, "cause"))->setValue($event, EntityDamageEvent::CAUSE_ENTITY_ATTACK);
		$stand->attack($event);
		self::assertSame(10, $stand->getNetworkProperties()->getAll()[EntityMetadataProperties::HURT_TIME]->getValue());
		$this->environment->advanceTicks(20);
		self::assertSame(0, $stand->getNetworkProperties()->getAll()[EntityMetadataProperties::HURT_TIME]->getValue());
		self::assertTrue($stand->isAlive());
	}

	public function testRepeatedDeathDoesNotDuplicateDrops() : void{
		$stand = new ArmorStand(new Location(8.5, 10, 8.5, $this->environment->getWorld(), 0, 0));
		$stand->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET());
		$stand->setMainHandItem(VanillaItems::DIAMOND());
		$this->environment->getWorld()->expects($this->exactly(3))->method("dropItem");
		$stand->kill();
		$stand->kill();
		self::assertFalse($stand->isAlive());
		self::assertTrue($stand->isFlaggedForDespawn());
	}
}
