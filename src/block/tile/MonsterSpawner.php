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

namespace pocketmine\block\tile;

use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\event\block\SpawnerSpawnEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\particle\MobSpawnParticle;
use pocketmine\world\Position;
use pocketmine\YmlServerProperties;
use function max;
use function min;
use function mt_rand;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * @deprecated
 */
class MonsterSpawner extends Spawnable{

	private const TAG_LEGACY_ENTITY_TYPE_ID = "EntityId"; //TAG_Int
	private const TAG_ENTITY_TYPE_ID = "EntityIdentifier"; //TAG_String
	private const TAG_SPAWN_DELAY = "Delay"; //TAG_Short
	private const TAG_SPAWN_POTENTIALS = "SpawnPotentials"; //TAG_List<TAG_Compound>
	private const TAG_SPAWN_DATA = "SpawnData"; //TAG_Compound
	private const TAG_MIN_SPAWN_DELAY = "MinSpawnDelay"; //TAG_Short
	private const TAG_MAX_SPAWN_DELAY = "MaxSpawnDelay"; //TAG_Short
	private const TAG_SPAWN_PER_ATTEMPT = "SpawnCount"; //TAG_Short
	private const TAG_MAX_NEARBY_ENTITIES = "MaxNearbyEntities"; //TAG_Short
	private const TAG_REQUIRED_PLAYER_RANGE = "RequiredPlayerRange"; //TAG_Short
	private const TAG_SPAWN_RANGE = "SpawnRange"; //TAG_Short
	private const TAG_ENTITY_WIDTH = "DisplayEntityWidth"; //TAG_Float
	private const TAG_ENTITY_HEIGHT = "DisplayEntityHeight"; //TAG_Float
	private const TAG_ENTITY_SCALE = "DisplayEntityScale"; //TAG_Float

	public const DEFAULT_MIN_SPAWN_DELAY = 200; //ticks
	public const DEFAULT_MAX_SPAWN_DELAY = 800;

	public const DEFAULT_MAX_NEARBY_ENTITIES = 6;
	public const DEFAULT_SPAWN_RANGE = 4; //blocks
	public const DEFAULT_REQUIRED_PLAYER_RANGE = 16;
	public const DEFAULT_SPAWN_COUNT = 4;

	/** TODO: replace this with a cached entity or something of that nature */
	private string $entityTypeId = ":";
	/** TODO: deserialize this properly and drop the NBT (PC and PE formats are different, just for fun) */
	private ?ListTag $spawnPotentials = null;
	/** TODO: deserialize this properly and drop the NBT (PC and PE formats are different, just for fun) */
	private ?CompoundTag $spawnData = null;

	private float $displayEntityWidth = 1.0;
	private float $displayEntityHeight = 1.0;
	private float $displayEntityScale = 1.0;

	private int $spawnDelay = self::DEFAULT_MIN_SPAWN_DELAY;
	private int $minSpawnDelay = self::DEFAULT_MIN_SPAWN_DELAY;
	private int $maxSpawnDelay = self::DEFAULT_MAX_SPAWN_DELAY;
	private int $spawnPerAttempt = self::DEFAULT_SPAWN_COUNT;
	private int $maxNearbyEntities = self::DEFAULT_MAX_NEARBY_ENTITIES;
	private int $spawnRange = self::DEFAULT_SPAWN_RANGE;
	private int $requiredPlayerRange = self::DEFAULT_REQUIRED_PLAYER_RANGE;

	public function readSaveData(CompoundTag $nbt) : void{
		if(($legacyIdTag = $nbt->getTag(self::TAG_LEGACY_ENTITY_TYPE_ID)) instanceof IntTag){
			//TODO: this will cause unexpected results when there's no mapping for the entity
			$this->entityTypeId = LegacyEntityIdToStringIdMap::getInstance()->legacyToString($legacyIdTag->getValue()) ?? ":";
		}elseif(($idTag = $nbt->getTag(self::TAG_ENTITY_TYPE_ID)) instanceof StringTag){
			$this->entityTypeId = $idTag->getValue();
		}else{
			$this->entityTypeId = ":"; //default - TODO: replace this with a constant
		}

		$this->spawnData = $nbt->getCompoundTag(self::TAG_SPAWN_DATA);
		$this->spawnPotentials = $nbt->getListTag(self::TAG_SPAWN_POTENTIALS);

		$this->spawnDelay = $nbt->getShort(self::TAG_SPAWN_DELAY, self::DEFAULT_MIN_SPAWN_DELAY);
		$this->minSpawnDelay = $nbt->getShort(self::TAG_MIN_SPAWN_DELAY, self::DEFAULT_MIN_SPAWN_DELAY);
		$this->maxSpawnDelay = $nbt->getShort(self::TAG_MAX_SPAWN_DELAY, self::DEFAULT_MAX_SPAWN_DELAY);
		$this->spawnPerAttempt = $nbt->getShort(self::TAG_SPAWN_PER_ATTEMPT, self::DEFAULT_SPAWN_COUNT);
		$this->maxNearbyEntities = $nbt->getShort(self::TAG_MAX_NEARBY_ENTITIES, self::DEFAULT_MAX_NEARBY_ENTITIES);
		$this->requiredPlayerRange = $nbt->getShort(self::TAG_REQUIRED_PLAYER_RANGE, self::DEFAULT_REQUIRED_PLAYER_RANGE);
		$this->spawnRange = $nbt->getShort(self::TAG_SPAWN_RANGE, self::DEFAULT_SPAWN_RANGE);

		$this->displayEntityWidth = $nbt->getFloat(self::TAG_ENTITY_WIDTH, 1.0);
		$this->displayEntityHeight = $nbt->getFloat(self::TAG_ENTITY_HEIGHT, 1.0);
		$this->displayEntityScale = $nbt->getFloat(self::TAG_ENTITY_SCALE, 1.0);

		if(isset($this->position) && $this->position->isValid()){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setString(self::TAG_ENTITY_TYPE_ID, $this->entityTypeId);
		if($this->spawnData !== null){
			$nbt->setTag(self::TAG_SPAWN_DATA, clone $this->spawnData);
		}
		if($this->spawnPotentials !== null){
			$nbt->setTag(self::TAG_SPAWN_POTENTIALS, clone $this->spawnPotentials);
		}

		$nbt->setShort(self::TAG_SPAWN_DELAY, $this->spawnDelay);
		$nbt->setShort(self::TAG_MIN_SPAWN_DELAY, $this->minSpawnDelay);
		$nbt->setShort(self::TAG_MAX_SPAWN_DELAY, $this->maxSpawnDelay);
		$nbt->setShort(self::TAG_SPAWN_PER_ATTEMPT, $this->spawnPerAttempt);
		$nbt->setShort(self::TAG_MAX_NEARBY_ENTITIES, $this->maxNearbyEntities);
		$nbt->setShort(self::TAG_REQUIRED_PLAYER_RANGE, $this->requiredPlayerRange);
		$nbt->setShort(self::TAG_SPAWN_RANGE, $this->spawnRange);

		$nbt->setFloat(self::TAG_ENTITY_WIDTH, $this->displayEntityWidth);
		$nbt->setFloat(self::TAG_ENTITY_HEIGHT, $this->displayEntityHeight);
		$nbt->setFloat(self::TAG_ENTITY_SCALE, $this->displayEntityScale);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		$nbt->setString(self::TAG_ENTITY_TYPE_ID, $this->entityTypeId);

		//TODO: we can't set SpawnData here because it might crash the client if it's from a PC world (we need to implement full deserialization)

		$nbt->setFloat(self::TAG_ENTITY_SCALE, $this->displayEntityScale);
	}

	public function getEntityId() : string{
		return $this->entityTypeId;
	}

	public function setEntityId(string $id) : void{
		$this->entityTypeId = $id;
		$this->clearSpawnCompoundCache();
		$min = min($this->minSpawnDelay, $this->maxSpawnDelay);
		$max = max($this->minSpawnDelay, $this->maxSpawnDelay);
		$this->spawnDelay = mt_rand($min, $max);
		if(isset($this->position) && $this->position->isValid()){
			$this->position->getWorld()->setBlock($this->position, $this->getBlock(), false);
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}

	public function getSpawnDelay() : int{
		return $this->spawnDelay;
	}

	public function setSpawnDelay(int $delay) : void{
		$this->spawnDelay = $delay;
	}

	public function getMinSpawnDelay() : int{
		return $this->minSpawnDelay;
	}

	public function setMinSpawnDelay(int $delay) : void{
		$this->minSpawnDelay = $delay;
	}

	public function getMaxSpawnDelay() : int{
		return $this->maxSpawnDelay;
	}

	public function setMaxSpawnDelay(int $delay) : void{
		$this->maxSpawnDelay = $delay;
	}

	public function getRequiredPlayerRange() : int{
		return $this->requiredPlayerRange;
	}

	public function setRequiredPlayerRange(int $range) : void{
		$this->requiredPlayerRange = $range;
	}

	public function getMaxNearbyEntities() : int{
		return $this->maxNearbyEntities;
	}

	public function setMaxNearbyEntities(int $max) : void{
		$this->maxNearbyEntities = $max;
	}

	public function getSpawnRange() : int{
		return $this->spawnRange;
	}

	public function setSpawnRange(int $range) : void{
		$this->spawnRange = $range;
	}

	public function getSpawnCount() : int{
		return $this->spawnPerAttempt;
	}

	public function setSpawnCount(int $count) : void{
		$this->spawnPerAttempt = $count;
	}

	public function onUpdate() : int{
		if($this->closed || !isset($this->position) || !$this->position->isValid()){
			return 0;
		}

		if(!$this->hasNearbyPlayer()){
			return 20;
		}

		--$this->spawnDelay;
		if($this->spawnDelay <= 0){
			$this->spawnMobs();
			$min = min($this->minSpawnDelay, $this->maxSpawnDelay);
			$max = max($this->minSpawnDelay, $this->maxSpawnDelay);
			$this->spawnDelay = mt_rand($min, $max);
		}

		return 1;
	}

	protected function hasNearbyPlayer() : bool{
		if(!isset($this->position) || !$this->position->isValid()){
			return false;
		}

		$world = $this->position->getWorld();
		$maxDistanceSq = $this->requiredPlayerRange ** 2;

		foreach($world->getPlayers() as $player){
			if($player->isAlive() && !$player->isSpectator() && $player->getPosition()->distanceSquared($this->position) <= $maxDistanceSq){
				return true;
			}
		}

		return false;
	}

	public function spawnMobs() : void{
		if(!isset($this->position) || !$this->position->isValid()){
			return;
		}

		$world = $this->position->getWorld();
		if(!$world->isLoaded()){
			return;
		}

		if($this->entityTypeId === ":" || $this->entityTypeId === ""){
			return;
		}

		$pos = $this->position;
		$bb = new AxisAlignedBB(
			$pos->x - 4.5,
			$pos->y - 4.5,
			$pos->z - 4.5,
			$pos->x + 4.5,
			$pos->y + 4.5,
			$pos->z + 4.5
		);

		$nearbyEntities = $world->getNearbyEntities($bb);
		$matchingCount = 0;
		foreach($nearbyEntities as $entity){
			if($this->entityMatches($entity)){
				++$matchingCount;
			}
		}

		if($matchingCount >= $this->maxNearbyEntities){
			return;
		}

		$remainingCapacity = $this->maxNearbyEntities - $matchingCount;

		$server = Server::getInstance();
		$ignoreLight = $server?->getConfigGroup()->getPropertyBool(YmlServerProperties::SPAWNERS_IGNORE_LIGHT_LEVEL, false) ?? false;

		for($attempt = 0; $attempt < $this->spawnPerAttempt; ++$attempt){
			if($remainingCapacity <= 0){
				break;
			}

			$targetX = $pos->getFloorX() + mt_rand(-$this->spawnRange, $this->spawnRange);
			$targetY = $pos->getFloorY() + mt_rand(-1, 1);
			$targetZ = $pos->getFloorZ() + mt_rand(-$this->spawnRange, $this->spawnRange);

			if(!$world->isInWorld($targetX, $targetY, $targetZ) || !$world->isInWorld($targetX, $targetY - 1, $targetZ) || !$world->isInWorld($targetX, $targetY + 1, $targetZ)){
				continue;
			}

			$block = $world->getBlockAt($targetX, $targetY, $targetZ);
			$blockBelow = $world->getBlockAt($targetX, $targetY - 1, $targetZ);
			$blockAbove = $world->getBlockAt($targetX, $targetY + 1, $targetZ);

			if($block->isSolid() || !$blockBelow->isSolid() || $blockAbove->isSolid()){
				continue;
			}

			if(!$ignoreLight && $world->getFullLightAt($targetX, $targetY, $targetZ) > 7){
				continue;
			}

			$spawnPos = new Position($targetX + 0.5, $targetY, $targetZ + 0.5, $world);

			$yaw = (float) mt_rand(0, 359);
			$pitch = 0.0;
			$nbt = $this->spawnData !== null ? clone $this->spawnData : CompoundTag::create();
			$nbt->setString(EntityFactory::TAG_IDENTIFIER, $this->entityTypeId);
			$nbt->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag($spawnPos->x),
				new DoubleTag($spawnPos->y),
				new DoubleTag($spawnPos->z)
			]));
			$nbt->setTag(Entity::TAG_MOTION, new ListTag([
				new DoubleTag(0.0),
				new DoubleTag(0.0),
				new DoubleTag(0.0)
			]));
			$nbt->setTag(Entity::TAG_ROTATION, new ListTag([
				new FloatTag($yaw),
				new FloatTag($pitch)
			]));

			try{
				$entity = EntityFactory::getInstance()->createFromData($world, $nbt);
			}catch(\Throwable){
				$entity = null;
			}

			if($entity === null){
				continue;
			}

			if(count($world->getBlockCollisionBoxes($entity->getBoundingBox())) > 0){
				$entity->close();
				continue;
			}

			$ev = new SpawnerSpawnEvent($this, $entity, $spawnPos);
			$ev->call();
			if($ev->isCancelled()){
				$entity->close();
				continue;
			}

			$entity->spawnToAll();
			$world->addParticle($spawnPos, new MobSpawnParticle());
			--$remainingCapacity;
		}
	}

	protected function entityMatches(Entity $entity) : bool{
		if($entity->isFlaggedForDespawn() || !$entity->isAlive()){
			return false;
		}

		$targetId = strtolower($this->entityTypeId);
		if(str_starts_with($targetId, "minecraft:")){
			$targetIdWithoutPrefix = substr($targetId, 10);
		}else{
			$targetIdWithoutPrefix = $targetId;
		}

		$networkId = strtolower($entity::getNetworkTypeId());
		if($networkId === $targetId || $networkId === "minecraft:" . $targetIdWithoutPrefix){
			return true;
		}

		try{
			$saveId = strtolower(EntityFactory::getInstance()->getSaveId($entity::class));
			if($saveId === $targetId || $saveId === $targetIdWithoutPrefix){
				return true;
			}
		}catch(\Throwable){
		}

		return false;
	}
}
