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

namespace pocketmine\addon\entity\ai;

use pocketmine\addon\entity\ai\goal\AvoidMobTypeGoal;
use pocketmine\addon\entity\ai\goal\BreedGoal;
use pocketmine\addon\entity\ai\goal\FloatGoal;
use pocketmine\addon\entity\ai\goal\FollowGoal;
use pocketmine\addon\entity\ai\goal\HurtByTargetGoal;
use pocketmine\addon\entity\ai\goal\LeapAtTargetGoal;
use pocketmine\addon\entity\ai\goal\LookAtEntityGoal;
use pocketmine\addon\entity\ai\goal\MeleeAttackGoal;
use pocketmine\addon\entity\ai\goal\MoveTowardsTargetGoal;
use pocketmine\addon\entity\ai\goal\NearestAttackableTargetGoal;
use pocketmine\addon\entity\ai\goal\OwnerDefendGoal;
use pocketmine\addon\entity\ai\goal\PanicGoal;
use pocketmine\addon\entity\ai\goal\PickupItemsGoal;
use pocketmine\addon\entity\ai\goal\RandomLookAroundGoal;
use pocketmine\addon\entity\ai\goal\RandomStrollGoal;
use pocketmine\addon\entity\ai\goal\RangedAttackGoal;
use pocketmine\addon\entity\ai\goal\StayWhileSittingGoal;
use pocketmine\addon\entity\ai\goal\TemptGoal;
use pocketmine\addon\entity\AddonEntity;
use function is_array;
use function is_numeric;
use function str_starts_with;
use function usort;

/**
 * Runs a mob's behaviors: the goal selector of the Bedrock/Java AI.
 *
 * Every behavior component ("minecraft:behavior.*") in the mob's active component set becomes a Goal. Lower
 * priority numbers are more important. A goal starts when its canStart() holds and none of its control flags is
 * held by a more important running goal; it pushes out less important goals holding those flags.
 *
 * Idle goals are polled every POLL_TICKS ticks (each mob on its own slot), and mobs with no player within the
 * activation range think only every 20 ticks, which keeps a few hundred mobs cheap.
 */
final class MobBrain{
	/** Behaviors are simulated at full rate only this close to a player (blocks). */
	public const ACTIVE_RANGE = 48.0;
	/** Idle goals are asked whether they can start this often (ticks). */
	public const POLL_TICKS = 4;

	/**
	 * Goal factories by behavior component name. Plugins may add or replace entries with registerGoal().
	 *
	 * @var array<string, \Closure(AddonEntity, mixed[], int) : Goal>|null
	 */
	private static ?array $factories = null;

	/** @var list<Goal> */
	private array $goals = [];
	private Navigator $navigator;
	/** @var list<string> */
	private array $unsupported = [];
	private int $tickCounter = 0;
	private int $slot;

	/**
	 * @param mixed[] $components the mob's active components
	 */
	public function __construct(private AddonEntity $mob, array $components){
		$this->slot = $mob->getId() % self::POLL_TICKS;
		$this->navigator = new Navigator($mob, MovementProfile::fromComponents($components));
		$this->rebuild($components);
	}

	/**
	 * Registers (or replaces) the goal for a behavior component, so plugins can implement behaviors this server
	 * does not, or change how existing ones act. The factory receives the mob, the component JSON and the priority.
	 *
	 * @param \Closure(AddonEntity, mixed[], int) : Goal $factory
	 */
	public static function registerGoal(string $component, \Closure $factory) : void{
		self::factories();
		self::$factories[$component] = $factory;
	}

	public static function isSupported(string $component) : bool{
		return isset(self::factories()[$component]);
	}

	/** @return array<string, \Closure(AddonEntity, mixed[], int) : Goal> */
	private static function factories() : array{
		if(self::$factories !== null){
			return self::$factories;
		}
		$simple = static fn(string $class) : \Closure => static fn(AddonEntity $mob, array $data, int $priority) : Goal => new $class($mob, $data, $priority);
		$f = [
			"float" => $simple(FloatGoal::class),
			"random_stroll" => $simple(RandomStrollGoal::class),
			"random_swim" => $simple(RandomStrollGoal::class),
			"random_fly" => $simple(RandomStrollGoal::class),
			"random_hover" => $simple(RandomStrollGoal::class),
			"swim_wander" => $simple(RandomStrollGoal::class),
			"swim_idle" => $simple(RandomLookAroundGoal::class),
			"look_at_player" => $simple(LookAtEntityGoal::class),
			"look_at_entity" => $simple(LookAtEntityGoal::class),
			"look_at_target" => $simple(LookAtEntityGoal::class),
			"look_at_trading_player" => $simple(LookAtEntityGoal::class),
			"random_look_around" => $simple(RandomLookAroundGoal::class),
			"random_look_around_and_sit" => $simple(RandomLookAroundGoal::class),
			"panic" => $simple(PanicGoal::class),
			"tempt" => $simple(TemptGoal::class),
			"follow_parent" => static fn(AddonEntity $mob, array $data, int $priority) : Goal => new FollowGoal($mob, $data, $priority, "parent"),
			"follow_owner" => static fn(AddonEntity $mob, array $data, int $priority) : Goal => new FollowGoal($mob, $data, $priority, "owner"),
			"follow_mob" => static fn(AddonEntity $mob, array $data, int $priority) : Goal => new FollowGoal($mob, $data, $priority, "mob"),
			"nearest_attackable_target" => $simple(NearestAttackableTargetGoal::class),
			"nearest_prioritized_attackable_target" => $simple(NearestAttackableTargetGoal::class),
			"hurt_by_target" => $simple(HurtByTargetGoal::class),
			"owner_hurt_by_target" => static fn(AddonEntity $mob, array $data, int $priority) : Goal => new OwnerDefendGoal($mob, $data, $priority, true),
			"owner_hurt_target" => static fn(AddonEntity $mob, array $data, int $priority) : Goal => new OwnerDefendGoal($mob, $data, $priority, false),
			"melee_attack" => $simple(MeleeAttackGoal::class),
			"melee_box_attack" => $simple(MeleeAttackGoal::class),
			"delayed_attack" => $simple(MeleeAttackGoal::class),
			"ranged_attack" => $simple(RangedAttackGoal::class),
			"avoid_mob_type" => $simple(AvoidMobTypeGoal::class),
			"avoid_entity" => $simple(AvoidMobTypeGoal::class),
			"leap_at_target" => $simple(LeapAtTargetGoal::class),
			"move_towards_target" => $simple(MoveTowardsTargetGoal::class),
			"breed" => $simple(BreedGoal::class),
			"stay_while_sitting" => $simple(StayWhileSittingGoal::class),
			"pickup_items" => $simple(PickupItemsGoal::class),
		];
		self::$factories = [];
		foreach($f as $name => $factory){
			self::$factories["minecraft:behavior.$name"] = $factory;
		}
		return self::$factories;
	}

	/**
	 * Rebuilds the goals after the active components changed (a component group was added or removed).
	 *
	 * @param mixed[] $components
	 */
	public function rebuild(array $components) : void{
		foreach($this->goals as $goal){
			if($goal->isRunning()){
				$goal->stop();
				$goal->setRunning(false);
			}
		}
		$this->goals = [];
		$this->unsupported = [];
		$factories = self::factories();
		foreach($components as $name => $data){
			if(!str_starts_with((string) $name, "minecraft:behavior.") && !isset($factories[$name])){
				continue;
			}
			$factory = $factories[$name] ?? null;
			if($factory === null){
				$this->unsupported[] = (string) $name;
				continue;
			}
			$data = is_array($data) ? $data : [];
			$this->goals[] = $factory($this->mob, $data, is_numeric($data["priority"] ?? null) ? (int) $data["priority"] : 10);
		}
		usort($this->goals, static fn(Goal $a, Goal $b) : int => $a->getPriority() <=> $b->getPriority());
		$this->navigator->setProfile(MovementProfile::fromComponents($components));
		$this->navigator->stop();
	}

	/** @return list<string> behavior components that have no goal (reported by the entity once). */
	public function getUnsupported() : array{ return $this->unsupported; }

	/** @return list<Goal> */
	public function getGoals() : array{ return $this->goals; }

	public function getNavigator() : Navigator{ return $this->navigator; }

	public function isNavigating() : bool{ return $this->navigator->isMoving(); }

	public function hasGoals() : bool{ return $this->goals !== []; }

	/**
	 * One AI step. $active is false when no player is near; the mob then only updates every 20 ticks.
	 */
	public function tick(bool $active) : void{
		$this->tickCounter++;
		if(!$active && $this->tickCounter % 20 !== 0){
			$this->navigator->stop();
			return;
		}

		//stop goals that can no longer continue
		$held = 0;
		foreach($this->goals as $goal){
			if($goal->isRunning()){
				if(!$goal->canContinue()){
					$goal->stop();
					$goal->setRunning(false);
				}else{
					$held |= $goal->getFlags();
				}
			}
		}

		//start the most important goals that fit; idle goals are polled every 4 ticks, each mob on its own slot
		if(($this->tickCounter + $this->slot) % self::POLL_TICKS === 0 || !$active){
			$blocked = 0; //flags held by running goals more important than the one being looked at
			foreach($this->goals as $goal){
				if($goal->isRunning()){
					$blocked |= $goal->getFlags();
					continue;
				}
				$flags = $goal->getFlags();
				if(($flags & $blocked) !== 0){
					continue;
				}
				if(($flags & $held) !== 0 && !$this->canPreempt($goal)){
					continue;
				}
				if(!$goal->canStart()){
					continue;
				}
				//push out less important goals using the same controls
				foreach($this->goals as $other){
					if($other !== $goal && $other->isRunning() && ($other->getFlags() & $flags) !== 0){
						$other->stop();
						$other->setRunning(false);
						$held &= ~$other->getFlags();
					}
				}
				$goal->setRunning(true);
				$goal->start();
				$held |= $flags;
				$blocked |= $flags;
			}
		}

		foreach($this->goals as $goal){
			if($goal->isRunning()){
				$goal->tick();
			}
		}
		$this->navigator->tick();
	}

	/** Whether $goal may push out the running goals that hold its flags (they are all less important and interruptable). */
	private function canPreempt(Goal $goal) : bool{
		foreach($this->goals as $other){
			if($other->isRunning() && ($other->getFlags() & $goal->getFlags()) !== 0){
				if($other->getPriority() <= $goal->getPriority() || !$other->isInterruptable()){
					return false;
				}
			}
		}
		return true;
	}

	public function stopAll() : void{
		foreach($this->goals as $goal){
			if($goal->isRunning()){
				$goal->stop();
				$goal->setRunning(false);
			}
		}
		$this->navigator->stop();
	}
}
