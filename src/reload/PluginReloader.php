<?php

/*
 *
 *     _             _               
 *    / \   _ __ ___ | |__   ___ _ __ 
 *   / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 *  / ___ \| | | | | | |_) |  __/ |   
 * /_/   \_\_| |_| |_|_.__/ \___|_|   
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */


declare(strict_types=1);

namespace pocketmine\reload;

use pocketmine\plugin\Plugin;
use pocketmine\Server;
use Symfony\Component\Filesystem\Path;
use function count;
use function hash;
use function hash_file;
use function is_dir;
use function is_file;
use function ksort;
use function scandir;

/**
 * Brings the plugins folder's changes into the running server: plugins added to it are loaded and enabled, plugins
 * removed from it are disabled. A loaded plugin's code cannot be replaced while PHP runs, so plugins whose files
 * changed are reported as needing a restart.
 */
final class PluginReloader{
	/** @var array<string, array{string, string}> plugin name => file, fingerprint of the files the server runs */
	private array $loaded;

	public function __construct(private Server $server){
		$this->loaded = $this->scan();
	}

	public function reload() : ReloadReport{
		$report = new ReloadReport();
		$manager = $this->server->getPluginManager();
		$current = $this->scan();

		foreach($current as $name => [$file, $fingerprint]){
			$plugin = $manager->getPlugin($name);
			if($plugin === null){
				$loaded = $manager->loadPlugins($file);
				if(count($loaded) === 0){
					$report->error("Plugin $name could not be loaded, see the console");
					continue;
				}
				foreach($loaded as $newPlugin){
					if(!$manager->enablePlugin($newPlugin)){
						$report->error("Plugin " . $newPlugin->getName() . " could not be enabled, see the console");
						continue;
					}
					$report->applied("Loaded new plugin " . $newPlugin->getDescription()->getFullName());
				}
				$this->loaded[$name] = [$file, $fingerprint];
			}elseif(isset($this->loaded[$name]) && $this->loaded[$name][1] !== $fingerprint){
				$report->needsRestart("Plugin $name changed on disk; its new code runs after a restart");
			}
		}

		foreach($this->loaded as $name => [$file]){
			if(isset($current[$name])){
				continue;
			}
			$plugin = $manager->getPlugin($name);
			if($plugin !== null && $plugin->isEnabled()){
				$manager->disablePlugin($plugin);
				$report->applied("Disabled plugin $name, which was removed from the plugins folder");
			}
			unset($this->loaded[$name]);
		}

		if(count($report->getApplied()) > 0){
			foreach($this->server->getOnlinePlayers() as $player){
				$player->getNetworkSession()->syncAvailableCommands();
			}
		}else{
			$report->applied("No plugins were added or removed");
		}
		return $report;
	}

	/**
	 * Disables and enables the plugin again, so it re-reads its configuration. Its code is not reloaded, and a plugin
	 * that keeps state about online players may not expect this.
	 */
	public function restartPlugin(Plugin $plugin) : ReloadReport{
		$report = new ReloadReport();
		$manager = $this->server->getPluginManager();
		$manager->disablePlugin($plugin);
		if($manager->enablePlugin($plugin)){
			$report->applied("Re-enabled " . $plugin->getDescription()->getFullName() . " (its configuration was read again; its code was not reloaded)");
		}else{
			$report->error("Plugin " . $plugin->getName() . " could not be enabled again, see the console");
		}
		foreach($this->server->getOnlinePlayers() as $player){
			$player->getNetworkSession()->syncAvailableCommands();
		}
		return $report;
	}

	/**
	 * @return array<string, array{string, string}> plugin name => file, fingerprint
	 */
	private function scan() : array{
		$manager = $this->server->getPluginManager();
		$folder = $this->server->getPluginPath();
		$entries = is_dir($folder) ? scandir($folder) : false;
		$plugins = [];
		foreach($entries === false ? [] : $entries as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$path = Path::join($folder, $entry);
			$loader = $manager->getLoaderFor($path);
			if($loader === null){
				continue;
			}
			try{
				$description = $loader->getPluginDescription($path);
			}catch(\Throwable){
				continue;
			}
			if($description !== null){
				$plugins[$description->getName()] = [$path, self::fingerprint($path)];
			}
		}
		return $plugins;
	}

	private static function fingerprint(string $path) : string{
		if(is_file($path)){
			return (string) hash_file("xxh128", $path);
		}
		$files = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
		/** @var \SplFileInfo $file */
		foreach($iterator as $file){
			if($file->isFile()){
				$files[Path::makeRelative($file->getPathname(), $path)] = (string) hash_file("xxh128", $file->getPathname());
			}
		}
		ksort($files);
		$joined = "";
		foreach($files as $name => $fileHash){
			$joined .= $name . ":" . $fileHash . "\n";
		}
		return hash("xxh128", $joined);
	}
}
