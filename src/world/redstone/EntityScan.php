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

namespace pocketmine\world\redstone;

use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use function array_values;
use function count;
use function spl_object_id;

final class EntityScan{
	private \Generator $candidates;
	private array $matches = [];
	private array $matched = [];
	private ?int $validationCursor = null;
	private int $validationEnd = 0;
	private int $work = 0;

	public function __construct(private World $world, private AxisAlignedBB $area, private int $stateId){
		$this->candidates = $world->iterateEntityCandidates($area);
	}

	public function getStateId() : int{ return $this->stateId; }

	public function getWork() : int{ return $this->work; }

	private function isMatch(Entity $entity, \Closure $filter) : bool{
		return !$entity->isClosed() && !$entity->isFlaggedForDespawn() && $entity->getWorld() === $this->world && $entity->getBoundingBox()->intersectsWith($this->area) && $filter($entity);
	}

	public function poll(int $limit, int $maxMatches, \Closure $filter) : ?array{
		$this->work = 0;
		while($this->work < $limit){
			if($this->validationCursor !== null){
				if($this->validationCursor < $this->validationEnd){
					$index = $this->validationCursor++;
					++$this->work;
					if(isset($this->matches[$index]) && !$this->isMatch($this->matches[$index], $filter)){
						unset($this->matched[spl_object_id($this->matches[$index])]);
						unset($this->matches[$index]);
					}
					continue;
				}
				if(count($this->matches) >= $maxMatches || !$this->candidates->valid()){
					return $this->matches;
				}
				$this->matches = array_values($this->matches);
				$this->validationCursor = null;
			}
			if(count($this->matches) >= $maxMatches || !$this->candidates->valid()){
				$this->validationCursor = 0;
				$this->validationEnd = count($this->matches);
				continue;
			}
			$entity = $this->candidates->current();
			$this->candidates->next();
			++$this->work;
			if(!isset($this->matched[spl_object_id($entity)]) && $this->isMatch($entity, $filter)){
				$this->matched[spl_object_id($entity)] = true;
				$this->matches[] = $entity;
			}
		}
		return null;
	}
}
