<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use function spl_object_id;
use function usort;

final class GoalSelector{

	/** @var Goal[] */
	private array $goals = [];
	/** @var array<int, Goal> */
	private array $activeGoals = [];

	public function addGoal(Goal $goal) : void{
		$this->goals[] = $goal;
		$this->sortGoals();
	}

	public function removeGoal(Goal $goal) : void{
		$id = spl_object_id($goal);
		if(isset($this->activeGoals[$id])){
			$this->activeGoals[$id]->stop();
			unset($this->activeGoals[$id]);
		}
		foreach($this->goals as $index => $g){
			if($g === $goal){
				unset($this->goals[$index]);
				break;
			}
		}
		$this->goals = array_values($this->goals);
	}

	public function clear() : void{
		foreach($this->activeGoals as $goal){
			$goal->stop();
		}
		$this->activeGoals = [];
		$this->goals = [];
	}

	private function sortGoals() : void{
		usort($this->goals, fn(Goal $a, Goal $b) => $a->getPriority() <=> $b->getPriority());
	}

	public function tick() : void{
		// 1. Check continuing status of currently active goals
		foreach($this->activeGoals as $id => $activeGoal){
			if(!$activeGoal->shouldContinue()){
				$activeGoal->stop();
				unset($this->activeGoals[$id]);
			}
		}

		// 2. Evaluate inactive goals to start or preempt lower priority active goals
		foreach($this->goals as $candidate){
			$id = spl_object_id($candidate);
			if(isset($this->activeGoals[$id])){
				continue;
			}

			// Check conflicting flags with active goals
			$conflicts = [];
			$blocked = false;

			foreach($this->activeGoals as $activeId => $activeGoal){
				if(($activeGoal->getFlags() & $candidate->getFlags()) !== 0){
					if($candidate->getPriority() < $activeGoal->getPriority() && $activeGoal->isInterruptible()){
						$conflicts[$activeId] = $activeGoal;
					}else{
						$blocked = true;
						break;
					}
				}
			}

			if($blocked){
				continue;
			}

			if($candidate->canStart()){
				// Preempt any conflicting lower priority goals
				foreach($conflicts as $conflictId => $conflictGoal){
					$conflictGoal->stop();
					unset($this->activeGoals[$conflictId]);
				}

				$candidate->start();
				$this->activeGoals[$id] = $candidate;
			}
		}

		// 3. Tick all active goals
		foreach($this->activeGoals as $activeGoal){
			$activeGoal->tick();
		}
	}

	/**
	 * @return Goal[]
	 */
	public function getActiveGoals() : array{
		return array_values($this->activeGoals);
	}
}
