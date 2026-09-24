// @minecraft/server for Amber.
//
// The classes mirror the game's script API (1.x and 2.x names). State is read from the server on demand (one
// snapshot per entity per tick, refreshed after anything that changes it) and every change is sent straight
// back, so scripts see a live world and plugins see what scripts do immediately.

import { call as ipcCall, post as ipcPost } from "./ipc.mjs";
import { host } from "./state.mjs";

// Reads keep the per-tick caches; anything else may change the world, so cached state is dropped after it.
const READ_OPS = new Set(["ent", "ents", "comp", "block", "top", "light", "biome", "ray", "rayents", "inv", "equip", "dp", "dpids", "prop", "geff", "effs", "time", "spawnpoint", "pspawn", "phas", "plist", "gamerule"]);
function call(op, args){
	const result = ipcCall(op, args);
	if(!READ_OPS.has(op)) host.invalidate();
	return result;
}
function post(op, args){
	ipcPost(op, args);
	if(op !== "log" && op !== "sub") host.invalidate();
}

// ---------------------------------------------------------------- enums

const enumOf = (names) => Object.freeze(Object.fromEntries(names.map(n => [n, n])));

export const GameMode = Object.freeze({
	survival: "survival", creative: "creative", adventure: "adventure", spectator: "spectator",
	Survival: "survival", Creative: "creative", Adventure: "adventure", Spectator: "spectator",
});
export const EntityDamageCause = enumOf(["anvil", "blockExplosion", "campfire", "charging", "contact", "drowning", "entityAttack", "entityExplosion", "fall", "fallingBlock", "fire", "fireTick", "fireworks", "flyIntoWall", "freezing", "lava", "lightning", "maceSmash", "magic", "magma", "none", "override", "piston", "projectile", "ramAttack", "selfDestruct", "sonicBoom", "soulCampfire", "stalactite", "stalagmite", "starve", "suffocation", "suicide", "temperature", "thorns", "void", "wither"]);
export const EquipmentSlot = enumOf(["Head", "Chest", "Legs", "Feet", "Mainhand", "Offhand"]);
export const Direction = enumOf(["Down", "Up", "North", "South", "West", "East"]);
export const DisplaySlotId = enumOf(["BelowName", "List", "Sidebar"]);
export const ObjectiveSortOrder = Object.freeze({ Ascending: 0, Descending: 1 });
export const ScoreboardIdentityType = enumOf(["Entity", "FakePlayer", "Player"]);
export const ScriptEventSource = enumOf(["Block", "Entity", "NPCDialogue", "Server"]);
export const EntityInitializationCause = enumOf(["Born", "Event", "Loaded", "Spawned", "Transformed"]);
export const TimeOfDay = Object.freeze({ Day: 1000, Noon: 6000, Sunset: 12000, Night: 13000, Midnight: 18000, Sunrise: 23000 });
export const MoonPhase = Object.freeze({ FullMoon: 0, WaningGibbous: 1, FirstQuarter: 2, WaningCrescent: 3, NewMoon: 4, WaxingCrescent: 5, LastQuarter: 6, WaxingGibbous: 7 });
export const WeatherType = enumOf(["Clear", "Rain", "Thunder"]);
export const GameRule = Object.freeze(Object.fromEntries(["commandBlockOutput", "commandBlocksEnabled", "doDayLightCycle", "doEntityDrops", "doFireTick", "doImmediateRespawn", "doInsomnia", "doLimitedCrafting", "doMobLoot", "doMobSpawning", "doTileDrops", "doWeatherCycle", "drowningDamage", "fallDamage", "fireDamage", "freezeDamage", "functionCommandLimit", "keepInventory", "maxCommandChainLength", "mobGriefing", "naturalRegeneration", "playersSleepingPercentage", "projectilesCanBreakBlocks", "pvp", "randomTickSpeed", "recipesUnlock", "respawnBlocksExplode", "sendCommandFeedback", "showBorderEffect", "showCoordinates", "showDaysPlayed", "showDeathMessages", "showRecipeMessages", "showTags", "spawnRadius", "tntExplodes", "tntExplosionDropDecay"].map(r => [r[0].toUpperCase() + r.slice(1), r])));
export const EntityHealCause = enumOf(["Heal", "Regeneration", "SelfHeal", "TotemOfUndying"]);
export const EntitySwingSource = enumOf(["Attack", "Build", "DropItem", "Event", "Interact", "Mine", "None", "ThrowItem", "UseItem"]);
export const InputMode = enumOf(["Gamepad", "KeyboardAndMouse", "MotionController", "Touch"]);
export const InputButton = enumOf(["Jump", "Sneak"]);
export const ButtonState = enumOf(["Pressed", "Released"]);
export const PlayerInventoryType = enumOf(["Hotbar", "Inventory"]);
export const InputPermissionCategory = Object.freeze({ Camera: 1, Movement: 2, LateralMovement: 4, Sneak: 5, Jump: 6, Mount: 7, Dismount: 8, MoveForward: 9, MoveBackward: 10, MoveLeft: 11, MoveRight: 12 });
export const HudElement = Object.freeze({ PaperDoll: 0, Armor: 1, ToolTips: 2, TouchControls: 3, Crosshair: 4, Hotbar: 5, Health: 6, ProgressBar: 7, Hunger: 8, AirBubbles: 9, HorseHealth: 10, StatusEffects: 11, ItemText: 12 });
export const HudVisibility = Object.freeze({ Hide: 0, Reset: 1 });
export const LiquidType = enumOf(["Water"]);
export const ItemLockMode = enumOf(["inventory", "none", "slot"]);
export const EntityComponentTypes = Object.freeze({
	AddRider: "minecraft:addrider", Ageable: "minecraft:ageable", Breathable: "minecraft:breathable", CanClimb: "minecraft:can_climb", CanFly: "minecraft:can_fly",
	Color: "minecraft:color", Equippable: "minecraft:equippable", Rideable: "minecraft:rideable", Riding: "minecraft:riding", Leashable: "minecraft:leashable", FireImmune: "minecraft:fire_immune", Health: "minecraft:health", Inventory: "minecraft:inventory",
	IsBaby: "minecraft:is_baby", IsTamed: "minecraft:is_tamed", Item: "minecraft:item", MarkVariant: "minecraft:mark_variant", Movement: "minecraft:movement",
	OnFire: "minecraft:onfire", Projectile: "minecraft:projectile", Scale: "minecraft:scale", SkinId: "minecraft:skin_id", Tameable: "minecraft:tameable",
	TypeFamily: "minecraft:type_family", Variant: "minecraft:variant",
});
export const ItemComponentTypes = Object.freeze({ Cooldown: "minecraft:cooldown", Durability: "minecraft:durability", Enchantable: "minecraft:enchantable", Food: "minecraft:food" });
export const BlockComponentTypes = Object.freeze({ Inventory: "minecraft:inventory" });
export const CustomCommandPermissionLevel = Object.freeze({ Any: 0, GameDirectors: 1, Admin: 2, Host: 3, Owner: 4 });
export const CommandPermissionLevel = CustomCommandPermissionLevel;

// ---------------------------------------------------------------- errors

export class InvalidEntityError extends Error{}
export class LocationOutOfWorldBoundariesError extends Error{}
export class LocationInUnloadedChunkError extends Error{}
export class CommandError extends Error{}
export class EnchantmentLevelOutOfBoundsError extends Error{}
export class EnchantmentTypeNotCompatibleError extends Error{}
export class EnchantmentTypeUnknownIdError extends Error{}
export class InvalidContainerSlotError extends Error{}
export class ContainerRulesError extends Error{}

// ---------------------------------------------------------------- helpers

const ns = (id) => (typeof id === "string" && !id.includes(":")) ? "minecraft:" + id : id;
const vec = (v) => ({ x: +v.x, y: +v.y, z: +v.z });

function flattenText(message){
	if(typeof message === "string") return message;
	if(Array.isArray(message)) return message.map(flattenText).join("");
	if(message && typeof message === "object"){
		if(message.rawtext) return message.rawtext.map(flattenText).join("");
		if(message.text !== undefined) return String(message.text);
		if(message.translate !== undefined){
			const w = Array.isArray(message.with) ? message.with : (message.with?.rawtext ? message.with.rawtext.map(flattenText) : []);
			return "%" + message.translate + (w.length ? "|" + w.map(flattenText).join("|") : "");
		}
		if(message.score) return "";
	}
	return String(message);
}

// ---------------------------------------------------------------- vectors, molang

export class MolangVariableMap{
	constructor(){ this._vars = []; }
	setFloat(name, value){ this._vars.push({ name, value: { type: "float", value: +value } }); }
	setVector3(name, v){ this._vars.push({ name, value: { type: "member_array", value: [["x", v.x], ["y", v.y], ["z", v.z]].map(([k, val]) => ({ name: "." + k, value: { type: "float", value: +val } })) } }); }
	setColorRGB(name, c){ this._vars.push({ name, value: { type: "member_array", value: [["r", c.red], ["g", c.green], ["b", c.blue]].map(([k, val]) => ({ name: "." + k, value: { type: "float", value: +val } })) } }); }
	setColorRGBA(name, c){ this._vars.push({ name, value: { type: "member_array", value: [["r", c.red], ["g", c.green], ["b", c.blue], ["a", c.alpha]].map(([k, val]) => ({ name: "." + k, value: { type: "float", value: +val } })) } }); }
	setSpeedAndDirection(name, speed, direction){ this.setFloat(name + ".speed", speed); this.setVector3(name + ".direction", direction); }
}

// ---------------------------------------------------------------- items

export class ItemType{ constructor(id){ this.id = id; } }
export class ItemTypes{
	static get(id){ return new ItemType(ns(id)); }
	static getAll(){ return []; }
}

class ItemComponent{ constructor(stack){ this._stack = stack; } get isValid(){ return host.valid(true); } }
class ItemDurabilityComponent extends ItemComponent{
	static componentId = "minecraft:durability";
	get damage(){ return this._stack._damage ?? 0; }
	set damage(v){ this._stack._damage = Math.max(0, v | 0); }
	get maxDurability(){ return this._stack._maxDamage ?? 0; }
	getDamageChance(unbreaking = 0){ return 100 / (unbreaking + 1); }
	getDamageChanceRange(){ return { min: 0, max: 100 }; }
}
class ItemEnchantableComponent extends ItemComponent{
	static componentId = "minecraft:enchantable";
	get slots(){ return []; }
	getEnchantments(){ return (this._stack._ench ?? []).map(e => ({ type: { id: e.id, maxLevel: e.max ?? 5 }, level: e.lvl })); }
	getEnchantment(type){ const id = typeof type === "string" ? type : type.id; const e = (this._stack._ench ?? []).find(x => x.id === id || x.id === ns(id)); return e ? { type: { id: e.id, maxLevel: e.max ?? 5 }, level: e.lvl } : undefined; }
	hasEnchantment(type){ return this.getEnchantment(type) !== undefined; }
	addEnchantment(e){ const id = typeof e.type === "string" ? e.type : e.type.id; this.removeEnchantment(id); (this._stack._ench ??= []).push({ id: ns(id), lvl: e.level }); }
	addEnchantments(list){ list.forEach(e => this.addEnchantment(e)); }
	removeEnchantment(type){ const id = ns(typeof type === "string" ? type : type.id); this._stack._ench = (this._stack._ench ?? []).filter(x => x.id !== id); }
	removeAllEnchantments(){ this._stack._ench = []; }
	canAddEnchantment(){ return true; }
}
class ItemCooldownComponent extends ItemComponent{
	static componentId = "minecraft:cooldown";
	get cooldownCategory(){ return this._stack.typeId; }
	get cooldownTicks(){ return 0; }
	startCooldown(){}
	getCooldownTicksRemaining(){ return 0; }
	isCooldownCategory(c){ return c === this.cooldownCategory; }
}
class ItemFoodComponent extends ItemComponent{
	static componentId = "minecraft:food";
	get canAlwaysEat(){ return false; } get nutrition(){ return 0; } get saturationModifier(){ return 0; } get usingConvertsTo(){ return undefined; }
}

export class ItemStack{
	constructor(itemType, amount = 1){
		this._type = ns(typeof itemType === "string" ? itemType : itemType.id);
		this.amount = amount;
		this.nameTag = undefined;
		this._lore = [];
		this._damage = 0;
		this._maxDamage = 0;
		this._ench = [];
		this.keepOnDeath = false;
		this.lockMode = "none";
		this._props = {};
	}
	get typeId(){ return this._type; }
	get type(){ return new ItemType(this._type); }
	get maxAmount(){ return this._max ?? 64; }
	get isStackable(){ return this.maxAmount > 1; }
	getLore(){ return [...this._lore]; }
	setLore(lore){ this._lore = lore ? [...lore] : []; }
	getRawLore(){ return this._lore.map(text => ({ text })); }
	getTags(){ return []; }
	hasTag(){ return false; }
	getComponent(id){
		id = ns(id);
		if(id === "minecraft:durability") return this._maxDamage > 0 ? new ItemDurabilityComponent(this) : undefined;
		if(id === "minecraft:enchantable") return new ItemEnchantableComponent(this);
		if(id === "minecraft:cooldown") return new ItemCooldownComponent(this);
		if(id === "minecraft:food") return new ItemFoodComponent(this);
		return undefined;
	}
	getComponents(){ return ["minecraft:durability", "minecraft:enchantable"].map(c => this.getComponent(c)).filter(Boolean); }
	hasComponent(id){ return this.getComponent(id) !== undefined; }
	clone(){ return ItemStack._from(this._toWire()); }
	isStackableWith(other){ return other instanceof ItemStack && other.typeId === this.typeId && other.nameTag === this.nameTag && JSON.stringify(other._lore) === JSON.stringify(this._lore); }
	matches(id){ return ns(id) === this._type; }
	setCanPlaceOn(){} setCanDestroy(){} getCanPlaceOn(){ return []; } getCanDestroy(){ return []; }
	getDynamicProperty(k){ return this._props[k]; }
	setDynamicProperty(k, v){ if(v === undefined) delete this._props[k]; else this._props[k] = v; }
	getDynamicPropertyIds(){ return Object.keys(this._props); }
	clearDynamicProperties(){ this._props = {}; }
	_toWire(){ return { id: this._type, count: this.amount, name: this.nameTag ?? null, lore: this._lore, dmg: this._damage, ench: this._ench, props: this._props }; }
	static _from(w){
		if(!w) return undefined;
		const s = new ItemStack(w.id, w.count);
		s.nameTag = w.name ?? undefined;
		s._lore = w.lore ?? [];
		s._damage = w.dmg ?? 0;
		s._maxDamage = w.maxDmg ?? 0;
		s._ench = w.ench ?? [];
		s._max = w.max;
		s._props = w.props ?? {};
		return s;
	}
}

// ---------------------------------------------------------------- blocks

export class BlockType{ constructor(id){ this.id = id; } }
export class BlockTypes{ static get(id){ return new BlockType(ns(id)); } static getAll(){ return []; } }

export class BlockPermutation{
	constructor(type, states){ this._type = ns(type); this._states = states ?? {}; }
	static resolve(type, states){ return new BlockPermutation(type, states); }
	get type(){ return new BlockType(this._type); }
	getAllStates(){ return { ...this._states }; }
	getState(name){ return this._states[name]; }
	withState(name, value){ return new BlockPermutation(this._type, { ...this._states, [name]: value }); }
	matches(type, states){ if(ns(type) !== this._type) return false; for(const k in (states ?? {})) if(this._states[k] !== states[k]) return false; return true; }
	getTags(){ return []; }
	hasTag(){ return false; }
	getItemStack(amount = 1){ return new ItemStack(this._type, amount); }
	isLiquid(){ return this._type.includes("water") || this._type.includes("lava"); }
}

export class Block{
	constructor(dimension, location){ this.dimension = dimension; this.x = Math.floor(location.x); this.y = Math.floor(location.y); this.z = Math.floor(location.z); this._data = undefined; this._tick = -1; }
	get location(){ return { x: this.x, y: this.y, z: this.z }; }
	_d(){ if(this._tick !== host.stamp || this._data === undefined){ this._data = call("block", { dim: this.dimension.id, x: this.x, y: this.y, z: this.z }); this._tick = host.stamp; } return this._data; }
	_valid(){ return this._d() !== null; }
	get isValid(){ return host.valid(this._valid()); }
	get typeId(){ return this._d()?.type ?? "minecraft:air"; }
	get type(){ return new BlockType(this.typeId); }
	get permutation(){ const d = this._d(); return new BlockPermutation(d?.type ?? "minecraft:air", d?.states ?? {}); }
	get isAir(){ return this.typeId === "minecraft:air"; }
	get isLiquid(){ return this.typeId.includes("water") || this.typeId.includes("lava"); }
	get isSolid(){ return this._d()?.solid ?? false; }
	get isWaterlogged(){ return false; }
	setType(type){ post("setblock", { dim: this.dimension.id, x: this.x, y: this.y, z: this.z, type: ns(typeof type === "string" ? type : type.id), states: {} }); this._data = undefined; }
	setPermutation(p){ post("setblock", { dim: this.dimension.id, x: this.x, y: this.y, z: this.z, type: p._type, states: p._states }); this._data = undefined; }
	offset(o){ return this.dimension.getBlock({ x: this.x + o.x, y: this.y + o.y, z: this.z + o.z }); }
	above(n = 1){ return this.offset({ x: 0, y: n, z: 0 }); }
	below(n = 1){ return this.offset({ x: 0, y: -n, z: 0 }); }
	north(n = 1){ return this.offset({ x: 0, y: 0, z: -n }); }
	south(n = 1){ return this.offset({ x: 0, y: 0, z: n }); }
	east(n = 1){ return this.offset({ x: n, y: 0, z: 0 }); }
	west(n = 1){ return this.offset({ x: -n, y: 0, z: 0 }); }
	center(){ return { x: this.x + 0.5, y: this.y + 0.5, z: this.z + 0.5 }; }
	bottomCenter(){ return { x: this.x + 0.5, y: this.y, z: this.z + 0.5 }; }
	getComponent(){ return undefined; }
	hasTag(){ return false; }
	getTags(){ return []; }
	getItemStack(amount = 1){ return new ItemStack(this.typeId, amount); }
	matches(type, states){ return this.permutation.matches(type, states); }
	canPlace(){ return true; }
	getRedstonePower(){ return 0; }
}

// ---------------------------------------------------------------- containers

export class ContainerSlot{
	constructor(container, slot){ this._c = container; this._slot = slot; }
	getItem(){ return this._c.getItem(this._slot); }
	setItem(item){ this._c.setItem(this._slot, item); }
	hasItem(){ return this.getItem() !== undefined; }
	get isValid(){ return host.valid(true); }
	get typeId(){ return this.getItem()?.typeId; }
	get amount(){ return this.getItem()?.amount ?? 0; }
	set amount(v){ const i = this.getItem(); if(i){ i.amount = v; this.setItem(i); } }
	get nameTag(){ return this.getItem()?.nameTag; }
	set nameTag(v){ const i = this.getItem(); if(i){ i.nameTag = v; this.setItem(i); } }
	getLore(){ return this.getItem()?.getLore() ?? []; }
	setLore(l){ const i = this.getItem(); if(i){ i.setLore(l); this.setItem(i); } }
}

export class Container{
	constructor(owner, kind){ this._owner = owner; this._kind = kind; this._data = undefined; this._tick = -1; }
	_d(){ if(this._tick !== host.stamp || this._data === undefined){ this._data = call("inv", { id: this._owner.id, kind: this._kind }); this._tick = host.stamp; } return this._data ?? { size: 0, items: {} }; }
	get size(){ return this._d().size; }
	get emptySlotsCount(){ const d = this._d(); return d.size - Object.keys(d.items).length; }
	get isValid(){ return host.valid(this._owner._valid()); }
	getItem(slot){ if(slot < 0 || slot >= this.size) throw new InvalidContainerSlotError(`slot ${slot}`); return ItemStack._from(this._d().items[slot]); }
	getSlot(slot){ return new ContainerSlot(this, slot); }
	setItem(slot, item){ post("invset", { id: this._owner.id, kind: this._kind, slot, item: item ? item._toWire() : null }); this._data = undefined; }
	addItem(item){ const left = call("invadd", { id: this._owner.id, kind: this._kind, item: item._toWire() }); this._data = undefined; return ItemStack._from(left); }
	clearAll(){ post("invclear", { id: this._owner.id, kind: this._kind }); this._data = undefined; }
	swapItems(a, b, other){ const x = this.getItem(a); const y = other.getItem(b); this.setItem(a, y); other.setItem(b, x); }
	transferItem(from, to){ const x = this.getItem(from); if(!x) return undefined; this.setItem(from, undefined); return to.addItem(x); }
	moveItem(from, to, other){ const x = this.getItem(from); this.setItem(from, undefined); other.setItem(to, x); }
	contains(item){ return Object.values(this._d().items).some(w => w.id === item.typeId); }
	find(item){ for(const [slot, w] of Object.entries(this._d().items)) if(w.id === item.typeId) return +slot; return undefined; }
	firstEmptySlot(){ const d = this._d(); for(let i = 0; i < d.size; i++) if(d.items[i] === undefined) return i; return undefined; }
	firstItem(){ const d = this._d(); for(let i = 0; i < d.size; i++) if(d.items[i] !== undefined) return i; return undefined; }
}

// ---------------------------------------------------------------- entity components

class EntityComponent{
	constructor(entity, typeId, data){ this.entity = entity; this.typeId = typeId; this._data = data ?? {}; }
	get isValid(){ return host.valid(this.entity._valid()); }
}
class EntityAttributeComponent extends EntityComponent{
	get currentValue(){ return this.entity._s().hp; }
	get effectiveMax(){ return this.entity._s().maxHp; }
	get effectiveMin(){ return 0; }
	get defaultValue(){ return this.entity._s().maxHp; }
	setCurrentValue(v){ post("hp", { id: this.entity.id, v }); this.entity._dirty(); return true; }
	resetToMaxValue(){ post("hp", { id: this.entity.id, v: this.entity._s().maxHp }); this.entity._dirty(); }
	resetToDefaultValue(){ this.resetToMaxValue(); }
	resetToMinValue(){ post("hp", { id: this.entity.id, v: 0 }); this.entity._dirty(); }
}
class EntityInventoryComponent extends EntityComponent{
	get container(){ return new Container(this.entity, this.typeId === "minecraft:ender_chest_inventory" ? "ender" : "inventory"); }
	get inventorySize(){ return this.container.size; }
	get canBeSiphonedFrom(){ return false; } get private(){ return false; } get restrictToOwner(){ return false; } get additionalSlotsPerStrength(){ return 0; } get containerType(){ return "inventory"; }
}
class EntityEquippableComponent extends EntityComponent{
	getEquipment(slot){ return ItemStack._from(call("equip", { id: this.entity.id, slot })); }
	setEquipment(slot, item){ post("setequip", { id: this.entity.id, slot, item: item ? item._toWire() : null }); return true; }
	getEquipmentSlot(slot){ const self = this; return { getItem: () => self.getEquipment(slot), setItem: (i) => self.setEquipment(slot, i), hasItem: () => self.getEquipment(slot) !== undefined, get isValid(){ return host.valid(true); } }; }
	get totalArmor(){ return this.entity._s().armor ?? 0; }
	get totalToughness(){ return 0; }
}
class EntityValueComponent extends EntityComponent{
	get value(){ const v = this._data?.value ?? this._data; return typeof v === "number" ? v : (v?.value ?? 0); }
	set value(v){}
}
class EntityTypeFamilyComponent extends EntityComponent{
	getTypeFamilies(){ return this.entity._s().fam ?? []; }
	hasTypeFamily(f){ return this.getTypeFamilies().includes(f); }
}
class EntityOnFireComponent extends EntityComponent{ get onFireTicksRemaining(){ return this.entity._s().fireTicks ?? 0; } }
class EntityMovementComponent extends EntityAttributeComponent{
	get currentValue(){ return this.entity._s().speed ?? 0.1; }
	get effectiveMax(){ return 1024; }
	setCurrentValue(v){ post("speed", { id: this.entity.id, v }); return true; }
}
class EntityProjectileComponent extends EntityComponent{
	shoot(velocity, opts){ post("imp", { id: this.entity.id, x: velocity.x, y: velocity.y, z: velocity.z, set: true, owner: opts?.owner?.id }); }
	get owner(){ return this.entity._owner(); }
	set owner(v){ post("owner", { id: this.entity.id, owner: v?.id ?? null }); }
}
class EntityTameableComponent extends EntityComponent{
	get isTamed(){ return this.entity._s().tamed ?? false; }
	get tamedToPlayer(){ return this.entity._owner(); }
	get tamedToPlayerId(){ return this.entity._s().owner ?? undefined; }
	tame(player){ post("tame", { id: this.entity.id, owner: player?.id }); this.entity._dirty(); return true; }
}
class EntityItemComponent extends EntityComponent{ get itemStack(){ return ItemStack._from(this.entity._s().item); } }
class EntityRideableComponent extends EntityComponent{
	get seatCount(){ return this._data?.seat_count ?? (Array.isArray(this._data?.seats) ? this._data.seats.length : 1); }
	get controllingSeat(){ return this._data?.controlling_seat ?? 0; }
	get crouchingSkipInteract(){ return this._data?.crouching_skip_interact ?? true; }
	get interactText(){ return this._data?.interact_text ?? ""; }
	get pullInEntities(){ return this._data?.pull_in_entities ?? false; }
	get riderCanInteract(){ return this._data?.rider_can_interact ?? false; }
	getFamilyTypes(){ return this._data?.family_types ?? []; }
	getSeats(){ const s = this._data?.seats; return (Array.isArray(s) ? s : (s ? [s] : [])).map(x => ({ position: { x: x.position?.[0] ?? 0, y: x.position?.[1] ?? 0, z: x.position?.[2] ?? 0 }, minRiderCount: x.min_rider_count ?? 0, maxRiderCount: x.max_rider_count ?? 1, lockRiderRotation: x.lock_rider_rotation ?? 0 })); }
	getRiders(){ return (this.entity._s().riders ?? []).map(id => entityFrom(id)).filter(Boolean); }
	addRider(rider){ const r = call("rideadd", { id: this.entity.id, rider: rider.id }); this.entity._dirty(); return r; }
	ejectRider(rider){ call("rideremove", { id: this.entity.id, rider: rider.id }); this.entity._dirty(); }
	ejectRiders(){ call("rideeject", { id: this.entity.id }); this.entity._dirty(); }
}
class EntityRidingComponent extends EntityComponent{
	get entityRidingOn(){ const v = this.entity._s().vehicle; return v ? entityFrom(v) : undefined; }
}
class EntityLeashableComponent extends EntityComponent{
	get isLeashed(){ return !!this.entity._s().leashHolder; }
	get leashHolder(){ const h = this.entity._s().leashHolder; return h ? entityFrom(h) : undefined; }
	get leashHolderEntityId(){ return this.entity._s().leashHolder ?? undefined; }
	get softDistance(){ return this._data?.soft_distance ?? 4; }
	get hardDistance(){ return this._data?.hard_distance ?? 6; }
	get maxDistance(){ return this._data?.max_distance ?? 10; }
	get canBeStolen(){ return this._data?.can_be_stolen ?? false; }
	leashTo(entity){ call("leash", { id: this.entity.id, holder: entity.id }); this.entity._dirty(); }
	unleash(){ call("leash", { id: this.entity.id }); this.entity._dirty(); }
}

const componentClasses = {
	"minecraft:health": EntityAttributeComponent,
	"minecraft:inventory": EntityInventoryComponent,
	"minecraft:ender_chest_inventory": EntityInventoryComponent,
	"minecraft:equippable": EntityEquippableComponent,
	"minecraft:variant": EntityValueComponent,
	"minecraft:mark_variant": EntityValueComponent,
	"minecraft:skin_id": EntityValueComponent,
	"minecraft:color": EntityValueComponent,
	"minecraft:scale": EntityValueComponent,
	"minecraft:type_family": EntityTypeFamilyComponent,
	"minecraft:onfire": EntityOnFireComponent,
	"minecraft:movement": EntityMovementComponent,
	"minecraft:projectile": EntityProjectileComponent,
	"minecraft:tameable": EntityTameableComponent,
	"minecraft:item": EntityItemComponent,
	"minecraft:rideable": EntityRideableComponent,
	"minecraft:riding": EntityRidingComponent,
	"minecraft:leashable": EntityLeashableComponent,
};
const alwaysComponents = ["minecraft:health", "minecraft:type_family", "minecraft:movement"];

// ---------------------------------------------------------------- effects

export class Effect{
	constructor(w){ this.typeId = w.type; this.duration = w.duration; this.amplifier = w.amplifier; this.displayName = w.name ?? w.type; }
	get isValid(){ return host.valid(true); }
	get isInfinite(){ return this.duration < 0; }
}
export class EffectType{ constructor(id){ this._id = id; } getName(){ return this._id; } }
export class EffectTypes{ static get(id){ return new EffectType(ns(id)); } static getAll(){ return []; } }

// ---------------------------------------------------------------- entities

const entityCache = new Map();

export function entityFrom(id, snapshot, typeHint){
	if(id === undefined || id === null) return undefined;
	id = String(id);
	let e = entityCache.get(id);
	if(e === undefined){
		const s = snapshot ?? call("ent", { id });
		if(s === null){
			//gone already (an event about it arrived after it despawned): as in the game, id and typeId still
			//read, anything else throws InvalidEntityError
			if(typeHint === undefined) return undefined;
			const ghost = new Entity(id);
			ghost._typeHint = typeHint;
			return ghost;
		}
		e = s.player ? new Player(id) : new Entity(id);
		entityCache.set(id, e);
		e._snap = s; e._tick = host.stamp;
	}else if(snapshot){
		e._snap = snapshot; e._tick = host.stamp;
	}
	return e;
}
export function forgetEntity(id){ entityCache.delete(String(id)); }

export class Entity{
	constructor(id){ this.id = String(id); this._snap = undefined; this._tick = -1; this._typeHint = undefined; }
	_s(){
		if(this._tick !== host.stamp || this._snap === undefined){
			this._snap = call("ent", { id: this.id });
			this._tick = host.stamp;
		}
		if(this._snap === null) throw new InvalidEntityError(`Entity ${this.id} is no longer valid`);
		this._typeHint = this._snap.type;
		return this._snap;
	}
	_dirty(){ this._tick = -1; }
	_owner(){ const o = this._s().owner; return o ? entityFrom(o) : undefined; }
	_valid(){ if(this._tick !== host.stamp || this._snap === undefined){ this._snap = call("ent", { id: this.id }); this._tick = host.stamp; } return this._snap !== null && this._snap.alive !== false; }
	get isValid(){ return host.valid(this._valid()); }
	get typeId(){
		try{ return this._s().type; }catch(e){ if(this._typeHint !== undefined) return this._typeHint; throw e; }
	}
	get location(){ const s = this._s(); return { x: s.x, y: s.y, z: s.z }; }
	get dimension(){ return world.getDimension(this._s().dim); }
	get nameTag(){ return this._s().nameTag ?? ""; }
	set nameTag(v){ post("nametag", { id: this.id, v: String(v) }); this._dirty(); }
	get isSneaking(){ return this._s().sneak ?? false; }
	set isSneaking(v){ post("sneak", { id: this.id, v: !!v }); this._dirty(); }
	get isSprinting(){ return this._s().sprint ?? false; }
	get isSwimming(){ return this._s().swim ?? false; }
	get isOnGround(){ return this._s().ground ?? false; }
	get isInWater(){ return this._s().water ?? false; }
	get isFalling(){ return !this.isOnGround && (this._s().vy ?? 0) < 0; }
	get isClimbing(){ return false; }
	get isGliding(){ return false; }
	get isSleeping(){ return false; }
	get localizationKey(){ return "entity." + this.typeId.replace("minecraft:", "") + ".name"; }
	get scoreboardIdentity(){ return world.scoreboard._identity(this); }
	get target(){ const t = this._s().target; return t ? entityFrom(t) : undefined; }
	getHeadLocation(){ const s = this._s(); return { x: s.x, y: s.y + (s.eye ?? 1.62), z: s.z }; }
	getVelocity(){ const s = this._s(); return { x: s.vx, y: s.vy, z: s.vz }; }
	getRotation(){ const s = this._s(); return { x: s.rx, y: s.ry }; }
	setRotation(r){ post("rot", { id: this.id, x: r.x, y: r.y }); this._dirty(); }
	getViewDirection(){
		const s = this._s();
		const pitch = s.rx * Math.PI / 180, yaw = s.ry * Math.PI / 180;
		return { x: -Math.sin(yaw) * Math.cos(pitch), y: -Math.sin(pitch), z: Math.cos(yaw) * Math.cos(pitch) };
	}
	getComponent(id){
		id = ns(id);
		const cls = componentClasses[id];
		const has = alwaysComponents.includes(id) || (this._s().comps ?? []).includes(id) || (id === "minecraft:inventory" && (this._s().player || (this._s().comps ?? []).includes("minecraft:inventory"))) || (id === "minecraft:equippable" && this._s().living) || (id === "minecraft:ender_chest_inventory" && this._s().player) || (id === "minecraft:onfire" && (this._s().fireTicks ?? 0) > 0) || (id === "minecraft:item" && this._s().item) || (id === "minecraft:riding" && this._s().vehicle);
		if(!has) return undefined;
		const data = (id in (this._s().compData ?? {})) ? this._s().compData[id] : (this._s().comps?.includes(id) ? call("comp", { id: this.id, name: id }) : undefined);
		if(cls) return new cls(this, id, data);
		return Object.assign(new EntityComponent(this, id, data), typeof data === "object" && data !== null ? data : {});
	}
	getComponents(){ return [...new Set([...alwaysComponents, ...(this._s().comps ?? [])])].map(c => this.getComponent(c)).filter(Boolean); }
	hasComponent(id){ return this.getComponent(id) !== undefined; }
	addTag(tag){ const r = call("tag", { id: this.id, tag, add: true }); this._dirty(); return r; }
	removeTag(tag){ const r = call("tag", { id: this.id, tag, add: false }); this._dirty(); return r; }
	hasTag(tag){ return (this._s().tags ?? []).includes(tag); }
	getTags(){ return [...(this._s().tags ?? [])]; }
	kill(){ const r = call("kill", { id: this.id }); this._dirty(); return r; }
	remove(){ post("remove", { id: this.id }); forgetEntity(this.id); }
	teleport(location, options = {}){
		const a = { id: this.id, x: location.x, y: location.y, z: location.z };
		if(options.dimension) a.dim = options.dimension.id;
		if(options.rotation){ a.rx = options.rotation.x; a.ry = options.rotation.y; }
		if(options.facingLocation){ a.fx = options.facingLocation.x; a.fy = options.facingLocation.y; a.fz = options.facingLocation.z; }
		post("tp", a); this._dirty();
	}
	tryTeleport(location, options){ this.teleport(location, options); return true; }
	applyDamage(amount, options){
		const a = { id: this.id, amount };
		if(options?.cause) a.cause = options.cause;
		if(options?.damagingEntity) a.damager = options.damagingEntity.id;
		const r = call("dmg", a); this._dirty(); return r;
	}
	applyImpulse(v){ post("imp", { id: this.id, x: v.x, y: v.y, z: v.z }); this._dirty(); }
	applyKnockback(a, b, c, d){
		//1.x: (directionX, directionZ, horizontalStrength, verticalStrength); 2.x: ({x, z} strength vector, verticalStrength)
		if(typeof a === "object") post("kb", { id: this.id, dx: a.x, dz: a.z, h: Math.hypot(a.x, a.z), v: b, raw: true });
		else post("kb", { id: this.id, dx: a, dz: b, h: c, v: d });
		this._dirty();
	}
	clearVelocity(){ post("clrvel", { id: this.id }); this._dirty(); }
	addEffect(type, duration, options = {}){
		post("eff", { id: this.id, effect: ns(typeof type === "string" ? type : type.getName()), dur: duration, amp: options.amplifier ?? 0, particles: options.showParticles !== false });
		this._dirty();
	}
	removeEffect(type){ const r = call("reff", { id: this.id, effect: ns(typeof type === "string" ? type : type.getName()) }); this._dirty(); return r; }
	getEffect(type){ const w = call("geff", { id: this.id, effect: ns(typeof type === "string" ? type : type.getName()) }); return w ? new Effect(w) : undefined; }
	getEffects(){ return (call("effs", { id: this.id }) ?? []).map(w => new Effect(w)); }
	setOnFire(seconds, useEffects = true){ post("fire", { id: this.id, sec: seconds }); this._dirty(); return true; }
	extinguishFire(){ post("fire", { id: this.id, sec: 0 }); this._dirty(); return true; }
	triggerEvent(event){ post("trig", { id: this.id, event }); this._dirty(); }
	getProperty(name){ return call("prop", { id: this.id, name }); }
	setProperty(name, value){ post("sprop", { id: this.id, name, v: value }); }
	resetProperty(name){ return call("rprop", { id: this.id, name }); }
	runCommand(command){ return call("cmd", { cmd: command, as: this.id }); }
	runCommandAsync(command){ return Promise.resolve(this.runCommand(command)); }
	getDynamicProperty(key){ return call("dp", { scope: this.id, key }) ?? undefined; }
	setDynamicProperty(key, value){ post("sdp", { scope: this.id, key, v: value === undefined ? null : (typeof value === "object" ? vec(value) : value) }); }
	getDynamicPropertyIds(){ return call("dpids", { scope: this.id }); }
	getDynamicPropertyTotalByteCount(){ return 0; }
	clearDynamicProperties(){ post("dpclear", { scope: this.id }); }
	getBlockFromViewDirection(options = {}){
		const head = this.getHeadLocation();
		return this.dimension.getBlockFromRay(head, this.getViewDirection(), options);
	}
	getEntitiesFromViewDirection(options = {}){
		const head = this.getHeadLocation(), dir = this.getViewDirection(), max = options.maxDistance ?? 64;
		return (call("rayents", { dim: this._s().dim, x: head.x, y: head.y, z: head.z, dx: dir.x, dy: dir.y, dz: dir.z, max, self: this.id }) ?? [])
			.map(h => ({ entity: entityFrom(h.id, h.snap), distance: h.d })).filter(h => h.entity);
	}
	lookAt(location){ post("lookat", { id: this.id, x: location.x, y: location.y, z: location.z }); this._dirty(); }
	matches(options){ return (call("ents", { q: { ...queryWire(options), ids: [this.id] } }) ?? []).length > 0; }
	playAnimation(animation, options){ post("anim", { id: this.id, anim: animation, next: options?.nextState, players: options?.players?.map(p => typeof p === "string" ? p : p.name) }); }
	getAABB(){ const s = this._s(); return { center: { x: s.x, y: s.y + (s.h ?? 1.8) / 2, z: s.z }, extent: { x: (s.w ?? 0.6) / 2, y: (s.h ?? 1.8) / 2, z: (s.w ?? 0.6) / 2 } }; }
}

class ScreenDisplay{
	constructor(player){ this._p = player; }
	setTitle(title, options = {}){
		post("title", { id: this._p.id, kind: "title", text: flattenText(title), sub: options.subtitle !== undefined ? flattenText(options.subtitle) : null, fi: options.fadeInDuration ?? 10, st: options.stayDuration ?? 70, fo: options.fadeOutDuration ?? 20 });
	}
	updateSubtitle(subtitle){ post("title", { id: this._p.id, kind: "subtitle", text: flattenText(subtitle) }); }
	setActionBar(text){ post("title", { id: this._p.id, kind: "actionbar", text: flattenText(text) }); }
	setHudVisibility(visible, elements){ call("hud", { id: this._p.id, hide: visible === 0 || visible === false || visible === "Hide", elements }); }
	hideAllExcept(elements){ const keep = new Set(elements ?? []); call("hud", { id: this._p.id, hide: true, elements: [...Array(13).keys()].filter(e => !keep.has(e)) }); }
	resetHudElements(){ call("hud", { id: this._p.id, hide: false }); }
	isForcedHidden(){ return false; }
	get isValid(){ return host.valid(this._p._valid()); }
}

class PlayerInputPermissions{
	constructor(p){ this._p = p; }
	get cameraEnabled(){ return true; } set cameraEnabled(v){ post("inperm", { id: this._p.id, perm: "camera", v: !!v }); }
	get movementEnabled(){ return true; } set movementEnabled(v){ post("inperm", { id: this._p.id, perm: "movement", v: !!v }); }
	setPermissionCategory(category, enabled){ post("inperm", { id: this._p.id, perm: category, v: !!enabled }); }
	isPermissionCategoryEnabled(){ return true; }
}

class Camera{
	constructor(p){ this._p = p; }
	clear(){ call("camera", { id: this._p.id, action: "clear" }); }
	fade(options){ call("camera", { id: this._p.id, action: "fade", o: options ?? {} }); }
	setCamera(preset, options){
		const o = { ...(options ?? {}) };
		if(o.facingEntity) o.facingEntity = o.facingEntity.id;
		call("camera", { id: this._p.id, action: "set", preset, o });
	}
	setDefaultCamera(preset, easeOptions){ this.setCamera(preset, { easeOptions }); }
	setFov(options){ call("camera", { id: this._p.id, action: "fov", o: options ?? {} }); }
	clearFov(){ call("camera", { id: this._p.id, action: "clearfov" }); }
	get isValid(){ return host.valid(true); }
}

export class Player extends Entity{
	constructor(id){ super(id); this.onScreenDisplay = new ScreenDisplay(this); this.inputPermissions = new PlayerInputPermissions(this); this.camera = new Camera(this); }
	get name(){ return this._s().name; }
	get typeId(){ return "minecraft:player"; }
	get level(){ return this._s().xpl ?? 0; }
	get totalXpNeededForNextLevel(){ const l = this.level; return l >= 30 ? 112 + (l - 30) * 9 : (l >= 15 ? 37 + (l - 15) * 5 : 7 + l * 2); }
	get xpEarnedAtCurrentLevel(){ return this._s().xpp ?? 0; }
	get selectedSlotIndex(){ return this._s().slot ?? 0; }
	set selectedSlotIndex(v){ post("selslot", { id: this.id, v }); this._dirty(); }
	get isFlying(){ return this._s().flying ?? false; }
	get isJumping(){ return false; }
	get isEmoting(){ return false; }
	get clientSystemInfo(){ return { maxRenderDistance: 12, memoryTier: 3, platformType: "Desktop" }; }
	get graphicsMode(){ return "Fancy"; }
	get commandPermissionLevel(){ return this._s().op ? 2 : 0; }
	get playerPermissionLevel(){ return this._s().op ? 2 : 1; }
	sendMessage(message){ post("msg", { ids: [this.id], text: flattenText(message) }); }
	getGameMode(){ return this._s().gm; }
	setGameMode(mode){ post("gm", { id: this.id, mode: mode === undefined ? world._defaultGameMode : String(mode).toLowerCase() }); this._dirty(); }
	isOp(){ return this._s().op ?? false; }
	setOp(v){ post("op", { id: this.id, v: !!v }); this._dirty(); }
	playSound(sound, options = {}){ const l = options.location ?? this.location; post("sound", { dim: this._s().dim, name: sound, x: l.x, y: l.y, z: l.z, vol: options.volume ?? 1, pitch: options.pitch ?? 1, ids: [this.id] }); }
	stopSound(sound){ post("stopsound", { id: this.id, name: sound ?? "" }); }
	stopAllSounds(){ post("stopsound", { id: this.id, name: "" }); }
	playMusic(){} queueMusic(){} stopMusic(){}
	addExperience(amount){ post("xp", { id: this.id, amt: amount, levels: false }); this._dirty(); return this.level; }
	addLevels(amount){ post("xp", { id: this.id, amt: amount, levels: true }); this._dirty(); return this.level + amount; }
	resetLevel(){ post("xp", { id: this.id, reset: true }); this._dirty(); }
	getTotalXp(){ return this._s().xpt ?? 0; }
	getSpawnPoint(){ const s = call("pspawn", { id: this.id }); return s ? { ...s, dimension: world.getDimension(s.dim) } : undefined; }
	setSpawnPoint(p){ post("setpspawn", { id: this.id, x: p?.x, y: p?.y, z: p?.z, dim: p?.dimension?.id }); }
	getItemCooldown(){ return 0; }
	startItemCooldown(){}
	spawnParticle(name, location, vars){ post("particle", { dim: this._s().dim, name, x: location.x, y: location.y, z: location.z, vars: vars?._vars ?? [], ids: [this.id] }); }
	postClientMessage(){}
	eatItem(){}
	getControlScheme(){ return "LockedPlayerRelativeStrafe"; }
	setControlScheme(){}
	clearPropertyOverridesForEntity(){} removePropertyOverrideForEntity(){} setPropertyOverrideForEntity(){}
}

// ---------------------------------------------------------------- queries

function queryWire(o = {}){
	const q = {};
	for(const k of ["type", "name", "families", "excludeFamilies", "tags", "excludeTags", "excludeNames", "maxDistance", "minDistance", "closest", "farthest", "gameMode", "excludeGameModes"]){
		if(o[k] !== undefined) q[k] = o[k];
	}
	if(o.type) q.type = ns(o.type);
	if(o.excludeTypes) q.excludeTypes = o.excludeTypes.map(ns);
	if(o.location) q.location = vec(o.location);
	if(o.volume) q.volume = vec(o.volume);
	if(o.minHorizontalRotation !== undefined || o.maxHorizontalRotation !== undefined){ q.minRy = o.minHorizontalRotation; q.maxRy = o.maxHorizontalRotation; }
	if(o.propertyOptions) q.props = o.propertyOptions;
	return q;
}

// ---------------------------------------------------------------- dimensions

export class Dimension{
	constructor(id, info){ this.id = id; this._info = info ?? { min: -64, max: 320 }; }
	get heightRange(){ return { min: this._info.min, max: this._info.max }; }
	get localizationKey(){ return "dimension." + this.id.replace("minecraft:", ""); }
	getBlock(location){
		const b = new Block(this, location);
		if(b._d() === null) return undefined;
		return b;
	}
	getBlockAbove(location, o){ const y = call("top", { dim: this.id, x: Math.floor(location.x), z: Math.floor(location.z) }); return y !== null ? this.getBlock({ x: location.x, y, z: location.z }) : undefined; }
	getBlockBelow(location){ for(let y = Math.floor(location.y) - 1; y >= this._info.min; y--){ const b = this.getBlock({ x: location.x, y, z: location.z }); if(b && !b.isAir) return b; } return undefined; }
	getTopmostBlock(location, minHeight){ const y = call("top", { dim: this.id, x: Math.floor(location.x), z: Math.floor(location.z), min: minHeight }); return y === null ? undefined : this.getBlock({ x: location.x, y, z: location.z }); }
	getBlockFromRay(location, direction, options = {}){
		const r = call("ray", { dim: this.id, x: location.x, y: location.y, z: location.z, dx: direction.x, dy: direction.y, dz: direction.z, max: options.maxDistance ?? 64, liquid: !!options.includeLiquidBlocks, passable: !!options.includePassableBlocks });
		if(!r) return undefined;
		const block = new Block(this, r); block._data = { type: r.type, states: r.states, solid: true }; block._tick = host.stamp;
		return { block, face: r.face, faceLocation: { x: r.fx, y: r.fy, z: r.fz } };
	}
	getEntitiesFromRay(location, direction, options = {}){
		return (call("rayents", { dim: this.id, x: location.x, y: location.y, z: location.z, dx: direction.x, dy: direction.y, dz: direction.z, max: options.maxDistance ?? 64 }) ?? [])
			.map(h => ({ entity: entityFrom(h.id, h.snap), distance: h.d })).filter(h => h.entity);
	}
	getEntities(options = {}){
		return (call("ents", { dim: this.id, q: queryWire(options) }) ?? []).map(s => entityFrom(s.id, s)).filter(Boolean);
	}
	getEntitiesAtBlockLocation(location){
		return this.getEntities({ location: { x: Math.floor(location.x) + 0.5, y: Math.floor(location.y), z: Math.floor(location.z) + 0.5 }, maxDistance: 1 });
	}
	getPlayers(options = {}){
		return (call("ents", { dim: this.id, q: { ...queryWire(options), players: true } }) ?? []).map(s => entityFrom(s.id, s)).filter(Boolean);
	}
	spawnEntity(identifier, location, options = {}){
		const s = call("spawn", { dim: this.id, type: ns(identifier), x: location.x, y: location.y, z: location.z, ev: options.spawnEvent ?? null, init: options.initialPersistence ?? false });
		if(s === null) throw new Error(`Cannot spawn ${identifier}`);
		return entityFrom(s.id, s);
	}
	spawnItem(item, location){ const s = call("spawnitem", { dim: this.id, item: item._toWire(), x: location.x, y: location.y, z: location.z }); return s ? entityFrom(s.id, s) : undefined; }
	spawnParticle(name, location, vars){ post("particle", { dim: this.id, name, x: location.x, y: location.y, z: location.z, vars: vars?._vars ?? [] }); }
	playSound(sound, location, options = {}){ post("sound", { dim: this.id, name: sound, x: location.x, y: location.y, z: location.z, vol: options.volume ?? 1, pitch: options.pitch ?? 1 }); }
	createExplosion(location, radius, options = {}){ post("boom", { dim: this.id, x: location.x, y: location.y, z: location.z, r: radius, fire: !!options.causesFire, breaks: options.breaksBlocks !== false, src: options.source?.id }); return true; }
	runCommand(command){ return call("cmd", { cmd: command, dim: this.id }); }
	runCommandAsync(command){ return Promise.resolve(this.runCommand(command)); }
	setBlockType(location, type){ post("setblock", { dim: this.id, x: Math.floor(location.x), y: Math.floor(location.y), z: Math.floor(location.z), type: ns(typeof type === "string" ? type : type.id), states: {} }); }
	setBlockPermutation(location, p){ post("setblock", { dim: this.id, x: Math.floor(location.x), y: Math.floor(location.y), z: Math.floor(location.z), type: p._type, states: p._states }); }
	fillBlocks(volume, block, options){
		//1.x: fillBlocks(begin, end, block, options); 2.x: fillBlocks(volume, block, options)
		const legacy = block && typeof block === "object" && typeof block.x === "number" && !(block instanceof BlockPermutation);
		const from = legacy ? volume : (volume.from ?? volume.getMin?.() ?? volume), to = legacy ? block : (volume.to ?? volume.getMax?.() ?? volume);
		if(legacy){ block = options; }
		const p = typeof block === "string" ? new BlockPermutation(block, {}) : (block instanceof BlockType ? new BlockPermutation(block.id, {}) : block);
		if(!(p instanceof BlockPermutation)) throw new TypeError("fillBlocks needs a block type or permutation");
		return call("fill", { dim: this.id, x1: from.x, y1: from.y, z1: from.z, x2: to.x, y2: to.y, z2: to.z, type: p._type, states: p._states });
	}
	getWeather(){ return call("weather", { dim: this.id }); }
	setWeather(type, duration){ post("setweather", { dim: this.id, type: String(type).toLowerCase(), ticks: duration }); }
	isChunkLoaded(location){ return call("block", { dim: this.id, x: Math.floor(location.x), y: 0, z: Math.floor(location.z) }) !== null; }
	getLightLevel(location){ return call("light", { dim: this.id, x: Math.floor(location.x), y: Math.floor(location.y), z: Math.floor(location.z) }) ?? 0; }
	getSkyLightLevel(location){ return call("light", { dim: this.id, x: Math.floor(location.x), y: Math.floor(location.y), z: Math.floor(location.z), sky: true }) ?? 0; }
	getBiome(location){ return { id: call("biome", { dim: this.id, x: Math.floor(location.x), y: Math.floor(location.y), z: Math.floor(location.z) }) }; }
}

// ---------------------------------------------------------------- events

const subscribed = new Set();
let subscriptionsDirty = false;

export class EventSignal{
	constructor(name, kind = "after"){ this._name = name; this._kind = kind; this._handlers = []; }
	subscribe(callback, options){
		const pack = host.currentPack;
		this._handlers.push({ callback, options, pack });
		const key = this._kind + ":" + this._name;
		if(!subscribed.has(key)){ subscribed.add(key); subscriptionsDirty = true; if(host.tick > 0) flushSubscriptions(); }
		return callback;
	}
	unsubscribe(callback){ this._handlers = this._handlers.filter(h => h.callback !== callback); }
	_fire(event, filter){
		for(const h of [...this._handlers]){
			if(filter && !filter(h.options)) continue;
			host.run(h.pack, () => h.callback(event), this._name);
		}
	}
	get _active(){ return this._handlers.length > 0; }
}

export function flushSubscriptions(){
	if(subscriptionsDirty){ subscriptionsDirty = false; post("sub", { events: [...subscribed] }); }
}

const afterNames = ["blockContainerClosed", "blockContainerOpened", "blockExplode", "buttonPush", "chatSend", "dataDrivenEntityTrigger", "effectAdd", "entityContainerClosed", "entityContainerOpened", "entityDie", "entityHeal", "entityHealthChanged", "entityHitBlock", "entityHitEntity", "entityHurt", "entityItemDrop", "entityItemPickup", "entityLoad", "entityRemove", "entitySpawn", "entityStartSneaking", "entityStopSneaking", "entityTamed", "entityUpgrade", "explosion", "gameRuleChange", "itemCompleteUse", "itemReleaseUse", "itemStartUse", "itemStartUseOn", "itemStopUse", "itemStopUseOn", "itemUse", "itemUseOn", "leverAction", "pistonActivate", "playerBreakBlock", "playerButtonInput", "playerCancelBreakingBlock", "playerDimensionChange", "playerEmote", "playerGameModeChange", "playerHotbarSelectedSlotChange", "playerInputModeChange", "playerInputPermissionCategoryChange", "playerInteractWithBlock", "playerInteractWithEntity", "playerInventoryItemChange", "playerJoin", "playerLeave", "playerPlaceBlock", "playerSpawn", "playerStartBreakingBlock", "playerSwingStart", "pressurePlatePop", "pressurePlatePush", "projectileHitBlock", "projectileHitEntity", "soundCompleted", "targetBlockHit", "tripWireTrip", "weatherChange", "worldInitialize", "worldLoad"];
const beforeNames = ["chatSend", "effectAdd", "entityHeal", "entityItemPickup", "entityRemove", "entityTamed", "explosion", "itemUse", "itemUseOn", "playerBreakBlock", "playerGameModeChange", "playerInteractWithBlock", "playerInteractWithEntity", "playerLeave", "weatherChange", "worldInitialize", "startup"];

class WorldAfterEvents{ constructor(){ for(const n of afterNames) this[n] = new EventSignal(n, "after"); } }
class WorldBeforeEvents{ constructor(){ for(const n of beforeNames) this[n] = new EventSignal(n, "before"); } }

// ---------------------------------------------------------------- scoreboard

class ScoreboardIdentity{
	constructor(key, type, displayName, entity){ this.id = key; this.type = type; this.displayName = displayName; this._entity = entity; }
	getEntity(){ return this._entity ? entityFrom(this._entity) : undefined; }
	get isValid(){ return host.valid(true); }
}
const participantKey = (p) => typeof p === "string" ? p : (p instanceof ScoreboardIdentity ? p.id : (p instanceof Player ? p.name : "entity:" + p.id));
const identityFor = (k) => k.startsWith("entity:") ? new ScoreboardIdentity(k, "Entity", k, k.slice(7)) : new ScoreboardIdentity(k, "Player", k);
const sb = (args) => call("sb", args);

// world.scoreboard is the server's scoreboard: /scoreboard, plugins and every pack see the same objectives
class ScoreboardObjective{
	constructor(id, displayName){ this.id = id; this.displayName = displayName ?? id; }
	get isValid(){ return host.valid(this.id in (sb({ do: "objectives" }) ?? {})); }
	getScore(p){ return sb({ do: "get", obj: this.id, p: participantKey(p) }) ?? undefined; }
	setScore(p, v){ sb({ do: "set", obj: this.id, p: participantKey(p), v: v | 0 }); }
	addScore(p, v){ return sb({ do: "addscore", obj: this.id, p: participantKey(p), v: v | 0 }); }
	removeParticipant(p){ return sb({ do: "reset", obj: this.id, p: participantKey(p) }); }
	hasParticipant(p){ return this.getScore(p) !== undefined; }
	getParticipants(){ return Object.keys(sb({ do: "scores", obj: this.id }) ?? {}).map(identityFor); }
	getScores(){ return Object.entries(sb({ do: "scores", obj: this.id }) ?? {}).map(([k, score]) => ({ participant: identityFor(k), score })); }
}
class Scoreboard{
	_identity(e){ return e instanceof Player ? new ScoreboardIdentity(e.name, "Player", e.name, e.id) : new ScoreboardIdentity("entity:" + e.id, "Entity", e.id, e.id); }
	addObjective(id, displayName){ if(!sb({ do: "add", obj: id, name: displayName ?? id })) throw new Error(`Objective ${id} already exists`); return new ScoreboardObjective(id, displayName ?? id); }
	removeObjective(o){ return sb({ do: "remove", obj: typeof o === "string" ? o : o.id }); }
	getObjective(id){ const all = sb({ do: "objectives" }) ?? {}; return id in all ? new ScoreboardObjective(id, all[id]) : undefined; }
	getObjectives(){ return Object.entries(sb({ do: "objectives" }) ?? {}).map(([id, name]) => new ScoreboardObjective(id, name)); }
	getParticipants(){ return (sb({ do: "participants" }) ?? []).map(identityFor); }
	setObjectiveAtDisplaySlot(slot, options){ sb({ do: "display", slot: String(slot).toLowerCase(), obj: options?.objective?.id ?? "", order: options?.sortOrder ?? 1 }); return undefined; }
	clearObjectiveAtDisplaySlot(slot){ const had = this.getObjectiveAtDisplaySlot(slot); sb({ do: "display", slot: String(slot).toLowerCase(), obj: "" }); return had?.objective; }
	getObjectiveAtDisplaySlot(slot){ const d = sb({ do: "getdisplay", slot: String(slot).toLowerCase() }); return d ? { objective: this.getObjective(d.objective), sortOrder: d.order } : undefined; }
}

class Structure{
	constructor(id, size){ this.id = id; this.size = { x: size[0], y: size[1], z: size[2] }; }
	get isValid(){ return host.valid(true); }
	getBlockPermutation(){ return undefined; }
	getIsWaterlogged(){ return false; }
	setBlockPermutation(){ throw new Error("Structures from packs are read-only on this server"); }
	saveAs(){ throw new Error("Saving structures is not supported on this server"); }
	saveToWorld(){ throw new Error("Saving structures is not supported on this server"); }
}
const structureManager = {
	get(id){ const s = call("structget", { name: id }); return s ? new Structure(s.id, s.size) : undefined; },
	getWorldStructureIds(){ return call("structids", {}) ?? []; },
	getPackStructureIds(){ return call("structids", {}) ?? []; },
	place(structure, dimension, location){ call("structplace", { name: typeof structure === "string" ? structure : structure.id, dim: dimension.id, x: location.x, y: location.y, z: location.z }); },
	createEmpty(){ throw new Error("Creating structures is not supported on this server"); },
	createFromWorld(){ throw new Error("Creating structures is not supported on this server"); },
	delete(){ return false; },
};

// ---------------------------------------------------------------- world

class GameRules{ constructor(){ return new Proxy(this, { get: (t, k) => (typeof k === "string" ? (call("gamerule", { name: k.toLowerCase() }) ?? undefined) : undefined), set: (t, k, v) => { post("setgamerule", { name: String(k).toLowerCase(), v }); return true; } }); } }

class World{
	constructor(){
		this.afterEvents = new WorldAfterEvents();
		this.beforeEvents = new WorldBeforeEvents();
		this.scoreboard = new Scoreboard();
		this.gameRules = new GameRules();
		this._dims = new Map();
		this._defaultGameMode = "survival";
		this.isHardcore = false;
	}
	_setDimensions(list){ for(const d of list) this._dims.set(d.id, new Dimension(d.id, d)); }
	getDimension(id){
		const key = id.includes(":") ? id : "minecraft:" + id;
		const d = this._dims.get(key) ?? this._dims.get(id);
		if(!d) throw new Error(`Dimension ${id} does not exist`);
		return d;
	}
	getAllPlayers(){ return (call("ents", { q: { players: true } }) ?? []).map(s => entityFrom(s.id, s)).filter(Boolean); }
	getPlayers(options = {}){ return (call("ents", { q: { ...queryWire(options), players: true } }) ?? []).map(s => entityFrom(s.id, s)).filter(Boolean); }
	getEntity(id){ const e = entityFrom(id); return e && e._valid() ? e : undefined; }
	sendMessage(message){ post("msg", { ids: null, text: flattenText(message) }); }
	getAbsoluteTime(){ return call("time").abs; }
	getTimeOfDay(){ return call("time").tod; }
	setTimeOfDay(t){ post("settime", { v: typeof t === "number" ? t : 0 }); }
	getDay(){ return call("time").day; }
	getMoonPhase(){ return call("time").moon; }
	setAbsoluteTime(t){ post("settime", { v: t, abs: true }); }
	getDefaultSpawnLocation(){ return call("spawnpoint"); }
	setDefaultSpawnLocation(l){ post("setspawn", { x: l.x, y: l.y, z: l.z }); }
	getDynamicProperty(key){ const v = call("dp", { scope: "world", key }); return v === null ? undefined : v; }
	setDynamicProperty(key, value){ post("sdp", { scope: "world", key, v: value === undefined ? null : (typeof value === "object" ? vec(value) : value) }); }
	getDynamicPropertyIds(){ return call("dpids", { scope: "world" }); }
	getDynamicPropertyTotalByteCount(){ return 0; }
	clearDynamicProperties(){ post("dpclear", { scope: "world" }); }
	playMusic(){} queueMusic(){} stopMusic(){}
	playSound(sound, location, options = {}){ post("sound", { dim: "minecraft:overworld", name: sound, x: location.x, y: location.y, z: location.z, vol: options.volume ?? 1, pitch: options.pitch ?? 1 }); }
	getLootTableManager(){ return { generateLootFromEntity: () => undefined, generateLootFromTable: () => undefined }; }
	get structureManager(){ return structureManager; }
	getDifficulty(){ return call("time").difficulty ?? "Normal"; }
	setDifficulty(){}
}

// ---------------------------------------------------------------- system

class SystemAfterEvents{ constructor(){ this.scriptEventReceive = new EventSignal("scriptEventReceive", "after"); } }
class SystemBeforeEvents{
	constructor(){
		this.watchdogTerminate = new EventSignal("watchdogTerminate", "before");
		this.startup = new EventSignal("startup", "before");
		this.shutdown = new EventSignal("shutdown", "before");
	}
}

class System{
	constructor(){
		this.afterEvents = new SystemAfterEvents();
		this.beforeEvents = new SystemBeforeEvents();
		this.currentTick = 0;
		this.serverSystemInfo = { memoryTier: 3 };
		this.isEditorWorld = false;
		this._nextId = 1;
		this._runs = new Map();
	}
	get _now(){ return this.currentTick; }
	_schedule(cb, delay, interval){
		const id = this._nextId++;
		this._runs.set(id, { cb, at: this.currentTick + Math.max(1, delay | 0), interval, pack: host.currentPack });
		return id;
	}
	run(cb){ return this._schedule(cb, 1, 0); }
	runTimeout(cb, ticks = 1){ return this._schedule(cb, ticks, 0); }
	runInterval(cb, ticks = 1){ return this._schedule(cb, ticks, Math.max(1, ticks | 0)); }
	runJob(generator){ const id = this._nextId++; this._runs.set(id, { job: generator, at: this.currentTick + 1, pack: host.currentPack }); return id; }
	clearRun(id){ this._runs.delete(id); }
	clearJob(id){ this._runs.delete(id); }
	waitTicks(ticks = 1){ return new Promise(resolve => this.runTimeout(resolve, ticks)); }
	sendScriptEvent(id, message){ post("sev", { id, msg: String(message), pack: host.currentPack }); }
	beforeShutdown(){}
	/** Runs everything due this tick (called by the host). */
	_tick(){
		const now = this.currentTick;
		for(const [id, r] of [...this._runs]){
			if(r.at > now || !this._runs.has(id)) continue;
			if(r.job){
				const start = Date.now();
				let done = false;
				host.run(r.pack, () => {
					while(Date.now() - start < 3){
						if(r.job.next().done){ done = true; break; }
					}
				}, "runJob");
				if(done) this._runs.delete(id); else r.at = now + 1;
				continue;
			}
			if(r.interval > 0) r.at = now + r.interval; else this._runs.delete(id);
			host.run(r.pack, r.cb, "run");
		}
	}
}

export const world = new World();
export const system = new System();

// ---------------------------------------------------------------- custom components

export const customComponents = { item: new Map(), block: new Map() };
class ComponentRegistry{
	constructor(kind){ this._kind = kind; }
	registerCustomComponent(name, component){
		customComponents[this._kind].set(name, { component, pack: host.currentPack });
		const hooks = Object.keys(component).filter(k => typeof component[k] === "function");
		for(let proto = Object.getPrototypeOf(component); proto && proto !== Object.prototype; proto = Object.getPrototypeOf(proto)){
			for(const k of Object.getOwnPropertyNames(proto)) if(k !== "constructor" && typeof component[k] === "function") hooks.push(k);
		}
		post("customcomp", { kind: this._kind, name, hooks });
	}
}
export const itemComponentRegistry = new ComponentRegistry("item");
export const blockComponentRegistry = new ComponentRegistry("block");

// custom commands (2.x startup event)
export const customCommands = new Map();
class CustomCommandRegistry{
	registerCommand(definition, callback){ customCommands.set(definition.name, { definition, callback, pack: host.currentPack }); post("customcmd", { name: definition.name, description: definition.description ?? "", permission: definition.permissionLevel ?? 0 }); }
	registerEnum(){}
}
export const customCommandRegistry = new CustomCommandRegistry();
export const CustomCommandStatus = Object.freeze({ Success: 0, Failure: 1 });
export const CustomCommandParamType = enumOf(["Boolean", "Integer", "Float", "String", "EntitySelector", "PlayerSelector", "Location", "BlockType", "ItemType", "Enum"]);
export const CustomCommandSource = enumOf(["Block", "Entity", "NPCDialogue", "Server"]);

export const TicksPerSecond = 20;
export const TicksPerDay = 24000;
export const MinecraftDimensionTypes = Object.freeze({ Overworld: "minecraft:overworld", Nether: "minecraft:nether", TheEnd: "minecraft:the_end" });
export { flattenText as _flattenText };
