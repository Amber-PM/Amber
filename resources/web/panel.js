"use strict";
(() => {
	const $ = (id) => document.getElementById(id);
	let token = sessionStorage.getItem("amberToken") || "";
	let tab = "overview";
	let logNext = 0;
	let timer = null;

	async function api(path, options = {}){
		const response = await fetch(path, { ...options, headers: { ...(options.headers || {}), Authorization: "Bearer " + token } });
		if(response.status === 401){ logout("Wrong token"); throw new Error("unauthorized"); }
		const data = await response.json();
		if(!response.ok) throw new Error(data.error || response.statusText);
		return data;
	}

	function cell(row, text){ const td = document.createElement("td"); td.textContent = String(text); row.appendChild(td); return td; }
	function fill(tbody, rows, render){
		tbody.replaceChildren(...rows.map((item) => { const tr = document.createElement("tr"); render(tr, item); return tr; }));
	}
	function bytes(n){ return n >= 1073741824 ? (n / 1073741824).toFixed(2) + " GB" : n >= 1048576 ? (n / 1048576).toFixed(1) + " MB" : (n / 1024).toFixed(1) + " KB"; }
	function duration(s){ s = Math.floor(s); const d = Math.floor(s / 86400), h = Math.floor(s / 3600) % 24, m = Math.floor(s / 60) % 60; return (d ? d + "d " : "") + h + "h " + m + "m"; }

	async function refresh(){
		try{
			if(tab === "overview" || tab === "players"){
				const s = await api("/api/status");
				$("server-version").textContent = s.server.name + " " + s.server.version;
				$("tps").textContent = s.tps.toFixed(2);
				$("tps-avg").textContent = "average " + s.tpsAverage.toFixed(2);
				$("load").textContent = s.load.toFixed(1) + "%";
				$("players-count").textContent = s.players.online + " / " + s.players.max;
				$("memory").textContent = bytes(s.memory.mainThread);
				$("memory-process").textContent = "process " + bytes(s.memory.process);
				$("uptime").textContent = duration(s.uptime);
				const requests = s.chunkCache.hits + s.chunkCache.misses;
				$("cache").textContent = bytes(s.chunkCache.bytes);
				$("cache-hits").textContent = (requests ? (s.chunkCache.hits / requests * 100).toFixed(1) : "0.0") + "% hits";
				fill($("versions"), s.players.byVersion, (tr, v) => { cell(tr, v.version); cell(tr, v.protocol); cell(tr, v.players); });
				fill($("worlds"), s.worlds, (tr, w) => { cell(tr, w.name); cell(tr, w.chunks); cell(tr, w.entities); cell(tr, w.players); cell(tr, w.tickMs.toFixed(2)); });
				fill($("player-list"), s.players.list, (tr, p) => {
					cell(tr, p.name); cell(tr, p.version); cell(tr, p.ping === null ? "-" : p.ping + " ms"); cell(tr, p.world); cell(tr, p.position.join(", ")); cell(tr, p.gamemode.toLowerCase());
					const kick = document.createElement("button");
					kick.textContent = "Kick";
					kick.addEventListener("click", () => { if(confirm("Kick " + p.name + "?")) run("kick \"" + p.name + "\""); });
					cell(tr, "").appendChild(kick);
				});
			}else if(tab === "console"){
				const log = await api("/api/log?after=" + logNext);
				const view = $("log");
				const atBottom = view.scrollTop + view.clientHeight >= view.scrollHeight - 4;
				for(const [, line] of log.lines) view.appendChild(document.createTextNode(line + "\n"));
				while(view.childNodes.length > 1000) view.removeChild(view.firstChild);
				logNext = log.next;
				if(atBottom) view.scrollTop = view.scrollHeight;
			}else if(tab === "addons"){
				fill($("addon-list"), await api("/api/addons"), (tr, a) => { cell(tr, a.name); cell(tr, a.version); cell(tr, a.type); cell(tr, a.source); });
			}
		}catch(e){ /* shown on next successful refresh; 401 logs out */ }
	}

	async function run(command){
		try{
			const result = await api("/api/command", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ command }) });
			const view = $("log");
			view.appendChild(document.createTextNode("> " + command + "\n" + result.output.map((l) => "  " + l + "\n").join("")));
			view.scrollTop = view.scrollHeight;
		}catch(e){ alert(e.message); }
	}

	function show(name){
		tab = name;
		for(const section of document.querySelectorAll(".tab")) section.hidden = section.id !== name;
		for(const button of document.querySelectorAll("#tabs [data-tab]")) button.classList.toggle("active", button.dataset.tab === name);
		refresh();
	}

	function login(){
		$("login").hidden = true;
		$("tabs").hidden = false;
		show(tab);
		clearInterval(timer);
		timer = setInterval(refresh, 2000);
	}

	function logout(message){
		token = "";
		sessionStorage.removeItem("amberToken");
		clearInterval(timer);
		for(const section of document.querySelectorAll(".tab")) section.hidden = true;
		$("tabs").hidden = true;
		$("login").hidden = false;
		$("login-error").textContent = message || "";
	}

	$("login-form").addEventListener("submit", async (event) => {
		event.preventDefault();
		token = $("token").value;
		try{
			await api("/api/status");
			sessionStorage.setItem("amberToken", token);
			$("token").value = "";
			login();
		}catch(e){ if(e.message !== "unauthorized") $("login-error").textContent = e.message; }
	});
	$("command-form").addEventListener("submit", (event) => {
		event.preventDefault();
		const command = $("command").value.trim();
		if(command){ run(command); $("command").value = ""; }
	});
	for(const button of document.querySelectorAll("#tabs [data-tab]")) button.addEventListener("click", () => show(button.dataset.tab));
	$("logout").addEventListener("click", () => logout());

	if(token) api("/api/status").then(login, () => {});
})();
