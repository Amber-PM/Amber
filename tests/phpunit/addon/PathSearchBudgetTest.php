<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\entity\ai\Pathfinder;

final class PathSearchBudgetTest extends TestCase{
	public function testFixedOrderRequestersEventuallyReceiveBudgetWithoutExceedingCap() : void{
		$served = array_fill_keys(range(1, 161), 0);
		$nextAttempt = array_fill_keys(range(1, 161), 1000);
		for($tick = 1000; $tick < 1040; ++$tick){
			$grants = 0;
			foreach(array_keys($served) as $id){
				if($tick < $nextAttempt[$id]){
					continue;
				}
				if(Pathfinder::takeBudget($tick, $id)){
					$served[$id]++;
				$nextAttempt[$id] = $tick + 10; //a failed path may be retried after the navigator cooldown
					$grants++;
				}
			}
			self::assertLessThanOrEqual(Pathfinder::SEARCHES_PER_TICK, $grants);
		}
		self::assertSame([], array_keys(array_filter($served, static fn(int $count) : bool => $count === 0)));
	}
}
