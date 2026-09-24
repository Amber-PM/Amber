// Amber add-on script host: the pipe to the server.
//
// Messages are JSON, one per line. The server drives everything: it sends "init", then one "tick" per server
// tick, "before" for cancellable events and "hook" for custom components. While handling one, scripts call into
// the server synchronously (call) - the host writes the request and blocks on stdin until the reply arrives, so
// script API calls behave exactly like the game's: synchronous, inside the tick. post() sends without waiting.

import fs from "node:fs";

const out = 1;
const inp = 0;
let buffer = Buffer.alloc(0);
const chunk = Buffer.alloc(1 << 16);

function writeLine(obj){
	const line = JSON.stringify(obj) + "\n";
	let data = Buffer.from(line, "utf8");
	let offset = 0;
	while(offset < data.length){
		try{
			offset += fs.writeSync(out, data, offset);
		}catch(e){
			if(e.code !== "EAGAIN") throw e;
		}
	}
}

/** Blocks until one full line arrives from the server. */
export function readMessage(){
	for(;;){
		const nl = buffer.indexOf(10);
		if(nl !== -1){
			const line = buffer.subarray(0, nl).toString("utf8");
			buffer = buffer.subarray(nl + 1);
			if(line.length === 0) continue;
			return JSON.parse(line);
		}
		let n;
		try{
			n = fs.readSync(inp, chunk, 0, chunk.length, null);
		}catch(e){
			if(e.code === "EAGAIN"){ continue; }
			if(e.code === "EOF"){ process.exit(0); }
			throw e;
		}
		if(n === 0){ process.exit(0); }
		buffer = Buffer.concat([buffer, chunk.subarray(0, n)]);
	}
}

/** Handlers for messages that arrive while a call is waiting (a nested before-event); set by the host. */
export const nested = { handle: null };

/** A synchronous call into the server. Throws if the server reports an error. */
export function call(op, args = {}){
	writeLine({ t: "c", op, a: args });
	for(;;){
		const msg = readMessage();
		if(msg.t === "r"){
			if(msg.e !== undefined) throw new Error(msg.e);
			return msg.v;
		}
		//the server may run a cancellable event (triggered by this call) before replying
		if(nested.handle !== null) nested.handle(msg);
	}
}

/** A call that does not wait for a reply. Runs on the server in order with calls. */
export function post(op, args = {}){
	writeLine({ t: "p", op, a: args });
}

export function send(obj){
	writeLine(obj);
}
