// Module resolution for add-on scripts.
//
// "@minecraft/server" and friends resolve to the host's implementations. Pack code may import its own files
// (relative paths inside its pack) and those modules only: Node built-ins and packages are refused, which keeps
// scripts inside the same sandbox the game gives them (together with Node's --permission flag).
//
// These are synchronous in-thread hooks (module.registerHooks), so no worker thread is needed.

import { pathToFileURL, fileURLToPath } from "node:url";
import path from "node:path";

const here = path.dirname(fileURLToPath(import.meta.url));
const modules = {
	"@minecraft/server": "server.mjs",
	"@minecraft/server-ui": "server-ui.mjs",
	"@minecraft/common": "common.mjs",
	"@minecraft/math": "math.mjs",
	"@minecraft/vanilla-data": "vanilla-data.mjs",
	//modules this server does not provide: every name imports, and using one throws
	"@minecraft/server-gametest": "server-gametest-names.mjs",
	"@minecraft/server-net": "server-net-names.mjs",
	"@minecraft/server-editor": "unsupported.mjs",
	"@minecraft/debug-utilities": "debug-utilities-names.mjs",
	"@minecraft/diagnostics": "diagnostics-names.mjs",
	"@minecraft/server-graphics": "server-graphics-names.mjs",
	"@minecraft/server-admin": "admin.mjs",
	"@amber/plugins": "bridge.mjs",
};

function insideHost(url){
	try{ return fileURLToPath(url).startsWith(here + path.sep); }catch{ return false; }
}

function isInside(root, file){
	const relative = path.relative(root, file);
	return relative === "" ||
		(
			relative !== ".." &&
			!relative.startsWith(".." + path.sep) &&
			!path.isAbsolute(relative)
		);
}

export function makeHooks(roots){
	const packRoots = roots.map(r => path.resolve(r));
	return {
		resolve(specifier, context, next){
			if(modules[specifier] !== undefined){
				return { url: pathToFileURL(path.join(here, modules[specifier])).href, shortCircuit: true, format: "module" };
			}
			const parent = context.parentURL;
			if(parent === undefined || insideHost(parent) || !parent.startsWith("file:")){
				return next(specifier, context);
			}
			if(specifier.startsWith("./") || specifier.startsWith("../") || specifier.startsWith("/")){
				let file = fileURLToPath(new URL(specifier, parent));
				if(!/\.m?js$/.test(file)) file += ".js";

				const parentFile = fileURLToPath(parent);
				const parentRoot = packRoots.find(root => isInside(root, parentFile));

				if(!parentRoot || !isInside(parentRoot, file)){
					throw new Error(`Script import ${specifier} leaves its behavior pack`);
				}
				return { url: pathToFileURL(file).href, shortCircuit: true, format: "module" };
			}
			throw new Error(`Script import "${specifier}" is not available (only @minecraft/* modules and the pack's own files are)`);
		},
		load(url, context, next){
			if(url.startsWith("file:") && !insideHost(url)){
				//pack scripts are ES modules whatever their extension or package.json says
				const result = next(url, { ...context, format: "module" });
				return { ...result, format: "module", shortCircuit: true };
			}
			return next(url, context);
		},
	};
}
