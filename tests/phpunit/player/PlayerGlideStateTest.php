<?php

declare(strict_types=1);

namespace pocketmine\player;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Human;
use pocketmine\entity\HungerManager;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Elytra;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class PlayerGlideStateTest extends TestCase{

	private function createTestPlayer(?World $world = null, ?Vector3 $pos = null) : Player{
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->getMock();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "getNetworkSession", "isConnected", "broadcastMovement", "sendPosition"])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 100);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Player::class, "networkSession"))->setValue($player, $session);
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($player, new EffectManager($player));
		(new ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, new AttributeMap());
		(new ReflectionClass(Player::class))->getMethod("addAttributes")->invoke($player);
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($player, new ArmorInventory($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($player, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($player, 1.0);

		if($world === null){
			$world = $this->createMock(World::class);
			$world->method("isLoaded")->willReturn(true);
		}
		$location = new Location($pos !== null ? $pos->x : 0.0, $pos !== null ? $pos->y : 10.0, $pos !== null ? $pos->z : 0.0, $world, 0.0, 0.0);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, $location);
		(new ReflectionProperty(Entity::class, "boundingBox"))->setValue($player, new AxisAlignedBB(
			$location->x - 0.3,
			$location->y,
			$location->z - 0.3,
			$location->x + 0.3,
			$location->y + 1.8,
			$location->z + 0.3
		));
		(new ReflectionProperty(Entity::class, "motion"))->setValue($player, Vector3::zero());
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, new PlayerInventory($player));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($player, new \pocketmine\inventory\PlayerEnderInventory($player));
		(new ReflectionProperty(Player::class, "cursorInventory"))->setValue($player, new \pocketmine\inventory\PlayerCursorInventory($player));
		(new ReflectionProperty(Player::class, "craftingGrid"))->setValue($player, new \pocketmine\inventory\PlayerCraftingInventory($player));
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
		(new ReflectionProperty(Human::class, "xpManager"))->setValue($player, new ExperienceManager($player));
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);

		$player->method("getName")->willReturn("GlideTester");
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testCannotGlideWithoutElytra() : void{
		$player = $this->createTestPlayer();
		self::assertFalse($player->toggleGlide(true));
		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testCannotGlideWithBrokenElytra() : void{
		$player = $this->createTestPlayer();
		$elytra = VanillaItems::ELYTRA();
		$elytra->setDamage(431); // 1 durability remaining = broken
		self::assertTrue($elytra->isBroken());

		$player->getArmorInventory()->setChestplate($elytra);

		self::assertFalse($player->toggleGlide(true));
		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testCannotGlideWhileOnGround() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());

		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, true);

		self::assertFalse($player->toggleGlide(true));
		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideActivationWhenAirborneWithElytra() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);

		self::assertTrue($player->toggleGlide(true));
		self::assertTrue($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testFallDamageNegatedWhileGliding() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);

		$calcMethod = (new ReflectionClass(Player::class))->getMethod("calculateFallDamage");

		// Normal fall damage when not gliding
		$damageNotGliding = $calcMethod->invoke($player, 10.0);
		self::assertGreaterThan(0.0, $damageNotGliding);

		// Negated when gliding
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());
		$damageGliding = $calcMethod->invoke($player, 10.0);
		self::assertSame(0.0, $damageGliding);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideTickDurabilityDegradation() : void{
		$player = $this->createTestPlayer();
		$elytra = VanillaItems::ELYTRA();
		$player->getArmorInventory()->setChestplate($elytra);
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		// Tick 20 times (1 second of flight)
		for($i = 0; $i < 20; ++$i){
			(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 1);
		}

		/** @var Elytra $chest */
		$chest = $player->getArmorInventory()->getChestplate();
		self::assertInstanceOf(Elytra::class, $chest);
		self::assertSame(1, $chest->getDamage());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testGlideAutoTerminatesOnGround() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		// Land on ground
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, true);
		(new ReflectionClass(Player::class))->getMethod("entityBaseTick")->invoke($player, 1);

		self::assertFalse($player->isGliding());
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}
}
