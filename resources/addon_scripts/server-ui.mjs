// @minecraft/server-ui for Amber: forms are sent as native server forms; show() resolves when the player answers.

import { post } from "./ipc.mjs";
import { host } from "./state.mjs";
import { _flattenText as text } from "./server.mjs";

export const FormCancelationReason = Object.freeze({ UserBusy: "UserBusy", UserClosed: "UserClosed" });
export const FormRejectReason = Object.freeze({ MalformedResponse: "MalformedResponse", PlayerQuit: "PlayerQuit", ServerShutdown: "ServerShutdown" });

export class FormRejectError extends Error{ constructor(reason){ super(reason); this.reason = reason; } }

let nextFormId = 1;
const pending = new Map();

/** Called by the host when a player answers (data === null: closed). */
export function _formResponse(id, data, reason){
	const entry = pending.get(id);
	if(!entry) return;
	pending.delete(id);
	host.run(entry.pack, () => entry.resolve(entry.parse(data, reason)), "form response");
}

/** Called by the host when the player left before answering. */
export function _formRejectAll(playerId){
	for(const [id, entry] of pending){
		if(entry.player === playerId){
			pending.delete(id);
			host.run(entry.pack, () => entry.reject(new FormRejectError("PlayerQuit")), "form");
		}
	}
}

function show(player, json, parse){
	return new Promise((resolve, reject) => {
		const id = nextFormId++;
		pending.set(id, { resolve, reject, parse, pack: host.currentPack, player: player.id });
		post("form", { id: player.id, fid: id, form: json });
	});
}

export class ActionFormData{
	constructor(){ this._title = ""; this._body = ""; this._elements = []; this._buttons = 0; }
	title(t){ this._title = text(t); return this; }
	body(t){ this._body = text(t); return this; }
	button(label, iconPath){
		const b = { text: text(label) };
		if(iconPath) b.image = { type: iconPath.startsWith("http") ? "url" : "path", data: iconPath };
		this._elements.push({ type: "button", b, index: this._buttons++ });
		return this;
	}
	header(t){ this._elements.push({ type: "header", text: text(t) }); return this; }
	label(t){ this._elements.push({ type: "label", text: text(t) }); return this; }
	divider(){ this._elements.push({ type: "divider" }); return this; }
	show(player){
		//headers, labels and dividers become part of the body on clients without them
		let body = this._body;
		const buttons = [];
		for(const e of this._elements){
			if(e.type === "button") buttons.push(e.b);
			else if(e.type === "label" || e.type === "header") body += (body ? "\n" : "") + e.text;
		}
		return show(player, { type: "form", title: this._title, content: body, buttons }, (data, reason) =>
			data === null ? { canceled: true, cancelationReason: reason ?? "UserClosed", selection: undefined } : { canceled: false, selection: data });
	}
}

export class MessageFormData{
	constructor(){ this._title = ""; this._body = ""; this._b1 = ""; this._b2 = ""; }
	title(t){ this._title = text(t); return this; }
	body(t){ this._body = text(t); return this; }
	button1(t){ this._b1 = text(t); return this; }
	button2(t){ this._b2 = text(t); return this; }
	show(player){
		//the game's selection is 0 for button1 and 1 for button2; a modal "true" is button1
		return show(player, { type: "modal", title: this._title, content: this._body, button1: this._b1, button2: this._b2 }, (data, reason) =>
			data === null ? { canceled: true, cancelationReason: reason ?? "UserClosed", selection: undefined } : { canceled: false, selection: data === true ? 0 : 1 });
	}
}

export class ModalFormData{
	constructor(){ this._title = ""; this._content = []; this._kinds = []; this._submit = undefined; }
	title(t){ this._title = text(t); return this; }
	_add(kind, element){ this._content.push(element); this._kinds.push(kind); return this; }
	textField(label, placeholder, options){
		const def = typeof options === "object" ? options?.defaultValue : options;
		return this._add("text", { type: "input", text: text(label), placeholder: text(placeholder ?? ""), default: def ?? "" });
	}
	dropdown(label, items, options){
		const def = typeof options === "object" ? options?.defaultValueIndex : options;
		return this._add("dropdown", { type: "dropdown", text: text(label), options: items.map(text), default: def ?? 0 });
	}
	slider(label, min, max, step, options){
		let def = options, stepValue = step;
		if(typeof step === "object"){ def = step?.defaultValue; stepValue = step?.valueStep ?? 1; }
		else if(typeof options === "object"){ def = options?.defaultValue; stepValue = options?.valueStep ?? step ?? 1; }
		return this._add("slider", { type: "slider", text: text(label), min, max, step: stepValue ?? 1, default: def ?? min });
	}
	toggle(label, options){
		const def = typeof options === "object" ? options?.defaultValue : options;
		return this._add("toggle", { type: "toggle", text: text(label), default: !!def });
	}
	header(t){ return this._add("label", { type: "label", text: text(t) }); }
	label(t){ return this._add("label", { type: "label", text: text(t) }); }
	divider(){ return this._add("label", { type: "label", text: "" }); }
	submitButton(t){ this._submit = text(t); return this; }
	show(player){
		const form = { type: "custom_form", title: this._title, content: this._content };
		if(this._submit !== undefined) form.submit = this._submit;
		return show(player, form, (data, reason) => {
			if(data === null) return { canceled: true, cancelationReason: reason ?? "UserClosed", formValues: undefined };
			const values = (Array.isArray(data) ? data : []).map((v, i) => this._kinds[i] === "label" ? undefined : v);
			return { canceled: false, formValues: values };
		});
	}
}

export const uiManager = { closeAllForms(player){ post("closeforms", { id: player.id }); } };
