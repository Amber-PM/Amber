# Bedrock Add-ons

[← Back to the AmberPM README](README.md)

AmberPM runs Minecraft: Bedrock Edition add-ons. Put an add-on in the `addons/` folder, restart, and it works:
its items, blocks and entities exist on the server, its mobs have AI, its scripts run, its loot tables and
spawn rules apply, and players get its resource pack when they join. Plugins keep working next to it, and they
can talk to the add-on (and the add-on to them), so one server can mix both.

- [Quick start](#quick-start)
- [What runs](#what-runs)
- [Scripts](#scripts-minecraftserver)
- [Entities and mob AI](#entities-and-mob-ai)
- [Loot, spawning, recipes, functions and commands](#loot-spawning-recipes-functions-and-commands)
- [Plugins and add-ons together](#plugins-and-add-ons-together)
- [Configuration](#configuration-addonsconfigyml)
- [Performance](#performance)
- [Formats and client versions](#formats-and-client-versions)
- [Commands](#commands)
- [Troubleshooting](#troubleshooting)
- [Not supported](#not-supported)

## Quick start

1. Put the add-on in `addons/` next to `server.properties`: an `.mcaddon`, an `.mcpack`, a `.zip`, or an unpacked
   pack folder (the folder holding `manifest.json`).
2. **If the add-on has scripts**, install [Node.js](https://nodejs.org) **22.15 or newer** on the server machine.
   Nothing else is needed; without Node.js everything else still works and the console says scripts are off.
3. Restart the server. The console lists what loaded, and `/addons` shows it any time.

That's all. Add-ons are read at startup, so restart after adding, updating or removing one.

## What runs

| Part of the add-on | | Notes |
|---|---|---|
| Resource pack | ✅ | Sent to players on join. A pack already in `resource_packs/` is not added twice. |
| Items | ✅ | Tools, weapons, armour, food, durability, custom components. In the creative inventory and `/give`. |
| Blocks | ✅ | Geometry, textures, collision, light, states, permutations, placement traits, loot, custom components. |
| Entities | ✅ | Component groups, events, properties, sensors, timers, damage sensor, interactions, taming, breeding, growing up, projectiles, explosions, transformations, despawning, variants. |
| Mob AI | ✅ | The common `minecraft:behavior.*` goals with A* pathfinding ([list](#behaviors)). |
| Scripts (`@minecraft/server`, `server-ui`) | ✅ | Run by Node.js in a sandbox, synchronously with the server tick ([details](#scripts-minecraftserver)). |
| Loot tables | ✅ | Entity and block drops, and interaction `spawn_items`. |
| Spawn rules | ✅ | Natural spawning around players: surface, underground, underwater, herds, biomes, light, height, density caps. |
| Recipes | ✅ | Shaped, shapeless, stonecutter, furnace family, brewing (mix and container). |
| Functions (`.mcfunction`) | ✅ | Through `/function`, `queue_command` and scripts. |
| Riding, leashing, trading, inventories | ✅ | Seats and steering, leads, villager-style trade screens, mob inventories and equipment, boss bars ([details](#riding-trading-and-inventories)). |
| World | ✅ | Game rules, weather, scoreboards, ticking areas, structures from the pack, camera and fog ([details](#world-game-rules-weather-scoreboard-structures)). |

Nothing is guessed at: whatever the server skips is named once in the console at startup (an unknown component,
a behavior with no implementation, a recipe for an item PocketMine lacks), so you always know where you stand.

## Scripts (`@minecraft/server`)

Behavior pack scripts run in a Node.js process next to the server. They run **inside the server tick**, as in
the game: every tick the scripts get that tick's events and their scheduled callbacks run; cancellable events
(`beforeEvents`) and custom components run the moment they happen, so a script can cancel a block break or a
chat message. Script calls such as `entity.location` or `dimension.getBlock()` are answered by the server
directly, so scripts always see the live world, and plugins see script changes immediately.

**What scripts can use**

- `world`, `system` (`run`, `runTimeout`, `runInterval`, `runJob`, `waitTicks`, `clearRun`, `sendScriptEvent`)
- `Dimension`: blocks, entities with full query options, `spawnEntity`, `spawnItem`, `spawnParticle` (with
  `MolangVariableMap`), `playSound`, `createExplosion`, `runCommand`, `fillBlocks`, `getBlocks` and
  `containsBlock` with block filters, raycasts, light, biomes
- `BlockVolume`, `ListBlockVolume`; `EnchantmentTypes`, `EntityTypes`, `DimensionTypes`, `BiomeTypes`;
  `world.getLootTableManager()` (pack loot tables and add-on entities' tables), `world.tickingAreaManager`,
  `world.seed`
- `Entity` / `Player`: position, rotation, velocity, health, tags, name tags, effects, properties, events,
  impulses and knockback, teleport, damage, dynamic properties, inventory, equipment, messages, titles,
  action bar, sounds, game mode, XP, spawn point, input permissions
- `Block`, `BlockPermutation` (with states), `ItemStack` (name, lore, durability, enchantments), `Container`
- `world.scoreboard` (saved, shown on the sidebar, list or below names, and the same scoreboard `/scoreboard` uses)
- `world.gameRules`, `dimension.getWeather()`/`setWeather()`, `world.structureManager` (`get`, `place`)
- `player.camera` (`setCamera` with presets and easing, `fade`, `clear`, `setFov`, `clearFov`) and
  `player.onScreenDisplay` HUD visibility
- Entity components `rideable`, `riding`, `leashable`, `inventory` and `equippable` on add-on mobs
- Dynamic properties for the world, entities and players (saved across restarts)
- After events: spawn, death, hurt, heal, health changes, hit (entities and blocks), remove, player
  join/leave/spawn, block break/place, starting and cancelling block breaking, swings, item use (and on a
  block), item pickup and drop, sneaking, containers opened and closed, interactions with blocks and entities,
  levers, buttons and pressure plates, exploded blocks, chat, emotes, projectile hits, effects, taming, game
  mode and dimension changes, hotbar slot and inventory changes, button presses and input mode, input
  permissions, game rules, weather, data-driven entity events, `itemStartUse`/`itemStopUse`/`itemReleaseUse`,
  `scriptEventReceive`, `worldLoad`
- Before events (cancellable): chat, block break, item use, interactions, effect add, heal (and change the
  amount), item pickup, taming, game mode change, weather change, explosion, player leave, entity remove
- Custom components: items (`onUse`, `onUseOn`, `onConsume`, `onHitEntity`, `onMineBlock`) and blocks
  (`onPlayerInteract`, `onPlace`, `onPlayerBreak`/`onPlayerDestroy`, `onTick` with `minecraft:tick`,
  `onRandomTick`, `onStepOn`/`onStepOff`, `onEntityFallOn`), registered in `startup`/`worldInitialize`
- Custom commands (`customCommandRegistry`), which become real server commands
- `@minecraft/server-ui`: `ActionFormData`, `ModalFormData`, `MessageFormData`
- `@minecraft/math`, `@minecraft/common`, and `@minecraft/vanilla-data` with the game's own identifiers
- `@amber/plugins`: call PHP plugins ([see below](#plugins-and-add-ons-together))

Both API generations work: packs written for **1.x** (`isValid()` as a method, `"survival"`) and **2.x**
(`isValid` as a property, `"Survival"`) each get the form their manifest asks for.

**Every import links.** Each `@minecraft` module exports every name the game's does (all classes, enums with
the game's values, errors, constants), generated from Mojang's script API metadata by
`tools/generate-addon-script-names.php`. A pack that imports something this server does not implement still
loads; only using that one thing throws.

**Defense in depth.** Behavior packs are treated as trusted server code, similar to PHP plugins. The runtime uses a Worker thread, restricted module resolution, a sanitized environment, and Node's permission model to reduce accidental filesystem, process, native-code, and IPC access. These mechanisms are defense in depth only and are not a security boundary against intentionally malicious behavior-pack code. An error in one pack is logged against that pack and does not affect the others. Each tick scripts get a time budget (30 ms by default): scripts that run longer are left to finish in the background, delaying script callbacks but never the server, and a script host stuck for 10 seconds is restarted.

`console.log`/`warn`/`error` go to the server console, tagged with the pack name.

## Entities and mob AI

Add-on entities run their behavior pack definition as in the game:

- **Component groups and events**: `add`, `remove`, `randomize`, `sequence`, `first_valid`, `trigger`,
  `set_property` (with Molang), `queue_command`, `reset_target`, with filters.
- **Built-in events**: `minecraft:entity_spawned`, `entity_born` (breeding), `entity_transformed`, and spawn
  rule / `summon` events.
- **Properties** (`int`, `float`, `bool`, `enum`), synced to clients so render controllers and animations
  see them.
- **Sensors and triggers**: `environment_sensor`, `entity_sensor`, `target_nearby_sensor`, `block_sensor`,
  `inside_block_notifier`, `timer`, `scheduler`, `damage_sensor` (per cause, cancel or scale damage), `on_hurt`,
  `on_hurt_by_player`, `on_target_acquired`, `on_target_escape`, `on_friendly_anger`.
- **Sounds**: the pack's ambient (`ambient_sound_interval`), hurt and death sounds.
- **Interaction**: `interact` (use or hurt the held item, swap it, drop loot, fire events), `tameable`,
  `healable`, `breedable` (both `breeds_with` forms) with `offspring` (cross-breeds, inherited properties),
  `ageable` (babies grow up, can be fed), `sittable`, `tamemount`.
- **Combat**: `attack` (damage and effect), `shooter` with add-on or vanilla projectiles, `projectile`
  (impact damage, knockback, effects, sticking, events), `explode`, `area_attack`, `mob_effect`, `angry`
  (with `broadcast_anger` and `calm_event`), `attack_cooldown`, `damage_over_time`, `follow_range`,
  `cannot_be_attacked`, `mob_effect_immunity`.
- **World**: `despawn`, `instant_despawn`, `burns_in_daylight`, `breathable`, `hurt_on_condition`,
  `spell_effects`, `transformation`, `loot`, `experience_reward`, `persistent`, `transient`, `spawn_entity`
  (laying eggs and other drops), `teleport`, `home`, `trail`, `buoyant`, `pushable`; `movement.jump` and
  `movement.skip` move in hops.
- **Looks**: `variant`, `mark_variant`, `skin_id`, `color`, `scale`, baby/tamed/sitting/saddled/chested/
  sheared/charged flags.

### Riding, trading and inventories

- **Riding** (`rideable`): seats with positions and rotation limits, `family_types`, `interact_text`, riders
  pulled along. `input_ground_controlled` and `input_air_controlled` let the player in the controlling seat steer
  with movement keys and jump; sneaking or leaving dismounts.
- **Leashing** (`leashable`): a lead ties the mob to a player or another mob; it follows past `soft_distance`,
  is pulled at `hard_distance`, and the lead breaks past `max_distance`.
- **Inventories** (`inventory`): `inventory_size`, extra slots when chested, `private` and `restrict_to_owner`.
  Players open them by sneak-interacting (or interacting with mobs that cannot be ridden). The contents are
  saved with the mob and dropped when it dies.
- **Equipment** (`equipment`): gear rolled from a loot table when the mob first spawns, with `slot_drop_chance`.
  Held items and armour are shown to every client and saved.
- **Trading** (`trade_table`, `economy_trade_table`): tiers unlocked by trader experience, groups with
  `num_to_select`, `choice` items and quantity ranges. Players get the real trade screen (old or new style);
  trades are checked on the server, count their uses and reward experience. The trader stops and looks at its
  customer.
- **Boss bars** (`boss`): shown to players within `hud_range`, following the mob's health.
- **Picking up items** (`behavior.pickup_items` with `shareables` or `can_pickup_any_item`): into the inventory,
  empty armour slots or hands.

Filters cover the tests packs actually use: families, components, properties, variants, equipment, effects,
health, distance to players, water/lava/fire, daylight and time, biomes and biome tags, brightness, altitude,
difficulty, targets and owners, random chance, and more. A test the server cannot evaluate is reported once
and counts as false.

### Behaviors

`float`, `random_stroll`, `random_swim`, `random_fly`, `random_hover`, `swim_wander`, `swim_idle`,
`look_at_player`, `look_at_entity`, `look_at_target`, `random_look_around`, `panic`, `tempt`, `follow_parent`,
`follow_owner`, `follow_mob`, `nearest_attackable_target`, `nearest_prioritized_attackable_target`,
`hurt_by_target`, `owner_hurt_by_target`, `owner_hurt_target`, `melee_attack`, `melee_box_attack`,
`delayed_attack`, `ranged_attack`, `avoid_mob_type`, `avoid_entity`, `leap_at_target`, `move_towards_target`,
`breed`, `stay_while_sitting`, `pickup_items`, `equip_item`, `timer_flag_1`/`2`/`3`, `teleport_to_owner`,
`summon_entity`, `send_event`, `knockback_roar`, `swell`, `move_to_block`, `move_to_water`, `move_to_land`,
`move_to_lava`, `move_to_liquid`, `move_to_random_block`, `flee_sun`, `avoid_block`, `go_home`,
`move_towards_home_restriction`, `find_mount`, `mount_pathing`, `run_around_like_crazy`, `player_ride_tamed`,
`controlled_by_player`, `eat_block`, `random_sitting`, `float_wander`, the slime behaviors, `float_tempt`,
and the attack variants (`stomp_attack`, `ram_attack`, `charge_attack`, `swoop_attack`, `ocelotattack`).

Walking mobs use A* pathfinding (step up, drop down, avoid lava, fire and cactus, walk through open doors, no corner
cutting); flying and swimming mobs steer directly. Knockback and collisions still come from the normal entity
physics. A behavior not in the list is named at startup, and a plugin can supply it (see below).

## World: game rules, weather, scoreboard, structures

Add-ons get the world features vanilla packs expect, shared between scripts, commands and plugins, and saved
in `addons/.runtime/`:

- **Game rules**: all vanilla rules can be read and set (`/gamerule`, `world.gameRules`). The server acts on
  `pvp`, `keepinventory`, `showdeathmessages`, `falldamage`, `firedamage`, `drowningdamage`,
  `naturalregeneration`, `domobloot`, `dotiledrops`, `domobspawning`, `mobgriefing`,
  `tntexplodes`, `dofiretick`, `dodaylightcycle` and `doweathercycle`; rules the client displays
  (coordinates, days played, immediate respawn...) are sent to it.
- **Weather** per world: clear, rain and thunder with a duration, a natural cycle while `doweathercycle` is on,
  and the `weatherChange` script event.
- **Scoreboard**: objectives, scores for players, entities and fake names, display slots; one scoreboard for
  scripts and `/scoreboard`.
- **Ticking areas**: `/tickingarea add|remove|list` keep chunks loaded and ticking (up to 10 areas of 100 chunks).
- **Structures**: `.mcstructure` files in the behavior pack are placed with `/structure load` or
  `world.structureManager.place()`, block states upgraded to the running version.
- **Camera and fog**: `/camera` and `player.camera` with the pack's camera presets (`cameras/presets`), fades and
  field of view; `/fog push|pop|remove`.

## Loot, spawning, recipes, functions and commands

- **Loot tables**: pools, rolls and bonus rolls, weights, nested tables, `set_count`, `set_data`, `set_name`,
  `set_lore`, `set_damage`, `looting_enchant`, `furnace_smelt`, `enchant_randomly`, and the conditions
  `random_chance`, `random_chance_with_looting`, `killed_by_player`, `entity_properties`.
- **Spawn rules** run around players every 2 seconds: surface, underground, underwater and lava spots, weights,
  herds and herd events, `permute_type`, light, height, difficulty, distance, world age, block and biome filters,
  per-entity density limits, and per-category caps that scale with player count (see the config).
- **Brewing**: `recipe_brewing_mix` and `recipe_brewing_container` work in the brewing stand.
- **Commands** from add-ons (`queue_command`, scripts' `runCommand`, `.mcfunction` files) support selectors
  (`@s @p @a @r @e` with `type`, `family`, `tag`, `name`, `r`, `rm`, `c`, `x y z`, `dx dy dz`, `m`), relative
  and local coordinates, and `execute` (`as`, `at`, `positioned`, `rotated`, `in`, `if`/`unless entity|block`,
  `run`, and the old syntax). The server adds the commands packs rely on: `tag`, `summon`, `setblock`, `fill`,
  `particle`, `playsound`, `stopsound`, `camerashake`, `inputpermission`, `playanimation`, `tellraw`, `damage`,
  `event entity`, `replaceitem`, `testfor`, `function`, `camera`, `fog`, `hud`, `weather`, `gamerule`,
  `scoreboard`, `tickingarea`, `structure load`, `scriptevent`, `schedule`, `clone`, `testforblock`,
  `testforblocks`, `loot`, `titleraw`, `spreadplayers`, `toggledownfall`, `daylock`, `clearspawnpoint`, and
  entity-aware `kill`, `tp` and `effect`. Every other
  command goes to the server's command map, **so plugin commands work from add-ons**.

## Plugins and add-ons together

Add-ons don't replace plugins; they run side by side and can call each other. Everything below uses
`$this->getServer()->getAddonManager()`.

**React to what an add-on does**: every entity event a pack fires is a normal server event.

```php
use pocketmine\addon\event\AddonEntityTriggerEvent;

public function onPackEvent(AddonEntityTriggerEvent $event) : void{
	if($event->getTriggerName() === "example:become_angry"){
		$this->getServer()->broadcastMessage("A " . $event->getEntity()->getName() . " got angry!");
		// $event->cancel(); would stop the pack's event from running
	}
}
```

Add-on entities also fire the usual PocketMine events (spawn, damage, death with the loot table's drops), so
existing plugins such as protection, economy or anti-cheat plugins see them like any other entity.

**Drive add-on entities from PHP**

```php
$wisp = $addons->createEntity("example:wisp", $player->getLocation());
$wisp->spawnToAll();
$wisp->triggerEvent("example:become_tame");        // run one of the pack's events
$wisp->addComponentGroup("example:glowing");       // or change its component groups directly
$wisp->setProperty("example:level", 3);            // entity properties (synced to clients)
$wisp->setAlwaysActive(true);                      // keep its AI running with no player nearby
$addons->onEntityInteract("example:wisp", function(Player $player, AddonEntity $wisp, Item $held) : bool{
	return false; // true cancels the pack's own interaction
});
```

**Add or replace mob behaviors** with your own goals (for behaviors AmberPM does not implement, or to
change how one works):

```php
use pocketmine\addon\entity\ai\MobBrain;
use pocketmine\addon\entity\ai\goal\CallbackGoal;
use pocketmine\addon\entity\ai\Goal;

MobBrain::registerGoal("minecraft:behavior.eat_block", fn(AddonEntity $mob, array $json, int $priority) => new CallbackGoal(
	$mob, $json, $priority, Goal::FLAG_MOVE,
	canStart: fn(AddonEntity $mob) => mt_rand(0, 999) === 0,
	tick: fn(AddonEntity $mob) => $mob->triggerEvent("minecraft:on_eat_block"),
));
```

**Talk to scripts**

```php
$scripts = $addons->getScriptHost();

// A PHP function scripts can call: plugins.call("economy:balance", player.name)
$scripts->exposeFunction("economy:balance", fn(string $player) : int => $this->economy->get($player));

// Call a function a script exposed with plugins.expose(...)
$level = $scripts->callScript("mypack:getLevel", $player->getName());

// Script events, both ways
$addons->sendScriptEvent("mypack:open_shop", $player->getName());   // scripts: system.afterEvents.scriptEventReceive
// ...and listen to AddonScriptEvent for what scripts send with system.sendScriptEvent()

// Dynamic properties are shared
$scripts->setDynamicProperty("mypack:season", "winter");
```

In a script:

```js
import { world } from "@minecraft/server";
import { plugins } from "@amber/plugins";

world.afterEvents.playerSpawn.subscribe(({ player }) => {
	const balance = plugins.call("economy:balance", player.name);   // PHP answers synchronously
	player.sendMessage(`You have ${balance} coins`);
});
plugins.expose("mypack:getLevel", (name) => world.getDynamicProperty(`level:${name}`) ?? 1);
```

Scripts' `runCommand()` can also run any plugin command, so packs can use plugin features without any code.
Plugins can call `exposeFunction()` in `onEnable()`; scripts start right after all plugins are enabled.

**Other hooks**: `onItemUse()`, `onBlockInteract()`, `getItem()`, `getBlock()`, `getLootTables()`,
`getCommandBridge()->run($command, $entity)`, and the loaded definitions (`getItemDefinitions()`,
`getBlockDefinitions()`, `getEntityDefinitions()`, `getPacks()`).

## Configuration (`addons/config.yml`)

Created with these defaults on first start:

```yaml
scripting:
  enabled: true        # run behavior pack scripts (needs Node.js 22.15+)
  node: node           # path to the Node.js binary
  tick-budget-ms: 30   # script time per tick before the server stops waiting
  memory-mb: 256       # memory limit of the script host
spawning:
  enabled: true        # natural spawning from spawn rules
  interval-ticks: 40   # how often a spawn attempt is made per player
  worlds: []           # world folder names to spawn in; empty = all worlds
  caps:                # mobs per player, per spawn rule population_control
    monster: 10
    animal: 6
    water_animal: 4
    ambient: 3
    default: 5
```

Mob AI runs at full rate within 48 blocks of a player and once a second farther away (plugins can override
this per mob with `setAlwaysActive()`).

## Performance

The runtime is built to stay out of the tick's way:

- Mobs away from players think once a second; idle behaviors are polled every 4 ticks, spread across mobs.
- Pathfinding reads raw block states, classifies each state once, rejects unreachable goals up front, and
  is limited to 16 searches per tick across all mobs (the rest wait a tick): about 0.7 ms per search.
- Molang expressions are compiled once (about 3 µs per evaluation); loot tables and their items are resolved
  once (about 19 µs per roll).
- Player lookups walk the world's player list instead of scanning every entity.
- Scripts run under a per-tick budget; world state they read is cached per tick and refreshed after writes.

Measured on a 2-core VPS shared with a live server: **200 mobs with full AI, all active at once and strolling
six times more often than vanilla, kept the server at 19.6–20 TPS across repeated runs.** `/timings` reports have an **Add-ons** group
(entity runtime, mob AI, pathfinding, scripts, spawning), so you can see exactly what add-ons cost.

## Formats and client versions

Add-ons written for **Minecraft 1.21.0 and later** load in every JSON shape those versions allow (a bare
value or `{"value": ...}`, a string icon or a `textures` object, one collision box or a list...). Every
component is turned into its exact network form before a client sees it; the shapes follow
[Customies](https://github.com/CustomiesDevs/Customies) and [Dragonfly](https://github.com/df-mc/dragonfly).

The same add-on reaches every client from **1.21.0 (protocol 685)** to the newest in the format that version
reads: one origin/size collision box and byte light values before 1.21.130, min/max boxes and int light from
1.21.130; `ItemComponentPacket` before 1.21.60 and the item registry from it; hashed block IDs from 1.26.50;
and custom block IDs in the client's own (FNV-1 64) order on every version.

## Commands

| Command | Does |
|---|---|
| `/addons` | Lists the loaded packs and counts items, blocks, entities and recipes |
| `/addons items` · `blocks` · `entities` | Lists that content with identifiers |
| `/addons give <id> [count] [player]` | Gives an add-on item or block |
| `/addons spawn <id>` | Spawns an add-on entity at your position |

Permission: `pocketmine.command.addons` (operators by default).

The vanilla commands add-ons use (`camera`, `fog`, `gamerule`, `weather`, `scoreboard`, `tickingarea`,
`structure`, `hud`, `summon`, `setblock`, `fill`, `execute`...) are also registered as server commands for
operators, unless a plugin already has that name: the plugin's command always wins.

## Troubleshooting

- **"Add-on scripts are not run: Node.js was not found"**: install Node.js 22.15+, or set `scripting.node` to
  its full path in `addons/config.yml`.
- **`[Script:<pack>] ...` errors**: the pack's script threw; the message and stack point at the pack file.
  The pack's other callbacks and other packs keep running.
- **"behavior ... is not implemented"**: that mob behavior is skipped; the mob still does everything else.
  A plugin can add it with `MobBrain::registerGoal()`.
- **"filter test ... is not supported and is treated as false"**: that one filter is off; the rest work.
- **"component(s) not sent to clients"**: those item/block components are left out rather than sent in a shape
  the client might reject; the rest of the item or block works.
- **"unknown recipe item ... the server has no such item"**: the recipe uses a vanilla item PocketMine does not
  implement (such as `minecraft:elytra`); the add-on's other recipes are registered.
- **"/x is not a command on this server"**: a pack ran a command neither the server nor a plugin provides.
- **Untextured items or blocks**: the resource pack does not define the texture named in `minecraft:icon` or
  `minecraft:material_instances`.

## Not supported

- **Vanilla overrides** (`minecraft:player`, `minecraft:zombie`...): skipped with one warning; the server keeps
  its own versions (PocketMine has no vanilla mob AI to override).
- **Behaviors and components tied to the game's villages, raids and specific vanilla mobs** (villager
  schedules and trading AI, raids, the ender dragon, warden, squid, panda...), `npc` dialogue, `dash`,
  `trusting`, `peek`, and physics details PocketMine does not model (pistons pushing mobs, freezing): behaviors
  are named at startup, and plugins can add them with `MobBrain::registerGoal()`.
- **Scripts**: `@minecraft/server-net`, `server-gametest`, `server-editor`, `debug-utilities` and
  `server-graphics` import with all their names but throw if used; the same goes for the few
  `@minecraft/server` classes with nothing behind them here (aim assist, waypoints, primitive shapes...).
  Structures can be loaded and placed, not saved from the world.
- **Commands**: `music`, `mobevent`, `dialogue`, `ride`, `aimassist` and `controlscheme` are accepted and do
  nothing; `structure save` is not available.
- **Game rules** with no PocketMine mechanic behind them (`doinsomnia`, `freezedamage`, `doentitydrops`, `randomtickspeed`,
  `playerssleepingpercentage`...) are stored and reported but change nothing.
