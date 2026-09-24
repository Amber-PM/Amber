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

namespace pocketmine\addon\world;

use Symfony\Component\Filesystem\Path;
use function array_key_exists;
use function array_keys;
use function is_dir;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * The structures behavior packs ship (structures/**.mcstructure), by the names the game uses:
 * "namespace:name" for structures/namespace/name.mcstructure, "mystructure:name" (or just "name") for files
 * at the top of the folder. Files are parsed when first used.
 */
final class AddonStructures{
	/** @var array<string, string> name => file */
	private array $files = [];
	/** @var array<string, AddonStructure|null> */
	private array $loaded = [];

	/** @param list<string> $packRoots */
	public function __construct(array $packRoots, private \Logger $logger){
		foreach($packRoots as $root){
			$dir = Path::join($root, "structures");
			if(!is_dir($dir)){
				continue;
			}
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
			foreach($iterator as $file){
				if(!$file instanceof \SplFileInfo || strtolower($file->getExtension()) !== "mcstructure"){
					continue;
				}
				$relative = str_replace("\\", "/", substr($file->getPathname(), strlen($dir) + 1, -strlen(".mcstructure")));
				$slash = strpos($relative, "/");
				if($slash === false){
					$this->files["mystructure:" . $relative] ??= $file->getPathname();
					$this->files[$relative] ??= $file->getPathname();
				}else{
					$this->files[substr($relative, 0, $slash) . ":" . substr($relative, $slash + 1)] ??= $file->getPathname();
				}
			}
		}
	}

	/** @return list<string> */
	public function getNames() : array{
		return array_keys($this->files);
	}

	public function get(string $name) : ?AddonStructure{
		if(!isset($this->files[$name])){
			return null;
		}
		if(!array_key_exists($name, $this->loaded)){
			try{
				$this->loaded[$name] = AddonStructure::load($name, $this->files[$name]);
			}catch(\Throwable $e){
				$this->logger->warning("[Addons] structure $name could not be read: " . $e->getMessage());
				$this->loaded[$name] = null;
			}
		}
		return $this->loaded[$name];
	}
}
