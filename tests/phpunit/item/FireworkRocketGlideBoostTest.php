<?php

declare(strict_types=1);

namespace pocketmine\item;

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
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class FireworkRocketGlideBoostTest extends TestCase{

	private function createTestPlayer(bool $creative = false) : Player{
		$session = $this->getMockBuilder(NetworkSession::class)->disableOriginalConstructor()->getMock();
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getName", "getUniqueId", "isSneaking", "isCreative", "isSpectator", "getViewers", "canInteract", "broadcastSound", "broadcastAnimation", "getNetworkSession", "isConnected", "broadcastMovement", "sendPosition", "hasFiniteResources"])
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
		$location = new Location(0.0, 50.0, 0.0, $world, 0.0, 0.0);
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

		$player->method("getName")->willReturn("RocketTester");
		$player->method("getUniqueId")->willReturn(Uuid::uuid4());
		$player->method("isSneaking")->willReturn(false);
		$player->method("isCreative")->willReturn($creative);
		$player->method("hasFiniteResources")->willReturn(!$creative);
		$player->method("isSpectator")->willReturn(false);
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isConnected")->willReturn(false);

		return $player;
	}

	public function testBoostWhileGlidingAcceleratesPlayer() : void{
		$player = $this->createTestPlayer(false);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$rocket = VanillaItems::FIREWORK_ROCKET();
		$rocket->setCount(3);

		$returnedItems = [];
		$result = $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertSame(2, $rocket->getCount());
		self::assertGreaterThan(0.0, $player->getMotion()->length());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testBoostFailsWhenNotGliding() : void{
		$player = $this->createTestPlayer(false);
		self::assertFalse($player->isGliding());

		$rocket = VanillaItems::FIREWORK_ROCKET();
		$rocket->setCount(3);

		$returnedItems = [];
		$result = $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems);

		self::assertSame(ItemUseResult::FAIL, $result);
		self::assertSame(3, $rocket->getCount());
		self::assertSame(0.0, $player->getMotion()->length());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}

	public function testCreativeModeDoesNotConsumeRocket() : void{
		$player = $this->createTestPlayer(true);
		$player->getArmorInventory()->setChestplate(VanillaItems::ELYTRA());
		$player->toggleGlide(true);
		self::assertTrue($player->isGliding());

		$rocket = VanillaItems::FIREWORK_ROCKET();
		$rocket->setCount(3);

		$returnedItems = [];
		$result = $rocket->onClickAir($player, $player->getDirectionVector(), $returnedItems);

		self::assertSame(ItemUseResult::SUCCESS, $result);
		self::assertSame(3, $rocket->getCount());
		self::assertGreaterThan(0.0, $player->getMotion()->length());

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
	}
}
