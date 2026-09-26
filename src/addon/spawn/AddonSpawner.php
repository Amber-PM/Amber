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

namespace pocketmine\addon\spawn;

use pocketmine\addon\AddonMath;
use pocketmine\addon\AddonManager;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use function array_rand;
use function cos;
use function count;
use function floor;
use function in_array;
use function max;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use function str_starts_with;
use function substr;
use const M_PI;

/**
 * Spawns add-on entities naturally around players, following their spawn rules.
 *
 * Every interval, for each player in an enabled world, a random spot 24 to 64 blocks away is picked on the
 * surface, underground and underwater; the rules that accept the spot are weighed and one entity (or a herd)
 * is spawned there. Population caps are per category (monster, animal...) and scale with the number of players
 * in the world, like the game's mob caps. Entities placed by the spawner despawn by their own
 * minecraft:despawn component.
 *
 * Settings live in addons/config.yml ("spawning").
 */
final class AddonSpawner{
	private const MIN_DISTANCE = 24;
	private const MAX_DISTANCE = 64;

	/** @var array<string, list<SpawnRule>> category => rules */
	private array $rulesByCategory = [];

	/**
	 * @param list<SpawnRule> $rules
	 * @param array<string, int> $caps       category => mobs per player
	 * @param list<string>       $worlds     folder names; empty for all worlds
	 */
	public function __construct(
		private Server $server,
		private AddonManager $manager,
		array $rules,
		private int $interval,
		private array $caps,
		private array $worlds
	){
		foreach($rules as $rule){
			if($manager->getEntityDefinition($rule->getIdentifier()) !== null){
				$this->rulesByCategory[$rule->getCategory()][] = $rule;
			}
		}
	}

	public function hasRules() : bool{ return $this->rulesByCategory !== []; }

	public function tick(int $currentTick) : void{
		if($this->rulesByCategory === [] || $currentTick % $this->interval !== 0){
			return;
		}
		foreach($this->server->getWorldManager()->getWorlds() as $world){
			if($this->worlds !== [] && !in_array($world->getFolderName(), $this->worlds, true)){
				continue;
			}
			$players = $world->getPlayers();
			if($players === []){
				continue;
			}
			$counts = $this->countByCategory($world);
			foreach($this->rulesByCategory as $category => $rules){
				if($category === "monster" && $world->getDifficulty() === World::DIFFICULTY_PEACEFUL){
					continue;
				}
				$cap = ($this->caps[$category] ?? $this->caps["default"] ?? 10) * count($players);
				if(($counts[$category] ?? 0) >= $cap){
					continue;
				}
				$player = $players[array_rand($players)];
				$counts[$category] = ($counts[$category] ?? 0) + $this->attempt($world, $player, $rules);
			}
		}
	}

	/** @return array<string, int> */
	private function countByCategory(World $world) : array{
		$counts = [];
		foreach($world->getEntities() as $entity){
			if($entity instanceof AddonEntity && $entity->hasTag(self::categoryTag(""))){
				foreach($entity->getTags() as $tag){
					if(str_starts_with($tag, "amber:spawn_category=")){
						$category = substr($tag, 21);
						$counts[$category] = ($counts[$category] ?? 0) + 1;
					}
				}
			}
		}
		return $counts;
	}

	private static function categoryTag(string $category) : string{
		return $category === "" ? "amber:natural" : "amber:spawn_category=$category";
	}

	/**
	 * @param list<SpawnRule> $rules
	 * @return int how many entities were spawned
	 */
	private function attempt(World $world, Player $player, array $rules) : int{
		$origin = $player->getPosition();
		$angle = AddonMath::randomFloat() * 2 * M_PI;
		$radius = self::MIN_DISTANCE + AddonMath::randomFloat() * (self::MAX_DISTANCE - self::MIN_DISTANCE);
		$x = (int) floor($origin->x + cos($angle) * $radius);
		$z = (int) floor($origin->z + sin($angle) * $radius);
		if(!$world->isChunkLoaded($x >> 4, $z >> 4) || !$world->isChunkGenerated($x >> 4, $z >> 4)){
			return 0;
		}
		$spots = $this->findSpots($world, $x, $z, (int) floor($origin->y));
		if($spots === []){
			return 0;
		}

		//weigh every (rule, condition) that accepts one of the spots
		$choices = [];
		$total = 0;
		foreach($spots as [$kind, $y]){
			$distance = sqrt(($x - $origin->x) ** 2 + ($y - $origin->y) ** 2 + ($z - $origin->z) ** 2);
			foreach($rules as $rule){
				$condition = $rule->match($world, $x, $y, $z, $kind, $distance);
				if($condition === null){
					continue;
				}
				$weight = SpawnRule::weight($condition);
				if($weight > 0){
					$choices[] = [$rule, $condition, $kind, $y, $weight];
					$total += $weight;
				}
			}
		}
		if($total === 0){
			return 0;
		}
		$roll = mt_rand(1, $total);
		foreach($choices as [$rule, $condition, $kind, $y, $weight]){
			$roll -= $weight;
			if($roll <= 0){
				return $this->spawnHerd($world, $rule, $condition, $kind, $x, $y, $z);
			}
		}
		return 0;
	}

	/**
	 * Candidate spots in the column: [kind, feet y].
	 *
	 * @return list<array{int, int}>
	 */
	private function findSpots(World $world, int $x, int $z, int $playerY) : array{
		$spots = [];
		$highest = $world->getHighestBlockAt($x, $z);
		if($highest === null){
			return [];
		}
		$top = $world->getBlockAt($x, $highest, $z);
		if($top instanceof Water){
			//underwater: somewhere in the water column
			$bottom = $highest;
			while($bottom > $world->getMinY() && $world->getBlockAt($x, $bottom - 1, $z) instanceof Water){
				$bottom--;
			}
			$spots[] = [SpawnRule::UNDERWATER, mt_rand($bottom, $highest)];
		}elseif($top instanceof Lava){
			$spots[] = [SpawnRule::LAVA, $highest];
		}elseif($this->fits($world, $x, $highest + 1, $z)){
			$spots[] = [SpawnRule::SURFACE, $highest + 1];
		}
		//underground: a random height below the surface, near the player's level
		$minY = max($world->getMinY() + 1, $playerY - 24);
		$maxY = min($highest - 2, $playerY + 24);
		for($try = 0; $try < 4 && $maxY > $minY; ++$try){
			$y = mt_rand($minY, $maxY);
			if($this->fits($world, $x, $y, $z) && $world->getBlockAt($x, $y - 1, $z)->isSolid()){
				$spots[] = [SpawnRule::UNDERGROUND, $y];
				break;
			}
		}
		return $spots;
	}

	private function fits(World $world, int $x, int $y, int $z) : bool{
		$feet = $world->getBlockAt($x, $y, $z);
		$head = $world->getBlockAt($x, $y + 1, $z);
		return !$feet->isSolid() && !$head->isSolid() && !$feet instanceof Lava && !$feet instanceof Water
			&& $world->getBlockAt($x, $y - 1, $z)->isSolid();
	}

	/** @param mixed[] $condition */
	private function spawnHerd(World $world, SpawnRule $rule, array $condition, int $kind, int $x, int $y, int $z) : int{
		$limit = SpawnRule::densityLimit($condition, $kind);
		if($limit !== null){
			$existing = 0;
			foreach($world->getEntities() as $entity){
				if($entity instanceof AddonEntity && $entity->getAddonIdentifier() === $rule->getIdentifier()){
					$existing++;
				}
			}
			if($existing >= $limit){
				return 0;
			}
		}
		[$size, $herdEvent, $skip] = SpawnRule::herd($condition);
		$spawned = 0;
		for($i = 0; $i < $size; ++$i){
			$px = $x + ($i === 0 ? 0 : mt_rand(-3, 3));
			$pz = $z + ($i === 0 ? 0 : mt_rand(-3, 3));
			$py = $y;
			if($i > 0){
				$column = $world->getHighestBlockAt($px, $pz);
				if($kind === SpawnRule::SURFACE && $column !== null){
					$py = $column + 1;
				}
				if($kind !== SpawnRule::UNDERWATER && !$this->fits($world, $px, $py, $pz)){
					continue;
				}
			}
			[$identifier, $event] = $rule->permute($condition);
			$entity = $this->manager->createEntity($identifier, new Location($px + 0.5, $py, $pz + 0.5, $world, AddonMath::randomFloat() * 360, 0));
			if($entity === null){
				continue;
			}
			$entity->addTag(self::categoryTag(""));
			$entity->addTag(self::categoryTag($rule->getCategory()));
			if($herdEvent !== null && $i >= $skip){
				$event = $herdEvent;
			}
			if($event !== null){
				$entity->setSpawnEvent($event);
			}
			$entity->spawnToAll();
			$spawned++;
		}
		return $spawned;
	}
}
