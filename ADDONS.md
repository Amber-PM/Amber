# Bedrock Add-ons

[← Back to the AmberPM README](README.md)

AmberPM loads Minecraft: Bedrock Edition add-ons directly: drop an add-on in the server's `addons/` folder and
restart. Custom items, blocks, entities and recipes become real server content, and players receive the
resource pack when they join. No plugin is needed, and every supported client version gets the content in the
format it expects.

## Installing an add-on

1. Put the add-on in `addons/` next to `server.properties`. Any of these work:
   - an `.mcaddon` (one or more packs),
   - an `.mcpack` or `.zip`,
   - an unpacked pack folder (the folder that holds `manifest.json`, or a folder of such folders).
2. Restart the server. Add-ons are read once, at startup.
3. Check the console, or run `/addons`, to see what loaded.

Archives are unpacked into `addons/.cache/`, keyed by file hash, so replacing an add-on file is picked up on
the next start.

## What an add-on can do on AmberPM

| Part of the add-on | Supported | Notes |
|---|---|---|
| Resource pack | Yes | Added to the server's resource pack stack and sent to players. A pack already in `resource_packs/` (same UUID) is not added twice. |
| Items (`items/*.json`) | Yes | Plain items, tools (durability, damage), food, armour. They appear in the creative inventory and in `/give`. |
| Blocks (`blocks/*.json`) | Yes | Geometry, material instances, collision and selection boxes, light, friction, flammability, map colour, transformation, custom states, permutations, and the `placement_direction` / `placement_position` traits. |
| Entities (`entities/*.json`) | Yes | Spawnable and saved with the world. Health, size, scale, gravity and fire immunity are read. The client renders them from the resource pack. |
| Recipes (`recipes/*.json`) | Mostly | Shaped (crafting table), shapeless (crafting table, stonecutter, cartography and smithing tables) and furnace recipes (furnace, blast furnace, smoker, campfires). Brewing, smithing transforms and trims are skipped. |
| Scripts (`@minecraft/server`) | No | PocketMine does not run add-on JavaScript. Items that only work through scripts load, but do nothing. |
| Entity AI, loot tables, spawn rules, trading, functions | No | Reported at startup. Plugins can implement the behaviour through the API below. |
| Vanilla overrides (`minecraft:player`, `minecraft:zombie`...) | No | Skipped with one warning per pack; the server keeps its own versions. |

Nothing is guessed at. Anything the server skips is logged at startup: a component the loader does not know,
a recipe for an item the server does not have, a recipe type it does not run.

## Supported add-on formats

Add-ons written for **Minecraft 1.21.0 and later** are supported, in every JSON shape those versions allow:
a bare value or `{"value": ...}`, a string icon, `{"texture"}` or `{"textures": {"default"}}`, one collision
box or a list of them, named or numeric food saturation, and so on. Behavior packs that declare only a
`script` module are loaded too.

The loader turns every component into its exact network form, with fixed tag types, before any client sees
it. The shapes follow [Customies](https://github.com/CustomiesDevs/Customies) and
[Dragonfly](https://github.com/df-mc/dragonfly), both of which are proven against real clients. JSON leaves
types loose (`16` or `16.0`), but the client does not accept that looseness on the wire.

### Client versions

The same add-on is sent to every client from **1.21.0 (protocol 685)** to the newest in the format that
version reads:

- **Before 1.21.130:** a block's collision box is one origin/size box, and light values are bytes.
- **From 1.21.130:** collision is a list of min/max boxes, so multi-box collision works, and light values are
  ints.
- **Before 1.21.60:** custom items reach the client through `ItemComponentPacket`. From 1.21.60 they are part
  of the item registry.
- **From 1.26.50:** custom blocks use hashed network IDs, like vanilla ones.
- **All versions:** each custom block gets its `block_id` in the client's own order (FNV-1 64 of the name).

## Commands

| Command | Does |
|---|---|
| `/addons` | Lists the loaded packs and counts items, blocks, entities and recipes |
| `/addons items` · `blocks` · `entities` | Lists that content with identifiers |
| `/addons give <id> [count] [player]` | Gives an add-on item or block |
| `/addons spawn <id>` | Spawns an add-on entity at your position |

Permission: `pocketmine.command.addons` (operators by default).

## Plugin API

```php
use pocketmine\addon\AddonManager;

$addons = $this->getServer()->getAddonManager();

// Give an add-on item.
$player->getInventory()->addItem($addons->getItem("example:ruby_sword"));

// Place an add-on block.
$world->setBlock($position, $addons->getBlock("example:ruby_block"));

// Spawn an add-on entity.
$addons->createEntity("example:wisp", $player->getLocation())?->spawnToAll();

// Give an add-on item behaviour its scripts would normally provide.
$addons->onItemUse("example:ruby", function(Player $player, Item $item, ?Block $clicked) : bool{
	$player->sendMessage("You used a ruby");
	return true; // true cancels the item's default action
});
$addons->onBlockInteract("example:ruby_lamp", function(Player $player, AddonBlock $block, Item $held) : bool{
	return false;
});
```

`getPacks()`, `getItemDefinitions()`, `getBlockDefinitions()` and `getEntityDefinitions()` expose what was
loaded, including each definition's raw JSON components.

## Troubleshooting

- **"component(s) not sent to clients, the loader does not support them yet"**: the listed components are left
  out rather than sent in an unknown shape, since a wrongly typed component can make the client reject the
  item or block. Everything else about that content still works.
- **"unknown recipe item ... the server has no such item"**: the recipe uses a vanilla item that PocketMine does
  not implement (for example `minecraft:elytra` or `minecraft:kelp`). The rest of the add-on's recipes are
  registered.
- **An item or block shows as missing or untextured**: the resource pack does not define the texture named in
  `minecraft:icon` or `minecraft:material_instances`. Check the pack's `item_texture.json` and
  `terrain_texture.json`.
