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

use pocketmine\addon\AddonJson;
use pocketmine\addon\AddonManager;
use pocketmine\addon\AddonMath;
use pocketmine\addon\AddonTimings;
use pocketmine\addon\entity\ai\MobBrain;
use pocketmine\addon\event\AddonEntityTriggerEvent;
use pocketmine\addon\loot\LootContext;
use pocketmine\addon\script\ScriptHost;
use pocketmine\addon\entity\trade\TradeInventory;
use pocketmine\addon\entity\trade\TradeOffer;
use pocketmine\addon\entity\trade\TradeTable;
use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Egg;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Durable;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\particle\HeartParticle;
use function array_filter;
use function array_intersect;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_search;
use function array_values;
use function atan2;
use function cos;
use function count;
use function deg2rad;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function min;
use function mt_rand;
use function rtrim;
use function sin;
use function sqrt;
use function str_contains;
use function strtolower;
use const ARRAY_FILTER_USE_KEY;
use const M_PI;

/**
 * A custom entity, running its behavior pack definition.
 *
 * One class serves every add-on entity: the identifier lives on the instance (and in its saved NBT), and the
 * spawn packet carries it instead of a class-wide network ID.
 *
 * The data-driven model is implemented as in the game: base components plus the active component groups make
 * the current component set; events add and remove groups, set properties, trigger other events and run
 * commands; sensors, timers, the damage sensor and interactions fire events. Behaviors run in a MobBrain.
 *
 * Plugins can drive it the same way the pack does (triggerEvent(), addComponentGroup(), setProperty()), listen
 * to AddonEntityTriggerEvent to watch or cancel any event, and add goals with MobBrain::registerGoal().
 */
class AddonEntity extends Living{
	/** Save ID shared by all add-on entities; "AddonIdentifier" in the NBT says which one it is. */
	public const SAVE_ID = "amber:addon_entity";
	public const TAG_IDENTIFIER = "AddonIdentifier";
	private const TAG_GROUPS = "AddonGroups";
	private const TAG_PROPERTIES = "AddonProperties";
	private const TAG_STATE = "AddonState";
	private const TAG_TAGS = "AddonTags";
	private const TAG_INVENTORY = "AddonInventory";
	private const TAG_MAINHAND = "AddonMainHand";
	private const TAG_OFFHAND = "AddonOffHand";
	private const TAG_TRADES = "AddonTrades";

	/** Deepest chain of events triggering events before the chain is cut (packs can loop). */
	private const MAX_EVENT_DEPTH = 16;

	/** @var array<string, true> messages already logged, by definition */
	private static array $reported = [];

	/** @var list<string> */
	private array $activeGroups = [];
	/** @var mixed[] */
	private array $components = [];
	/** @var array<string, int|float|bool|string> */
	private array $properties = [];
	private bool $propertiesDirty = false;
	/** @var list<string> */
	private array $families = [];
	/** @var array<string, true> */
	private array $tags = [];

	private ?MobBrain $brain = null;
	private ?EntityFeatures $features = null;
	/** ticks until the next hop, for minecraft:movement.jump / movement.skip */
	private int $hopDelay = 0;
	private string $behaviorKey = "";

	private int $variant = 0;
	private int $markVariant = 0;
	private int $skinId = 0;
	private int $color = 0;
	private bool $tamed = false;
	private bool $sitting = false;
	private ?string $ownerName = null;

	private bool $spawned = false;
	private ?string $spawnEvent = "minecraft:entity_spawned";
	private int $eventDepth = 0;
	/** @var list<array{string, mixed, mixed}> data-driven and script property changes awaiting the next entity update */
	private array $pendingMutations = [];

	private string $timerKey = "";
	private ?int $timerDeadline = null;
	private int $ageTicks = 0;
	private int $loveTicks = 0;
	private int $breedCooldownUntil = 0;
	private int $interactCooldownUntil = 0;
	private int $fuseTicks = -1;
	private ?int $transformAt = null;
	private int $knockbackTicks = 0;

	private int $lastHurtTick = -1000;
	private ?int $lastHurtCause = null;
	/** @var \WeakReference<Entity>|null */
	private ?\WeakReference $lastDamager = null;

	private bool $playerNear = true;
	private bool $alwaysActive = false;

	/** @var array<int, Entity> seat index => rider */
	private array $riders = [];
	/** @var \WeakMap<Entity, AddonEntity>|null rider => the add-on entity it rides */
	private static ?\WeakMap $vehicles = null;
	/** @var array{float, float, bool} controlling rider's input: strafe, forward, jump */
	private array $riderInput = [0.0, 0.0, false];
	private int $riderInputTick = -1;

	/** @var \WeakReference<Entity>|null */
	private ?\WeakReference $leashHolder = null;

	private ?AddonEntityInventory $inventory = null;
	private ?Item $mainHand = null;
	private ?Item $offHand = null;
	private bool $equipped = false;

	/** @var list<TradeOffer>|null rolled on first trade */
	private ?array $tradeOffers = null;
	private int $tradeExp = 0;
	private ?TradeTable $tradeTable = null;
	/** @var \WeakReference<Player>|null the player trading right now */
	private ?\WeakReference $tradingWith = null;
	private const TRADE_NET_ID_BASE = 1000000;

	/** @var array<int, Player> players currently shown this entity's boss bar */
	private array $bossViewers = [];
	private bool $bossDirty = true;
	private int $projectileAge = 0;
	private bool $stuck = false;

	public static function getNetworkTypeId() : string{
		//never sent: sendSpawnPacket() uses the instance's own identifier
		return self::SAVE_ID;
	}

	public function __construct(Location $location, private AddonEntityDefinition $addonDefinition, ?CompoundTag $nbt = null){
		$this->components = $addonDefinition->resolveComponents([]);
		parent::__construct($location, $nbt);
	}

	public function getAddonDefinition() : AddonEntityDefinition{ return $this->addonDefinition; }

	/** The add-on identifier, e.g. "example:wisp". */
	public function getAddonIdentifier() : string{ return $this->addonDefinition->getIdentifier(); }

	public function getName() : string{
		return $this->addonDefinition->getDisplayName();
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		$box = $this->components["minecraft:collision_box"] ?? null;
		return new EntitySizeInfo(
			is_array($box) && is_numeric($box["height"] ?? null) ? max(0.01, (float) $box["height"]) : 1.8,
			is_array($box) && is_numeric($box["width"] ?? null) ? max(0.01, (float) $box["width"]) : 0.6
		);
	}

	protected function getInitialGravity() : float{
		return AddonEntityDefinition::gravityOf($this->components) ? parent::getInitialGravity() : 0.0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->getFeatures()->load($nbt);

		$groups = $nbt->getListTag(self::TAG_GROUPS);
		if($groups !== null){
			foreach($groups->getValue() as $tag){
				if($tag instanceof StringTag && isset($this->addonDefinition->getComponentGroups()[$tag->getValue()])){
					$this->activeGroups[] = $tag->getValue();
				}
			}
		}
		$this->properties = $this->addonDefinition->getDefaultProperties();
		$saved = $nbt->getCompoundTag(self::TAG_PROPERTIES);
		if($saved !== null){
			foreach($saved->getValue() as $name => $tag){
				if(isset($this->properties[$name])){
					$this->properties[$name] = $this->coerceProperty($name, $tag->getValue());
				}
			}
		}

		$state = $nbt->getCompoundTag(self::TAG_STATE);
		if($state !== null){
			$this->spawned = $state->getByte("Spawned", 0) === 1;
			$this->tamed = $state->getByte("Tamed", 0) === 1;
			$this->sitting = $state->getByte("Sitting", 0) === 1;
			$this->ageTicks = $state->getInt("Age", 0);
			$owner = $state->getString("Owner", "");
			$this->ownerName = $owner !== "" ? $owner : null;
		}
		$this->equipped = $state?->getByte("Equipped", 0) === 1;
		$items = $nbt->getListTag(self::TAG_INVENTORY);
		if($items !== null && ($inventory = $this->getInventory()) !== null){
			foreach($items->getValue() as $tag){
				if($tag instanceof CompoundTag){
					$slot = $tag->getByte(SavedItemStackData::TAG_SLOT, -1);
					try{
						if($slot >= 0 && $slot < $inventory->getSize()){
							$inventory->setItem($slot, Item::nbtDeserialize($tag));
						}
					}catch(SavedDataLoadingException){
						//an item this server no longer knows: dropped from the container
					}
				}
			}
		}
		$this->tradeExp = $state?->getInt("TradeExp", 0) ?? 0;
		$offers = $nbt->getListTag(self::TAG_TRADES);
		if($offers !== null){
			$this->tradeOffers = [];
			foreach($offers->getValue() as $i => $tag){
				$offer = $tag instanceof CompoundTag ? TradeOffer::load($tag, self::TRADE_NET_ID_BASE + $i) : null;
				if($offer !== null){
					$this->tradeOffers[] = $offer;
				}
			}
		}
		$hand = $nbt->getCompoundTag(self::TAG_MAINHAND);
		$this->mainHand = $hand !== null ? Item::nbtDeserialize($hand) : null;
		$off = $nbt->getCompoundTag(self::TAG_OFFHAND);
		$this->offHand = $off !== null ? Item::nbtDeserialize($off) : null;
		$tags = $nbt->getListTag(self::TAG_TAGS);
		foreach($tags?->getValue() ?? [] as $tag){
			if($tag instanceof StringTag){
				$this->tags[$tag->getValue()] = true;
			}
		}

		$this->refreshComponents([]);
		if($nbt->getTag("Health") === null){
			$this->setHealth((float) AddonEntityDefinition::startHealth($this->components));
		}
	}

	/** The components that run on their own: sounds, sensors, anger, spawning, schedules, teleporting... */
	public function getFeatures() : EntityFeatures{
		return $this->features ??= new EntityFeatures($this);
	}

	/** minecraft:transient entities are never saved. */
	public function canSaveWithChunk() : bool{
		return !isset($this->components["minecraft:transient"]) && parent::canSaveWithChunk();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT()->setString(self::TAG_IDENTIFIER, $this->addonDefinition->getIdentifier());
		$this->getFeatures()->save($nbt);
		$nbt->setTag(self::TAG_GROUPS, new ListTag(array_map(static fn(string $g) : StringTag => new StringTag($g), $this->activeGroups), NBT::TAG_String));
		$properties = CompoundTag::create();
		foreach($this->properties as $name => $value){
			$properties->setTag($name, match(true){
				is_bool($value) => new ByteTag($value ? 1 : 0),
				is_int($value) => new IntTag($value),
				is_float($value) => new FloatTag($value),
				default => new StringTag((string) $value),
			});
		}
		$nbt->setTag(self::TAG_PROPERTIES, $properties);

		$nbt->setTag(self::TAG_STATE, CompoundTag::create()
			->setByte("Spawned", 1)
			->setByte("Tamed", $this->tamed ? 1 : 0)
			->setByte("Sitting", $this->sitting ? 1 : 0)
			->setInt("Age", $this->ageTicks)
			->setByte("Equipped", $this->equipped ? 1 : 0)
			->setInt("TradeExp", $this->tradeExp)
			->setString("Owner", $this->ownerName ?? ""));
		if($this->inventory !== null){
			$items = [];
			foreach($this->inventory->getContents() as $slot => $item){
				$items[] = $item->nbtSerialize($slot);
			}
			$nbt->setTag(self::TAG_INVENTORY, new ListTag($items, NBT::TAG_Compound));
		}
		if($this->tradeOffers !== null){
			$nbt->setTag(self::TAG_TRADES, new ListTag(array_map(static fn(TradeOffer $o) : CompoundTag => $o->save(), $this->tradeOffers), NBT::TAG_Compound));
		}
		if($this->mainHand !== null && !$this->mainHand->isNull()){
			$nbt->setTag(self::TAG_MAINHAND, $this->mainHand->nbtSerialize());
		}
		if($this->offHand !== null && !$this->offHand->isNull()){
			$nbt->setTag(self::TAG_OFFHAND, $this->offHand->nbtSerialize());
		}
		$nbt->setTag(self::TAG_TAGS, new ListTag(array_map(static fn(string $t) : StringTag => new StringTag($t), array_keys($this->tags)), NBT::TAG_String));
		return $nbt;
	}

	/** @return list<string> */
	public function getActiveComponentGroups() : array{ return $this->activeGroups; }

	/** @return mixed[] the components in effect right now */
	public function getComponents() : array{ return $this->components; }

	public function hasComponent(string $name) : bool{
		return isset($this->components[str_contains($name, ":") ? $name : "minecraft:$name"]);
	}

	/** @return mixed[]|null */
	public function getComponent(string $name) : mixed{
		return $this->components[$name] ?? null;
	}

	public function addComponentGroup(string ...$groups) : void{
		$this->changeGroups($groups, []);
	}

	public function removeComponentGroup(string ...$groups) : void{
		$this->changeGroups([], $groups);
	}

	/**
	 * @param list<string> $add
	 * @param list<string> $remove
	 */
	private function changeGroups(array $add, array $remove) : void{
		$before = $this->activeGroups;
		foreach($remove as $group){
			$index = array_search($group, $this->activeGroups, true);
			if($index !== false){
				unset($this->activeGroups[$index]);
			}
		}
		$this->activeGroups = array_values($this->activeGroups);
		foreach($add as $group){
			if(!in_array($group, $this->activeGroups, true)){
				if(isset($this->addonDefinition->getComponentGroups()[$group])){
					$this->activeGroups[] = $group;
				}else{
					$this->report("component group \"$group\" does not exist");
				}
			}
		}
		if($before !== $this->activeGroups){
			$old = $this->components;
			$this->components = $this->addonDefinition->resolveComponents($this->activeGroups);
			$this->refreshComponents($old);
		}
	}

	/**
	 * Applies the current component set: size, health, physics, flags, behaviors, and one-shot components
	 * that were just added.
	 *
	 * @param mixed[] $old the previous component set
	 */
	private function refreshComponents(array $old) : void{
		$c = $this->components;

		$this->setSize($this->getInitialSizeInfo());
		$scale = AddonEntityDefinition::scale($c);
		if($scale !== $this->getScale()){
			$this->setScale(max(0.01, $scale));
		}
		$this->setHasGravity(AddonEntityDefinition::gravityOf($c) && !isset($c["minecraft:can_fly"]));
		if(isset($c["minecraft:projectile"])){
			$projectile = is_array($c["minecraft:projectile"]) ? $c["minecraft:projectile"] : [];
			$this->gravity = is_numeric($projectile["gravity"] ?? null) ? (float) $projectile["gravity"] : 0.05;
			$this->drag = 1 - (is_numeric($projectile["inertia"] ?? null) ? (float) $projectile["inertia"] : 0.99);
		}

		$max = AddonEntityDefinition::maxHealth($c);
		if($max !== $this->getMaxHealth()){
			$this->setMaxHealth($max);
			if($this->getHealth() > $max){
				$this->setHealth((float) $max);
			}
		}
		$this->getAttributeMap()->get(Attribute::KNOCKBACK_RESISTANCE)?->setValue(min(1.0, max(0.0, AddonEntityDefinition::knockbackResistance($c))));
		$movement = AddonJson::scalar($c["minecraft:movement"] ?? null);
		if(is_numeric($movement)){
			$this->setMovementSpeed(max(0.0, (float) $movement));
		}

		$this->families = AddonEntityDefinition::families($c);
		$this->variant = self::intValue($c["minecraft:variant"] ?? null);
		$this->markVariant = self::intValue($c["minecraft:mark_variant"] ?? null);
		$this->skinId = self::intValue($c["minecraft:skin_id"] ?? null);
		$this->color = self::intValue($c["minecraft:color"] ?? null);
		$this->setCanClimb(isset($c["minecraft:can_climb"]));
		$nameable = $c["minecraft:nameable"] ?? null;
		if(is_array($nameable) && (bool) ($nameable["always_show"] ?? false)){
			$this->setNameTagAlwaysVisible();
		}
		$this->networkPropertiesDirty = true;

		//behaviors: rebuilt only when the behavior set changed
		$behaviors = array_filter($c, static fn($k) : bool => str_contains((string) $k, "behavior.") || str_contains((string) $k, "navigation.") || str_contains((string) $k, "movement"), ARRAY_FILTER_USE_KEY);
		$key = (string) json_encode($behaviors);
		if($key !== $this->behaviorKey){
			$this->behaviorKey = $key;
			if($this->brain === null){
				$this->brain = new MobBrain($this, $c);
			}else{
				$this->brain->rebuild($c);
			}
			foreach($this->brain->getUnsupported() as $name){
				$this->report("behavior $name is not implemented; register one with MobBrain::registerGoal()");
			}
		}

		//timer: restarts whenever its definition changes
		$timer = $c["minecraft:timer"] ?? null;
		$timerKey = $timer === null ? "" : (string) json_encode($timer);
		if($timerKey !== $this->timerKey){
			$this->timerKey = $timerKey;
			$this->timerDeadline = is_array($timer) ? $this->now() + $this->timerLength($timer) : null;
		}

		//one-shot components, applied when added
		if(isset($c["minecraft:spell_effects"]) && ($old["minecraft:spell_effects"] ?? null) !== $c["minecraft:spell_effects"]){
			$this->applySpellEffects($c["minecraft:spell_effects"]);
		}
		if(isset($c["minecraft:transformation"]) && !isset($old["minecraft:transformation"])){
			$delay = $c["minecraft:transformation"]["delay"] ?? 0;
			$seconds = is_array($delay) ? (float) ($delay["value"] ?? 0) : (is_numeric($delay) ? (float) $delay : 0.0);
			$this->transformAt = $this->now() + (int) ($seconds * 20);
		}elseif(!isset($c["minecraft:transformation"])){
			$this->transformAt = null;
		}
		$explode = $c["minecraft:explode"] ?? null;
		if(is_array($explode) && !isset($old["minecraft:explode"]) && (bool) ($explode["fuse_lit"] ?? false)){
			$this->lightFuse();
		}elseif($explode === null){
			$this->fuseTicks = -1;
		}
		if(isset($c["minecraft:instant_despawn"])){
			$this->flagForDespawn();
		}
		$this->getFeatures()->componentsChanged($old, $c);
	}

	private static function intValue(mixed $component) : int{
		$value = AddonJson::scalar($component);
		return is_numeric($value) ? (int) $value : 0;
	}

	/**
	 * Runs one of the definition's events (or a built-in minecraft:* one). Returns false when the event does not
	 * exist, was cancelled by a plugin, or the chain was too deep.
	 */
	public function triggerEvent(string $name, ?Entity $other = null) : bool{
		$event = $this->addonDefinition->getEvent($name);
		if($event === null){
			if(!str_contains($name, "minecraft:")){
				$this->report("event \"$name\" does not exist");
			}
			return false;
		}
		if($this->eventDepth >= self::MAX_EVENT_DEPTH){
			$this->report("event chain through \"$name\" is deeper than " . self::MAX_EVENT_DEPTH . " and was cut");
			return false;
		}
		$ev = new AddonEntityTriggerEvent($this, $name, $other);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}
		AddonManager::getInstance()?->getScriptHost()?->onEntityTrigger($this, $name);
		$this->eventDepth++;
		try{
			$this->runEventNode($event, new FilterContext($this, $other));
		}finally{
			$this->eventDepth--;
		}
		return true;
	}

	/**
	 * Runs an inline trigger definition (a string event name or {"event", "target", "filters"}), as found in
	 * sensors, behaviors and interactions.
	 */
	public function triggerEventDefinition(mixed $trigger, ?Entity $other = null) : void{
		if(is_string($trigger)){
			$this->triggerEvent($trigger, $other);
			return;
		}
		if(!is_array($trigger)){
			return;
		}
		$context = new FilterContext($this, $other);
		if(isset($trigger["filters"]) && !EntityFilter::test($trigger["filters"], $context, $this->reporter())){
			return;
		}
		$name = $trigger["event"] ?? null;
		if(!is_string($name)){
			return;
		}
		$target = $context->subject(is_string($trigger["target"] ?? null) ? $trigger["target"] : "self");
		if($target instanceof AddonEntity){
			$target->triggerEvent($name, $target === $this ? $other : $this);
		}
	}

	/** @param mixed[] $node */
	private function runEventNode(array $node, FilterContext $context) : void{
		if(isset($node["filters"]) && !EntityFilter::test($node["filters"], $context, $this->reporter())){
			return;
		}
		$add = self::groupList($node["add"] ?? null);
		$remove = self::groupList($node["remove"] ?? null);
		if($add !== [] || $remove !== []){
			$this->pendingMutations[] = ["groups", $add, $remove];
		}
		if(is_array($node["set_property"] ?? null)){
			foreach($node["set_property"] as $property => $value){
				$property = (string) $property;
				$definition = $this->addonDefinition->getProperties()[$property] ?? [];
				//an enum value is written as its plain name ("angry"); anything else is a Molang expression
				$literal = ($definition["type"] ?? null) === "enum" && is_string($value) && in_array($value, (array) ($definition["values"] ?? []), true);
				$this->pendingMutations[] = ["property", $property, $literal ? $value : Molang::evaluate($value, $this, $context)];
			}
		}
		if(isset($node["reset_target"])){
			$this->setTargetEntity(null);
		}
		if(isset($node["queue_command"])){
			$commands = is_array($node["queue_command"]) ? ($node["queue_command"]["command"] ?? []) : $node["queue_command"];
			foreach(is_array($commands) ? $commands : [$commands] as $command){
				if(is_string($command)){
					$this->runCommand($command);
				}
			}
		}
		if(isset($node["trigger"])){
			$trigger = $node["trigger"];
			$this->triggerEventDefinition($trigger, $context->other);
		}
		if(is_array($node["sequence"] ?? null)){
			foreach($node["sequence"] as $step){
				if(is_array($step)){
					$this->runEventNode($step, $context);
				}
			}
		}
		if(is_array($node["first_valid"] ?? null)){
			foreach($node["first_valid"] as $step){
				if(is_array($step) && (!isset($step["filters"]) || EntityFilter::test($step["filters"], $context, $this->reporter()))){
					$this->runEventNode($step, $context);
					break;
				}
			}
		}
		if(is_array($node["randomize"] ?? null)){
			$choices = array_values(array_filter($node["randomize"], static fn($c) : bool => is_array($c)));
			$total = 0.0;
			foreach($choices as $choice){
				$total += max(0.0, (float) ($choice["weight"] ?? 1));
			}
			$roll = AddonMath::randomFloat() * $total;
			foreach($choices as $choice){
				$roll -= max(0.0, (float) ($choice["weight"] ?? 1));
				if($roll <= 0){
					$this->runEventNode($choice, $context);
					break;
				}
			}
		}
	}

	/** @return list<string> */
	private static function groupList(mixed $value) : array{
		$groups = is_array($value) ? ($value["component_groups"] ?? []) : [];
		$out = [];
		foreach(is_array($groups) ? $groups : [$groups] as $group){
			if(is_string($group)){
				$out[] = $group;
			}
		}
		return $out;
	}

	/**
	 * Runs a command from an event (queue_command) or a script, through the server's command map, so plugin
	 * commands work too; "@s" is this entity, and "~ ~ ~" its position.
	 */
	public function runCommand(string $command) : bool{
		return (AddonManager::getInstance()?->getCommandBridge()->run($command, $this) ?? 0) > 0;
	}

	public function hasProperty(string $name) : bool{ return isset($this->properties[$name]); }

	public function getProperty(string $name) : int|float|bool|string|null{ return $this->properties[$name] ?? null; }

	/** @return array<string, int|float|bool|string> */
	public function getProperties() : array{ return $this->properties; }

	public function setProperty(string $name, mixed $value) : void{
		if(!isset($this->properties[$name])){
			$this->report("entity property \"$name\" is not declared");
			return;
		}
		$value = $this->coerceProperty($name, $value);
		if($this->properties[$name] !== $value){
			$this->properties[$name] = $value;
			$this->propertiesDirty = true;
		}
	}

	/** validates a script api assignment now and applies it at the next entity update */
	public function setScriptProperty(string $name, mixed $value) : void{
		$definition = $this->addonDefinition->getProperties()[$name] ?? null;
		if(!is_array($definition) || !array_key_exists($name, $this->properties)){
			throw new \InvalidArgumentException("entity property \"$name\" is not declared");
		}
		$type = (string) ($definition["type"] ?? "int");
		$valid = match($type){
			"bool" => is_bool($value),
			"int" => is_int($value),
			"float" => is_float($value) || is_int($value),
			"enum" => is_string($value) && in_array($value, $definition["values"] ?? [], true),
			default => false,
		};
		if(!$valid){
			throw new \InvalidArgumentException("invalid value for entity property \"$name\"");
		}
		$range = $definition["range"] ?? null;
		if(($type === "int" || $type === "float") && (!is_finite((float) $value) || (is_array($range) && ($value < $range[0] || $value > ($range[1] ?? $range[0]))))){
			throw new \OutOfRangeException("entity property \"$name\" is outside its range");
		}
		$this->pendingMutations[] = ["property", $name, $value];
	}

	/** returns the configured default now and applies the reset at the next entity update */
	public function resetScriptProperty(string $name) : int|float|bool|string{
		$defaults = $this->addonDefinition->getDefaultProperties();
		if(!array_key_exists($name, $defaults) || !array_key_exists($name, $this->properties)){
			throw new \InvalidArgumentException("entity property \"$name\" is not declared");
		}
		$default = $defaults[$name];
		$this->pendingMutations[] = ["property", $name, $default];
		return $default;
	}

	private function coerceProperty(string $name, mixed $value) : int|float|bool|string{
		$definition = $this->addonDefinition->getProperties()[$name] ?? [];
		$type = (string) ($definition["type"] ?? "int");
		$range = is_array($definition["range"] ?? null) ? $definition["range"] : null;
		switch($type){
			case "bool":
				return is_string($value) ? $value === "true" : (bool) $value;
			case "float":
				$v = is_numeric($value) ? (float) $value : (is_bool($value) ? ($value ? 1.0 : 0.0) : 0.0);
				return $range !== null ? max((float) $range[0], min((float) ($range[1] ?? $range[0]), $v)) : $v;
			case "enum":
				$values = is_array($definition["values"] ?? null) ? $definition["values"] : [];
				$v = (string) $value;
				return in_array($v, $values, true) ? $v : (string) ($this->properties[$name] ?? ($values[0] ?? ""));
			default:
				$v = is_numeric($value) ? (int) floor((float) $value) : (is_bool($value) ? ($value ? 1 : 0) : 0);
				return $range !== null ? max((int) $range[0], min((int) ($range[1] ?? $range[0]), $v)) : $v;
		}
	}

	/** Property values in the order the client was told about them (AddonManager::getActorPropertyNbt()). */
	private function propertySyncData() : PropertySyncData{
		$ints = [];
		$floats = [];
		$index = 0;
		foreach($this->addonDefinition->getProperties() as $name => $definition){
			$value = $this->properties[$name] ?? 0;
			$type = (string) ($definition["type"] ?? "int");
			if($type === "float"){
				$floats[$index] = (float) $value;
			}elseif($type === "enum"){
				$found = array_search($value, is_array($definition["values"] ?? null) ? $definition["values"] : [], true);
				$ints[$index] = $found === false ? 0 : (int) $found;
			}else{
				$ints[$index] = (int) $value;
			}
			$index++;
		}
		return new PropertySyncData($ints, $floats);
	}

	private function flushProperties() : void{
		if(!$this->propertiesDirty){
			return;
		}
		$this->propertiesDirty = false;
		$packet = SetActorDataPacket::create($this->getId(), [], $this->propertySyncData(), 0);
		foreach($this->getViewers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket($packet);
		}
	}

	/** @return list<string> */
	public function getFamilies() : array{ return $this->families; }

	public function getVariant() : int{ return $this->variant; }

	public function getMarkVariant() : int{ return $this->markVariant; }

	public function getSkinId() : int{ return $this->skinId; }

	public function getColor() : int{ return $this->color; }

	public function isBaby() : bool{ return isset($this->components["minecraft:is_baby"]); }

	public function isTamed() : bool{ return $this->tamed || isset($this->components["minecraft:is_tamed"]); }

	public function isSitting() : bool{ return $this->sitting; }

	public function setSitting(bool $sitting) : void{
		$this->sitting = $sitting;
		$this->networkPropertiesDirty = true;
	}

	public function hasTag(string $tag) : bool{ return isset($this->tags[$tag]); }

	public function addTag(string $tag) : void{ $this->tags[$tag] = true; }

	public function removeTag(string $tag) : void{ unset($this->tags[$tag]); }

	/** @return list<string> */
	public function getTags() : array{ return array_keys($this->tags); }

	public function isInLove() : bool{ return $this->loveTicks > 0; }

	private function canEnterLoveMode(int $now) : bool{
		return !$this->isBaby() && !$this->isInLove() && $now >= $this->breedCooldownUntil;
	}

	public function getTicksLived() : int{ return $this->ticksLived; }

	public function getLastHurtTick() : int{ return $this->lastHurtTick; }

	public function getLastHurtCause() : ?int{ return $this->lastHurtCause; }

	public function getLastDamager() : ?Entity{ return $this->lastDamager?->get(); }

	public function getBrain() : MobBrain{
		return $this->brain ??= new MobBrain($this, $this->components);
	}

	/**
	 * Mobs think at full rate only near players (MobBrain::ACTIVE_RANGE) and once a second otherwise. Set this for
	 * mobs that must act with nobody watching (NPC fights, arenas, tests).
	 */
	public function setAlwaysActive(bool $active) : void{
		$this->alwaysActive = $active;
		$this->playerNear = $active || $this->playerNear;
	}

	/** Makes the entity fire this event instead of minecraft:entity_spawned when it first ticks. */
	public function setSpawnEvent(?string $event) : void{ $this->spawnEvent = $event; }

	public function setOwner(?Player $owner) : void{
		$this->setOwningEntity($owner);
		$this->ownerName = $owner?->getName();
	}

	/** Tames the mob for a player (minecraft:tamemount, scripts and plugins). */
	public function tameBy(Player $owner) : void{
		$this->tamed = true;
		$this->setOwner($owner);
		$this->networkPropertiesDirty = true;
	}

	private function now() : int{
		return $this->server->getTick();
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);
		if(!$this->spawned){
			$this->spawned = true;
			if($this->spawnEvent !== null){
				$this->triggerEvent($this->spawnEvent);
			}
		}
		if(!$this->equipped){
			$this->equipped = true;
			$this->rollEquipment();
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$this->commitPendingMutations();
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive() || $this->closed){
			return $hasUpdate;
		}
		AddonTimings::$entities->startTiming();
		try{
			return $this->runtimeTick($tickDiff);
		}finally{
			AddonTimings::$entities->stopTiming();
		}
	}

	private function commitPendingMutations() : void{
		$pending = $this->pendingMutations;
		$this->pendingMutations = [];
		foreach($pending as $index => [$kind, $first, $second]){
			try{
				if($kind === "groups"){
					$this->changeGroups($first, $second);
				}else{
					$this->setProperty($first, $second);
				}
			}catch(\Throwable $error){
				$this->pendingMutations = array_merge(array_slice($pending, $index + 1), $this->pendingMutations);
				throw $error;
			}
		}
	}

	private function runtimeTick(int $tickDiff) : bool{
		$now = $this->now();
		$slot = $this->getId() % 20;
		$c = $this->components;

		if($now % 20 === $slot){
			$this->playerNear = $this->alwaysActive || EntityFilter::nearestPlayer($this, MobBrain::ACTIVE_RANGE) !== null;
			$this->slowTick();
			if($this->closed){
				return false;
			}
		}
		if(isset($c["minecraft:projectile"])){
			$this->tickProjectile();
			return true;
		}
		if($now % 5 === $slot % 5 && isset($c["minecraft:environment_sensor"])){
			foreach(self::triggers($c["minecraft:environment_sensor"]) as $trigger){
				$this->triggerEventDefinition($trigger);
			}
		}
		if($this->timerDeadline !== null && $now >= $this->timerDeadline && is_array($c["minecraft:timer"] ?? null)){
			$timer = $c["minecraft:timer"];
			$this->timerDeadline = (bool) ($timer["looping"] ?? true) ? $now + $this->timerLength($timer) : null;
			$this->triggerEventDefinition($timer["time_down_event"] ?? null);
		}
		if($this->isBaby() && is_array($c["minecraft:ageable"] ?? null)){
			$this->ageTicks += $tickDiff;
			$duration = (float) ($c["minecraft:ageable"]["duration"] ?? 1200);
			if($duration >= 0 && $this->ageTicks >= $duration * 20){
				$this->ageTicks = 0;
				$this->triggerEventDefinition($c["minecraft:ageable"]["grow_up"] ?? null);
			}
		}
		if($this->loveTicks > 0){
			$this->loveTicks -= $tickDiff;
			if($this->loveTicks % 10 === 0){
				$this->getWorld()->addParticle($this->location->add(AddonMath::randomFloat() - 0.5, $this->size->getHeight() + 0.3, AddonMath::randomFloat() - 0.5), new HeartParticle());
			}
			if($this->loveTicks <= 0){
				$this->networkPropertiesDirty = true;
			}
		}
		if($this->fuseTicks > 0 && --$this->fuseTicks === 0){
			$this->explode();
			return false;
		}
		if($this->transformAt !== null && $now >= $this->transformAt){
			$this->transformAt = null;
			$this->transform();
			return false;
		}
		if($this->knockbackTicks > 0){
			$this->knockbackTicks -= $tickDiff;
		}
		if($this->brain !== null && $this->brain->hasGoals()){
			AddonTimings::$ai->startTiming();
			$this->brain->tick($this->playerNear);
			AddonTimings::$ai->stopTiming();
		}
		$this->getFeatures()->tick($now);
		if($this->riders !== []){
			$this->updateRiders();
		}
		if($this->tradingWith !== null){
			$trader = $this->tradingWith->get();
			if($trader === null || !$trader->isConnected() || !($trader->getCurrentWindow() instanceof TradeInventory)){
				$this->tradingWith = null;
			}else{
				$this->brain?->getNavigator()->stop();
				$this->lookAt($trader->getEyePos());
			}
		}
		if($this->leashHolder !== null){
			$this->updateLeash();
		}
		if($this->bossDirty && $this->bossViewers !== []){
			$this->bossDirty = false;
			$packet = BossEventPacket::healthPercent($this->getId(), $this->getHealth() / max(1, $this->getMaxHealth()));
			foreach($this->bossViewers as $viewer){
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		}
		$this->flushProperties();
		return true;
	}

	/** Once a second: despawning, daylight, owner lookup, damage and effect auras. */
	private function slowTick() : void{
		$c = $this->components;
		$this->updateBossBar();
		if(is_array($c["minecraft:despawn"] ?? null) || isset($c["minecraft:despawn"])){
			if($this->checkDespawn(is_array($c["minecraft:despawn"]) ? $c["minecraft:despawn"] : [])){
				return;
			}
		}
		if(isset($c["minecraft:burns_in_daylight"]) && !$this->isFireProof() && EntityFilter::isDay($this->getWorld())){
			$pos = $this->location;
			$highest = $this->getWorld()->getHighestBlockAt((int) floor($pos->x), (int) floor($pos->z));
			if(($highest === null || $highest < $pos->y + $this->getEyeHeight()) && !$this->getWorld()->getBlock($pos) instanceof Water && $this->getArmorInventory()->getHelmet()->isNull()){
				$this->setOnFire(8);
			}
		}
		if($this->ownerName !== null && $this->getOwningEntityId() === null){
			$owner = $this->server->getPlayerExact($this->ownerName);
			if($owner !== null && $owner->getWorld() === $this->getWorld()){
				$this->setOwningEntity($owner);
			}
		}
		if(is_array($c["minecraft:hurt_on_condition"] ?? null)){
			foreach(self::listOf($c["minecraft:hurt_on_condition"]["damage_conditions"] ?? []) as $condition){
				if(EntityFilter::test($condition["filters"] ?? null, new FilterContext($this), $this->reporter())){
					$cause = EntityFilter::DAMAGE_CAUSES[(string) ($condition["cause"] ?? "lava")] ?? EntityDamageEvent::CAUSE_CUSTOM;
					$this->attack(new EntityDamageEvent($this, $cause < 0 ? EntityDamageEvent::CAUSE_CUSTOM : $cause, (float) ($condition["damage_per_tick"] ?? 4)));
				}
			}
		}
		if(is_array($c["minecraft:mob_effect"] ?? null)){
			$this->applyAura($c["minecraft:mob_effect"], false);
		}
		if(is_array($c["minecraft:area_attack"] ?? null)){
			$this->applyAura($c["minecraft:area_attack"], true);
		}
	}

	/** @param mixed[] $despawn */
	private function checkDespawn(array $despawn) : bool{
		if(isset($this->components["minecraft:persistent"]) || $this->getNameTag() !== "" || $this->isTamed() || $this->alwaysActive){
			return false;
		}
		//as in the game, mobs only despawn relative to players: with nobody in the world, nothing despawns
		if($this->getWorld()->getPlayers() === []){
			return false;
		}
		if(isset($despawn["filters"]) && !EntityFilter::test($despawn["filters"], new FilterContext($this), $this->reporter())){
			return false;
		}
		$distance = is_array($despawn["despawn_from_distance"] ?? null) ? $despawn["despawn_from_distance"] : [];
		$min = (float) ($distance["min_distance"] ?? 32);
		$max = (float) ($distance["max_distance"] ?? 128);
		$nearest = EntityFilter::nearestPlayer($this, $max);
		$gone = $nearest === null;
		if(!$gone && $nearest->getPosition()->distanceSquared($this->location) > $min * $min){
			//the game rolls 1 in 800 each tick; this runs once a second
			$gone = (bool) ($despawn["despawn_from_chance"] ?? true) && mt_rand(1, 40) === 1;
		}
		if($gone){
			$this->flagForDespawn();
		}
		return $gone;
	}

	/** @param mixed[] $aura */
	private function applyAura(array $aura, bool $damage) : void{
		$range = (float) ($aura[$damage ? "damage_range" : "effect_range"] ?? 0.2);
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy($range, $range, $range), $this) as $other){
			if(!$other instanceof Living || !$other->isAlive() || ($other instanceof Player && !$other->hasFiniteResources())){
				continue;
			}
			if(isset($aura["entity_filter"]) && !EntityFilter::test($aura["entity_filter"], new FilterContext($this, $other), $this->reporter())){
				continue;
			}
			if($damage){
				$cause = EntityFilter::DAMAGE_CAUSES[(string) ($aura["cause"] ?? "entity_attack")] ?? EntityDamageEvent::CAUSE_ENTITY_ATTACK;
				$other->attack(new EntityDamageByEntityEvent($this, $other, $cause < 0 ? EntityDamageEvent::CAUSE_ENTITY_ATTACK : $cause, (float) ($aura["damage_per_tick"] ?? 2), [], 0.0));
			}else{
				$effect = StringToEffectParser::getInstance()->parse((string) ($aura["mob_effect"] ?? ""));
				if($effect !== null){
					$other->getEffects()->add(new EffectInstance($effect, (int) ((float) ($aura["effect_time"] ?? 10) * 20)));
				}
			}
		}
	}

	/** @param mixed[] $timer */
	private function timerLength(array $timer) : int{
		if(is_array($timer["random_time_choices"] ?? null) && $timer["random_time_choices"] !== []){
			$choices = $timer["random_time_choices"];
			$total = 0.0;
			foreach($choices as $choice){
				$total += (float) ($choice["weight"] ?? 1);
			}
			$roll = AddonMath::randomFloat() * $total;
			foreach($choices as $choice){
				$roll -= (float) ($choice["weight"] ?? 1);
				if($roll <= 0){
					return max(1, (int) ((float) ($choice["value"] ?? 1) * 20));
				}
			}
		}
		$time = $timer["time"] ?? 0;
		if(is_array($time)){
			$a = (float) ($time[0] ?? 0);
			$b = (float) ($time[1] ?? $a);
			return max(1, (int) (($a + AddonMath::randomFloat() * max(0.0, $b - $a)) * 20));
		}
		return max(1, (int) (Molang::number($time, $this) * 20));
	}

	/**
	 * Trigger entries of a sensor-like component: {"triggers": [...]}, {"triggers": {...}}, or the entry itself.
	 *
	 * @return list<mixed>
	 */
	private static function triggers(mixed $component) : array{
		if(!is_array($component)){
			return [];
		}
		$triggers = $component["triggers"] ?? (isset($component["event"]) || isset($component["on_damage"]) ? $component : null);
		if(!is_array($triggers)){
			return [];
		}
		return array_is_list($triggers) ? $triggers : [$triggers];
	}

	/** @return list<mixed[]> */
	private static function listOf(mixed $value) : array{
		if(!is_array($value)){
			return [];
		}
		return array_values(array_filter(array_is_list($value) ? $value : [$value], static fn($v) : bool => is_array($v)));
	}

	public function hasMovementUpdate() : bool{
		return parent::hasMovementUpdate() || ($this->brain !== null && $this->brain->isNavigating());
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		$from = $this->location->asVector3();
		parent::move($dx, $dy, $dz);
		AddonManager::getInstance()?->checkStep($this, $from, $this->location);
	}

	protected function tryChangeMovement() : void{
		parent::tryChangeMovement();
		if($this->knockbackTicks === 0 && $this->steerByRider()){
			return;
		}
		if($this->brain === null || $this->knockbackTicks > 0 || isset($this->components["minecraft:projectile"])){
			return;
		}
		$navigator = $this->brain->getNavigator();
		if(!$navigator->isMoving()){
			return;
		}
		$hop = $this->components["minecraft:movement.jump"] ?? $this->components["minecraft:movement.skip"] ?? null;
		if($hop !== null && !$navigator->isDirect()){
			if(!$this->onGround){
				return; //keep the hop's momentum
			}
			if($this->hopDelay > 0){
				$this->hopDelay--;
				$this->motion = new Vector3(0, $this->motion->y, 0);
				return;
			}
			$delay = is_array($hop) ? ($hop["jump_delay"] ?? [0, 0]) : [0, 0];
			$this->hopDelay = is_array($delay) ? mt_rand((int) ((float) ($delay[0] ?? 0) * 20), (int) ((float) ($delay[1] ?? $delay[0] ?? 0) * 20)) : (int) ((float) $delay * 20);
			$this->jump();
			$this->motion = new Vector3($navigator->getWantedX() * 2, $this->motion->y, $navigator->getWantedZ() * 2);
			return;
		}
		if($navigator->isDirect()){
			$this->motion = new Vector3($navigator->getWantedX(), $navigator->getWantedY(), $navigator->getWantedZ());
		}elseif($this->onGround || $this->getWorld()->getBlock($this->location) instanceof Water){
			$this->motion = new Vector3($navigator->getWantedX(), $this->motion->y, $navigator->getWantedZ());
		}else{
			//a little air control, as mobs have
			$this->motion = new Vector3(
				$this->motion->x + ($navigator->getWantedX() - $this->motion->x) * 0.1,
				$this->motion->y,
				$this->motion->z + ($navigator->getWantedZ() - $this->motion->z) * 0.1
			);
		}
	}

	public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void{
		parent::knockBack($x, $z, $force, $verticalLimit);
		$this->knockbackTicks = 8;
	}

	public function getJumpVelocity() : float{
		$jump = $this->components["minecraft:jump.static"] ?? null;
		return is_array($jump) && is_numeric($jump["jump_power"] ?? null) ? (float) $jump["jump_power"] : parent::getJumpVelocity();
	}

	public function canBreathe() : bool{
		$breathable = $this->components["minecraft:breathable"] ?? null;
		if(!is_array($breathable)){
			return parent::canBreathe();
		}
		return $this->isUnderwater() ? (bool) ($breathable["breathes_water"] ?? false) || parent::canBreathe() : (bool) ($breathable["breathes_air"] ?? true);
	}

	public function isFireProof() : bool{
		return isset($this->components["minecraft:fire_immune"]);
	}

	public function attack(EntityDamageEvent $source) : void{
		$damager = $source instanceof EntityDamageByEntityEvent ? $source->getDamager() : null;
		//minecraft:cannot_be_attacked, unless the attacker has minecraft:ignore_cannot_be_attacked (and passes its filters)
		if($damager !== null && isset($this->components["minecraft:cannot_be_attacked"])){
			$ignore = $damager instanceof AddonEntity ? $damager->getComponent("minecraft:ignore_cannot_be_attacked") : null;
			if($ignore === null || (is_array($ignore) && isset($ignore["filters"]) && !EntityFilter::test($ignore["filters"], new FilterContext($damager, $this), $this->reporter()))){
				$source->cancel();
				return;
			}
		}
		$context = new FilterContext($this, $damager, $damager, $source->getCause(), $source->getFinalDamage() >= $this->getHealth());
		foreach(self::triggers($this->components["minecraft:damage_sensor"] ?? null) as $trigger){
			if(!is_array($trigger)){
				continue;
			}
			$cause = EntityFilter::DAMAGE_CAUSES[(string) ($trigger["cause"] ?? "all")] ?? null;
			if($cause !== -1 && $cause !== $source->getCause()){
				continue;
			}
			$onDamage = is_array($trigger["on_damage"] ?? null) ? $trigger["on_damage"] : [];
			if(isset($onDamage["filters"]) && !EntityFilter::test($onDamage["filters"], $context, $this->reporter())){
				continue;
			}
			if(isset($onDamage["event"])){
				$this->triggerEventDefinition($onDamage, $damager);
			}
			if(is_numeric($trigger["damage_multiplier"] ?? null)){
				$source->setBaseDamage($source->getBaseDamage() * (float) $trigger["damage_multiplier"]);
			}
			if(is_numeric($trigger["damage_modifier"] ?? null)){
				$source->setBaseDamage(max(0.0, $source->getBaseDamage() + (float) $trigger["damage_modifier"]));
			}
			$deals = $trigger["deals_damage"] ?? true;
			if($deals === false || $deals === "no" || $deals === "no_but_side_effects_apply"){
				$source->cancel();
			}
			break;
		}
		if(isset($this->components["minecraft:projectile"]) && $source->getCause() !== EntityDamageEvent::CAUSE_VOID){
			$source->cancel();
		}

		parent::attack($source);
		if($source->isCancelled()){
			return;
		}
		$this->lastHurtTick = $this->now();
		$this->lastHurtCause = $source->getCause();
		if($this->isAlive()){
			$this->getFeatures()->playSound("hurt");
		}
		if($damager !== null){
			$this->lastDamager = \WeakReference::create($damager);
			if($this->sitting && $damager === $this->getOwningEntity()){
				$this->setSitting(false);
			}
		}
		$this->triggerEventDefinition($this->components["minecraft:on_hurt"] ?? null, $damager);
		if($damager instanceof Player){
			$this->triggerEventDefinition($this->components["minecraft:on_hurt_by_player"] ?? null, $damager);
		}
	}

	public function setTargetEntity(?Entity $target) : void{
		$previous = $this->getTargetEntityId();
		parent::setTargetEntity($target);
		if($target !== null && $previous !== $target->getId()){
			$this->triggerEventDefinition($this->components["minecraft:on_target_acquired"] ?? null, $target);
		}elseif($target === null && $previous !== null){
			$this->triggerEventDefinition($this->components["minecraft:on_target_escape"] ?? null);
		}
	}

	public function getDrops() : array{
		$drops = [];
		$loot = $this->components["minecraft:loot"] ?? null;
		$table = is_array($loot) ? ($loot["table"] ?? null) : null;
		$tables = AddonManager::getInstance()?->getLootTables();
		if(is_string($table) && $tables !== null){
			$drops = $tables->roll($table, LootContext::forEntityDeath($this, $this->getLastDamager()));
		}
		if($this->inventory !== null){
			foreach($this->inventory->getContents() as $item){
				$drops[] = $item;
			}
			$this->inventory->clearAll();
		}
		foreach(["slot.weapon.mainhand" => $this->mainHand, "slot.weapon.offhand" => $this->offHand, "slot.armor.head" => $this->armorInventory->getHelmet(),
			"slot.armor.chest" => $this->armorInventory->getChestplate(), "slot.armor.legs" => $this->armorInventory->getLeggings(), "slot.armor.feet" => $this->armorInventory->getBoots()] as $slot => $item){
			if($item !== null && !$item->isNull() && EntityFilter::chance($this->equipmentDropChance($slot))){
				$drops[] = $item;
			}
		}
		return $drops;
	}

	/** The entity's container (minecraft:inventory), created on first use; null without the component. */
	public function getInventory() : ?AddonEntityInventory{
		if($this->inventory !== null){
			return $this->inventory;
		}
		$component = $this->components["minecraft:inventory"] ?? $this->addonDefinition->resolveComponents(array_keys($this->addonDefinition->getComponentGroups()))["minecraft:inventory"] ?? null;
		if($component === null){
			return null;
		}
		$size = is_array($component) ? (int) ($component["inventory_size"] ?? 5) : 5;
		if(isset($this->components["minecraft:is_chested"]) && is_array($component) && is_numeric($component["additional_slots_per_strength"] ?? null)){
			$size += 3 * (int) $component["additional_slots_per_strength"];
		}
		return $this->inventory = new AddonEntityInventory($this, max(1, min(54, $size)));
	}

	/** Opens the entity's inventory for a player, when minecraft:inventory allows it. */
	public function openInventoryFor(Player $player) : bool{
		$component = $this->components["minecraft:inventory"] ?? null;
		$inventory = $this->getInventory();
		if($inventory === null || $component === null){
			return false;
		}
		if(is_array($component) && ((bool) ($component["private"] ?? false) || ((bool) ($component["restrict_to_owner"] ?? false) && $this->getOwningEntity() !== $player))){
			return false;
		}
		return $player->setCurrentWindow($inventory);
	}

	public function getMainHandItem() : Item{ return $this->mainHand ?? VanillaItems::AIR(); }

	public function getOffHandItem() : Item{ return $this->offHand ?? VanillaItems::AIR(); }

	public function setMainHandItem(Item $item) : void{
		$this->mainHand = $item->isNull() ? null : clone $item;
		$this->sendHeldItems($this->getViewers());
	}

	public function setOffHandItem(Item $item) : void{
		$this->offHand = $item->isNull() ? null : clone $item;
		$this->sendHeldItems($this->getViewers());
	}

	/**
	 * Takes an item off the ground (behavior.pickup_items): into the inventory, else an empty armour slot or
	 * hand. Returns false when there is no room.
	 */
	public function takeItem(Item $item, bool $toEquipment = true) : bool{
		$inventory = $this->getInventory();
		if($inventory !== null && $inventory->canAddItem($item)){
			$inventory->addItem($item);
		}elseif($toEquipment && $item instanceof Armor && $this->armorInventory->getItem($item->getArmorSlot())->isNull()){
			$this->armorInventory->setItem($item->getArmorSlot(), $item);
		}elseif($toEquipment && $this->mainHand === null){
			$this->setMainHandItem($item);
		}else{
			return false;
		}
		$this->broadcastSound(new \pocketmine\world\sound\ItemFrameAddItemSound());
		return true;
	}

	/** minecraft:equipment: gear from a loot table, rolled once when the entity first spawns. */
	private function rollEquipment() : void{
		$equipment = $this->components["minecraft:equipment"] ?? null;
		$table = is_array($equipment) ? ($equipment["table"] ?? null) : null;
		$tables = AddonManager::getInstance()?->getLootTables();
		if(!is_string($table) || $tables === null){
			return;
		}
		foreach($tables->roll($table, new LootContext($this)) as $item){
			if($item instanceof Armor){
				$slot = $item->getArmorSlot();
				if($this->armorInventory->getItem($slot)->isNull()){
					$this->armorInventory->setItem($slot, $item);
					continue;
				}
			}
			if($this->mainHand === null){
				$this->mainHand = $item;
			}elseif($this->offHand === null){
				$this->offHand = $item;
			}
		}
		$this->sendHeldItems($this->getViewers());
	}

	private function equipmentDropChance(string $slot) : float{
		$equipment = $this->components["minecraft:equipment"] ?? null;
		foreach(is_array($equipment) && is_array($equipment["slot_drop_chance"] ?? null) ? $equipment["slot_drop_chance"] : [] as $entry){
			if(is_array($entry) && ($entry["slot"] ?? null) === $slot){
				return (float) ($entry["drop_chance"] ?? 0);
			}
		}
		return is_array($equipment) ? 0.085 : 0.0; //the game's default for rolled gear; other held items always drop
	}

	/**
	 * Held items, encoded for each viewer's own protocol.
	 *
	 * @param Player[] $viewers
	 */
	private function sendHeldItems(array $viewers) : void{
		if($this->mainHand === null && $this->offHand === null){
			return;
		}
		foreach($viewers as $viewer){
			$session = $viewer->getNetworkSession();
			$converter = $session->getTypeConverter();
			$session->sendDataPacket(MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getMainHandItem())), 0, 0, ContainerIds::INVENTORY));
			$session->sendDataPacket(MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getOffHandItem())), 0, 0, ContainerIds::OFFHAND));
		}
	}

	public function getXpDropAmount() : int{
		$reward = $this->components["minecraft:experience_reward"] ?? null;
		if(!is_array($reward) || !isset($reward["on_death"]) || $this->isBaby()){
			return 0;
		}
		return max(0, (int) Molang::number($reward["on_death"], $this, new FilterContext($this, $this->getLastDamager())));
	}

	protected function onDeath() : void{
		$this->getFeatures()->playSound("death");
		$this->ejectRiders();
		$this->unleash();
		$this->hideBossBar();
		parent::onDeath();
		$this->brain?->stopAll();
	}

	/** Hits the entity with this mob's minecraft:attack (damage, and an optional effect). */
	public function attackEntity(Entity $target) : void{
		$attack = is_array($this->components["minecraft:attack"] ?? null) ? $this->components["minecraft:attack"] : [];
		if(!isset($attack["damage"]) && isset($this->components["minecraft:attack_damage"])){
			//the older form of the attack damage
			$attack["damage"] = AddonJson::scalar($this->components["minecraft:attack_damage"]) ?? 2;
		}
		$damage = $attack["damage"] ?? 2;
		if(is_array($damage)){
			$min = (int) ($damage["range_min"] ?? $damage[0] ?? 1);
			$max = (int) ($damage["range_max"] ?? $damage[1] ?? $min);
			$damage = mt_rand(min($min, $max), max($min, $max));
		}
		$event = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, (float) $damage);
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$target->attack($event);
		if(!$event->isCancelled()){
			$this->getFeatures()->attacked();
		}
		if(!$event->isCancelled() && $target instanceof Living && is_string($attack["effect_name"] ?? null)){
			$effect = StringToEffectParser::getInstance()->parse($attack["effect_name"]);
			if($effect !== null){
				$target->getEffects()->add(new EffectInstance($effect, (int) ((float) ($attack["effect_duration"] ?? 0) * 20)));
			}
		}
	}

	/** @param mixed[]|mixed $spell */
	private function applySpellEffects(mixed $spell) : void{
		if(!is_array($spell)){
			return;
		}
		foreach(self::listOf($spell["add_effects"] ?? []) as $entry){
			$effect = StringToEffectParser::getInstance()->parse((string) ($entry["effect"] ?? ""));
			if($effect !== null){
				$this->effectManager->add(new EffectInstance($effect, (int) ((float) ($entry["duration"] ?? 30) * 20), (int) ($entry["amplifier"] ?? 0), (bool) ($entry["visible"] ?? true), (bool) ($entry["ambient"] ?? false)));
			}
		}
		foreach(is_array($spell["remove_effects"] ?? null) ? $spell["remove_effects"] : [$spell["remove_effects"] ?? null] as $name){
			$effect = is_string($name) ? StringToEffectParser::getInstance()->parse($name) : null;
			if($effect !== null){
				$this->effectManager->remove($effect);
			}
		}
	}

	private function lightFuse() : void{
		$explode = $this->components["minecraft:explode"] ?? [];
		$fuse = is_array($explode) ? ($explode["fuse_length"] ?? 1.5) : 1.5;
		$seconds = is_array($fuse) ? (float) ($fuse[0] ?? 1.5) + AddonMath::randomFloat() * max(0.0, (float) ($fuse[1] ?? $fuse[0] ?? 1.5) - (float) ($fuse[0] ?? 1.5)) : (float) $fuse;
		$this->fuseTicks = max(1, (int) ($seconds * 20));
		$this->networkPropertiesDirty = true;
	}

	private function explode() : void{
		$explode = is_array($this->components["minecraft:explode"] ?? null) ? $this->components["minecraft:explode"] : [];
		$explosion = new Explosion($this->getPosition(), max(0.1, (float) ($explode["power"] ?? 3)), $this, (bool) ($explode["causes_fire"] ?? false) ? 1 / 3 : 0.0);
		if((bool) ($explode["breaks_blocks"] ?? true)){
			$explosion->explodeA();
		}
		$explosion->explodeB();
		$this->flagForDespawn();
	}

	/** minecraft:transformation: replace this entity with another one. */
	private function transform() : void{
		$options = is_array($this->components["minecraft:transformation"] ?? null) ? $this->components["minecraft:transformation"] : [];
		$into = $options["into"] ?? null;
		if(!is_string($into)){
			return;
		}
		[$identifier, $event] = str_contains($into, "<") ? explode("<", rtrim($into, ">"), 2) : [$into, null];
		$manager = AddonManager::getInstance();
		$replacement = $manager?->createEntity($identifier, $this->getLocation());
		if($replacement === null){
			$this->report("transformation target $identifier is not an add-on entity");
			return;
		}
		$replacement->setSpawnEvent($event ?? "minecraft:entity_transformed");
		$replacement->setHealth(min((float) $replacement->getMaxHealth(), $this->getHealth() / max(1, $this->getMaxHealth()) * $replacement->getMaxHealth()));
		$replacement->setNameTag($this->getNameTag());
		$replacement->setMotion($this->getMotion());
		if((bool) ($options["keep_owner"] ?? false)){
			$replacement->tamed = $this->tamed;
			$replacement->ownerName = $this->ownerName;
			$replacement->setOwningEntity($this->getOwningEntity());
		}
		$dropItems = [];
		$sourceInventory = $this->getInventory();
		if($sourceInventory !== null){
			$contents = $sourceInventory->getContents();
			if((bool) ($options["drop_inventory"] ?? false)){
				array_push($dropItems, ...array_values($contents));
			}elseif(($targetInventory = $replacement->getInventory()) !== null){
				foreach($contents as $slot => $item){
					if($slot < $targetInventory->getSize()){
						$targetInventory->setItem($slot, $item);
					}else{
						$dropItems[] = $item;
					}
				}
			}else{
				array_push($dropItems, ...array_values($contents));
			}
		}
		$armorItems = $this->getArmorInventory()->getContents();
		$equipment = [$this->mainHand, $this->offHand, ...array_values($armorItems)];
		if((bool) ($options["drop_equipment"] ?? false)){
			foreach($equipment as $item){
				if($item !== null && !$item->isNull()){
					$dropItems[] = $item;
				}
			}
		}elseif((bool) ($options["preserve_equipment"] ?? false)){
			$replacement->mainHand = $this->mainHand === null ? null : clone $this->mainHand;
			$replacement->offHand = $this->offHand === null ? null : clone $this->offHand;
			$replacement->getArmorInventory()->setContents($armorItems);
			$replacement->equipped = true;
		}
		$sourceTrades = $this->tradeComponent();
		if($replacement->tradeComponent() !== null){
			if((bool) ($sourceTrades["persist_trades"] ?? false) && $this->tradeOffers !== null){
				$replacement->tradeOffers = array_map(static fn(TradeOffer $offer) : TradeOffer => clone $offer, $this->tradeOffers);
			}
			if((bool) ($options["keep_level"] ?? false)){
				$replacement->tradeExp = $this->tradeExp;
			}
		}
		$add = $options["add"]["component_groups"] ?? [];
		if(is_array($add)){
			$replacement->addComponentGroup(...array_values(array_filter($add, "is_string")));
		}
		$replacement->spawnToAll();
		foreach($dropItems as $item){
			$this->getWorld()->dropItem($this->location, $item);
		}
		if($sourceInventory !== null && $contents !== []){
			$sourceInventory->clearAll();
		}
		$this->mainHand = null;
		$this->offHand = null;
		if($armorItems !== []){
			$this->getArmorInventory()->clearAll();
		}
		$this->flagForDespawn();
	}

	/**
	 * Fires this mob's minecraft:shooter projectile at the target.
	 */
	public function shootAt(Entity $target, float $charge = 0.0) : void{
		$shooter = is_array($this->components["minecraft:shooter"] ?? null) ? $this->components["minecraft:shooter"] : [];
		$def = is_string($shooter["def"] ?? null) ? $shooter["def"] : "minecraft:arrow";
		$eye = $this->getEyePos();
		$aim = $target->getEyePos()->subtractVector($eye);
		$horizontal = sqrt($aim->x ** 2 + $aim->z ** 2);
		$aim = $aim->add(0, $horizontal * 0.2, 0);
		$length = $aim->length();
		if($length < 0.0001){
			return;
		}
		$direction = $aim->divide($length);
		$location = Location::fromObject($eye->addVector($direction->multiply(0.8)), $this->getWorld(), $this->location->yaw, $this->location->pitch);

		$manager = AddonManager::getInstance();
		$projectile = $manager?->getEntityDefinition($def) !== null ? $manager?->createEntity($def, $location) : null;
		if($projectile instanceof AddonEntity){
			$power = is_array($projectile->components["minecraft:projectile"] ?? null) ? (float) ($projectile->components["minecraft:projectile"]["power"] ?? 1.3) : 1.3;
			$projectile->setOwningEntity($this);
		}else{
			$power = 1.6;
			$projectile = match(strtolower($def)){
				"minecraft:snowball" => new Snowball($location, $this),
				"minecraft:egg" => new Egg($location, $this),
				default => new Arrow($location, $this, $charge >= 1.0),
			};
			if(!in_array(strtolower($def), ["minecraft:arrow", "minecraft:snowball", "minecraft:egg"], true)){
				$this->report("shooter projectile $def is not available on this server; an arrow is fired instead");
			}
		}
		$spread = 0.0075 * max(0.0, 14 - $this->getWorld()->getDifficulty() * 4);
		$projectile->setMotion(new Vector3(
			($direction->x + (AddonMath::randomFloat() - 0.5) * $spread) * $power,
			($direction->y + (AddonMath::randomFloat() - 0.5) * $spread) * $power,
			($direction->z + (AddonMath::randomFloat() - 0.5) * $spread) * $power
		));
		$projectile->spawnToAll();
	}

	private function tickProjectile() : void{
		$this->projectileAge++;
		if($this->projectileAge > 1200){
			$this->flagForDespawn();
			return;
		}
		$projectile = is_array($this->components["minecraft:projectile"]) ? $this->components["minecraft:projectile"] : [];
		$onHit = is_array($projectile["on_hit"] ?? null) ? $projectile["on_hit"] : [];
		if($this->stuck){
			return;
		}
		$motion = $this->motion;
		$owner = $this->getOwningEntity();
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->addCoord($motion->x, $motion->y, $motion->z)->expand(0.3, 0.3, 0.3), $this) as $entity){
			if(($entity === $owner && $this->projectileAge < 5) || !$entity instanceof Living || !$entity->isAlive() || ($entity instanceof AddonEntity && isset($entity->components["minecraft:projectile"]))){
				continue;
			}
			$this->onProjectileHit($entity, $onHit);
			return;
		}
		if($this->isCollided && $this->projectileAge > 1){
			$this->onProjectileHit(null, $onHit);
		}elseif($motion->lengthSquared() > 0.0001){
			$this->setRotation(atan2($motion->x, $motion->z) * -180 / M_PI, atan2($motion->y, sqrt($motion->x ** 2 + $motion->z ** 2)) * -180 / M_PI);
		}
	}

	/** @param mixed[] $onHit */
	private function onProjectileHit(?Living $hit, array $onHit) : void{
		$owner = $this->getOwningEntity();
		if($hit !== null && is_array($onHit["impact_damage"] ?? null)){
			$impact = $onHit["impact_damage"];
			$filter = $impact["filter"] ?? null;
			if(!is_string($filter) || in_array($filter, EntityFilter::familiesOf($hit), true)){
				$damage = $impact["damage"] ?? 1;
				if(is_array($damage)){
					$damage = mt_rand((int) ($damage[0] ?? 1), (int) ($damage[1] ?? $damage[0] ?? 1));
				}elseif((bool) ($impact["semi_random_diff_damage"] ?? false)){
					$damage = (float) $damage * (0.5 + AddonMath::randomFloat());
				}
				$event = $owner !== null
					? new EntityDamageByChildEntityEvent($owner, $this, $hit, EntityDamageEvent::CAUSE_PROJECTILE, (float) $damage)
					: new EntityDamageEvent($hit, EntityDamageEvent::CAUSE_PROJECTILE, (float) $damage);
				if(!(bool) ($impact["knockback"] ?? true) && $event instanceof EntityDamageByEntityEvent){
					$event->setKnockBack(0.0);
				}
				$hit->attack($event);
				if((bool) ($impact["catch_fire"] ?? false)){
					$hit->setOnFire(5);
				}
			}
		}
		if($hit !== null && is_array($onHit["mob_effect"] ?? null)){
			$effect = StringToEffectParser::getInstance()->parse((string) ($onHit["mob_effect"]["effect"] ?? ""));
			if($effect !== null){
				$hit->getEffects()->add(new EffectInstance($effect, (int) ($onHit["mob_effect"]["duration"] ?? $onHit["mob_effect"]["durationeasy"] ?? 100), (int) ($onHit["mob_effect"]["amplifier"] ?? 0)));
			}
		}
		if(is_array($onHit["definition_event"] ?? null)){
			$definition = $onHit["definition_event"];
			$trigger = $definition["event_trigger"] ?? null;
			if((bool) ($definition["affect_projectile"] ?? true)){
				$this->triggerEventDefinition($trigger, $hit);
			}
			if((bool) ($definition["affect_target"] ?? false) && $hit instanceof AddonEntity && is_array($trigger) && is_string($trigger["event"] ?? null)){
				$hit->triggerEvent($trigger["event"], $this);
			}
		}
		if(isset($onHit["teleport_owner"]) && $owner !== null){
			$owner->teleport($this->location);
		}
		if(isset($onHit["remove_on_hit"]) || ($hit !== null && !isset($onHit["stick_in_ground"]))){
			$this->flagForDespawn();
		}elseif($hit === null){
			$this->stuck = true;
			$this->motion = Vector3::zero();
			$this->setHasGravity(false);
			if(!isset($onHit["stick_in_ground"])){
				$this->flagForDespawn();
			}
		}
		AddonManager::getInstance()?->getScriptHost()?->onProjectileHit($this, $hit);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$now = $this->now();
		if($now < $this->interactCooldownUntil){
			return false;
		}
		$manager = AddonManager::getInstance();
		if($manager !== null && $manager->dispatchEntityInteract($player, $this)){
			return true;
		}
		$held = $player->getInventory()->getItemInHand();
		$context = new FilterContext($this, $player);
		$c = $this->components;

		$interact = $c["minecraft:interact"] ?? null;
		if(is_array($interact)){
			$interactions = $interact["interactions"] ?? $interact;
			foreach(self::listOf($interactions) as $entry){
				if($this->runInteraction($entry, $player, $held, $context)){
					$this->interactCooldownUntil = $now + (int) ((float) ($entry["cooldown"] ?? 0) * 20);
					return true;
				}
			}
		}
		if(is_array($c["minecraft:tameable"] ?? null) && !$this->isTamed() && $this->matchesAny($held, $c["minecraft:tameable"]["tame_items"] ?? [])){
			$this->consume($player, $held);
			$host = AddonManager::getInstance()?->getScriptHost();
			$data = ["entity" => $this->getId(), "player" => $player->getId()];
			if(EntityFilter::chance((float) ($c["minecraft:tameable"]["probability"] ?? 1.0)) && ($host?->before("entityTamed", $data)["cancel"] ?? false) !== true){
				$host?->queueEvent("entityTamed", $data);
				$this->tamed = true;
				$this->setOwner($player);
				$this->networkPropertiesDirty = true;
				$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight(), 0), new HeartParticle());
				$this->triggerEventDefinition($c["minecraft:tameable"]["tame_event"] ?? null, $player);
			}
			return true;
		}
		if(is_array($c["minecraft:healable"] ?? null) && $this->getHealth() < $this->getMaxHealth()){
			foreach(self::listOf($c["minecraft:healable"]["items"] ?? []) as $entry){
				if($this->matchesAny($held, [$entry["item"] ?? ""])){
					$this->consume($player, $held);
					$this->heal(new EntityRegainHealthEvent($this, (float) ($entry["heal_amount"] ?? 1), EntityRegainHealthEvent::CAUSE_EATING));
					return true;
				}
			}
		}
		if(is_array($c["minecraft:breedable"] ?? null) && $this->canEnterLoveMode($now)
			&& (!(bool) ($c["minecraft:breedable"]["require_tame"] ?? false) || $this->isTamed())
			&& $this->matchesAny($held, $c["minecraft:breedable"]["breed_items"] ?? [])){
			$this->consume($player, $held);
			$this->loveTicks = 600;
			$this->networkPropertiesDirty = true;
			return true;
		}
		if($this->isBaby() && is_array($c["minecraft:ageable"] ?? null)){
			foreach(self::listOf(array_map(static fn($i) => is_string($i) ? ["item" => $i] : $i, is_array($c["minecraft:ageable"]["feed_items"] ?? null) ? $c["minecraft:ageable"]["feed_items"] : [])) as $entry){
				if($this->matchesAny($held, [$entry["item"] ?? ""])){
					$this->consume($player, $held);
					$this->ageTicks += (int) ((float) ($entry["growth"] ?? 0.1) * (float) ($c["minecraft:ageable"]["duration"] ?? 1200) * 20);
					return true;
				}
			}
		}
		if(isset($c["minecraft:leashable"])){
			if($this->getLeashHolder() === $player){
				$this->unleash();
				return true;
			}
			if(!$held->isNull() && ScriptHost::itemWire($held)["id"] === "minecraft:lead" && $this->leashTo($player)){
				$this->consume($player, $held);
				return true;
			}
		}
		if(isset($c["minecraft:sittable"]) && $this->isTamed() && $this->getOwningEntity() === $player && !$player->isSneaking()){
			$this->setSitting(!$this->sitting);
			$sittable = is_array($c["minecraft:sittable"]) ? $c["minecraft:sittable"] : [];
			$this->triggerEventDefinition($sittable[$this->sitting ? "sit_event" : "stand_event"] ?? null, $player);
			return true;
		}
		if((isset($c["minecraft:trade_table"]) || isset($c["minecraft:economy_trade_table"])) && !$this->isBaby() && !$player->isSneaking() && $this->openTradeFor($player)){
			return true;
		}
		if(isset($c["minecraft:inventory"]) && ($player->isSneaking() || !isset($c["minecraft:rideable"])) && $this->openInventoryFor($player)){
			return true;
		}
		$rideable = $c["minecraft:rideable"] ?? null;
		//behavior.player_ride_tamed: only a tamed mob carries players (a wild one with minecraft:tamemount can be ridden to tame it)
		$rideAllowed = !isset($this->components["minecraft:behavior.player_ride_tamed"]) || $this->isTamed() || isset($this->components["minecraft:tamemount"]);
		if(is_array($rideable) && $rideAllowed && !($player->isSneaking() && (bool) ($rideable["crouching_skip_interact"] ?? true)) && $this->addRider($player)){
			return true;
		}
		return false;
	}

	/** The add-on entity this entity rides, if any. */
	public static function getVehicleOf(Entity $rider) : ?AddonEntity{
		return self::$vehicles !== null ? (self::$vehicles[$rider] ?? null) : null;
	}

	/** @return list<Entity> */
	public function getRiders() : array{
		return array_values($this->riders);
	}

	/** @return list<mixed[]> */
	private function seats() : array{
		$rideable = is_array($this->components["minecraft:rideable"] ?? null) ? $this->components["minecraft:rideable"] : [];
		$seats = $rideable["seats"] ?? [];
		$seats = is_array($seats) ? (array_is_list($seats) ? $seats : [$seats]) : [];
		$count = max(1, (int) ($rideable["seat_count"] ?? count($seats)));
		$out = [];
		for($i = 0; $i < $count; ++$i){
			$out[] = is_array($seats[$i] ?? null) ? $seats[$i] : (is_array($seats[0] ?? null) ? $seats[0] : []);
		}
		return $out;
	}

	/** Whether the entity may ride this one: minecraft:rideable, a free seat, and its family allowed. */
	public function canAddRider(Entity $rider) : bool{
		$rideable = $this->components["minecraft:rideable"] ?? null;
		if(!is_array($rideable) || $rider === $this || $rider->isClosed() || !$rider->isAlive() || self::getVehicleOf($rider) !== null || $rider->getWorld() !== $this->getWorld()){
			return false;
		}
		if(count($this->riders) >= count($this->seats())){
			return false;
		}
		$families = is_array($rideable["family_types"] ?? null) ? $rideable["family_types"] : [];
		return $families === [] || array_intersect($families, EntityFilter::familiesOf($rider)) !== [];
	}

	/** Puts an entity in the first free seat. Plugins and scripts can call this directly. */
	public function addRider(Entity $rider) : bool{
		if(!$this->canAddRider($rider)){
			return false;
		}
		$seat = 0;
		while(isset($this->riders[$seat])){
			$seat++;
		}
		$this->riders[$seat] = $rider;
		self::$vehicles ??= new \WeakMap();
		self::$vehicles[$rider] = $this;
		$this->applyRiderMetadata($rider, $seat, true);
		$this->broadcastLink($rider, $seat === $this->controllingSeat() ? EntityLink::TYPE_RIDER : EntityLink::TYPE_PASSENGER);
		$this->navigatorStop();
		$rideable = is_array($this->components["minecraft:rideable"]) ? $this->components["minecraft:rideable"] : [];
		$this->triggerEventDefinition($rideable["on_rider_enter_event"] ?? null, $rider);
		return true;
	}

	public function removeRider(Entity $rider) : bool{
		$seat = array_search($rider, $this->riders, true);
		if($seat === false){
			return false;
		}
		unset($this->riders[$seat]);
		if(self::$vehicles !== null){
			unset(self::$vehicles[$rider]);
		}
		$this->broadcastLink($rider, EntityLink::TYPE_REMOVE);
		if(!$rider->isClosed()){
			$this->applyRiderMetadata($rider, (int) $seat, false);
			$exit = $this->location->add(0, $this->size->getHeight() + 0.1, 0);
			if($rider instanceof Player){
				$rider->teleport($exit);
			}else{
				$rider->teleport(Location::fromObject($exit, $this->getWorld(), $rider->getLocation()->yaw, $rider->getLocation()->pitch));
			}
		}
		$rideable = is_array($this->components["minecraft:rideable"] ?? null) ? $this->components["minecraft:rideable"] : [];
		$this->triggerEventDefinition($rideable["on_rider_exit_event"] ?? null, $rider);
		return true;
	}

	public function ejectRiders() : void{
		foreach($this->riders as $rider){
			$this->removeRider($rider);
		}
	}

	private function controllingSeat() : int{
		$rideable = is_array($this->components["minecraft:rideable"] ?? null) ? $this->components["minecraft:rideable"] : [];
		return (int) ($rideable["controlling_seat"] ?? 0);
	}

	private function applyRiderMetadata(Entity $rider, int $seat, bool $riding) : void{
		$properties = $rider->getNetworkProperties();
		$properties->setGenericFlag(EntityMetadataFlags::RIDING, $riding);
		if($riding){
			$config = $this->seats()[$seat] ?? [];
			$position = is_array($config["position"] ?? null) ? $config["position"] : [0, $this->size->getHeight(), 0];
			$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3((float) ($position[0] ?? 0), (float) ($position[1] ?? 0), (float) ($position[2] ?? 0)));
			$properties->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, (bool) ($config["lock_rider_rotation"] ?? false) ? 1 : 0);
			$limit = is_numeric($config["lock_rider_rotation"] ?? null) ? (float) $config["lock_rider_rotation"] : 181.0;
			$properties->setFloat(EntityMetadataProperties::RIDER_MAX_ROTATION, $limit);
			$properties->setFloat(EntityMetadataProperties::RIDER_MIN_ROTATION, -$limit);
		}
		$rider->sendData(null);
		$this->networkPropertiesDirty = true;
	}

	private function broadcastLink(Entity $rider, int $type) : void{
		$packet = SetActorLinkPacket::create(new EntityLink($this->getId(), $rider->getId(), $type, true, false, 0.0));
		$viewers = $this->getViewers();
		if($rider instanceof Player){
			$viewers[] = $rider;
		}
		foreach($viewers as $viewer){
			$viewer->getNetworkSession()->sendDataPacket($packet);
		}
	}

	/** Keeps riders seated; drops the ones that left, died or changed world. */
	private function updateRiders() : void{
		foreach($this->riders as $seat => $rider){
			if($rider->isClosed() || !$rider->isAlive() || $rider->getWorld() !== $this->getWorld() || $rider->getPosition()->distanceSquared($this->location) > 100){
				$this->removeRider($rider);
				continue;
			}
			if(!$rider instanceof Player){
				//players report their seat position themselves; other riders are carried
				$config = $this->seats()[$seat] ?? [];
				$offset = is_array($config["position"] ?? null) ? $config["position"] : [0, $this->size->getHeight(), 0];
				$yaw = deg2rad($this->location->yaw);
				$x = (float) ($offset[0] ?? 0);
				$z = (float) ($offset[2] ?? 0);
				$target = $this->location->add($x * cos($yaw) - $z * sin($yaw), (float) ($offset[1] ?? 0), $x * sin($yaw) + $z * cos($yaw));
				$rider->teleport(Location::fromObject($target, $this->getWorld(), $rider->getLocation()->yaw, $rider->getLocation()->pitch));
			}
		}
	}

	/** @internal the controlling player's movement input, from PlayerAuthInputPacket */
	public static function setRiderInput(Player $player, float $strafe, float $forward, bool $jump) : void{
		$vehicle = self::getVehicleOf($player);
		if($vehicle !== null && ($vehicle->riders[$vehicle->controllingSeat()] ?? null) === $player){
			$vehicle->riderInput = [$strafe, $forward, $jump];
			$vehicle->riderInputTick = $vehicle->now();
		}
	}

	/** Moves the entity by its controlling rider's input (minecraft:input_ground_controlled / input_air_controlled). */
	private function steerByRider() : bool{
		$rider = $this->riders[$this->controllingSeat()] ?? null;
		$c = $this->components;
		$ground = isset($c["minecraft:input_ground_controlled"]) || isset($c["minecraft:behavior.controlled_by_player"]);
		$air = isset($c["minecraft:input_air_controlled"]);
		if(!$rider instanceof Player || (!$ground && !$air)){
			return false;
		}
		[$strafe, $forward, $jump] = $this->now() - $this->riderInputTick < 5 ? $this->riderInput : [0.0, 0.0, false];
		$yaw = $rider->getLocation()->yaw;
		$this->setRotation($yaw, 0.0);
		$movement = AddonJson::scalar($c["minecraft:movement"] ?? null);
		$multiplier = is_array($c["minecraft:input_ground_controlled"] ?? null) ? (float) ($c["minecraft:input_ground_controlled"]["movement_speed_multiplier"] ?? 1.0) : 1.0;
		$speed = (is_numeric($movement) ? (float) $movement : 0.2) * $multiplier;
		$rad = deg2rad($yaw);
		$dx = -sin($rad) * $forward + cos($rad) * $strafe;
		$dz = cos($rad) * $forward + sin($rad) * $strafe;
		$length = sqrt($dx * $dx + $dz * $dz);
		if($length > 1){
			$dx /= $length;
			$dz /= $length;
		}
		$my = $this->motion->y;
		if($air){
			$my = -sin(deg2rad($rider->getLocation()->pitch)) * $forward * $speed;
		}elseif($jump && $this->onGround){
			$my = $this->getJumpVelocity();
		}
		$this->motion = new Vector3($dx * $speed, $my, $dz * $speed);
		return true;
	}

	private function navigatorStop() : void{
		$this->brain?->getNavigator()->stop();
	}

	/** @return array<string, mixed>|null the trade_table or economy_trade_table component */
	private function tradeComponent() : ?array{
		$component = $this->components["minecraft:trade_table"] ?? $this->components["minecraft:economy_trade_table"] ?? null;
		return is_array($component) ? $component : null;
	}

	private function table() : ?TradeTable{
		$component = $this->tradeComponent();
		$path = is_string($component["table"] ?? null) ? $component["table"] : null;
		if($path === null){
			return null;
		}
		return $this->tradeTable ??= AddonManager::getInstance()?->getTradeTable($path);
	}

	/** @return list<TradeOffer> */
	public function getTradeOffers() : array{
		if($this->tradeOffers === null){
			$this->tradeOffers = $this->table()?->roll(self::TRADE_NET_ID_BASE) ?? [];
		}
		return $this->tradeOffers;
	}

	/** @return list<int> */
	public function getTradeTierExperience() : array{
		return $this->table()?->getTierExperience() ?? [0];
	}

	public function getTradeTier() : int{
		$tier = 0;
		foreach($this->getTradeTierExperience() as $index => $exp){
			if($this->tradeExp >= $exp){
				$tier = $index;
			}
		}
		return $tier;
	}

	public function getTradeDisplayName() : string{
		$component = $this->tradeComponent();
		return is_string($component["display_name"] ?? null) ? $component["display_name"] : $this->getName();
	}

	public function usesNewTradeScreen() : bool{
		return (bool) ($this->tradeComponent()["new_screen"] ?? true);
	}

	/** Opens the trade screen (minecraft:trade_table / economy_trade_table). */
	public function openTradeFor(Player $player) : bool{
		if($this->tradeComponent() === null || $this->getTradeOffers() === []){
			return false;
		}
		$current = $this->tradingWith?->get();
		if($current !== null && $current !== $player && $current->getCurrentWindow() instanceof TradeInventory){
			return false; //one customer at a time, as in the game
		}
		$this->tradingWith = \WeakReference::create($player);
		return $player->setCurrentWindow(new TradeInventory($this, $player));
	}

	/** @internal after a trade went through */
	public function onTraded(Player $player, TradeOffer $offer, int $repetitions) : void{
		$tierBefore = $this->getTradeTier();
		$this->tradeExp += $offer->traderExp * $repetitions;
		if($offer->rewardExp){
			$this->getWorld()->dropExperience($player->getPosition(), mt_rand(3, 6) * $repetitions);
		}
		$window = $player->getCurrentWindow();
		if($window instanceof TradeInventory && ($id = $player->getNetworkSession()->getInvManager()?->getWindowId($window)) !== null){
			//refresh uses, and new offers if the trader reached a new tier
			$player->getNetworkSession()->sendDataPacket($window->createPacket($id));
		}
		if($this->getTradeTier() > $tierBefore){
			$this->getWorld()->addParticle($this->location->add(0, $this->size->getHeight() + 0.3, 0), new HeartParticle());
		}
	}

	public function getLeashHolder() : ?Entity{
		$holder = $this->leashHolder?->get();
		return $holder !== null && !$holder->isClosed() ? $holder : null;
	}

	/** Leashes the entity to a holder (minecraft:leashable). */
	public function leashTo(Entity $holder) : bool{
		$leashable = $this->components["minecraft:leashable"] ?? null;
		if(!is_array($leashable) && $leashable === null){
			return false;
		}
		if($this->getLeashHolder() !== null && !(bool) (is_array($leashable) ? ($leashable["can_be_stolen"] ?? false) : false)){
			return false;
		}
		$this->leashHolder = \WeakReference::create($holder);
		$this->networkPropertiesDirty = true;
		$this->triggerEventDefinition(is_array($leashable) ? ($leashable["on_leash"] ?? null) : null, $holder);
		return true;
	}

	public function unleash() : void{
		if($this->leashHolder === null){
			return;
		}
		$holder = $this->getLeashHolder();
		$this->leashHolder = null;
		$this->networkPropertiesDirty = true;
		$leashable = $this->components["minecraft:leashable"] ?? null;
		$this->triggerEventDefinition(is_array($leashable) ? ($leashable["on_unleash"] ?? null) : null, $holder);
	}

	/** Pulls the entity towards its holder past soft_distance; the lead breaks past max_distance. */
	private function updateLeash() : void{
		$holder = $this->getLeashHolder();
		$leashable = is_array($this->components["minecraft:leashable"] ?? null) ? $this->components["minecraft:leashable"] : [];
		if($holder === null || !isset($this->components["minecraft:leashable"]) || $holder->getWorld() !== $this->getWorld()){
			$this->unleash();
			return;
		}
		$soft = (float) ($leashable["soft_distance"] ?? 4.0);
		$max = (float) ($leashable["max_distance"] ?? 10.0);
		$delta = $holder->getPosition()->subtractVector($this->location);
		$distance = $delta->length();
		if($distance > $max){
			$this->unleash();
			return;
		}
		if($distance > $soft){
			$pull = min(0.4, ($distance - $soft) * 0.08);
			$this->setMotion($this->motion->addVector($delta->divide($distance)->multiply($pull)));
		}
	}

	/** minecraft:boss: shows the bar to players within hud_range, hides it from the rest. */
	private function updateBossBar() : void{
		$boss = $this->components["minecraft:boss"] ?? null;
		if(!is_array($boss) && $boss === null){
			$this->hideBossBar();
			return;
		}
		$range = is_array($boss) ? (float) ($boss["hud_range"] ?? 55) : 55.0;
		$title = is_array($boss) && is_string($boss["name"] ?? null) ? $boss["name"] : ($this->getNameTag() !== "" ? $this->getNameTag() : $this->getName());
		$darken = is_array($boss) && (bool) ($boss["should_darken_sky"] ?? false);
		$inRange = [];
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->getPosition()->distanceSquared($this->location) <= $range * $range){
				$inRange[$player->getId()] = $player;
			}
		}
		$show = BossEventPacket::show($this->getId(), $title, $this->getHealth() / max(1, $this->getMaxHealth()), $darken);
		foreach($inRange as $id => $player){
			if(!isset($this->bossViewers[$id])){
				$player->getNetworkSession()->sendDataPacket($show);
			}
		}
		$hide = BossEventPacket::hide($this->getId());
		foreach($this->bossViewers as $id => $player){
			if(!isset($inRange[$id]) && $player->isConnected()){
				$player->getNetworkSession()->sendDataPacket($hide);
			}
		}
		$this->bossViewers = $inRange;
	}

	private function hideBossBar() : void{
		if($this->bossViewers === []){
			return;
		}
		$hide = BossEventPacket::hide($this->getId());
		foreach($this->bossViewers as $player){
			if($player->isConnected()){
				$player->getNetworkSession()->sendDataPacket($hide);
			}
		}
		$this->bossViewers = [];
	}

	public function setHealth(float $amount) : void{
		parent::setHealth($amount);
		$this->bossDirty = true;
	}

	/** @param mixed[] $entry */
	private function runInteraction(array $entry, Player $player, Item $held, FilterContext $context) : bool{
		$on = is_array($entry["on_interact"] ?? null) ? $entry["on_interact"] : [];
		if(isset($on["filters"]) && !EntityFilter::test($on["filters"], $context, $this->reporter())){
			return false;
		}
		$survival = $player->hasFiniteResources();
		if((bool) ($entry["use_item"] ?? false) && $survival){
			$this->consume($player, $held);
		}
		if(is_numeric($entry["hurt_item"] ?? null) && $held instanceof Durable && $survival){
			$held->applyDamage((int) $entry["hurt_item"]);
			$player->getInventory()->setItemInHand($held);
		}
		if(is_string($entry["transform_to_item"] ?? null) && $survival){
			$item = self::parseItem($entry["transform_to_item"]);
			if($item !== null){
				$player->getInventory()->setItemInHand($item);
			}
		}
		if(is_array($entry["spawn_items"] ?? null) && is_string($entry["spawn_items"]["table"] ?? null)){
			foreach(AddonManager::getInstance()?->getLootTables()?->roll($entry["spawn_items"]["table"], new LootContext($this, $player, $held, true)) ?? [] as $item){
				$this->getWorld()->dropItem($this->location->add(0, 0.5, 0), $item);
			}
		}
		if((bool) ($entry["swing"] ?? false)){
			$player->broadcastAnimation(new ArmSwingAnimation($player));
		}
		if(isset($on["event"])){
			$this->triggerEventDefinition($on, $player);
		}
		return true;
	}

	/** Whether the item is one of the names ("minecraft:wheat", "wheat", "ns:item", "item:meta"). */
	private function matchesAny(Item $held, mixed $names) : bool{
		if($held->isNull()){
			return false;
		}
		foreach(is_array($names) ? $names : [$names] as $name){
			$name = is_array($name) ? ($name["item"] ?? null) : $name;
			if(!is_string($name)){
				continue;
			}
			$item = self::parseItem($name);
			if($item !== null && $item->getTypeId() === $held->getTypeId()){
				return true;
			}
		}
		return false;
	}

	private static function parseItem(string $name) : ?Item{
		$name = strtolower($name);
		$parser = StringToItemParser::getInstance();
		return $parser->parse($name) ?? (str_contains($name, ":") ? null : $parser->parse("minecraft:$name")) ?? AddonManager::getInstance()?->getItem($name);
	}

	private function consume(Player $player, Item $held) : void{
		if($player->hasFiniteResources()){
			$held->pop();
			$player->getInventory()->setItemInHand($held);
		}
	}

	/** Whether the two can make a baby (minecraft:breedable breeds_with, or the same identifier). */
	public function canBreedWith(AddonEntity $other) : bool{
		if($other === $this || $other->isBaby() || $this->isBaby()){
			return false;
		}
		return $this->breedPartner($other) !== null;
	}

	/** @return mixed[]|null the breeds_with entry for this partner */
	private function breedPartner(AddonEntity $other) : ?array{
		$breedable = is_array($this->components["minecraft:breedable"] ?? null) ? $this->components["minecraft:breedable"] : [];
		$with = $breedable["breeds_with"] ?? [];
		//the current format maps mate types to their options: {"minecraft:cow": {}}
		if(is_array($with) && !array_is_list($with) && !isset($with["mate_type"])){
			$entries = [];
			foreach($with as $mate => $options){
				$entries[] = ["mate_type" => (string) $mate] + (is_array($options) ? $options : []);
			}
			$with = $entries;
		}
		$with = self::listOf($with);
		if($with === []){
			return $other->getAddonIdentifier() === $this->getAddonIdentifier() ? [] : null;
		}
		foreach($with as $entry){
			if(($entry["mate_type"] ?? $this->getAddonIdentifier()) === $other->getAddonIdentifier()){
				return $entry;
			}
		}
		return null;
	}

	public function breedWith(AddonEntity $mate) : void{
		//both mates run the goal; the one with the lower ID makes the baby
		if($mate->getId() < $this->getId()){
			return;
		}
		$entry = $this->breedPartner($mate) ?? [];
		//minecraft:offspring names the baby for each mate type (a horse and a donkey make a mule)
		$offspring = is_array($this->components["minecraft:offspring"] ?? null) ? $this->components["minecraft:offspring"] : [];
		$pairs = is_array($offspring["offspring_pairs"] ?? null) ? $offspring["offspring_pairs"] : [];
		$babyType = is_string($pairs[$mate->getAddonIdentifier()] ?? null) ? $pairs[$mate->getAddonIdentifier()]
			: (is_string($entry["baby_type"] ?? null) ? $entry["baby_type"] : $this->getAddonIdentifier());
		$baby = AddonManager::getInstance()?->createEntity($babyType, $this->getLocation());
		foreach([$this, $mate] as $parent){
			$breedable = is_array($parent->components["minecraft:breedable"] ?? null) ? $parent->components["minecraft:breedable"] : [];
			$cooldown = max(0.0, (float) ($breedable["breed_cooldown"] ?? 60.0));
			$parent->loveTicks = 0;
			$parent->breedCooldownUntil = $this->now() + (int) ($cooldown * 20);
			$parent->networkPropertiesDirty = true;
		}
		if($baby === null){
			return;
		}
		$event = $entry["breed_event"] ?? null;
		$baby->setSpawnEvent(is_array($event) && is_string($event["event"] ?? null) ? $event["event"] : "minecraft:entity_born");
		//property_inheritance: the baby takes each listed property from one of its parents
		foreach(is_array($offspring["property_inheritance"] ?? null) ? $offspring["property_inheritance"] : [] as $property => $rule){
			$parent = mt_rand(0, 1) === 0 ? $this : $mate;
			$value = $parent->getProperty((string) $property);
			if($value !== null){
				$baby->setProperty((string) $property, $value);
			}
		}
		if($this->isTamed() && $this->ownerName !== null){
			$baby->tamed = true;
			$baby->ownerName = $this->ownerName;
		}
		$baby->spawnToAll();
		$this->getWorld()->dropExperience($this->location, mt_rand(1, 7));
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->variant);
		$properties->setInt(EntityMetadataProperties::MARK_VARIANT, $this->markVariant);
		$properties->setInt(EntityMetadataProperties::SKIN_ID, $this->skinId);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->color);
		$c = $this->components;
		$properties->setGenericFlag(EntityMetadataFlags::BABY, isset($c["minecraft:is_baby"]));
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->isTamed());
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, isset($c["minecraft:is_saddled"]));
		$properties->setGenericFlag(EntityMetadataFlags::CHESTED, isset($c["minecraft:is_chested"]));
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, isset($c["minecraft:is_sheared"]));
		$properties->setGenericFlag(EntityMetadataFlags::CHARGED, isset($c["minecraft:is_charged"]));
		$properties->setGenericFlag(EntityMetadataFlags::POWERED, isset($c["minecraft:is_charged"]));
		$properties->setGenericFlag(EntityMetadataFlags::IGNITED, isset($c["minecraft:is_ignited"]) || $this->fuseTicks > 0);
		$properties->setGenericFlag(EntityMetadataFlags::INLOVE, $this->loveTicks > 0);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_FLY, isset($c["minecraft:can_fly"]));
		$properties->setGenericFlag(EntityMetadataFlags::STUNNED, isset($c["minecraft:is_stunned"]));
		$properties->setGenericFlag(EntityMetadataFlags::PREGNANT, isset($c["minecraft:is_pregnant"]));
		$properties->setByte(EntityMetadataProperties::COLOR_2, self::intValue($c["minecraft:color2"] ?? null));
		//Entity::syncNetworkData() resets the lead holder, so it is set here
		$holder = $this->getLeashHolder();
		$properties->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, $holder?->getId() ?? -1);
		$properties->setGenericFlag(EntityMetadataFlags::LEASHED, $holder !== null);
		$controllable = isset($c["minecraft:input_ground_controlled"]) || isset($c["minecraft:input_air_controlled"]);
		$properties->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, $controllable && isset($this->riders[$this->controllingSeat()]));
		$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, $this->controllingSeat());
	}

	protected function sendSpawnPacket(Player $player) : void{
		$player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
			$this->getId(),
			$this->getId(),
			$this->addonDefinition->getIdentifier(),
			$this->getOffsetPosition($this->location->asVector3()),
			$this->getMotion(),
			$this->location->pitch,
			$this->location->yaw,
			$this->location->yaw,
			$this->location->yaw,
			array_map(function(Attribute $attr) : NetworkAttribute{
				return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue());
			}, $this->attributeMap->getAll()),
			$this->getAllNetworkData(),
			$this->propertySyncData(),
			array_map(fn(int $seat) : EntityLink => new EntityLink($this->getId(), $this->riders[$seat]->getId(), $seat === $this->controllingSeat() ? EntityLink::TYPE_RIDER : EntityLink::TYPE_PASSENGER, true, false, 0.0), array_keys($this->riders))
		));
		$networkSession = $player->getNetworkSession();
		$networkSession->getEntityEventBroadcaster()->onMobArmorChange([$networkSession], $this);
		$this->sendHeldItems([$player]);
	}

	protected function onDispose() : void{
		$this->ejectRiders();
		$this->hideBossBar();
		$this->leashHolder = null;
		parent::onDispose();
	}

	protected function destroyCycles() : void{
		$this->riders = [];
		$this->bossViewers = [];
		$this->brain?->stopAll();
		$this->brain = null;
		$this->lastDamager = null;
		parent::destroyCycles();
	}

	/** A callback that logs a problem with this entity's definition once. @return \Closure(string) : void */
	public function reporter() : \Closure{
		return fn(string $message) => $this->report($message);
	}

	private function report(string $message) : void{
		$key = $this->addonDefinition->getIdentifier() . "\0" . $message;
		if(isset(self::$reported[$key])){
			return;
		}
		self::$reported[$key] = true;
		$this->server->getLogger()->warning("[Addons] " . $this->addonDefinition->getIdentifier() . " (" . $this->addonDefinition->getPackName() . "): $message");
	}
}
