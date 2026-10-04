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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class PlayerKineticDamageTest extends TestCase{

	private function createTestPlayer() : Player{
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

		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$location = new Location(0.0, 10.0, 0.0, $world, 0.0, 0.0);
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
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($player, new PlayerEnderInventory($player));
		(new ReflectionProperty(Player::class, "cursorInventory"))->setValue($player, new PlayerCursorInventory($player));
		(new ReflectionProperty(Player::class, "craftingGrid"))->setValue($player, new PlayerCraftingInventory($player));
		(new ReflectionProperty(Human::class, "hungerManager"))->setValue($player, new HungerManager($player));
		(new ReflectionProperty(Human::class, "xpManager"))->setValue($player, new ExperienceManager($player));
		(new ReflectionProperty(Entity::class, "onGround"))->setValue($player, false);

		$player->method("getName")->willReturn("KineticTester");
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testKineticDamageAppliedOnHighSpeedCollision() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		// Collide with speed 1.5 (threshold is 0.75) -> deals floor(1.5 * 10) = 15 damage
		$damaged = $player->checkGlidingKineticDamage(1.5, true);
		self::assertTrue($damaged);
		self::assertSame($initialHealth - 15.0, $player->getHealth());
		self::assertFalse($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testNoKineticDamageBelowThreshold() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		// Speed 0.5 is below threshold 0.75
		$damaged = $player->checkGlidingKineticDamage(0.5, true);
		self::assertFalse($damaged);
		self::assertSame($initialHealth, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testNoKineticDamageWithoutCollision() : void{
		$player = $this->createTestPlayer();
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$initialHealth = $player->getHealth();

		// High speed 2.0 but no horizontal collision
		$damaged = $player->checkGlidingKineticDamage(2.0, false);
		self::assertFalse($damaged);
		self::assertSame($initialHealth, $player->getHealth());
		self::assertTrue($player->isGliding());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}
}
