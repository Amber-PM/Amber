// @amber/plugins: talk to the server's PHP plugins.
//
//   import { plugins } from "@amber/plugins";
//   const money = plugins.call("economy:getMoney", player.name);   // a function a plugin exposed
//   plugins.expose("mypack:openShop", (playerName) => { ... });     // a function plugins can call
//   plugins.send("mypack:event", "data");                           // an AddonScriptEvent for plugins
//
// Values crossing the bridge are JSON (numbers, strings, booleans, arrays, plain objects).

import { call, post } from "./ipc.mjs";
import { host } from "./state.mjs";

export const exposed = new Map();

export const plugins = Object.freeze({
	call(name, ...args){ return call("pcall", { name, args }); },
	has(name){ return call("phas", { name }); },
	list(){ return call("plist", {}); },
	expose(name, fn){ exposed.set(name, { fn, pack: host.currentPack }); post("expose", { name }); },
	send(id, message = ""){ post("sev", { id, msg: String(message), pack: host.currentPack }); },
	isAmber: true,
});
export default plugins;
