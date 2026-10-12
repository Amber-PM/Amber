<?php

/*
 *
 *     _             _
 *    / \   _ __ ___ | |__   ___ _ __
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |
 * /_/   \_\_| |_| |_|_.__/ \___|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\handler;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\ItemInteractionData;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;

final class InGamePacketHandlerUseItemTest extends TestCase{

	/** @return Player&\PHPUnit\Framework\MockObject\MockObject */
	private function createMockPlayer() : Player{
		$player = $this->createMock(Player::class);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	/**
	 * @param NetworkInventoryAction[] $actions
	 */
	private function createUseItemTransactionData(
		int $handSlot = 0,
		int $hotbarSlot = 0,
		int $actionType = UseItemTransactionData::ACTION_CLICK_AIR,
		array $actions = []
	) : UseItemTransactionData{
		$data = new UseItemTransactionData();
		(new ReflectionProperty(UseItemTransactionData::class, "handSlot"))->setValue($data, $handSlot);
		(new ReflectionProperty(UseItemTransactionData::class, "hotbarSlot"))->setValue($data, $hotbarSlot);
		(new ReflectionProperty(UseItemTransactionData::class, "actionType"))->setValue($data, $actionType);
		(new ReflectionProperty(UseItemTransactionData::class, "actions"))->setValue($data, $actions);

		return $data;
	}

	private function createAuthInputPacket(UseItemTransactionData $trData) : PlayerAuthInputPacket{
		$interactionData = new ItemInteractionData(1, null, $trData);

		return PlayerAuthInputPacket::create(
			new Vector3(0, 0, 0),
			0.0,
			0.0,
			0.0,
			0.0,
			0.0,
			new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS),
			0,
			0,
			0,
			null,
			new Vector2(0, 0),
			1,
			new Vector3(0, 0, 0),
			$interactionData,
			null,
			null,
			null,
			0.0,
			0.0,
			new Vector3(0, 0, 0),
			new Vector2(0, 0)
		);
	}

	private function invokeHandleUseItemTransaction(InGamePacketHandler $handler, UseItemTransactionData $data) : bool{
		$method = new ReflectionMethod(InGamePacketHandler::class, "handleUseItemTransaction");

		return $method->invoke($handler, $data);
	}

	public function testHandSlotZeroSelectsHotbarAndExecutesItemUse() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::once())
			->method("selectHotbarSlot")
			->with(3)
			->willReturn(true);
		$player->method("isUsingItem")->willReturn(false);
		$player->expects(self::once())->method("useHeldItem")->willReturn(true);

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));
		$inventoryManager = $this->createMock(InventoryManager::class);

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);
		$data = $this->createUseItemTransactionData(handSlot: 0, hotbarSlot: 3);

		$result = $this->invokeHandleUseItemTransaction($handler, $data);
		self::assertTrue($result);
	}

	public function testHandSlotOneRejectsWithoutCallingSelectHotbarSlot() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("selectHotbarSlot");
		$player->expects(self::never())->method("useHeldItem");

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));
		$inventoryManager = $this->createMock(InventoryManager::class);

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);
		$data = $this->createUseItemTransactionData(handSlot: 1, hotbarSlot: 3);

		$result = $this->invokeHandleUseItemTransaction($handler, $data);
		self::assertFalse($result);
	}

	public function testMalformedHandSlotRejectsWithoutCallingSelectHotbarSlot() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("selectHotbarSlot");
		$player->expects(self::never())->method("useHeldItem");

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));
		$inventoryManager = $this->createMock(InventoryManager::class);

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);

		foreach([2, 255, -1] as $invalidHandSlot){
			$data = $this->createUseItemTransactionData(handSlot: $invalidHandSlot, hotbarSlot: 2);
			$result = $this->invokeHandleUseItemTransaction($handler, $data);
			self::assertFalse($result);
		}
	}

	public function testPlayerAuthInputReconcilesPredictionsAndResetsRequestIdWhenTransactionRejected() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("selectHotbarSlot");

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));

		$inventoryManager = $this->createMock(InventoryManager::class);
		$matcher = self::exactly(2);
		$inventoryManager->expects($matcher)
			->method("setCurrentItemStackRequestId")
			->willReturnCallback(function(?int $id) use ($matcher) : void{
				match($matcher->numberOfInvocations()){
					1 => self::assertSame(1, $id),
					2 => self::assertNull($id)
				};
			});
		$inventoryManager->expects(self::once())
			->method("addRawPredictedSlotChanges")
			->with([]);
		$inventoryManager->expects(self::once())
			->method("syncMismatchedPredictedSlotChanges");

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);
		$packet = $this->createAuthInputPacket($this->createUseItemTransactionData(handSlot: 1, hotbarSlot: 4));

		$result = $handler->handlePlayerAuthInput($packet);
		self::assertFalse($result);
	}

	public function testPlayerAuthInputReconcilesPredictionsWhenTransactionSucceeds() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::once())
			->method("selectHotbarSlot")
			->with(0)
			->willReturn(true);
		$player->method("isUsingItem")->willReturn(false);
		$player->method("useHeldItem")->willReturn(true);

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));

		$inventoryManager = $this->createMock(InventoryManager::class);
		$inventoryManager->expects(self::once())
			->method("syncMismatchedPredictedSlotChanges");

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);
		$packet = $this->createAuthInputPacket($this->createUseItemTransactionData(handSlot: 0, hotbarSlot: 0));

		$result = $handler->handlePlayerAuthInput($packet);
		self::assertTrue($result);
	}

	public function testInventoryTransactionReconcilesPredictionsOnRejection() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("selectHotbarSlot");

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));

		$inventoryManager = $this->createMock(InventoryManager::class);
		$matcher = self::exactly(2);
		$inventoryManager->expects($matcher)
			->method("setCurrentItemStackRequestId")
			->willReturnCallback(function(?int $id) use ($matcher) : void{
				match($matcher->numberOfInvocations()){
					1 => self::assertSame(42, $id),
					2 => self::assertNull($id)
				};
			});
		$inventoryManager->expects(self::once())
			->method("addRawPredictedSlotChanges")
			->with([]);
		$inventoryManager->expects(self::once())
			->method("syncMismatchedPredictedSlotChanges");

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);

		$packet = new InventoryTransactionPacket();
		$packet->requestId = 42;
		$packet->trData = $this->createUseItemTransactionData(handSlot: 1, hotbarSlot: 1);

		$result = $handler->handleInventoryTransaction($packet);
		self::assertFalse($result);
	}

	public function testPlayerAuthInputReconcilesPredictionsAndResetsRequestIdWhenExceptionThrown() : void{
		$player = $this->createMockPlayer();
		$player->method("selectHotbarSlot")->willThrowException(new \RuntimeException("Simulated failure during interaction"));

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));

		$inventoryManager = $this->createMock(InventoryManager::class);
		$matcher = self::exactly(2);
		$inventoryManager->expects($matcher)
			->method("setCurrentItemStackRequestId")
			->willReturnCallback(function(?int $id) use ($matcher) : void{
				match($matcher->numberOfInvocations()){
					1 => self::assertSame(1, $id),
					2 => self::assertNull($id)
				};
			});
		$inventoryManager->expects(self::once())
			->method("addRawPredictedSlotChanges")
			->with([]);
		$inventoryManager->expects(self::once())
			->method("syncMismatchedPredictedSlotChanges");

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);
		$packet = $this->createAuthInputPacket($this->createUseItemTransactionData(handSlot: 0, hotbarSlot: 0));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Simulated failure during interaction");
		$handler->handlePlayerAuthInput($packet);
	}

	public function testPlayerAuthInputReconcilesRealPredictionPayloadOnRejection() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("selectHotbarSlot");

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));

		$action = new NetworkInventoryAction();
		$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
		$action->windowId = ContainerIds::INVENTORY;
		$action->inventorySlot = 2;
		$action->oldItem = new ItemStackWrapper(0, ItemStack::null());
		$action->newItem = new ItemStackWrapper(1, new ItemStack(1, 0, 1, 0, "", [], []));

		$inventoryManager = $this->createMock(InventoryManager::class);
		$matcher = self::exactly(2);
		$inventoryManager->expects($matcher)
			->method("setCurrentItemStackRequestId")
			->willReturnCallback(function(?int $id) use ($matcher) : void{
				match($matcher->numberOfInvocations()){
					1 => self::assertSame(1, $id),
					2 => self::assertNull($id)
				};
			});
		$inventoryManager->expects(self::once())
			->method("addRawPredictedSlotChanges")
			->with([$action]);
		$inventoryManager->expects(self::once())
			->method("syncMismatchedPredictedSlotChanges");

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);
		$packet = $this->createAuthInputPacket($this->createUseItemTransactionData(handSlot: 1, hotbarSlot: 2, actions: [$action]));

		$result = $handler->handlePlayerAuthInput($packet);
		self::assertFalse($result);
	}

	public function testInventoryTransactionReconcilesRealPredictionPayloadOnRejection() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("selectHotbarSlot");

		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn($this->createMock(Logger::class));

		$action = new NetworkInventoryAction();
		$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
		$action->windowId = ContainerIds::INVENTORY;
		$action->inventorySlot = 3;
		$action->oldItem = new ItemStackWrapper(0, ItemStack::null());
		$action->newItem = new ItemStackWrapper(2, new ItemStack(1, 0, 1, 0, "", [], []));

		$inventoryManager = $this->createMock(InventoryManager::class);
		$matcher = self::exactly(2);
		$inventoryManager->expects($matcher)
			->method("setCurrentItemStackRequestId")
			->willReturnCallback(function(?int $id) use ($matcher) : void{
				match($matcher->numberOfInvocations()){
					1 => self::assertSame(99, $id),
					2 => self::assertNull($id)
				};
			});
		$inventoryManager->expects(self::once())
			->method("addRawPredictedSlotChanges")
			->with([$action]);
		$inventoryManager->expects(self::once())
			->method("syncMismatchedPredictedSlotChanges");

		$handler = new InGamePacketHandler($player, $session, $inventoryManager);

		$packet = new InventoryTransactionPacket();
		$packet->requestId = 99;
		$packet->trData = $this->createUseItemTransactionData(handSlot: 1, hotbarSlot: 3, actions: [$action]);

		$result = $handler->handleInventoryTransaction($packet);
		self::assertFalse($result);
	}
}
