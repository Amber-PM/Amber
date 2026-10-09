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

namespace pocketmine\world\backup;

use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\leveldb\LevelDB;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use function array_slice;
use function basename;
use function copy;
use function count;
use function date;
use function glob;
use function in_array;
use function is_dir;
use function link;
use function mkdir;
use function rsort;
use function str_ends_with;
use function strtolower;
use function time;
use function unlink;

/**
 * Automatic world backups: zip files of each world in backups/<world>/, the oldest deleted beyond a set count.
 *
 * A backup saves the world, then takes a snapshot of its folder on the main thread: LevelDB's table files never
 * change once written, so they are hard-linked (instant); every other file is copied. The snapshot is then checked
 * (LevelDB worlds are opened to make sure the copy is consistent) and zipped by an async worker.
 */
final class WorldBackupManager{
	private const STAGING_DIR = ".staging";
	private const MAX_ATTEMPTS = 3;

	/** @var array<string, true> worlds with a backup in progress */
	private array $running = [];
	private int $nextScheduled;

	/**
	 * @param string[] $worlds world folder names to back up automatically; empty for every loaded world
	 */
	public function __construct(
		private Server $server,
		private string $backupPath,
		private bool $scheduled,
		private int $intervalSeconds,
		private int $keep,
		private array $worlds
	){
		$this->nextScheduled = time() + $this->intervalSeconds;
	}

	public function getBackupPath() : string{ return $this->backupPath; }

	/** Called once a second. */
	public function tick() : void{
		if(!$this->scheduled || time() < $this->nextScheduled){
			return;
		}
		$this->nextScheduled = time() + $this->intervalSeconds;
		foreach($this->server->getWorldManager()->getWorlds() as $world){
			if($this->worlds === [] || in_array($world->getFolderName(), $this->worlds, true)){
				$this->backup($world);
			}
		}
	}

	/**
	 * Starts a backup of the world. The callback gets the zip file's path, or null and the reason it failed.
	 *
	 * @phpstan-param (\Closure(?string $file, ?string $error) : void)|null $onDone
	 * @return bool false if a backup of this world is already running
	 */
	public function backup(World $world, ?\Closure $onDone = null) : bool{
		$name = $world->getFolderName();
		if(isset($this->running[$name])){
			return false;
		}
		$this->running[$name] = true;
		$this->attempt($world, 1, $onDone);
		return true;
	}

	/**
	 * @phpstan-param (\Closure(?string $file, ?string $error) : void)|null $onDone
	 */
	private function attempt(World $world, int $attempt, ?\Closure $onDone) : void{
		$name = $world->getFolderName();
		$logger = $this->server->getLogger();
		$stamp = date("Y-m-d_H-i-s");
		$staging = Path::join($this->backupPath, self::STAGING_DIR, $name . "-" . $stamp . "-" . $attempt);
		try{
			$world->save(true);
			$this->snapshot($world->getProvider()->getPath(), $staging);
		}catch(\Throwable $e){
			if(is_dir($staging)){
				Filesystem::recursiveUnlink($staging);
			}
			unset($this->running[$name]);
			$logger->error("Backup of world \"$name\" failed: " . $e->getMessage());
			if($onDone !== null){
				$onDone(null, $e->getMessage());
			}
			return;
		}

		$target = Path::join($this->backupPath, $name, $name . "-" . $stamp . ".zip");
		$isLevelDB = $world->getProvider() instanceof LevelDB;
		$this->server->getAsyncPool()->submitTask(new WorldBackupTask($staging, $target, $isLevelDB, function(?string $error) use ($world, $name, $target, $attempt, $onDone, $logger) : void{
			if($error !== null && $attempt < self::MAX_ATTEMPTS && $world->isLoaded()){
				//most likely LevelDB compacted its files while the snapshot was taken; take another
				$logger->debug("Backup of world \"$name\" was not consistent ($error), retrying");
				$this->attempt($world, $attempt + 1, $onDone);
				return;
			}
			unset($this->running[$name]);
			if($error !== null){
				$logger->error("Backup of world \"$name\" failed: $error");
			}else{
				$logger->info("Backed up world \"$name\" to " . Path::makeRelative($target, $this->server->getDataPath()));
				$this->rotate($name);
			}
			if($onDone !== null){
				$onDone($error === null ? $target : null, $error);
			}
		}));
	}

	/**
	 * Mirrors the world folder into $target: LevelDB table files hard-linked (copied if linking is not possible),
	 * everything else copied. Must run on the main thread, straight after the world is saved.
	 */
	private function snapshot(string $source, string $target) : void{
		if(!is_dir($source)){
			throw new \RuntimeException("World folder $source not found");
		}
		if(!@mkdir($target, 0777, true) && !is_dir($target)){
			throw new \RuntimeException("Cannot create $target");
		}
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
		foreach($iterator as $file){
			if(!$file instanceof \SplFileInfo){
				continue;
			}
			$destination = Path::join($target, Path::makeRelative($file->getPathname(), $source));
			if($file->isDir()){
				if(!@mkdir($destination, 0777, true) && !is_dir($destination)){
					throw new \RuntimeException("Cannot create $destination");
				}
				continue;
			}
			$fileName = $file->getFilename();
			if($fileName === "LOCK"){
				continue; //held by the open database; LevelDB creates its own
			}
			$lower = strtolower($fileName);
			$immutable = str_ends_with($lower, ".ldb") || str_ends_with($lower, ".sst");
			if(($immutable && @link($file->getPathname(), $destination)) || @copy($file->getPathname(), $destination)){
				continue;
			}
			if($immutable && !$file->isFile()){
				continue; //deleted by a compaction meanwhile: the consistency check decides
			}
			throw new \RuntimeException("Cannot copy " . $file->getPathname());
		}
	}

	/** Deletes the oldest backups of the world beyond the number to keep. */
	private function rotate(string $world) : void{
		if($this->keep <= 0){
			return;
		}
		$files = glob(Path::join($this->backupPath, $world, "*.zip"));
		if($files === false || count($files) <= $this->keep){
			return;
		}
		rsort($files); //names end with a sortable timestamp: newest first
		foreach(array_slice($files, $this->keep) as $old){
			@unlink($old);
			$this->server->getLogger()->debug("Deleted old backup " . basename($old));
		}
	}
}
