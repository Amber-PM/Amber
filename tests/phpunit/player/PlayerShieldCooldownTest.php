<?php

declare(strict_types=1);

namespace pocketmine\player;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\Server;
use pocketmine\timings\Timings;
use ReflectionProperty;

class PlayerShieldCooldownTest extends TestCase{

	public function testShieldResumesBlockingOnTickWithoutNewInput() : void{
		$serverTick = 1;
		$server = $this->createMock(Server::class);
		$server->method("getTick")->willReturnCallback(static function() use (&$serverTick) : int{
			return $serverTick;
		});
		$session = $this->createMock(NetworkSession::class);
		$session->expects(self::once())->method("onItemCooldownChanged")->with(VanillaItems::SHIELD(), 100);
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getNetworkSession", "isAlive", "isCreative", "isSpectator", "isUsingItem", "processMostRecentMovements", "entityBaseTick", "checkNearEntities"])
			->getMock();
		$player->method("getNetworkSession")->willReturn($session);
		$player->method("isAlive")->willReturn(true);
		$player->method("isCreative")->willReturn(false);
		$player->method("isSpectator")->willReturn(false);
		$player->method("isUsingItem")->willReturn(false);
		$player->method("entityBaseTick")->willReturn(false);
		(new ReflectionProperty(Player::class, "server"))->setValue($player, $server);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(\Logger::class));
		(new ReflectionProperty(Player::class, "spawned"))->setValue($player, true);
		(new ReflectionProperty(Entity::class, "justCreated"))->setValue($player, false);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Entity::class, "lastUpdate"))->setValue($player, 1);
		(new ReflectionProperty(Entity::class, "timings"))->setValue($player, Timings::getEntityTimings($player));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Human::class, "inventory"))->setValue($player, new PlayerInventory($player));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));
		(new ReflectionProperty(Living::class, "sneaking"))->setValue($player, true);
		$player->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		$player->updateBlockingState();
		self::assertTrue($player->isBlocking());
		$player->disableShield();
		self::assertFalse($player->isBlocking());

		$serverTick = 100;
		$player->onUpdate($serverTick);
		self::assertFalse($player->isBlocking());
		$serverTick = 101;
		$player->onUpdate($serverTick);
		self::assertTrue($player->isSneaking());
		self::assertTrue($player->isBlocking());
	}
}
