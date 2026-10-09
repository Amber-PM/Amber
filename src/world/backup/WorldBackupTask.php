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

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function dirname;
use function is_dir;
use function is_string;
use function mkdir;
use function rename;
use function unlink;
use const LEVELDB_ZLIB_RAW_COMPRESSION;

/**
 * Checks a world snapshot and zips it (see WorldBackupManager). The snapshot folder is deleted either way.
 */
final class WorldBackupTask extends AsyncTask{
	private const TLS_KEY_CALLBACK = "callback";

	/**
	 * @phpstan-param \Closure(?string $error) : void $onCompletion
	 */
	public function __construct(
		private string $staging,
		private string $target,
		private bool $isLevelDB,
		\Closure $onCompletion
	){
		$this->storeLocal(self::TLS_KEY_CALLBACK, $onCompletion);
	}

	public function onRun() : void{
		try{
			if($this->isLevelDB){
				self::checkLevelDB(Path::join($this->staging, "db"));
			}
			$this->zip();
			$this->setResult(null);
		}catch(\Throwable $e){
			$this->setResult($e->getMessage());
		}finally{
			if(is_dir($this->staging)){
				Filesystem::recursiveUnlink($this->staging);
			}
		}
	}

	/**
	 * Opens the copied database and reads every entry: LevelDB refuses to open or read a copy that is missing
	 * files, which is how a compaction during the snapshot shows up.
	 */
	private static function checkLevelDB(string $path) : void{
		$db = new \LevelDB($path, [
			"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
			"block_size" => 64 * 1024,
			"paranoid_checks" => true,
		], ["verify_check_sum" => true]);
		foreach($db->getIterator() as $_){
			//reading every block verifies checksums and that every referenced table file exists
		}
		unset($db);
	}

	private function zip() : void{
		$directory = dirname($this->target);
		if(!@mkdir($directory, 0777, true) && !is_dir($directory)){
			throw new \RuntimeException("Cannot create $directory");
		}
		$temporary = $this->target . ".tmp";
		$zip = new \ZipArchive();
		if($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
			throw new \RuntimeException("Cannot create $temporary");
		}
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->staging, \FilesystemIterator::SKIP_DOTS));
		foreach($iterator as $file){
			if($file instanceof \SplFileInfo && $file->isFile() && $file->getFilename() !== "LOCK"){
				$zip->addFile($file->getPathname(), Path::makeRelative($file->getPathname(), $this->staging));
			}
		}
		if(!$zip->close()){
			@unlink($temporary);
			throw new \RuntimeException("Cannot write $temporary");
		}
		@unlink($this->target);
		if(!@rename($temporary, $this->target)){
			@unlink($temporary);
			throw new \RuntimeException("Cannot move backup to " . $this->target);
		}
	}

	public function onCompletion() : void{
		/** @phpstan-var \Closure(?string $error) : void $callback */
		$callback = $this->fetchLocal(self::TLS_KEY_CALLBACK);
		$result = $this->getResult();
		$callback(is_string($result) ? $result : null);
	}
}
