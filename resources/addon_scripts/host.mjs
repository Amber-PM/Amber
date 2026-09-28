import { Worker } from 'node:worker_threads';
import readline from 'node:readline';
import crypto from 'node:crypto';
const runId = crypto.randomUUID();
const sab = new SharedArrayBuffer(4);
const signal = new Int32Array(sab);
const worker = new Worker(new URL('./worker.mjs', import.meta.url), {
	stdout: true,
	stderr: true,
	env: {},
	workerData: { sab, runId }
});
worker.stdout.on('data', () => {});
worker.stderr.on('data', chunk => {
	process.stderr.write(chunk);
});
const rl = readline.createInterface({
	input: process.stdin,
	terminal: false
});
rl.on('line', line => {
	if(!line) return;
	let msg;
	try {
		msg = JSON.parse(line);
	} catch(e) {
		return;
	}
	worker.postMessage(msg);
	Atomics.add(signal, 0, 1);
	Atomics.notify(signal, 0, 1);
});
rl.on('close', () => {
	process.exit(0);
});
const ALLOWED_TYPES = new Set(["c", "p", "done", "bres"]);
worker.on('message', msg => {
	if (!msg || typeof msg !== 'object') return;
	if (msg.type === '__ready') return;
	// validate protocol message
	if (!ALLOWED_TYPES.has(msg.t)) {
		return; // drop invalid/forged messages
	}
	// write to php
	try {
		process.stdout.write(JSON.stringify(msg) + "\n");
	} catch(e) {
		// ignore EPIPE
	}
});
worker.on('error', err => {
	process.stderr.write("Worker error: " + err.stack + "\n");
});
worker.on('exit', code => {
	process.exit(code);
});
