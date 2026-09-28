import assert from "node:assert/strict";
import { test } from "node:test";
import { Worker, isMainThread, parentPort, workerData } from "node:worker_threads";

if(!isMainThread){
	const { Entity } = await import("../../resources/addon_scripts/server.mjs");
	const { call } = await import("../../resources/addon_scripts/ipc.mjs");
	const { InvalidArgumentError, ArgumentOutOfBoundsError } = await import("../../resources/addon_scripts/common.mjs");
	const entity = new Entity("1");
	const results = {};
	for(const [label, name, value, errorClass] of [
		["type", "test:value", "wrong", InvalidArgumentError],
		["bounds", "test:value", 3, ArgumentOutOfBoundsError],
		["identifier", "test:missing", 1, InvalidArgumentError],
	]){
		try{
			entity.setProperty(name, value);
			results[label] = false;
		}catch(error){
			results[label] = error instanceof errorClass;
		}
	}
	results.unchanged = entity.getProperty("test:value");
	entity.setProperty("test:value", 2);
	results.beforeSetCommit = entity.getProperty("test:value");
	call("testCommit");
	results.afterSetCommit = entity.getProperty("test:value");
	results.resetReturn = entity.resetProperty("test:value");
	results.beforeResetCommit = entity.getProperty("test:value");
	call("testCommit");
	results.afterResetCommit = entity.getProperty("test:value");
	try{
		call("unrelatedFailure");
		results.unrelated = false;
	}catch(error){
		results.unrelated = error.constructor === Error && error.message === "unrelated";
	}
	parentPort.postMessage({ t: "done", results });
}else{
	test("Entity property API surfaces typed errors and defers valid writes and resets", async () => {
		const signal = new SharedArrayBuffer(4);
		const worker = new Worker(new URL(import.meta.url), { workerData: { runId: 1, sab: signal } });
		const wake = new Int32Array(signal);
		let current = 1;
		let pending = null;
		const results = await new Promise((resolve, reject) => {
			worker.on("error", reject);
			worker.on("exit", code => { if(code !== 0) reject(new Error(`Worker exited with ${code}`)); });
			worker.on("message", request => {
				if(request.t === "done"){
					resolve(request.results);
					return;
				}
				assert.equal(request.t, "c");
				let reply;
				switch(request.op){
					case "sprop":
						if(request.a.name !== "test:value" || typeof request.a.v !== "number"){
							reply = { t: "r", e: "invalid property value", errorType: "InvalidArgumentError" };
						}else if(request.a.v < 0 || request.a.v > 2){
							reply = { t: "r", e: "outside range", errorType: "ArgumentOutOfBoundsError" };
						}else{
							pending = request.a.v;
							reply = { t: "r", v: null };
						}
						break;
					case "rprop":
						pending = 0;
						reply = { t: "r", v: 0 };
						break;
					case "prop": reply = { t: "r", v: current }; break;
					case "testCommit": current = pending; pending = null; reply = { t: "r", v: null }; break;
					case "unrelatedFailure": reply = { t: "r", e: "unrelated" }; break;
					default: reject(new Error(`Unexpected operation: ${request.op}`)); return;
				}
				worker.postMessage(reply);
				Atomics.add(wake, 0, 1);
				Atomics.notify(wake, 0);
			});
		});
		await worker.terminate();
		assert.deepEqual(results, {
			type: true, bounds: true, identifier: true, unchanged: 1,
			beforeSetCommit: 1, afterSetCommit: 2,
			resetReturn: 0, beforeResetCommit: 2, afterResetCommit: 0,
			unrelated: true,
		});
	});
}
