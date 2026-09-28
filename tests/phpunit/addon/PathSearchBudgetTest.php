<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\ai\Pathfinder;
use ReflectionProperty;

final class PathSearchBudgetTest extends TestCase{
	protected function setUp() : void{
		foreach(['budgetTick' => -1, 'budgetUsed' => 0, 'workUsed' => 0, 'waiting' => [], 'waitQueue' => null, 'reserved' => [], 'reservationAttempted' => [], 'outstanding' => []] as $name => $value){
			(new ReflectionProperty(Pathfinder::class, $name))->setValue(null, $value);
		}
	}

	public function testWorstCaseAggregateWorkIsBoundedAndDeniedRequesterStaysQueued() : void{
		for($id = 1; $id <= 4; ++$id){
			self::assertTrue(Pathfinder::takeBudget(1000, $id));
			Pathfinder::completeBudget(1000, $id, 400);
		}
		self::assertFalse(Pathfinder::takeBudget(1000, 5));
		self::assertFalse(Pathfinder::takeBudget(1000, 5), 'Retry cannot bypass spent work');
		self::assertSame(Pathfinder::WORK_NODES_PER_TICK, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
		for($id = 6; $id <= 8; ++$id){
			self::assertTrue(Pathfinder::takeBudget(1001, $id));
			Pathfinder::completeBudget(1001, $id, 400);
		}
		self::assertFalse(Pathfinder::takeBudget(1001, 9), 'Fresh requests cannot steal the queued requester\'s work reservation');
		self::assertTrue(Pathfinder::takeBudget(1001, 5));
	}

	public function testCheapSearchesRefundUnusedWorkAndRetainCountCap() : void{
		for($id = 1; $id <= Pathfinder::SEARCHES_PER_TICK; ++$id){
			self::assertTrue(Pathfinder::takeBudget(2000, $id));
			Pathfinder::completeBudget(2000, $id, 30);
		}
		self::assertSame(480, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
		self::assertFalse(Pathfinder::takeBudget(2000, 17));
	}

	public function testOutstandingReservationCannotBeReusedAndNextTickResetsWork() : void{
		self::assertTrue(Pathfinder::takeBudget(3000, 1));
		self::assertFalse(Pathfinder::takeBudget(3000, 1));
		self::assertTrue(Pathfinder::takeBudget(3001, 2));
		self::assertSame(400, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
		Pathfinder::completeBudget(3000, 1, 0); //late completion must not modify the new tick
		self::assertSame(400, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
	}

	public function testFixedOrderRequestersEventuallyReceiveBudgetWithoutExceedingCap() : void{
		$served = array_fill_keys(range(1, 161), 0);
		$nextAttempt = array_fill_keys(range(1, 161), 4000);
		for($tick = 4000; $tick < 4080; ++$tick){
			$grants = 0;
			foreach(array_keys($served) as $id){
				if($tick < $nextAttempt[$id]){ continue; }
				if(Pathfinder::takeBudget($tick, $id)){
					$served[$id]++;
					$nextAttempt[$id] = $tick + 10;
					Pathfinder::completeBudget($tick, $id, 400);
					$grants++;
				}
			}
			self::assertLessThanOrEqual(Pathfinder::SEARCHES_PER_TICK, $grants);
			self::assertLessThanOrEqual(4, $grants);
		}
		self::assertSame([], array_keys(array_filter($served, static fn(int $count) : bool => $count === 0)));
	}

	public function testUnclaimedReservationDoesNotConsumeFutureTicks() : void{
		for($id = 1; $id <= 4; ++$id){
			self::assertTrue(Pathfinder::takeBudget(5000, $id));
			Pathfinder::completeBudget(5000, $id, 400);
		}
		for($id = 5; $id <= 8; ++$id){ self::assertFalse(Pathfinder::takeBudget(5000, $id)); }
		//IDs 5-8 disappear without claiming their reservations on tick 5001
		self::assertFalse(Pathfinder::takeBudget(5001, 9));
		for($id = 9; $id <= 12; ++$id){
			self::assertTrue(Pathfinder::takeBudget(5002, $id));
			Pathfinder::completeBudget(5002, $id, 400);
		}
		self::assertFalse(Pathfinder::takeBudget(5002, 13));
	}

	public function testMixedWorkloadKeepsQueuedCheapSearchesMoving() : void{
		$served = array_fill_keys(range(1, 100), 0);
		$maximumGrants = 0;
		for($tick = 6000; $tick < 6100; ++$tick){
			$grants = 0;
			foreach(array_keys($served) as $id){
				if(Pathfinder::takeBudget($tick, $id)){
					++$served[$id];
					++$grants;
					Pathfinder::completeBudget($tick, $id, $id % 10 === 0 ? 400 : 16);
				}
			}
			$maximumGrants = max($maximumGrants, $grants);
			self::assertLessThanOrEqual(Pathfinder::WORK_NODES_PER_TICK, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
		}
		self::assertSame(16, $maximumGrants);
		self::assertSame([], array_keys(array_filter($served, static fn(int $count) : bool => $count === 0)));
	}

	public function testCancelledThousandRequesterBacklogDoesNotDelayFreshRequest() : void{
		for($id = 1; $id <= 4; ++$id){
			self::assertTrue(Pathfinder::takeBudget(7000, $id));
			Pathfinder::completeBudget(7000, $id, 400);
		}
		for($id = 100; $id < 1100; ++$id){ self::assertFalse(Pathfinder::takeBudget(7000, $id)); }
		for($id = 100; $id < 1100; ++$id){ Pathfinder::cancelBudget($id); }
		self::assertTrue(Pathfinder::takeBudget(7001, 2000));
		self::assertSame([], (new ReflectionProperty(Pathfinder::class, 'waiting'))->getValue());
		self::assertSame([], (new ReflectionProperty(Pathfinder::class, 'reserved'))->getValue());
	}

	public function testCancelledAndRequeuedRequesterGoesBehindActiveWaiters() : void{
		for($id = 1; $id <= 4; ++$id){
			self::assertTrue(Pathfinder::takeBudget(8000, $id));
			Pathfinder::completeBudget(8000, $id, 400);
		}
		for($id = 100; $id <= 104; ++$id){ self::assertFalse(Pathfinder::takeBudget(8000, $id)); }
		Pathfinder::cancelBudget(102);
		self::assertFalse(Pathfinder::takeBudget(8000, 102));
		self::assertFalse(Pathfinder::takeBudget(8001, 102), 'Old queue entry must not restore the cancelled place');
		foreach([100, 101, 103, 104] as $id){
			self::assertTrue(Pathfinder::takeBudget(8001, $id));
			Pathfinder::completeBudget(8001, $id, 400);
		}
		self::assertTrue(Pathfinder::takeBudget(8002, 102));
	}

	public function testCancellationReleasesReservedSlotWithoutRefundingAdmittedWork() : void{
		for($id = 1; $id <= 4; ++$id){
			self::assertTrue(Pathfinder::takeBudget(9000, $id));
			Pathfinder::completeBudget(9000, $id, 400);
		}
		foreach([5, 6] as $id){ self::assertFalse(Pathfinder::takeBudget(9000, $id)); }
		self::assertTrue(Pathfinder::takeBudget(9001, 10));
		Pathfinder::cancelBudget(5);
		Pathfinder::cancelBudget(5); //idempotent cancellation
		self::assertArrayNotHasKey(5, (new ReflectionProperty(Pathfinder::class, 'reserved'))->getValue());
		self::assertSame(400, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
		Pathfinder::cancelBudget(10); //already admitted
		Pathfinder::completeBudget(9001, 10, 30);
		self::assertSame(30, (new ReflectionProperty(Pathfinder::class, 'workUsed'))->getValue());
		self::assertSame(1, (new ReflectionProperty(Pathfinder::class, 'budgetUsed'))->getValue());
		self::assertTrue(Pathfinder::takeBudget(9001, 6));
		Pathfinder::completeBudget(9001, 6, 400);
		self::assertTrue(Pathfinder::takeBudget(9001, 11));
		Pathfinder::completeBudget(9001, 11, 400);
		self::assertTrue(Pathfinder::takeBudget(9001, 12));
		Pathfinder::completeBudget(9001, 12, 400);
		self::assertFalse(Pathfinder::takeBudget(9001, 13));
	}
}
