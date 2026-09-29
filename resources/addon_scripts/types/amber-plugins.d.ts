// Type definitions for @amber/plugins, the bridge between behavior pack scripts and AmberPM's PHP plugins.
//
// Use in a TypeScript pack: copy this file into your project (the server also writes it to
// addons/types/amber-plugins.d.ts) and make sure tsconfig.json includes it, e.g. "include": ["src", "types"].
//
// Values crossing the bridge are JSON: numbers, strings, booleans, null, arrays and plain objects. Class
// instances (Player, Entity, ItemStack...) cannot be passed; pass names or IDs instead.

declare module "@amber/plugins" {
	/** A value that can cross the bridge. */
	export type JsonValue = string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue };

	/**
	 * Optional typing for plugin functions: declare your own interface extending this and use it with
	 * `plugins.call<"economy:balance">(...)`.
	 *
	 * ```ts
	 * declare module "@amber/plugins" {
	 *     interface PluginFunctions {
	 *         "economy:balance": (player: string) => number;
	 *     }
	 * }
	 * const money = plugins.call("economy:balance", player.name); // number
	 * ```
	 */
	export interface PluginFunctions {}

	type Args<F> = F extends (...args: infer A) => unknown ? A : JsonValue[];
	type Result<F> = F extends (...args: never[]) => infer R ? R : JsonValue;

	export interface Plugins {
		/**
		 * Calls a PHP function a plugin exposed with ScriptHost::exposeFunction(). The call is synchronous: it
		 * returns the plugin's answer. Throws if no plugin exposes the name, or if the PHP function throws.
		 */
		call<N extends keyof PluginFunctions>(name: N, ...args: Args<PluginFunctions[N]>): Result<PluginFunctions[N]>;
		call<N extends string>(name: N extends keyof PluginFunctions ? never : N, ...args: JsonValue[]): JsonValue;

		/** Whether a plugin exposes a function with this name. */
		has(name: string): boolean;

		/** The names of every function plugins expose. */
		list(): string[];

		/**
		 * Makes a function callable by plugins (ScriptHost::callScript()). The function runs synchronously and
		 * its return value is sent back as JSON.
		 */
		expose(name: string, fn: (...args: JsonValue[]) => JsonValue | void): void;

		/**
		 * Sends an event plugins receive as AddonScriptEvent. Unless a plugin cancels it, scripts also get it
		 * through system.afterEvents.scriptEventReceive.
		 */
		send(id: string, message?: string): void;

		/** Always true: lets a pack check that it runs on AmberPM. */
		readonly isAmber: true;
	}

	export const plugins: Plugins;
	export default plugins;
}
