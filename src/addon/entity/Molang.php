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

namespace pocketmine\addon\entity;

use pocketmine\addon\AddonMath;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use function abs;
use function ceil;
use function count;
use function ctype_alnum;
use function ctype_digit;
use function ctype_space;
use function floor;
use function fmod;
use function in_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function round;
use function sqrt;
use function strlen;
use function strtolower;
use function substr;
use const M_PI;

/**
 * A small Molang evaluator for the server-side expressions add-ons use: set_property values, experience
 * rewards, timers and filter values. It covers numbers, strings, booleans, arithmetic, comparisons,
 * &&, ||, !, ?:, the ?? operator, math.* functions and the entity queries that make sense on a server.
 *
 * Each expression is compiled once into closures and cached, so evaluating it again costs a few calls.
 * Anything it does not know evaluates to 0, as in the game.
 */
final class Molang{
	/** Compiled expressions kept at most (packs use a few dozen distinct ones). */
	private const CACHE_SIZE = 2048;

	/** @var array<string, \Closure(?AddonEntity, ?FilterContext) : (float|bool|string)> */
	private static array $compiled = [];

	private int $pos = 0;
	private string $src;

	private function __construct(string $source){
		//Molang is case-insensitive except inside string literals
		$out = "";
		$quote = null;
		for($i = 0, $n = strlen($source); $i < $n; ++$i){
			$c = $source[$i];
			if($quote === null && ($c === "'" || $c === "\"")){
				$quote = $c;
			}elseif($c === $quote){
				$quote = null;
			}
			$out .= $quote === null ? strtolower($c) : $c;
		}
		$this->src = $out;
	}

	/** Evaluates a JSON value that may be a Molang expression (a string) or a literal. */
	public static function evaluate(mixed $value, ?AddonEntity $entity = null, ?FilterContext $context = null) : float|bool|string{
		if(is_bool($value) || is_numeric($value)){
			return is_bool($value) ? $value : (float) $value;
		}
		if(!is_string($value)){
			return 0.0;
		}
		$expression = self::$compiled[$value] ?? self::compile($value);
		try{
			return $expression($entity, $context);
		}catch(\Throwable){
			return 0.0;
		}
	}

	public static function number(mixed $value, ?AddonEntity $entity = null, ?FilterContext $context = null) : float{
		$result = self::evaluate($value, $entity, $context);
		return is_bool($result) ? ($result ? 1.0 : 0.0) : (is_numeric($result) ? (float) $result : 0.0);
	}

	/** @return \Closure(?AddonEntity, ?FilterContext) : (float|bool|string) */
	private static function compile(string $source) : \Closure{
		try{
			$expression = (new self($source))->ternary();
		}catch(\Throwable){
			$expression = static fn() : float => 0.0;
		}
		if(count(self::$compiled) >= self::CACHE_SIZE){
			self::$compiled = [];
		}
		return self::$compiled[$source] = $expression;
	}

	private function ternary() : \Closure{
		$condition = $this->coalesce();
		if($this->eat("?")){
			$a = $this->ternary();
			if(!$this->eat(":")){
				return static fn(?AddonEntity $e, ?FilterContext $c) : float|bool|string => self::truthy($condition($e, $c)) ? $a($e, $c) : 0.0;
			}
			$b = $this->ternary();
			return static fn(?AddonEntity $e, ?FilterContext $c) : float|bool|string => self::truthy($condition($e, $c)) ? $a($e, $c) : $b($e, $c);
		}
		return $condition;
	}

	private function coalesce() : \Closure{
		$left = $this->logicalOr();
		while($this->eat("??")){
			$right = $this->logicalOr();
			$prev = $left;
			$left = static function(?AddonEntity $e, ?FilterContext $c) use ($prev, $right) : float|bool|string{
				$value = $prev($e, $c);
				return $value === 0.0 ? $right($e, $c) : $value;
			};
		}
		return $left;
	}

	private function logicalOr() : \Closure{
		$left = $this->logicalAnd();
		while($this->eat("||")){
			$right = $this->logicalAnd();
			$prev = $left;
			$left = static fn(?AddonEntity $e, ?FilterContext $c) : bool => self::truthy($prev($e, $c)) || self::truthy($right($e, $c));
		}
		return $left;
	}

	private function logicalAnd() : \Closure{
		$left = $this->comparison();
		while($this->eat("&&")){
			$right = $this->comparison();
			$prev = $left;
			$left = static fn(?AddonEntity $e, ?FilterContext $c) : bool => self::truthy($prev($e, $c)) && self::truthy($right($e, $c));
		}
		return $left;
	}

	private function comparison() : \Closure{
		$left = $this->additive();
		foreach(["==", "!=", "<=", ">=", "<", ">"] as $op){
			if($this->eat($op)){
				$right = $this->additive();
				return static function(?AddonEntity $e, ?FilterContext $c) use ($left, $right, $op) : bool{
					$l = $left($e, $c);
					$r = $right($e, $c);
					if(is_string($l) || is_string($r)){
						return match($op){ "==" => (string) $l === (string) $r, "!=" => (string) $l !== (string) $r, default => false };
					}
					$l = self::num($l);
					$r = self::num($r);
					return match($op){
						"==" => $l == $r, "!=" => $l != $r, "<=" => $l <= $r, ">=" => $l >= $r, "<" => $l < $r, default => $l > $r,
					};
				};
			}
		}
		return $left;
	}

	private function additive() : \Closure{
		$left = $this->multiplicative();
		while(true){
			if($this->eat("+")){
				$right = $this->multiplicative();
				$prev = $left;
				$left = static fn(?AddonEntity $e, ?FilterContext $c) : float => self::num($prev($e, $c)) + self::num($right($e, $c));
			}elseif($this->peekMinus()){
				$this->pos++;
				$right = $this->multiplicative();
				$prev = $left;
				$left = static fn(?AddonEntity $e, ?FilterContext $c) : float => self::num($prev($e, $c)) - self::num($right($e, $c));
			}else{
				return $left;
			}
		}
	}

	private function multiplicative() : \Closure{
		$left = $this->unary();
		while(true){
			if($this->eat("*")){
				$right = $this->unary();
				$prev = $left;
				$left = static fn(?AddonEntity $e, ?FilterContext $c) : float => self::num($prev($e, $c)) * self::num($right($e, $c));
			}elseif($this->eat("/")){
				$right = $this->unary();
				$prev = $left;
				$left = static function(?AddonEntity $e, ?FilterContext $c) use ($prev, $right) : float{
					$divisor = self::num($right($e, $c));
					return $divisor == 0.0 ? 0.0 : self::num($prev($e, $c)) / $divisor;
				};
			}else{
				return $left;
			}
		}
	}

	private function unary() : \Closure{
		if($this->eat("!")){
			$inner = $this->unary();
			return static fn(?AddonEntity $e, ?FilterContext $c) : bool => !self::truthy($inner($e, $c));
		}
		if($this->eat("-")){
			$inner = $this->unary();
			return static fn(?AddonEntity $e, ?FilterContext $c) : float => -self::num($inner($e, $c));
		}
		return $this->primary();
	}

	private function primary() : \Closure{
		$this->skip();
		if($this->eat("(")){
			$value = $this->ternary();
			$this->eat(")");
			return $value;
		}
		$c = $this->src[$this->pos] ?? "";
		if($c === "'" || $c === "\""){
			$end = $this->pos + 1;
			while($end < strlen($this->src) && $this->src[$end] !== $c){
				$end++;
			}
			$string = substr($this->src, $this->pos + 1, $end - $this->pos - 1);
			$this->pos = $end + 1;
			return static fn() : string => $string;
		}
		if(ctype_digit($c) || $c === "."){
			$start = $this->pos;
			while($this->pos < strlen($this->src) && (ctype_digit($this->src[$this->pos]) || $this->src[$this->pos] === ".")){
				$this->pos++;
			}
			$number = (float) substr($this->src, $start, $this->pos - $start);
			if(($this->src[$this->pos] ?? "") === "f"){
				$this->pos++;
			}
			return static fn() : float => $number;
		}
		$name = $this->identifier();
		if($name === ""){
			throw new \InvalidArgumentException("unexpected character");
		}
		$name = match(true){
			substr($name, 0, 2) === "q." => "query." . substr($name, 2),
			substr($name, 0, 2) === "v." => "variable." . substr($name, 2),
			substr($name, 0, 2) === "c." => "context." . substr($name, 2),
			default => $name,
		};
		$args = [];
		$this->skip();
		if($this->eat("(")){
			if(!$this->eat(")")){
				do{
					$args[] = $this->ternary();
				}while($this->eat(","));
				$this->eat(")");
			}
		}
		if($name === "true" || $name === "false"){
			$bool = $name === "true";
			return static fn() : bool => $bool;
		}
		if($name === "math.pi"){
			return static fn() : float => M_PI;
		}
		return static function(?AddonEntity $e, ?FilterContext $c) use ($name, $args) : float|bool|string{
			$values = [];
			foreach($args as $arg){
				$values[] = $arg($e, $c);
			}
			return self::call($name, $values, $e, $c);
		};
	}

	/** @param list<float|bool|string> $args */
	private static function call(string $name, array $args, ?AddonEntity $entity, ?FilterContext $context) : float|bool|string{
		$a = isset($args[0]) ? self::num($args[0]) : 0.0;
		$b = isset($args[1]) ? self::num($args[1]) : 0.0;
		switch($name){
			case "math.abs": return abs($a);
			case "math.floor": return floor($a);
			case "math.ceil": return ceil($a);
			case "math.round": return round($a);
			case "math.trunc": return (float) (int) $a;
			case "math.sqrt": return sqrt(max(0.0, $a));
			case "math.min": return min($a, $b);
			case "math.max": return max($a, $b);
			case "math.clamp": return min(max($a, $b), isset($args[2]) ? self::num($args[2]) : $b);
			case "math.mod": return $b == 0.0 ? 0.0 : fmod($a, $b);
			case "math.pow": return $a ** $b;
			case "math.lerp": return $a + ($b - $a) * (isset($args[2]) ? self::num($args[2]) : 0.0);
			case "math.random": return $a + AddonMath::randomFloat() * ($b - $a);
			case "math.random_integer": return (float) mt_rand((int) min($a, $b), (int) max($a, $b));
			case "math.die_roll":
				$sum = 0.0;
				for($i = 0; $i < (int) $a; ++$i){
					$sum += $b + AddonMath::randomFloat() * ((isset($args[2]) ? self::num($args[2]) : 0.0) - $b);
				}
				return $sum;
			case "math.die_roll_integer":
				$sum = 0;
				for($i = 0; $i < (int) $a; ++$i){
					$sum += mt_rand((int) $b, (int) (isset($args[2]) ? self::num($args[2]) : $b));
				}
				return (float) $sum;
		}
		if($context !== null && ($name === "query.other_is_player" || $name === "context.other_is_player")){
			return $context->other instanceof Player;
		}
		if($name === "query.other_health"){
			return $context?->other instanceof Living ? $context->other->getHealth() : 0.0;
		}
		if($entity === null){
			return 0.0;
		}
		switch($name){
			case "query.property":
			case "query.actor_property":
				$value = $entity->getProperty((string) ($args[0] ?? ""));
				return is_bool($value) || is_string($value) ? $value : (float) ($value ?? 0);
			case "query.has_property":
				return $entity->hasProperty((string) ($args[0] ?? ""));
			case "query.variant": return (float) $entity->getVariant();
			case "query.mark_variant": return (float) $entity->getMarkVariant();
			case "query.skin_id": return (float) $entity->getSkinId();
			case "query.color": return (float) $entity->getColor();
			case "query.is_baby": return $entity->isBaby();
			case "query.is_tamed": return $entity->isTamed();
			case "query.is_sitting": return $entity->isSitting();
			case "query.is_on_fire": return $entity->isOnFire();
			case "query.is_on_ground": return $entity->isOnGround();
			case "query.is_in_water": return $entity->isUnderwater();
			case "query.health": return $entity->getHealth();
			case "query.max_health": return (float) $entity->getMaxHealth();
			case "query.is_alive": return $entity->isAlive();
			case "query.has_target": return $entity->getTargetEntity() !== null;
			case "query.is_daytime": return EntityFilter::isDay($entity->getWorld());
			case "query.time_of_day": return ($entity->getWorld()->getTimeOfDay() % 24000) / 24000;
			case "query.day": return floor($entity->getWorld()->getTime() / 24000);
			case "query.moon_phase": return (float) ((int) ($entity->getWorld()->getTime() / 24000) % 8);
			case "query.position":
				$pos = $entity->getPosition();
				return match((int) $a){ 1 => $pos->y, 2 => $pos->z, default => $pos->x };
			case "query.last_hit_by_player":
				return $entity->getLastDamager() instanceof Player;
			case "query.is_sneaking":
				return $entity->isSneaking();
			case "query.life_time": return $entity->getTicksLived() / 20;
			case "query.has_component": return $entity->hasComponent((string) ($args[0] ?? ""));
			case "query.is_family":
			case "query.has_any_family":
				foreach($args as $family){
					if(in_array((string) $family, $entity->getFamilies(), true)){
						return true;
					}
				}
				return false;
		}
		return 0.0;
	}

	private function identifier() : string{
		$this->skip();
		$start = $this->pos;
		while($this->pos < strlen($this->src) && (ctype_alnum($this->src[$this->pos]) || $this->src[$this->pos] === "_" || $this->src[$this->pos] === "." || $this->src[$this->pos] === ":")){
			$this->pos++;
		}
		return substr($this->src, $start, $this->pos - $start);
	}

	private function eat(string $token) : bool{
		$this->skip();
		if(substr($this->src, $this->pos, strlen($token)) === $token){
			//"?" must not eat the first half of "??", "<"/">"/"!" not the first half of "<=", ">=", "!="
			$next = $this->src[$this->pos + strlen($token)] ?? "";
			if(($token === "?" && $next === "?") || (($token === "<" || $token === ">" || $token === "!") && $next === "=") || ($token === "=" && $next === "=")){
				return false;
			}
			$this->pos += strlen($token);
			return true;
		}
		return false;
	}

	private function peekMinus() : bool{
		$this->skip();
		return ($this->src[$this->pos] ?? "") === "-";
	}

	private function skip() : void{
		while($this->pos < strlen($this->src) && (ctype_space($this->src[$this->pos]) || $this->src[$this->pos] === ";")){
			$this->pos++;
		}
		if(substr($this->src, $this->pos, 7) === "return "){
			$this->pos += 7;
		}
	}

	private static function truthy(float|bool|string $value) : bool{
		return is_string($value) ? $value !== "" : (bool) $value;
	}

	private static function num(float|bool|string $value) : float{
		return is_bool($value) ? ($value ? 1.0 : 0.0) : (is_numeric($value) ? (float) $value : 0.0);
	}
}
