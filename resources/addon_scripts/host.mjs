// Amber add-on script host.
//
// Started by the server (ScriptHost.php) with the pack directories readable and nothing else. Loads every
// behavior pack's script entry and runs it against @minecraft/server, one server tick at a time.

import module from "node:module";
import { pathToFileURL } from "node:url";
import { readMessage, send, nested } from "./ipc.mjs";
import { host } from "./state.mjs";
import { makeHooks } from "./loader.mjs";

delete globalThis.fetch;
delete globalThis.WebSocket;
delete globalThis.EventSource;

let api, ui, bridge;

function drain(){
	return new Promise(resolve => setImmediate(resolve));
}

// ---------------------------------------------------------------- event objects

const E = (id, snap, type) => api.entityFrom(id, snap, type);
const P = (id, snap) => api.entityFrom(id, snap);
const item = (w) => api.ItemStack._from(w);
const dim = (id) => { try{ return api.world.getDimension(id); }catch{ return undefined; } };
function block(d){
	if(!d) return undefined;
	const b = new api.Block(dim(d.dim), d);
	if(d.type !== undefined){ b._data = { type: d.type, states: d.states ?? {}, solid: d.solid ?? true }; b._tick = host.stamp; }
	return b;
}
const perm = (d) => d ? api.BlockPermutation.resolve(d.type, d.states ?? {}) : undefined;
const source = (d) => ({ cause: d?.cause ?? "none", damagingEntity: d?.damager ? E(d.damager) : undefined, damagingProjectile: d?.projectile ? E(d.projectile) : undefined });

const builders = {
	playerSpawn: d => ({ player: P(d.player, d.snap), initialSpawn: !!d.initial }),
	playerJoin: d => ({ playerId: String(d.player), playerName: d.name }),
	playerLeave: d => ({ playerId: String(d.player), playerName: d.name }),
	entitySpawn: d => ({ entity: E(d.entity, d.snap, d.type), cause: d.cause ?? "Spawned" }),
	entityLoad: d => ({ entity: E(d.entity, d.snap) }),
	entityDie: d => ({ deadEntity: E(d.entity, d.snap, d.type), damageSource: source(d.src) }),
	entityHurt: d => ({ hurtEntity: E(d.entity, d.snap, d.type), damage: d.damage, damageSource: source(d.src) }),
	entityHealthChanged: d => ({ entity: E(d.entity), oldValue: d.old, newValue: d.new }),
	entityHitEntity: d => ({ damagingEntity: E(d.damager, undefined, d.damagerType), hitEntity: E(d.entity, undefined, d.type) }),
	entityHitBlock: d => ({ damagingEntity: E(d.damager), hitBlock: block(d.block), blockFace: d.face ?? "Up" }),
	entityRemove: d => ({ removedEntityId: String(d.entity), typeId: d.type }),
	playerBreakBlock: d => ({ player: P(d.player), block: block(d.block), brokenBlockPermutation: perm(d.block), itemStackBeforeBreak: item(d.item), itemStackAfterBreak: item(d.itemAfter ?? d.item), dimension: dim(d.block.dim) }),
	playerPlaceBlock: d => ({ player: P(d.player), block: block(d.block), dimension: dim(d.block.dim) }),
	itemUse: d => ({ source: P(d.player), itemStack: item(d.item) }),
	itemUseOn: d => ({ source: P(d.player), itemStack: item(d.item), block: block(d.block), blockFace: d.face ?? "Up", faceLocation: d.faceLocation ?? { x: 0.5, y: 0.5, z: 0.5 } }),
	itemCompleteUse: d => ({ source: P(d.player), itemStack: item(d.item), useDuration: 0 }),
	itemStartUse: d => ({ source: P(d.player), itemStack: item(d.item), useDuration: 0 }),
	itemReleaseUse: d => ({ source: P(d.player), itemStack: item(d.item), useDuration: d.duration ?? 0 }),
	itemStopUse: d => ({ source: P(d.player), itemStack: item(d.item), useDuration: d.duration ?? 0 }),
	playerInteractWithBlock: d => ({ player: P(d.player), block: block(d.block), itemStack: item(d.item), beforeItemStack: item(d.item), blockFace: d.face ?? "Up", faceLocation: d.faceLocation ?? { x: 0.5, y: 0.5, z: 0.5 }, isFirstEvent: true }),
	playerInteractWithEntity: d => ({ player: P(d.player), target: E(d.entity), itemStack: item(d.item), beforeItemStack: item(d.item) }),
	chatSend: d => ({ sender: P(d.player), message: d.message, targets: undefined }),
	dataDrivenEntityTrigger: d => ({ entity: E(d.entity), eventId: d.event, id: d.event, getModifiers: () => [] }),
	projectileHitEntity: d => ({ projectile: E(d.projectile), source: d.source ? E(d.source) : undefined, dimension: dim(d.dim), location: d.location, hitVector: d.hit ?? { x: 0, y: 0, z: 0 }, getEntityHit: () => ({ entity: E(d.entity) }), getBlockHit: () => undefined }),
	projectileHitBlock: d => ({ projectile: E(d.projectile), source: d.source ? E(d.source) : undefined, dimension: dim(d.dim), location: d.location, hitVector: d.hit ?? { x: 0, y: 0, z: 0 }, getBlockHit: () => ({ block: block(d.block), face: d.face ?? "Up", faceLocation: { x: 0.5, y: 0.5, z: 0.5 } }), getEntityHit: () => undefined }),
	playerDimensionChange: d => ({ player: P(d.player), fromDimension: dim(d.from), toDimension: dim(d.to), fromLocation: d.fromLocation, toLocation: d.toLocation }),
	playerGameModeChange: d => ({ player: P(d.player), fromGameMode: d.from, toGameMode: d.to }),
	effectAdd: d => ({ entity: E(d.entity), effect: new api.Effect(d.effect) }),
	playerHotbarSelectedSlotChange: d => ({ player: P(d.player), previousSlotSelected: d.from, newSlotSelected: d.to, itemStack: item(d.item) }),
	explosion: d => ({ source: d.source ? E(d.source) : undefined, dimension: dim(d.dim), getImpactedBlocks: () => (d.blocks ?? []).map(b => block({ ...b, dim: d.dim })) }),
	worldLoad: () => ({}),
	worldInitialize: () => ({}),
};

function deliver(name, d){
	if(name === "__form"){ ui._formResponse(d.fid, d.data, d.reason); return; }
	if(name === "__gone"){ api.forgetEntity(d.entity); return; }
	if(name === "__quit"){ ui._formRejectAll(String(d.player)); api.forgetEntity(d.player); return; }
	if(name === "scriptEventReceive"){
		const event = { id: d.id, message: d.message, sourceType: d.entity ? "Entity" : "Server", sourceEntity: d.entity ? E(d.entity) : undefined, initiator: undefined, sourceBlock: undefined };
		api.system.afterEvents.scriptEventReceive._fire(event, opts => !opts?.namespaces || opts.namespaces.some(n => d.id.startsWith(n + ":")));
		return;
	}
	const signal = api.world.afterEvents[name];
	const build = builders[name];
	if(!signal || !signal._active || !build) return;
	let event;
	try{ event = build(d); }catch(e){ host.error("", "building " + name, e); return; }
	signal._fire(event, opts => filterOptions(opts, event));
}

/** Entity/type filters some events accept as subscribe options. */
function filterOptions(opts, event){
	if(!opts) return true;
	const subject = event.entity ?? event.hurtEntity ?? event.deadEntity ?? event.damagingEntity ?? event.source;
	if(opts.entityTypes && subject && !opts.entityTypes.includes(subject.typeId)) return false;
	if(opts.entities && subject && !opts.entities.some(e => e.id === subject.id)) return false;
	if(opts.eventTypes && event.eventId && !opts.eventTypes.includes(event.eventId)) return false;
	return true;
}

// ---------------------------------------------------------------- before events and hooks

const beforeBuilders = {
	chatSend: d => {
		const ev = { sender: P(d.player), message: d.message, cancel: false, _targets: undefined };
		ev.setTargets = (t) => { ev._targets = t; };
		ev.getTargets = () => ev._targets ?? [];
		ev.sendToTargets = false;
		return ev;
	},
	playerBreakBlock: d => ({ player: P(d.player), block: block(d.block), itemStack: item(d.item), dimension: dim(d.block.dim), cancel: false }),
	itemUse: d => ({ source: P(d.player), itemStack: item(d.item), cancel: false }),
	itemUseOn: d => ({ source: P(d.player), itemStack: item(d.item), block: block(d.block), blockFace: d.face ?? "Up", faceLocation: d.faceLocation ?? { x: 0.5, y: 0.5, z: 0.5 }, isFirstEvent: true, cancel: false }),
	playerInteractWithBlock: d => ({ player: P(d.player), block: block(d.block), itemStack: item(d.item), blockFace: d.face ?? "Up", faceLocation: d.faceLocation ?? { x: 0.5, y: 0.5, z: 0.5 }, isFirstEvent: true, cancel: false }),
	playerInteractWithEntity: d => ({ player: P(d.player), target: E(d.entity), itemStack: item(d.item), cancel: false }),
	playerLeave: d => ({ player: P(d.player) }),
	entityRemove: d => ({ removedEntity: E(d.entity) }),
	effectAdd: d => ({ entity: E(d.entity), effectType: d.effect.type, duration: d.effect.duration, cancel: false }),
	playerGameModeChange: d => ({ player: P(d.player), fromGameMode: d.from, toGameMode: d.to, cancel: false }),
	explosion: d => {
		let blocks = (d.blocks ?? []).map(b => block({ ...b, dim: d.dim }));
		return { source: d.source ? E(d.source) : undefined, dimension: dim(d.dim), cancel: false, getImpactedBlocks: () => blocks, setImpactedBlocks: (list) => { blocks = list; } };
	},
};

function before(msg){
	const signal = api.world.beforeEvents[msg.name];
	const build = beforeBuilders[msg.name];
	if(!signal || !build || !signal._active) return { cancel: false };
	const event = build(msg.d);
	signal._fire(event);
	const out = { cancel: !!event.cancel };
	if(msg.name === "chatSend"){
		out.message = event.message;
		if(event._targets) out.targets = event._targets.map(p => p.id);
	}
	if(msg.name === "explosion"){
		out.blocks = event.getImpactedBlocks().map(b => ({ x: b.x, y: b.y, z: b.z }));
	}
	return out;
}

/** Custom component callbacks for an item or block. */
function hook(msg){
	const registry = api.customComponents[msg.kind];
	const d = msg.d;
	let event;
	if(msg.kind === "item"){
		event = {
			source: d.player ? P(d.player) : undefined, itemStack: item(d.item), block: block(d.block), blockFace: d.face ?? "Up", faceLocation: d.faceLocation,
			usedOnBlockPermutation: perm(d.block), attackingEntity: d.player ? P(d.player) : undefined, hitEntity: d.entity ? E(d.entity) : undefined, hadEffect: true,
			minedBlockPermutation: perm(d.block), cancel: false,
		};
	}else{
		const b = block(d.block);
		event = {
			block: b, dimension: b?.dimension, player: d.player ? P(d.player) : undefined, entity: d.entity ? E(d.entity) : undefined, face: d.face ?? "Up", faceLocation: d.faceLocation,
			destroyedBlockPermutation: perm(d.block), brokenBlockPermutation: perm(d.block), previousBlock: perm(d.previous), permutationToPlace: perm(d.block), cancel: false,
			fallDistance: d.fallDistance ?? 0,
		};
	}
	const hookNames = msg.hook === "onPlayerBreak" ? ["onPlayerBreak", "onPlayerDestroy"] : (msg.hook === "onPlayerDestroy" ? ["onPlayerDestroy", "onPlayerBreak"] : [msg.hook]);
	for(const name of msg.names){
		const entry = registry.get(name);
		if(!entry) continue;
		for(const h of hookNames){
			if(typeof entry.component[h] === "function"){
				host.run(entry.pack, () => entry.component[h](event, { params: d.params?.[name] }), `${name}.${h}`);
				break;
			}
		}
	}
	return { cancel: !!event.cancel };
}

function pcall(msg){
	const entry = bridge.exposed.get(msg.name);
	if(!entry) return { e: `no script function ${msg.name}` };
	let value;
	host.run(entry.pack, () => { value = entry.fn(...(msg.args ?? [])); }, msg.name);
	return { v: value === undefined ? null : value };
}

function command(msg){
	const entry = api.customCommands.get(msg.name);
	if(!entry) return { v: { status: 1, message: "unknown command" } };
	let result;
	const origin = { sourceEntity: msg.player ? P(msg.player) : undefined, initiator: msg.player ? P(msg.player) : undefined, sourceType: msg.player ? "Entity" : "Server" };
	host.run(entry.pack, () => { result = entry.callback(origin, ...(msg.args ?? [])); }, "/" + msg.name);
	return { v: { status: result?.status ?? 0, message: result?.message ?? null } };
}

function handleSync(msg){
	switch(msg.t){
		case "before": return { t: "bres", ...before(msg) };
		case "hook": return { t: "bres", ...hook(msg) };
		case "pcall": return { t: "bres", ...pcall(msg) };
		case "command": return { t: "bres", ...command(msg) };
		default: return { t: "bres", e: "unexpected message " + msg.t };
	}
}
nested.handle = (msg) => send(handleSync(msg));

// ---------------------------------------------------------------- lifecycle

async function init(msg){
	const roots = msg.packs.map(p => p.root);
	if(typeof module.registerHooks === "function"){
		module.registerHooks(makeHooks(roots));
	}else{
		throw new Error("Node.js 22.15 or newer is required for add-on scripts");
	}
	api = await import("./server.mjs");
	ui = await import("./server-ui.mjs");
	bridge = await import("./bridge.mjs");
	api.world._setDimensions(msg.dims);
	host.tick = msg.tick;
	api.system.currentTick = msg.tick;

	for(const pack of msg.packs){
		host.apiMajor[pack.name] = pack.api ?? 2;
	}
	for(const pack of msg.packs){
		host.currentPack = pack.name;
		try{
			await import(pathToFileURL(pack.entry).href);
		}catch(e){
			host.error(pack.name, "loading " + pack.entry, e);
		}
		host.currentPack = "";
	}
	await drain();

	const registries = { itemComponentRegistry: api.itemComponentRegistry, blockComponentRegistry: api.blockComponentRegistry, customCommandRegistry: api.customCommandRegistry };
	api.system.beforeEvents.startup._fire(registries);
	api.world.beforeEvents.worldInitialize._fire(registries);
	api.world.afterEvents.worldInitialize._fire({});
	api.world.afterEvents.worldLoad._fire({});
	await drain();
}

async function main(){
	for(;;){
		const msg = readMessage();
		if(msg.t === "init"){
			try{ await init(msg); }catch(e){ host.error("", "init", e); }
			api?.flushSubscriptions();
			send({ t: "done" });
			continue;
		}
		if(msg.t === "tick"){
			host.tick = msg.n;
			host.invalidate();
			api.system.currentTick = msg.n;
			for(const [name, d] of msg.ev ?? []){
				try{ deliver(name, d); }catch(e){ host.error("", name, e); }
			}
			api.system._tick();
			await drain();
			await drain();
			api.flushSubscriptions();
			send({ t: "done" });
			continue;
		}
		if(msg.t === "stop"){
			try{ api?.system.beforeEvents.shutdown._fire({}); }catch{}
			process.exit(0);
		}
		const reply = handleSync(msg);
		await drain();
		api?.flushSubscriptions();
		send(reply);
	}
}

main().catch(e => { host.error("", "host", e); process.exit(1); });
