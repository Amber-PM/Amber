import { parentPort, receiveMessageOnPort, workerData } from "node:worker_threads";
import { ArgumentOutOfBoundsError, InvalidArgumentError } from "./common.mjs";

export const runId = workerData.runId;
const signalBuffer = new Int32Array(workerData.sab);


function writeLine(obj){
	parentPort.postMessage(obj);
}

/** Blocks until one full message arrives from the main thread. */
export function readMessage(){
	for(;;){
		const current = Atomics.load(signalBuffer, 0);
		if(current === 0) {
			Atomics.wait(signalBuffer, 0, 0);
		}

		const msg = receiveMessageOnPort(parentPort);
		if(msg !== undefined){
			Atomics.sub(signalBuffer, 0, 1);
			return msg.message;
		}
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
			if(msg.e !== undefined){
				if(msg.errorType === "InvalidArgumentError") throw new InvalidArgumentError(msg.e);
				if(msg.errorType === "ArgumentOutOfBoundsError") throw new ArgumentOutOfBoundsError(msg.e);
				throw new Error(msg.e);
			}
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
