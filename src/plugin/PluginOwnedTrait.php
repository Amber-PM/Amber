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

namespace pocketmine\plugin;

/**
 * @see PluginOwned
 */
trait PluginOwnedTrait{
	/** @var Plugin */
	private $owningPlugin;

	public function __construct(Plugin $owningPlugin){
		$this->owningPlugin = $owningPlugin;
	}

	public function getOwningPlugin() : Plugin{
		return $this->owningPlugin;
	}
}
