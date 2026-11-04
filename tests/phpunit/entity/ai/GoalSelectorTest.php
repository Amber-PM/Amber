<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\ai\goal\Goal;
use pocketmine\entity\ai\goal\GoalSelector;

final class GoalSelectorTest extends TestCase{

	public function testGoalExecutionAndStopLifecycle() : void{
		$selector = new GoalSelector();

		$started = false;
		$ticks = 0;
		$stopped = false;
		$canStart = true;
		$shouldContinue = true;

		$testGoal = new class(
			$started,
			$ticks,
			$stopped,
			$canStart,
			$shouldContinue
		) extends Goal{
			public function __construct(
				public bool &$started,
				public int &$ticks,
				public bool &$stopped,
				public bool &$canStart,
				public bool &$shouldContinue
			){
				parent::__construct(1, Goal::FLAG_MOVE);
			}

			public function canStart() : bool{
				return $this->canStart;
			}

			public function shouldContinue() : bool{
				return $this->shouldContinue;
			}

			public function start() : void{
				$this->started = true;
			}

			public function tick() : void{
				$this->ticks++;
			}

			public function stop() : void{
				$this->stopped = true;
			}
		};

		$selector->addGoal($testGoal);

		// First tick: goal should start and tick
		$selector->tick();
		self::assertTrue($started);
		self::assertSame(1, $ticks);
		self::assertFalse($stopped);

		// Second tick: should continue and tick again
		$selector->tick();
		self::assertSame(2, $ticks);

		// When shouldContinue returns false: goal stops
		$shouldContinue = false;
		$canStart = false;
		$selector->tick();
		self::assertTrue($stopped);
		self::assertSame(2, $ticks);
	}

	public function testHigherPriorityGoalSuppressesConflictingLowerPriorityGoal() : void{
		$selector = new GoalSelector();

		$lowGoalTicked = 0;
		$highGoalTicked = 0;
		$highCanStart = false;

		$lowGoal = new class($lowGoalTicked) extends Goal{
			public function __construct(public int &$ticks){
				parent::__construct(priority: 5, flags: Goal::FLAG_MOVE);
			}
			public function canStart() : bool{ return true; }
			public function shouldContinue() : bool{ return true; }
			public function start() : void{}
			public function tick() : void{ $this->ticks++; }
			public function stop() : void{}
		};

		$highGoal = new class($highGoalTicked, $highCanStart) extends Goal{
			public function __construct(public int &$ticks, public bool &$canStart){
				parent::__construct(priority: 1, flags: Goal::FLAG_MOVE);
			}
			public function canStart() : bool{ return $this->canStart; }
			public function shouldContinue() : bool{ return $this->canStart; }
			public function start() : void{}
			public function tick() : void{ $this->ticks++; }
			public function stop() : void{}
		};

		$selector->addGoal($lowGoal);
		$selector->addGoal($highGoal);

		// Initially highGoal cannot start, so lowGoal runs
		$selector->tick();
		self::assertSame(1, $lowGoalTicked);
		self::assertSame(0, $highGoalTicked);

		// Now highGoal becomes eligible. Because both share FLAG_MOVE, highGoal (priority 1) suppresses lowGoal (priority 5)
		$highCanStart = true;
		$selector->tick();
		self::assertSame(1, $highGoalTicked);
		self::assertSame(1, $lowGoalTicked); // lowGoal was interrupted
	}

	public function testNonConflictingGoalsRunSimultaneously() : void{
		$selector = new GoalSelector();

		$moveTicked = 0;
		$lookTicked = 0;

		$moveGoal = new class($moveTicked) extends Goal{
			public function __construct(public int &$ticks){
				parent::__construct(priority: 2, flags: Goal::FLAG_MOVE);
			}
			public function canStart() : bool{ return true; }
			public function shouldContinue() : bool{ return true; }
			public function start() : void{}
			public function tick() : void{ $this->ticks++; }
			public function stop() : void{}
		};

		$lookGoal = new class($lookTicked) extends Goal{
			public function __construct(public int &$ticks){
				parent::__construct(priority: 3, flags: Goal::FLAG_LOOK);
			}
			public function canStart() : bool{ return true; }
			public function shouldContinue() : bool{ return true; }
			public function start() : void{}
			public function tick() : void{ $this->ticks++; }
			public function stop() : void{}
		};

		$selector->addGoal($moveGoal);
		$selector->addGoal($lookGoal);

		$selector->tick();
		self::assertSame(1, $moveTicked);
		self::assertSame(1, $lookTicked);
	}
}
