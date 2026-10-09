<?php

declare(strict_types=1);

namespace pocketmine\tests\entity;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat;
use pocketmine\item\BoatType;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputVehicleInfo;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use ReflectionProperty;
use function array_diff;
use function array_values;

class BoatPlayerInputTest extends TestCase{
	private function createWorld() : World{
		$world = $this->createMock(World::class);
		$world->method("isLoaded")->willReturn(true);
		$world->method("getBlockAt")->willReturn(\pocketmine\block\VanillaBlocks::AIR());
		$server = $this->createMock(Server::class);
		$server->method("getTick")->willReturn(100);
		$world->method("getServer")->willReturn($server);
		return $world;
	}

	private function createPlayer(World $world, ?callable $movementCallback = null, bool $realTeleport = false) : Player{
		$methods = ["isConnected", "isAlive", "getNetworkSession", "teleport", "onDispose", "hasReceivedChunk", "handleMovementInput", "toggleSneak", "isGliding", "sendData", "sendPosition", "broadcastMovement", "spawnToAll", "removeCurrentWindow", "stopSleep", "discardGlideBoosts"];
		if($realTeleport){
			$methods = array_values(array_diff($methods, ["teleport"]));
		}
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
		$player->method("isConnected")->willReturn(false);
		$player->method("hasReceivedChunk")->willReturn(true);
		$player->method("isAlive")->willReturn(true);
		$player->method("toggleSneak")->willReturn(true);
		$player->method("isGliding")->willReturn(false);
		if(!$realTeleport){
			$player->method("teleport")->willReturn(true);
		}
		if($movementCallback !== null){
			$player->method("handleMovementInput")->willReturnCallback($movementCallback);
		}
		$session = $this->createMock(NetworkSession::class);
		$session->method("getInvManager")->willReturn($this->createMock(InventoryManager::class));
		$player->method("getNetworkSession")->willReturn($session);
		(new ReflectionProperty(Entity::class, "id"))->setValue($player, 10000);
		(new ReflectionProperty(Entity::class, "location"))->setValue($player, new Location(0, 10, 0, $world, 0, 0));
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($player, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($player, new EntitySizeInfo(1.8, 0.6));
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));
		(new ReflectionProperty(Player::class, "craftingGrid"))->setValue($player, new \pocketmine\inventory\PlayerCraftingInventory($player));
		(new ReflectionProperty(Player::class, "cursorInventory"))->setValue($player, new \pocketmine\inventory\PlayerCursorInventory($player));
		return $player;
	}

	private function createHandler(Player $player) : InGamePacketHandler{
		$handler = (new \ReflectionClass(InGamePacketHandler::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(InGamePacketHandler::class, "player"))->setValue($handler, $player);
		(new ReflectionProperty(InGamePacketHandler::class, "forceMoveSync"))->setValue($handler, false);
		return $handler;
	}

	private function createInputPacket(int $tick, ?PlayerAuthInputVehicleInfo $vehicleInfo = null, bool $predictedVehicle = false) : PlayerAuthInputPacket{
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		$flags->set(PlayerAuthInputFlags::IN_CLIENT_PREDICTED_VEHICLE, $predictedVehicle);
		$flags->set(PlayerAuthInputFlags::PADDLING_LEFT, true);
		return PlayerAuthInputPacket::create(new Vector3($tick / 10, 11.62, 0), 0, 0, 0, 0, 1, $flags, 1, 0, 1, null, new Vector2(0, 0), $tick, Vector3::zero(), null, null, null, $vehicleInfo, 0, 1, Vector3::zero(), new Vector2(0, 1));
	}

	public function testBoatInputWithoutVehicleRotationIsStillForwarded() : void{
		$world = $this->createWorld();
		$player = $this->createPlayer($world);
		$boat = new Boat(new Location(0, 10, 0, $world, 0, 0), BoatType::OAK);
		self::assertTrue($boat->addRider($player));
		$vehicleInfo = new PlayerAuthInputVehicleInfo(null, $boat->getId());

		try{
			self::assertTrue($this->createHandler($player)->handlePlayerAuthInput($this->createInputPacket(100, $vehicleInfo, true)));
			self::assertSame([0.0, 1.0, true, false], (new ReflectionProperty(Boat::class, "riderInput"))->getValue($boat));
			self::assertTrue($boat->hasMovementUpdate());
		}finally{
			$boat->ejectRiders();
			$boat->close();
		}
	}

	public function testPlayerWorldTransferImmediatelyDetachesBoat() : void{
		$source = $this->createWorld();
		$destination = $this->createWorld();
		$player = $this->createPlayer($source, realTeleport: true);
		$boat = new Boat(new Location(0, 10, 0, $source, 0, 0), BoatType::OAK);
		self::assertTrue($boat->addRider($player));
		self::assertSame($boat, Boat::getVehicleOf($player));

		try{
			self::assertTrue($player->teleport(new Position(100, 10, 0, $destination)));
			self::assertSame($destination, $player->getWorld());
			self::assertNull(Boat::getVehicleOf($player));
			self::assertFalse($boat->isRider($player));
		}finally{
			$boat->close();
		}
	}
}
