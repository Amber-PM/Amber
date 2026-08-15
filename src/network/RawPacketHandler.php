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

namespace pocketmine\network;

interface RawPacketHandler{

	/**
	 * Returns a preg_match() compatible regex pattern used to filter packets on this handler. Only packets matching
	 * this pattern will be delivered to the handler.
	 */
	public function getPattern() : string;

	/**
	 * @throws PacketHandlingException
	 */
	public function handle(AdvancedNetworkInterface $interface, string $address, int $port, string $packet) : bool;
}
