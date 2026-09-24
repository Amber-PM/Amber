// Shared host state: the current tick, which pack is running, and error isolation between packs.

import { post } from "./ipc.mjs";

export const host = {
	tick: 0,
	/** Cache key for world state read by scripts: changes every tick and after every write. */
	stamp: 0,
	invalidate(){ host.stamp++; },
	currentPack: "",
	/** @minecraft/server major version each pack was written for (from its manifest). */
	apiMajor: {},
	/** Whether the running pack uses the 1.x API, where isValid() is a method rather than a property. */
	legacy(){ return (host.apiMajor[host.currentPack] ?? 2) < 2; },
	/** A validity flag in the form the running pack expects. */
	valid(value){ return host.legacy() ? () => value : value; },
	/** Runs pack code with its pack as the current one; an exception is logged against the pack and swallowed. */
	run(pack, fn, what){
		const previous = host.currentPack;
		host.currentPack = pack;
		try{
			const result = fn();
			if(result && typeof result.then === "function"){
				result.catch(e => host.error(pack, what, e));
			}
			return result;
		}catch(e){
			host.error(pack, what, e);
			return undefined;
		}finally{
			host.currentPack = previous;
		}
	},
	error(pack, what, e){
		const text = e && e.stack ? e.stack : String(e);
		post("log", { lvl: "error", pack, msg: `${what ?? "script"}: ${text}` });
	},
};

function format(args){
	return args.map(a => {
		if(typeof a === "string") return a;
		if(a instanceof Error) return a.stack ?? a.message;
		try{ return JSON.stringify(a); }catch{ return String(a); }
	}).join(" ");
}

// console.* go to the server log, tagged with the pack
for(const [name, lvl] of [["log", "info"], ["info", "info"], ["warn", "warning"], ["error", "error"], ["debug", "debug"]]){
	console[name] = (...args) => post("log", { lvl, pack: host.currentPack, msg: format(args) });
}
