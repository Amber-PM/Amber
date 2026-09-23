<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\addon;

use pocketmine\addon\block\AddonBlock;
use pocketmine\addon\block\AddonBlockDefinition;
use pocketmine\addon\entity\AddonEntity;
use pocketmine\addon\entity\AddonEntityDefinition;
use pocketmine\addon\item\AddonArmorItem;
use pocketmine\addon\item\AddonDurableItem;
use pocketmine\addon\item\AddonFoodItem;
use pocketmine\addon\item\AddonItem;
use pocketmine\addon\item\AddonItemDefinition;
use pocketmine\block\Block;
use pocketmine\crafting\CraftingManager;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\inventory\CreativeGroup;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\player\Player;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use function array_values;
use function basename;
use function count;
use function explode;
use function file_get_contents;
use function hash_file;
use function hash_final;
use function hash_init;
use function hash_update;
use function in_array;
use function is_bool;
use function is_dir;
use function is_file;
use function is_int;
use function ksort;
use function mkdir;
use function scandir;
use function sha1_file;
use function sort;
use function str_replace;
use function strtolower;
use function usort;

/**
 * Loads Bedrock add-ons (.mcaddon, .mcpack, .zip or unpacked folders) from the server's addons/ directory.
 *
 *  - resource packs are handed to the ResourcePackManager, so players download them on join;
 *  - behavior pack items, blocks and entities become real server items, blocks and entities, and the client
 *    learns about them through the item registry, the StartGamePacket block palette and the actor identifier
 *    list - for every protocol the server accepts.
 *
 * What a behavior pack describes but the server does not run (scripts, entity AI, loot tables, recipes,
 * spawn rules) is reported when the add-on loads. Plugins can act on add-on content through the API below.
 */
final class AddonManager{
	/** First runtime ID for custom items. Kept well clear of vanilla IDs (~2100 as of 1.26). */
	private const ITEM_RUNTIME_ID_BASE = 10000;
	/** First legacy numeric ID for custom blocks; their block items use 255 - id, as the client does. */
	private const BLOCK_NUMERIC_ID_BASE = 10000;
	/** First runtime ID in the actor identifier list for custom entities. */
	private const ENTITY_RUNTIME_ID_BASE = 5000;

	private static ?self $instance = null;

	/** @var AddonPack[] */
	private array $packs = [];
	/** @var array<string, AddonItemDefinition> */
	private array $itemDefinitions = [];
	/** @var array<string, AddonBlockDefinition> */
	private array $blockDefinitions = [];
	/** @var array<string, AddonEntityDefinition> */
	private array $entityDefinitions = [];

	/** @var array<string, Item> identifier => registered prototype */
	private array $items = [];
	/** @var array<string, AddonBlock> identifier => default state */
	private array $blocks = [];

	/** @var array<string, int> */
	private array $itemRuntimeIds = [];
	/** @var array<string, int> */
	private array $blockNumericIds = [];
	/** @var array<string, CreativeGroup> */
	private array $creativeGroups = [];

	/** @var array<string, list<\Closure>> */
	private array $itemUseHandlers = [];
	/** @var list<array{0: string, 1: string}> source label and path of every behavior pack recipe file */
	private array $recipeFiles = [];
	private int $recipeCount = 0;
	/** @var array<string, list<\Closure>> */
	private array $blockInteractHandlers = [];

	/** @var list<ItemTypeEntry>|null */
	private ?array $itemTypeEntries = null;
	/** @var list<BlockStateData>|null */
	private ?array $networkBlockStates = null;

	public static function getInstance() : ?self{
		return self::$instance;
	}

	public function __construct(
		private Server $server,
		private string $path,
		private \Logger $logger
	){
		self::$instance = $this;
	}

	public function getPath() : string{ return $this->path; }

	/**
	 * Reads every add-on and registers its content. Must run before worlds load and before the first
	 * TypeConverter is created, so the item and block tables include add-on content.
	 */
	public function load() : void{
		if(!is_dir($this->path)){
			@mkdir($this->path, 0777, true);
			Filesystem::safeFilePutContents(Path::join($this->path, "README.txt"),
				"Drop Bedrock add-ons here: .mcaddon, .mcpack, .zip files or unpacked pack folders.\n" .
				"Resource packs are sent to players; behavior pack items, blocks and entities are registered on the server.\n" .
				"Restart the server after adding or removing an add-on.\n");
		}

		$entries = scandir($this->path);
		foreach($entries === false ? [] : $entries as $entry){
			if($entry === "." || $entry === ".." || $entry === ".cache" || $entry === "README.txt"){
				continue;
			}
			$full = Path::join($this->path, $entry);
			try{
				foreach($this->unpack($full, $entry) as $pack){
					$this->packs[] = $pack;
				}
			}catch(AddonException $e){
				$this->logger->error("Add-on $entry could not be loaded: " . $e->getMessage());
			}
		}

		foreach($this->packs as $pack){
			if($pack->isBehaviorPack()){
				$this->readBehaviorPack($pack);
			}
		}

		//register in identifier order so runtime IDs do not depend on file system order
		ksort($this->itemDefinitions);
		ksort($this->blockDefinitions);
		ksort($this->entityDefinitions);
		foreach($this->blockDefinitions as $definition){
			$this->tryRegister("block", $definition->getIdentifier(), fn() => $this->registerBlock($definition));
		}
		foreach($this->itemDefinitions as $definition){
			$this->tryRegister("item", $definition->getIdentifier(), fn() => $this->registerItem($definition));
		}
		$this->assignBlockIds();
		$this->registerEntities();
		$this->reportUnsentComponents();

		$resourcePacks = count(array_filter($this->packs, fn(AddonPack $p) => $p->isResourcePack()));
		if($this->packs !== []){
			$this->logger->info("Loaded " . count($this->packs) . " add-on pack(s): $resourcePacks resource, " .
				count($this->itemDefinitions) . " item(s), " . count($this->blocks) . " block(s), " . count($this->entityDefinitions) . " entit" . (count($this->entityDefinitions) === 1 ? "y" : "ies"));
		}
	}

	/**
	 * Adds the add-ons' resource packs to the server's resource pack stack. Packs already present (same
	 * UUID, e.g. also placed in resource_packs/) are left alone.
	 */
	public function registerResourcePacks(ResourcePackManager $manager) : void{
		$stack = $manager->getResourceStack();
		foreach($this->packs as $pack){
			if(!$pack->isResourcePack()){
				continue;
			}
			if($manager->getPackById($pack->getUuid()) !== null){
				$this->logger->debug("Resource pack " . $pack->getName() . " is already loaded, skipping the add-on copy");
				continue;
			}
			try{
				$zip = $this->zipPack($pack);
				$stack[] = new ZippedResourcePack($zip);
			}catch(\Throwable $e){
				$this->logger->error("Resource pack " . $pack->getName() . " from " . $pack->getSource() . " could not be loaded: " . $e->getMessage());
			}
		}
		$manager->setResourceStack($stack);
	}

	/**
	 * @return list<AddonPack>
	 * @throws AddonException
	 */
	private function unpack(string $path, string $source) : array{
		if(is_dir($path)){
			return $this->findPacks($path, $source);
		}
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if(!in_array($extension, ["mcaddon", "mcpack", "zip"], true)){
			return [];
		}
		$hash = sha1_file($path);
		if($hash === false){
			throw new AddonException("cannot read $path");
		}
		$target = Path::join($this->path, ".cache", "unpacked", $hash);
		if(!is_dir($target)){
			$this->extractZip($path, $target);
		}
		return $this->findPacks($target, $source);
	}

	/** @throws AddonException */
	private function extractZip(string $zipPath, string $target) : void{
		$zip = new \ZipArchive();
		if($zip->open($zipPath) !== true){
			throw new AddonException(basename($zipPath) . " is not a valid zip archive");
		}
		for($i = 0; $i < $zip->numFiles; ++$i){
			$name = $zip->getNameIndex($i);
			if($name === false || str_contains($name, "..") || str_starts_with($name, "/")){
				$zip->close();
				throw new AddonException(basename($zipPath) . " contains an unsafe path");
			}
		}
		@mkdir($target, 0777, true);
		if(!$zip->extractTo($target)){
			$zip->close();
			throw new AddonException("failed to extract " . basename($zipPath));
		}
		$zip->close();
	}

	/**
	 * Packs live wherever a manifest.json is: at the root, in sub-folders (an .mcaddon of folders), or as
	 * nested .mcpack archives (an .mcaddon of archives).
	 *
	 * @return list<AddonPack>
	 * @throws AddonException
	 */
	private function findPacks(string $dir, string $source, int $depth = 0) : array{
		if(is_file(Path::join($dir, "manifest.json"))){
			return [AddonPack::fromDirectory($dir, $source)];
		}
		if($depth > 3){
			return [];
		}
		$packs = [];
		$entries = scandir($dir);
		foreach($entries === false ? [] : $entries as $entry){
			if($entry === "." || $entry === ".." || $entry === "__MACOSX"){
				continue;
			}
			$full = Path::join($dir, $entry);
			if(is_dir($full)){
				//one broken pack must not take the rest of the add-on down with it
				try{
					foreach($this->findPacks($full, $source, $depth + 1) as $pack){
						$packs[] = $pack;
					}
				}catch(AddonException $e){
					$this->logger->error("Add-on $source: skipped $entry: " . $e->getMessage());
				}
			}elseif(in_array(strtolower(pathinfo($full, PATHINFO_EXTENSION)), ["mcpack", "zip"], true)){
				foreach($this->unpack($full, "$source/$entry") as $pack){
					$packs[] = $pack;
				}
			}
		}
		return $packs;
	}

	/** @throws AddonException */
	private function zipPack(AddonPack $pack) : string{
		$root = Path::canonicalize($pack->getPath());
		/** @var array<string, string> $files */
		$files = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach($iterator as $file){
			if($file instanceof \SplFileInfo && $file->isFile()){
				$pathname = $file->getPathname();
				$rel = str_replace('\\', '/', Path::makeRelative(Path::canonicalize($pathname), $root));
				$files[$rel] = $pathname;
			}
		}
		ksort($files);

		$ctx = hash_init("sha256");
		foreach($files as $rel => $pathname){
			$fileHash = hash_file("sha256", $pathname);
			hash_update($ctx, $rel . "\0" . $fileHash . "\0");
		}
		$fingerprint = hash_final($ctx);

		$safeVersion = $pack->getVersionString() === "" ? "0" : $pack->getVersionString();
		$cacheDir = Path::join($this->path, ".cache", "packs");
		$zipPath = Path::join($cacheDir, $pack->getUuid() . "_" . $safeVersion . "_" . $fingerprint . ".mcpack");
		if(!Path::isBasePath($cacheDir, $zipPath)){
			throw new AddonException("pack cache path escapes cache directory");
		}
		@mkdir($cacheDir, 0777, true);
		if(is_file($zipPath)){
			return $zipPath;
		}
		$zip = new \ZipArchive();
		if($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
			throw new AddonException("cannot create $zipPath");
		}
		foreach($files as $rel => $pathname){
			$zip->addFile($pathname, $rel);
		}
		$zip->close();
		return $zipPath;
	}

	private function readBehaviorPack(AddonPack $pack) : void{
		$unsupported = [];
		foreach(["scripts" => "scripts", "loot_tables" => "loot tables", "spawn_rules" => "spawn rules", "trading" => "trades", "functions" => "functions"] as $dir => $label){
			if(is_dir(Path::join($pack->getPath(), $dir))){
				$unsupported[] = $label;
			}
		}
		if($unsupported !== []){
			$this->logger->warning("Add-on " . $pack->getName() . ": " . implode(", ", $unsupported) . " are not run by the server (items, blocks and entities are)");
		}

		$vanillaOverrides = [];
		foreach($this->jsonFiles(Path::join($pack->getPath(), "recipes")) as $file){
			$this->recipeFiles[] = [$pack->getName() . "/" . Path::makeRelative($file, $pack->getPath()), $file];
		}
		foreach(["items" => "item", "blocks" => "block", "entities" => "entity"] as $dir => $kind){
			foreach($this->jsonFiles(Path::join($pack->getPath(), $dir)) as $file){
				$relative = $pack->getName() . "/" . Path::makeRelative($file, $pack->getPath());
				try{
					$json = AddonJson::decode((string) file_get_contents($file), $relative);
					match($kind){
						"item" => $this->addDefinition($this->itemDefinitions, AddonItemDefinition::fromJson($json, $relative, $pack->getName()), $relative),
						"block" => $this->addDefinition($this->blockDefinitions, AddonBlockDefinition::fromJson($json, $relative, $pack->getName()), $relative),
						"entity" => $this->addDefinition($this->entityDefinitions, AddonEntityDefinition::fromJson($json, $relative, $pack->getName()), $relative),
					};
				}catch(AddonException $e){
					if($e->getCode() === AddonException::VANILLA_OVERRIDE){
						$vanillaOverrides[] = basename($file, ".json");
						continue;
					}
					$this->logger->error("Skipped " . $e->getMessage());
				}
			}
		}
		if($vanillaOverrides !== []){
			//behavior packs commonly redefine vanilla mobs or the player; the server keeps its own
			$this->logger->warning("Add-on " . $pack->getName() . ": " . count($vanillaOverrides) . " vanilla override(s) not applied, the server's own are used (" . implode(", ", $vanillaOverrides) . ")");
		}
	}

	/**
	 * @param array<string, AddonItemDefinition|AddonBlockDefinition|AddonEntityDefinition> $table
	 */
	private function addDefinition(array &$table, AddonItemDefinition|AddonBlockDefinition|AddonEntityDefinition $definition, string $source) : void{
		$id = $definition->getIdentifier();
		if(isset($table[$id]) || isset($this->itemDefinitions[$id]) && !$definition instanceof AddonItemDefinition){
			$this->logger->warning("Skipped $source: $id is already defined by another add-on");
			return;
		}
		$table[$id] = $definition;
	}

	/** @return list<string> */
	private function jsonFiles(string $dir) : array{
		if(!is_dir($dir)){
			return [];
		}
		$files = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
		foreach($iterator as $file){
			if($file instanceof \SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === "json"){
				$files[] = $file->getPathname();
			}
		}
		sort($files);
		return $files;
	}

	private function tryRegister(string $kind, string $identifier, \Closure $register) : void{
		try{
			$register();
		}catch(\Throwable $e){
			$this->logger->error("Add-on $kind $identifier could not be registered: " . $e->getMessage());
			$this->logger->logException($e);
		}
	}

	private function registerItem(AddonItemDefinition $definition) : void{
		$id = $definition->getIdentifier();
		if(isset($this->blockDefinitions[$id])){
			throw new AddonException("an add-on block already uses this identifier");
		}
		$identifier = new ItemIdentifier(ItemTypeIds::newId());
		$item = match($definition->getKind()){
			AddonItemDefinition::KIND_ARMOR => new AddonArmorItem($identifier, $definition),
			AddonItemDefinition::KIND_FOOD => new AddonFoodItem($identifier, $definition),
			AddonItemDefinition::KIND_TOOL => new AddonDurableItem($identifier, $definition),
			default => new AddonItem($identifier, $definition),
		};

		GlobalItemDataHandlers::getSerializer()->map($item, static fn() => new SavedItemData($id));
		GlobalItemDataHandlers::getDeserializer()->map($id, static fn() => clone $item);
		$this->registerAliases($id, static fn() => clone $item);

		$this->itemRuntimeIds[$id] = self::ITEM_RUNTIME_ID_BASE + count($this->itemRuntimeIds);
		$this->items[$id] = $item;
		if(!$definition->isHiddenInCommands()){
			CreativeInventory::getInstance()->add($item, $definition->getCategory(), $this->creativeGroup($definition->getGroup(), $item));
		}
	}

	private function registerBlock(AddonBlockDefinition $definition) : void{
		$id = $definition->getIdentifier();
		$block = new AddonBlock(
			new BlockIdentifier(BlockTypeIds::newId()),
			new BlockTypeInfo(new BlockBreakInfo($definition->getHardness(), BlockToolType::NONE, 0, $definition->getBlastResistance())),
			$definition
		);
		RuntimeBlockStateRegistry::getInstance()->register($block);

		$stateNames = $definition->getStates();
		GlobalBlockStateHandlers::getSerializer()->map($block, static function(AddonBlock $block) use ($id) : BlockStateWriter{
			$writer = BlockStateWriter::create($id);
			foreach($block->getStateValues() as $name => $value){
				match(true){
					is_bool($value) => $writer->writeBool($name, $value),
					is_int($value) => $writer->writeInt($name, $value),
					default => $writer->writeString($name, $value),
				};
			}
			return $writer;
		});
		$permutations = $definition->getPermutationValues();
		GlobalBlockStateHandlers::getDeserializer()->map($id, static function(BlockStateReader $reader) use ($block, $stateNames, $permutations) : AddonBlock{
			$values = [];
			foreach($stateNames as $name => $allowed){
				$first = $allowed[0];
				$values[$name] = match(true){
					is_bool($first) => $reader->readBool($name),
					is_int($first) => $reader->readInt($name),
					default => $reader->readString($name),
				};
			}
			$index = array_search($values, $permutations, true);
			return (clone $block)->setStateIndex($index === false ? 0 : $index);
		});

		BlockItemIdMap::getInstance()->register($id, $id);
		$this->registerAliases($id, static fn() => $block->asItem());

		$this->blocks[$id] = $block;
		CreativeInventory::getInstance()->add($block->asItem(), $definition->getCategory(), $this->creativeGroup($definition->getGroup(), $block->asItem()));
	}

	private function registerEntities() : void{
		if($this->entityDefinitions === []){
			return;
		}
		$definitions = $this->entityDefinitions;
		EntityFactory::getInstance()->register(AddonEntity::class, static function(World $world, CompoundTag $nbt) use ($definitions) : AddonEntity{
			$definition = $definitions[$nbt->getString(AddonEntity::TAG_IDENTIFIER, "")] ?? null;
			if($definition === null){
				//the add-on was removed: load it as a placeholder that removes itself
				$definition = AddonEntityDefinition::fromJson(["minecraft:entity" => ["description" => ["identifier" => "amber:missing_addon_entity"]]], "missing", "missing");
				$entity = new AddonEntity(EntityDataHelper::parseLocation($nbt, $world), $definition, $nbt);
				$entity->flagForDespawn();
				return $entity;
			}
			return new AddonEntity(EntityDataHelper::parseLocation($nbt, $world), $definition, $nbt);
		}, [AddonEntity::SAVE_ID]);
	}

	/** Registers "namespace:name" and, if nothing else uses it, the bare "name" with /give and friends. */
	private function registerAliases(string $identifier, \Closure $factory) : void{
		$parser = StringToItemParser::getInstance();
		$parser->register($identifier, $factory);
		$short = explode(":", $identifier, 2)[1];
		if($parser->parse($short) === null){
			$parser->register($short, $factory);
		}
	}

	private function creativeGroup(?string $name, Item $icon) : ?CreativeGroup{
		if($name === null){
			return null;
		}
		return $this->creativeGroups[$name] ??= new CreativeGroup($name, $icon);
	}

	/**
	 * Item registry entries for add-on items and add-on block items, appended to every protocol's
	 * ItemTypeDictionary.
	 *
	 * @return list<ItemTypeEntry>
	 */
	public function getItemTypeEntries() : array{
		if($this->itemTypeEntries !== null){
			return $this->itemTypeEntries;
		}
		$entries = [];
		foreach($this->items as $id => $item){
			$runtimeId = $this->itemRuntimeIds[$id];
			$entries[] = new ItemTypeEntry($id, $runtimeId, true, 1, new CacheableNbt($this->itemDefinitions[$id]->buildNetworkNbt($runtimeId)));
		}
		foreach($this->blocks as $id => $block){
			$entries[] = new ItemTypeEntry($id, 255 - $this->blockNumericIds[$id], false, 2, new CacheableNbt(CompoundTag::create()));
		}
		return $this->itemTypeEntries = $entries;
	}

	/**
	 * The component-based entries only: what pre-1.21.60 clients need in ItemComponentPacket.
	 *
	 * @return list<ItemTypeEntry>
	 */
	public function getComponentItemTypeEntries() : array{
		return array_values(array_filter($this->getItemTypeEntries(), fn(ItemTypeEntry $e) => $e->isComponentBased()));
	}

	/**
	 * StartGamePacket block palette entries describing every add-on block.
	 *
	 * @return list<BlockPaletteEntry>
	 */
	public function getBlockPaletteEntries(int $protocolId) : array{
		if(isset($this->paletteEntries[$protocolId])){
			return $this->paletteEntries[$protocolId];
		}
		$entries = [];
		//in block id order, which is the client's order (see assignBlockIds())
		foreach($this->blockNumericIds as $id => $numericId){
			$entries[] = new BlockPaletteEntry($id, new CacheableNbt($this->blocks[$id]->getAddonDefinition()->buildPaletteNbt($numericId, $protocolId)));
		}
		return $this->paletteEntries[$protocolId] = $entries;
	}

	/** @var array<int, list<BlockPaletteEntry>> protocol => palette entries */
	private array $paletteEntries = [];

	/**
	 * Numbers the registered blocks the way the client does: since 1.20.60 every custom block's "block_id" is
	 * 10000 plus its index among all custom blocks sorted by the FNV-1 64 hash of the name. A block numbered in
	 * any other order shows as a different block on the client once there are two or more.
	 */
	private function assignBlockIds() : void{
		$names = array_keys($this->blocks);
		usort($names, static fn(string $a, string $b) : int => strcmp(BlockNetworkHash::nameOrderKey($a), BlockNetworkHash::nameOrderKey($b)));
		$this->blockNumericIds = [];
		foreach($names as $i => $name){
			$this->blockNumericIds[$name] = self::BLOCK_NUMERIC_ID_BASE + $i;
		}
	}

	/**
	 * Registers the add-ons' recipes. Runs once the crafting manager exists, after every add-on item and block is
	 * registered, so recipes can use items from any add-on.
	 */
	public function registerRecipes(CraftingManager $manager) : void{
		if($this->recipeFiles === []){
			return;
		}
		$recipes = new AddonRecipes($manager);
		foreach($this->recipeFiles as [$source, $file]){
			try{
				$recipes->register(AddonJson::decode((string) file_get_contents($file), $source), $source);
			}catch(AddonException $e){
				$this->logger->error("Skipped recipe " . $e->getMessage());
			}
		}
		foreach($recipes->getErrors() as $error){
			$this->logger->warning("Skipped recipe $error");
		}
		$this->recipeCount = $recipes->getRegisteredCount();
		$this->logger->info("Registered " . $recipes->getRegisteredCount() . " add-on recipe(s)" . ($recipes->getUnsupportedCount() > 0
			? ", skipped " . $recipes->getUnsupportedCount() . " of a type the server does not support (brewing, smithing transforms, other stations)" : ""));
	}

	public function getRecipeCount() : int{ return $this->recipeCount; }

	/** Warns once per pack about components the client is not sent because the loader does not know them. */
	private function reportUnsentComponents() : void{
		$byPack = [];
		foreach($this->itemDefinitions as $id => $definition){
			if(isset($this->items[$id])){
				foreach($definition->getUnknownComponents() as $name){
					$byPack[$definition->getPackName()][$name] = true;
				}
			}
		}
		foreach($this->blockDefinitions as $id => $definition){
			if(isset($this->blocks[$id])){
				foreach($definition->getUnknownComponents() as $name){
					$byPack[$definition->getPackName()][$name] = true;
				}
			}
		}
		foreach($byPack as $pack => $names){
			$this->logger->warning("Add-on $pack: component(s) not sent to clients, the loader does not support them yet: " . implode(", ", array_keys($names)));
		}
	}

	/**
	 * Every state of every add-on block, as network block state data, in palette order.
	 *
	 * @return list<BlockStateData>
	 */
	public function getNetworkBlockStates() : array{
		if($this->networkBlockStates !== null){
			return $this->networkBlockStates;
		}
		$states = [];
		foreach($this->blocks as $id => $block){
			foreach($block->getAddonDefinition()->getPermutationValues() as $values){
				$tags = [];
				foreach($values as $name => $value){
					$tags[$name] = match(true){
						is_bool($value) => new ByteTag($value ? 1 : 0),
						is_int($value) => new IntTag($value),
						default => new StringTag($value),
					};
				}
				$states[] = BlockStateData::current($id, $tags);
			}
		}
		return $this->networkBlockStates = $states;
	}

	/**
	 * AvailableActorIdentifiersPacket entries for add-on entities.
	 *
	 * @return list<CompoundTag>
	 */
	public function getActorIdentifierEntries() : array{
		$entries = [];
		$i = 0;
		foreach($this->entityDefinitions as $definition){
			$entries[] = $definition->buildIdentifierNbt(self::ENTITY_RUNTIME_ID_BASE + $i++);
		}
		return $entries;
	}

	/** @return AddonPack[] */
	public function getPacks() : array{ return $this->packs; }

	/** @return array<string, AddonItemDefinition> */
	public function getItemDefinitions() : array{ return $this->itemDefinitions; }

	/** @return array<string, AddonBlockDefinition> */
	public function getBlockDefinitions() : array{ return $this->blockDefinitions; }

	/** @return array<string, AddonEntityDefinition> */
	public function getEntityDefinitions() : array{ return $this->entityDefinitions; }

	/** A new stack of an add-on item or add-on block, or null if there is no such identifier. */
	public function getItem(string $identifier, int $count = 1) : ?Item{
		$item = isset($this->items[$identifier]) ? clone $this->items[$identifier] : (isset($this->blocks[$identifier]) ? $this->blocks[$identifier]->asItem() : null);
		return $item?->setCount($count);
	}

	/** The default state of an add-on block, or null. */
	public function getBlock(string $identifier) : ?AddonBlock{
		return isset($this->blocks[$identifier]) ? clone $this->blocks[$identifier] : null;
	}

	/** Spawns an add-on entity (it is not spawned to players yet - call spawnToAll()). */
	public function createEntity(string $identifier, Location $location) : ?AddonEntity{
		$definition = $this->entityDefinitions[$identifier] ?? null;
		return $definition === null ? null : new AddonEntity($location, $definition);
	}

	/**
	 * Runs $handler when a player right-clicks with the item (in the air or on a block).
	 * Return true from the handler to cancel the item's default action.
	 *
	 * @phpstan-param \Closure(Player $player, Item $item, ?Block $clickedBlock) : bool $handler
	 */
	public function onItemUse(string $identifier, \Closure $handler) : void{
		$this->itemUseHandlers[$identifier][] = $handler;
	}

	/**
	 * Runs $handler when a player right-clicks the block. Return true to cancel the default interaction.
	 *
	 * @phpstan-param \Closure(Player $player, AddonBlock $block, Item $heldItem) : bool $handler
	 */
	public function onBlockInteract(string $identifier, \Closure $handler) : void{
		$this->blockInteractHandlers[$identifier][] = $handler;
	}

	/** @internal */
	public function dispatchItemUse(Player $player, Item $item, ?Block $clickedBlock) : bool{
		$identifier = $item instanceof AddonItem || $item instanceof AddonDurableItem || $item instanceof AddonFoodItem || $item instanceof AddonArmorItem ? $item->getAddonIdentifier() : null;
		$handled = false;
		foreach($identifier === null ? [] : ($this->itemUseHandlers[$identifier] ?? []) as $handler){
			$handled = (bool) $handler($player, $item, $clickedBlock) || $handled;
		}
		return $handled;
	}

	/** @internal */
	public function dispatchBlockInteract(Player $player, AddonBlock $block, Item $item) : bool{
		$handled = false;
		foreach($this->blockInteractHandlers[$block->getAddonIdentifier()] ?? [] as $handler){
			$handled = (bool) $handler($player, $block, $item) || $handled;
		}
		return $handled;
	}
}
