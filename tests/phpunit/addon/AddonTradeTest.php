<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\trade\TradeOffer;
use pocketmine\addon\entity\trade\TradeTable;
use pocketmine\addon\entity\trade\TradeTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\VanillaItems;
use Symfony\Component\Filesystem\Path;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class AddonTradeTest extends TestCase{
	private string $root;

	protected function setUp() : void{
		$this->root = Path::join(sys_get_temp_dir(), uniqid("amber_trade_", true));
		mkdir(Path::join($this->root, "trading"), 0777, true);
	}

	protected function tearDown() : void{
		@unlink(Path::join($this->root, "trading", "t.json"));
		if(is_dir(Path::join($this->root, "trading"))){
			rmdir(Path::join($this->root, "trading"));
		}
		rmdir($this->root);
	}

	/** @param mixed[] $json */
	private function table(array $json) : TradeTable{
		file_put_contents(Path::join($this->root, "trading", "t.json"), json_encode($json));
		$table = TradeTable::load("trading/t.json", [$this->root]);
		self::assertNotNull($table);
		return $table;
	}

	private function offer(int $uses = 0) : TradeOffer{
		return new TradeOffer(1, VanillaItems::EMERALD()->setCount(2), VanillaItems::STICK()->setCount(1), VanillaItems::BREAD()->setCount(3), 0, 4, 2, true, $uses);
	}

	public function testMissingTableIsNull() : void{
		self::assertNull(TradeTable::load("trading/none.json", [$this->root]));
	}

	public function testRollPicksPerGroupAndCountsNetIds() : void{
		$table = $this->table(["tiers" => [
			["total_exp_required" => 0, "groups" => [
				["num_to_select" => 1, "trades" => [
					["wants" => [["item" => "minecraft:emerald", "quantity" => 2]], "gives" => [["item" => "minecraft:bread", "quantity" => ["min" => 3, "max" => 5]]]],
					["wants" => [["item" => "minecraft:emerald"]], "gives" => [["item" => "minecraft:apple"]]],
				]],
			]],
			["total_exp_required" => 10, "trades" => [
				["wants" => [["item" => "emerald", "quantity" => 5]], "gives" => [["choice" => [["item" => "minecraft:diamond"], ["item" => "minecraft:gold_ingot"]]]], "max_uses" => 3],
				["wants" => [["item" => "minecraft:not_a_real_item"]], "gives" => [["item" => "minecraft:apple"]]],
			]],
		]]);
		self::assertSame([0, 10], $table->getTierExperience());
		$offers = $table->roll(100);
		self::assertCount(2, $offers, "one from the first group, one valid trade from the second tier");
		self::assertSame([100, 101], [$offers[0]->netId, $offers[1]->netId]);
		self::assertSame(0, $offers[0]->tier);
		self::assertSame(1, $offers[1]->tier);
		self::assertSame(3, $offers[1]->maxUses);
		self::assertSame(5, $offers[1]->wantsA->getCount());
		self::assertContains($offers[1]->gives->getTypeId(), [VanillaItems::DIAMOND()->getTypeId(), VanillaItems::GOLD_INGOT()->getTypeId()]);
		if($offers[0]->gives->getTypeId() === VanillaItems::BREAD()->getTypeId()){
			self::assertGreaterThanOrEqual(3, $offers[0]->gives->getCount());
			self::assertLessThanOrEqual(5, $offers[0]->gives->getCount());
		}
	}

	public function testOfferSurvivesSaveAndLoad() : void{
		$offer = $this->offer(2);
		$loaded = TradeOffer::load($offer->save(), 7);
		self::assertNotNull($loaded);
		self::assertSame(7, $loaded->netId);
		self::assertSame(2, $loaded->uses);
		self::assertSame(4, $loaded->maxUses);
		self::assertTrue($loaded->wantsA->equalsExact($offer->wantsA));
		self::assertNotNull($loaded->wantsB);
		self::assertTrue($loaded->gives->equalsExact($offer->gives));
	}

	public function testCheckAcceptsExactPayment() : void{
		$this->expectNotToPerformAssertions();
		TradeTransaction::check($this->offer(), 2, [VanillaItems::EMERALD()->setCount(4), VanillaItems::STICK()->setCount(2)], [VanillaItems::BREAD()->setCount(6)]);
	}

	public function testCheckRejectsUnderpayment() : void{
		$this->expectException(TransactionValidationException::class);
		TradeTransaction::check($this->offer(), 1, [VanillaItems::EMERALD()->setCount(1), VanillaItems::STICK()], [VanillaItems::BREAD()->setCount(3)]);
	}

	public function testCheckRejectsMissingSecondIngredient() : void{
		$this->expectException(TransactionValidationException::class);
		TradeTransaction::check($this->offer(), 1, [VanillaItems::EMERALD()->setCount(2)], [VanillaItems::BREAD()->setCount(3)]);
	}

	public function testCheckRejectsWrongResult() : void{
		$this->expectException(TransactionValidationException::class);
		TradeTransaction::check($this->offer(), 1, [VanillaItems::EMERALD()->setCount(2), VanillaItems::STICK()], [VanillaItems::BREAD()->setCount(4)]);
	}

	public function testCheckRejectsUsedUpOffer() : void{
		$this->expectException(TransactionValidationException::class);
		TradeTransaction::check($this->offer(3), 2, [VanillaItems::EMERALD()->setCount(4), VanillaItems::STICK()->setCount(2)], [VanillaItems::BREAD()->setCount(6)]);
	}
}
