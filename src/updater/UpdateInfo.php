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

namespace pocketmine\updater;

/**
 * Model class for JsonMapper to represent the information returned from the updater API.
 * @link https://update.pmmp.io/api
 */
final class UpdateInfo{
	/** @required */
	public string $php_version;
	/** @required */
	public string $base_version;
	/** @required */
	public bool $is_dev;
	/** @required */
	public string $channel;
	/** @required */
	public string $git_commit;
	/** @required */
	public string $mcpe_version;
	/** @required */
	public int $build;
	/** @required */
	public int $date;
	/** @required */
	public string $details_url;
	/** @required */
	public string $download_url;
	/** @required */
	public string $source_url;
}
