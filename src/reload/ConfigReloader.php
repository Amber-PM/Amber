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

use pocketmine\Server;

/**
 * Re-reads the server's configuration files. Settings read when they are used (MOTD, maximum players, whitelist,
 * view distance, accepted protocols...) apply at once; those read at startup (ports, worlds, web panel...) do not.
 */
final class ConfigReloader{

	public function __construct(private Server $server){}

	public function reload() : ReloadReport{
		$report = new ReloadReport();
		try{
			$this->server->reloadConfiguration();
		}catch(\Throwable $e){
			$report->error("Configuration could not be reloaded: " . $e->getMessage());
			return $report;
		}
		$report->applied("Reloaded server.properties, pocketmine.yml, operators, whitelist and bans");
		$report->needsRestart("Settings read at startup (ports, worlds, web panel, generator settings...) apply on the next restart");
		return $report;
	}
}
