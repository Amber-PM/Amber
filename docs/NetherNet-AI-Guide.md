# NetherNet AI Setup Guide

> Paste this whole page into your AI assistant (ChatGPT, Claude, Gemini...) and ask it to help you set up NetherNet on AmberPM.

## Instructions for the assistant

You are helping a Minecraft Bedrock server owner set up **NetherNet** on **AmberPM** (a PocketMine-MP fork). Follow these rules:

- Ask the questions in "Questions to ask" first, one short message, before giving any config.
- Give only the steps for the owner's case. Do not dump every case.
- Use the exact keys and snippets from this page. Do not invent settings.
- Never ask for passwords, panel logins or `nethernet.key` contents.
- After the config, give the "Verify" steps and wait for the console output.

## Facts

- Minecraft 26.60+ clients join over NetherNet. Older clients join over RakNet. AmberPM runs both.
- NetherNet needs **TCP** on `server-port` (signaling) and **UDP** for game traffic, `20000-20127` by default.
- RakNet needs **UDP** on `server-port`, or on `network.raknet-port` when set.
- A UDP port can belong to only one of them. TCP and UDP with the same number do not conflict.
- Behind NAT, Docker or a hosting panel, the server only knows its private IP, so `network.nethernet.advertised-address` must be the public IP. The forwarded UDP port must have the same number outside and inside.
- Required runtime: AmberPM PHP binaries **r3 or newer** (`ext-rtc`), from https://github.com/Amber-PM/PHP-Binaries/releases.
- `nethernet.key` in the server folder is the server identity. Losing it makes every player see the "trust this server" prompt again.

## Questions to ask

1. Where does the server run: VPS or dedicated, home PC, or a hosting panel (Pterodactyl or similar)?
2. If it is a panel: how many ports does it give you, and are they TCP and UDP?
3. Do you still need players on versions older than 26.60?
4. Is the server on Linux or Windows?

## Cases

### A. VPS or dedicated, no firewall
No config change. Install the r3 binaries and the new phar, then start.

### B. VPS with a firewall, or a home PC behind a router
Open TCP `server-port` and UDP `20000-20127`. On a router, forward both to the PC. No config change.

Linux ufw example:
```bash
ufw allow 19132/tcp
ufw allow 19132/udp
ufw allow 20000:20127/udp
```

### C. Panel with two ports, P (main) and P2 (extra)
All versions join on `IP:P`.
```yaml
network:
  raknet-port: 0
  nethernet:
    ice-udp-mux: true
    ice-port-range: [P2, P2]
    advertised-address: PUBLIC_IP
```

### D. Panel with one port P
Only 26.60+ clients can join. If older versions matter, tell the owner to ask the host for a second port and use case C.
```yaml
network:
  raknet-port: -1
  nethernet:
    ice-udp-mux: true
    ice-port-range: [P, P]
    advertised-address: PUBLIC_IP
```

### Environment variables (Docker or panels)
These replace the YAML above:
- `AMBER_NETHERNET_PORT=P2` sets one UDP port and turns on `ice-udp-mux`.
- `AMBER_NETHERNET_PUBLIC_IP=PUBLIC_IP` sets `advertised-address`.
- `--network.raknet-port=-1` on the start command turns RakNet off.

### Windows with `ice-udp-mux`
Also set `network.nethernet.ice-bind-address` to the machine LAN IPv4 (for example `192.168.1.20`). Wildcards like `0.0.0.0` are rejected.

## Verify

1. Start the server and find this line:
   `NetherNet (26.60+) needs TCP <port> and UDP <ports> open`
   The ports must match what is open or forwarded.
2. Join with a 26.60+ client using `IP:P`.
3. If it fails, ask the owner for the console lines that contain `NetherNet`.

## Troubleshooting

| Console message | Fix |
|---|---|
| `connect_timeout` | UDP port closed, or `advertised-address` missing or wrong |
| `failed authentication` | Ask the owner to update the game and retry |
| `max-per-ip is reached` | Raise `network.nethernet.max-per-ip` |
| `WebRTC runtime is missing or incompatible` | Install PHP binaries r3 or newer |
| `must be a numeric IPv4 or IPv6 address` | `advertised-address` must be an IP, not a domain |
| `ICE UDP mux requires one fixed ICE port` | Use `[P, P]`, both numbers equal |
| `ICE UDP mux on Windows requires an explicit, non-wildcard ICE bind address` | Set `ice-bind-address` to the LAN IPv4 |
| Old clients cannot join in case D | Expected, RakNet is off; use case C |

Full reference: [Wiki](https://github.com/Amber-PM/Amber/wiki/NetherNet-Setup).
