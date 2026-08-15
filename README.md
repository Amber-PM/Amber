<p align="center">
	<a href="https://github.com/Amber-PM/Amber">
		<img src=".github/readme/amberpm.png" width="128" height="128" alt="AmberPM Logo" title="AmberPM" />
	</a><br>
	<b>AmberPM: A high-performance, multi-version fork of PocketMine-MP written in PHP</b>
</p>

<p align="center">
	<a href="https://github.com/Amber-PM/Amber/releases/latest"><img alt="GitHub release (latest SemVer)" src="https://img.shields.io/github/v/release/Amber-PM/Amber?label=release&sort=semver"></a>
	<a href="https://discord.gg/k55gScjTs3"><img src="https://img.shields.io/badge/Discord-Chat-5865F2?logo=discord&logoColor=white" alt="Discord" /></a>
	<a href="LICENSE"><img src="https://img.shields.io/badge/License-LGPL--3.0-blue.svg" alt="License" /></a>
</p>

## What is AmberPM?
**AmberPM** is a high-performance, production-ready fork of PocketMine-MP designed specifically for server networks that require simultaneous multi-version (MV) client compatibility. 

Built on top of the stable **PocketMine-MP 5.44.2** codebase, this fork incorporates a dynamic protocol translation layer. This allows Minecraft: Bedrock Edition clients ranging from version **v1.20.0 (Protocol 589)** to **v1.26.30 (Protocol 1001)** to connect and play concurrently on the same server without requiring external proxies or translators.

### Key Features
* 🌐 **Dynamic Multi-Version Support** - Concurrently supports Minecraft: Bedrock protocols from **589 to 1001** (v1.20.0 to v1.26.30) out of the box.
* ⚙️ **Protocol-Isolated Dictionaries & Registries** - Utilizes version-aware mappings for block state NBTs, crafting recipes, and creative inventories using isolated instances to prevent memory cross-contamination.
* 🛠️ **Native Anvil & Repair System** - Provides built-in support for anvil transactions (`AnvilTransaction`), item renaming, item repairing, and enchantment combining (using customizable cost calculations).
* 🎯 **Custom Event Dispatchers** - Exposes developer-focused events such as `PlayerPressurePlateTriggerEvent`, `SessionDisconnectEvent`, and `ItemEntityDropEvent` for granular event manipulation.
* 🧩 **Extensible Plugin API** - Keeps full compatibility with the official PocketMine-MP v5 plugin API, enabling most standard plugins to run without modifications.
* ⚡ **Performance & Compression** - Features optimized protocol translation overhead and dynamic packet compression adjustments adapted to the connection's protocol level.

## :x: AmberPM is NOT a vanilla Minecraft server software.
**It is designed primarily for custom game modes, minigames, and lobby servers.**
Just like official PocketMine-MP, it does not ship with most survival features from the vanilla game (such as vanilla mob AI, redstone simulation, or vanilla world generation).

If you are trying to host a purely **vanilla survival multiplayer** server, please use the [official Minecraft: Bedrock server software](https://minecraft.net/download/server/bedrock).

## Getting Started

### Installation & Compilation
To compile AmberPM from source, you can use the integrated scripts or composer:

#### On Windows:
Simply run the included `compile.bat`:
```cmd
compile.bat
```
This script will verify your PHP installation, download Composer if missing, install the required dependencies with optimal performance flags, and package the code into `PocketMine-MP.phar`.

#### On Linux / macOS or via Composer:
Run the composer script to install production dependencies and compile the server:
```bash
composer run make-server
```

### Running the Server
You can launch the compiled server using the start scripts provided:
* **Windows**: `start.cmd` or `start.ps1`
* **Linux / macOS**: `./start.sh`

## Command Overloading & Autocompletion

This fork includes a built-in **Command Overloading** and **Network Autocompletion** system, letting you define multiple type-safe signatures for a single command. The server automatically translates these signatures into client-side Bedrock protocol command hints (`AvailableCommandsPacket`), enabling native tab-completion with items, blocks, positions, players, and textures.

### Creating an Overloaded Command

To create a command with overloading support, extend `OverloadedCommand` and define your overloads in the constructor using closures and PHP 8 attributes:

```php
use pocketmine\command\OverloadedCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\command\overload\attribute\IntRange;

class MyGiveCommand extends OverloadedCommand {
    public function __construct() {
        parent::__construct("mygive", "Give items to players");
        
        // Overload signature: /mygive <player> <item> [amount]
        $this->addOverload(
            function(CommandSender $sender, Player $target, Item $item, #[IntRange(1, 64)] int $amount = 1): void {
                $target->getInventory()->addItem($item->setCount($amount));
                $sender->sendMessage("Gave items!");
            }
        );
    }
}
```

### Supported Argument Parsers & Types

The system automatically infers parsers based on the parameter types of your closures, or they can be annotated using PHP 8 attributes:

| Parameter Type / Attribute | Client Autocomplete UI | Parser |
|---|---|---|
| `Player` / `PlayerOrSelf` | Player selectors & names | `PlayerArgumentParser` |
| `Item` | Item Registry names (with icons) | `ItemArgumentParser` |
| `Vector3` | X Y Z coordinate fields (`~` coordinate support) | `Vector3ArgumentParser` |
| `bool` | True / False dropdown enum | `BoolArgumentParser` |
| `int` / `#[IntRange(min, max)]` | Numeric integers with range bounds | `IntegerArgumentParser` |
| `float` / `#[FloatRange(min, max)]` | Decimal values with range bounds | `FloatArgumentParser` |
| `string` / `#[EnumValues(...)]` | Custom static dropdown options | `StringArgumentParser` |
| `#[DynamicEnum(ProviderClass::class)]` | Dynamically calculated option enums | `DynamicEnumArgumentParser` |

## Developing Plugins
AmberPM maintains compatibility with the PocketMine-MP v5 API. Refer to the following resources:
* [PocketMine-MP Developer Documentation](https://devdoc.pmmp.io) - General documentation for plugin developers.
* [ExamplePlugin](https://github.com/pmmp/ExamplePlugin/) - Reference implementation demonstrating core API usage.
* [DevTools](https://github.com/pmmp/DevTools/) - Development tools plugin for packaging plugins.

## Need Help?
Contact this fork developers on [Discord](https://discord.gg/k55gScjTs3).
We will provide full support for this fork on that server!

## Licensing
This project is licensed under the GNU Lesser General Public License v3.0 (LGPL-3.0). Please see the [LICENSE](/LICENSE) file for complete details.

*AmberPM and PocketMine-MP are not affiliated with Mojang Studios or Microsoft. All trademarks belong to their respective owners.*

## This fork is currently maintained by:
* [vapebw](https://github.com/vapebw)
* [funaoo](https://github.com/funaoo)
