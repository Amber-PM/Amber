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
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeOptionalStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\player\Player;
use ReflectionProperty;
use function str_repeat;

final class ItemStackRequestExecutorTest extends TestCase{

	/** @return Player&\PHPUnit\Framework\MockObject\MockObject */
	private function createMockPlayer() : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getCurrentWindow"])
			->getMock();

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	public function testOversizedAnvilRenameRejectedBeforeAccessingWindow() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::never())->method("getCurrentWindow");

		$inventoryManager = $this->createMock(InventoryManager::class);

		$oversizedRename = str_repeat("a", 257);
		$action = new CraftRecipeOptionalStackRequestAction(1234, 0);
		$request = new ItemStackRequest(1, [$action], [$oversizedRename], 0);

		$executor = new ItemStackRequestExecutor($player, $inventoryManager, $request);

		$this->expectException(ItemStackRequestProcessException::class);
		$this->expectExceptionMessage("Anvil rename is too long (257 bytes)");
		$executor->generateInventoryTransaction();
	}

	public function testAllowedAnvilRenameLengthProceedsToWindowCheck() : void{
		$player = $this->createMockPlayer();
		$player->expects(self::once())->method("getCurrentWindow")->willReturn(null);

		$inventoryManager = $this->createMock(InventoryManager::class);

		$validRename = str_repeat("a", 256);
		$action = new CraftRecipeOptionalStackRequestAction(1234, 0);
		$request = new ItemStackRequest(1, [$action], [$validRename], 0);

		$executor = new ItemStackRequestExecutor($player, $inventoryManager, $request);

		$this->expectException(ItemStackRequestProcessException::class);
		$this->expectExceptionMessage("Player's current window is not an anvil inventory");
		$executor->generateInventoryTransaction();
	}
}
