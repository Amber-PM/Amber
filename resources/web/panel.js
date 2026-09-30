"use strict";
(() => {
	const $ = (id) => document.getElementById(id);
	const SVG = "http://www.w3.org/2000/svg";
	const MAX_LOG_LINES = 5000;
	const LEVEL_GROUPS = { emergency: "error", alert: "error", critical: "error", error: "error", warning: "warning", notice: "info", info: "info", debug: "debug" };

	let token = sessionStorage.getItem("amberToken") || "";
	let scope = "read";
	let tab = "overview";
	let logNext = 0;
	let logLines = [];
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
	function quote(name){ return "\"" + name.replace(/"/g, "") + "\""; }

	function chart(id, values, format, fixedMax){
		const svg = $(id);
		const width = 300, height = 60;
		const max = Math.max(fixedMax || 0, ...values, 1);
		const points = values.map((v, i) => (values.length < 2 ? width : i / (values.length - 1) * width).toFixed(1) + "," + (height - v / max * (height - 4) - 2).toFixed(1));
		const line = document.createElementNS(SVG, "polyline");
		line.setAttribute("points", points.join(" "));
		line.setAttribute("class", "series");
		svg.replaceChildren(line);
		const last = values.length ? values[values.length - 1] : 0;
		$(id + "-value").textContent = format(last);
		$(id + "-range").textContent = values.length ? "max " + format(Math.max(...values)) + ", min " + format(Math.min(...values)) : "";
	}

	async function refreshOverview(){
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
			const actions = cell(tr, "");
			if(scope === "admin"){
				for(const [label, command] of [["Kick", "kick"], ["Ban", "ban"]]){
					const button = document.createElement("button");
					button.textContent = label;
					button.addEventListener("click", () => { if(confirm(label + " " + p.name + "?")) run(command + " " + quote(p.name)); });
					actions.appendChild(button);
				}
			}
		});
		if(tab === "overview"){
			const h = await api("/api/history");
			chart("chart-tps", h.tps, (v) => v.toFixed(1), 20);
			chart("chart-players", h.players, (v) => String(v));
			chart("chart-load", h.load, (v) => v.toFixed(0) + "%", 100);
			chart("chart-memory", h.memory, bytes);
			$("history-span").textContent = h.time.length > 1 ? "Last " + duration(h.time[h.time.length - 1] - h.time[0]) : "Collecting...";
		}
	}

	function lineVisible(entry){
		if(!$("level-" + entry.group).checked) return false;
		const search = $("log-search").value.trim().toLowerCase();
		return search === "" || entry.lower.includes(search);
	}

	function renderLog(){
		const view = $("log");
		const atBottom = view.scrollTop + view.clientHeight >= view.scrollHeight - 4;
		const scrollTop = view.scrollTop;
		view.replaceChildren(document.createTextNode(logLines.filter(lineVisible).map((e) => e.text).join("\n") + "\n"));
		view.scrollTop = atBottom ? view.scrollHeight : scrollTop;
	}

	function addLogLines(texts){
		const added = texts.map((text) => {
			const level = (text.match(/^\[([a-z]+)\]/) || [, "info"])[1];
			return { text, lower: text.toLowerCase(), group: LEVEL_GROUPS[level] || "info" };
		});
		logLines.push(...added);
		if(logLines.length > MAX_LOG_LINES) logLines = logLines.slice(-MAX_LOG_LINES);
		return added;
	}

	async function refreshLog(){
		const log = await api("/api/log?after=" + logNext);
		logNext = log.next;
		if(log.lines.length === 0) return;
		const added = addLogLines(log.lines.map(([, text]) => text));
		if($("log-pause").classList.contains("active")) return;
		const view = $("log");
		const atBottom = view.scrollTop + view.clientHeight >= view.scrollHeight - 4;
		const visible = added.filter(lineVisible);
		if(visible.length) view.appendChild(document.createTextNode(visible.map((e) => e.text).join("\n") + "\n"));
		if(view.childNodes.length > 200) renderLog();
		else if(atBottom) view.scrollTop = view.scrollHeight;
	}

	async function refresh(){
		try{
			if(tab === "overview" || tab === "players") await refreshOverview();
			else if(tab === "console") await refreshLog();
			else if(tab === "addons") fill($("addon-list"), await api("/api/addons"), (tr, a) => { cell(tr, a.name); cell(tr, a.version); cell(tr, a.type); cell(tr, a.source); });
		}catch(e){ /* shown on next successful refresh; 401 logs out */ }
	}

	async function run(command){
		try{
			const result = await api("/api/command", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ command }) });
			addLogLines(["> " + command, ...result.output.map((l) => "  " + l)]);
			renderLog();
			$("log").scrollTop = $("log").scrollHeight;
		}catch(e){ alert(e.message); }
	}

	function show(name){
		tab = name;
		for(const section of document.querySelectorAll(".tab")) section.hidden = section.id !== name;
		for(const button of document.querySelectorAll("#tabs [data-tab]")) button.classList.toggle("active", button.dataset.tab === name);
		refresh();
	}

	async function login(){
		const session = await api("/api/session");
		scope = session.scope;
		$("scope").textContent = scope === "admin" ? "" : "read-only";
		$("command-form").hidden = scope !== "admin";
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
		logLines = [];
		logNext = 0;
		$("log").replaceChildren();
		for(const section of document.querySelectorAll(".tab")) section.hidden = true;
		$("tabs").hidden = true;
		$("scope").textContent = "";
		$("login").hidden = false;
		$("login-error").textContent = message || "";
	}

	$("login-form").addEventListener("submit", async (event) => {
		event.preventDefault();
		token = $("token").value;
		try{
			await login();
			sessionStorage.setItem("amberToken", token);
			$("token").value = "";
		}catch(e){ if(e.message !== "unauthorized") $("login-error").textContent = e.message; }
	});
	$("command-form").addEventListener("submit", (event) => {
		event.preventDefault();
		const command = $("command").value.trim();
		if(command){ run(command); $("command").value = ""; }
	});
	for(const id of ["level-error", "level-warning", "level-info", "level-debug"]) $(id).addEventListener("change", renderLog);
	$("log-search").addEventListener("input", renderLog);
	$("log-pause").addEventListener("click", () => {
		const paused = $("log-pause").classList.toggle("active");
		$("log-pause").textContent = paused ? "Resume" : "Pause";
		if(!paused) renderLog();
	});
	$("log-download").addEventListener("click", () => {
		const link = document.createElement("a");
		link.href = URL.createObjectURL(new Blob([logLines.filter(lineVisible).map((e) => e.text).join("\n") + "\n"], { type: "text/plain" }));
		link.download = "amber-console-" + new Date().toISOString().replace(/[:.]/g, "-") + ".log";
		link.click();
		setTimeout(() => URL.revokeObjectURL(link.href), 1000);
	});
	for(const button of document.querySelectorAll("#tabs [data-tab]")) button.addEventListener("click", () => show(button.dataset.tab));
	$("logout").addEventListener("click", () => logout());

	if(token) login().catch(() => {});
})();
