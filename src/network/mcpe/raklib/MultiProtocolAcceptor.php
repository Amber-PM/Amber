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
 * @author PocketMineMV Team
 * @link https://github.com/vapebw/PocketmineMV
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\raklib;

use raklib\server\ProtocolAcceptor;
use function in_array;

final class MultiProtocolAcceptor implements ProtocolAcceptor{
	public const ACCEPTED_RAKNET_PROTOCOLS = [9, 10, 11];

	/**
	 * @param int[] $acceptedProtocols
	 */
	public function __construct(
		private int $primaryVersion = 11,
		private array $acceptedProtocols = self::ACCEPTED_RAKNET_PROTOCOLS
	){}

	public function accepts(int $protocolVersion) : bool{
		return in_array($protocolVersion, $this->acceptedProtocols, true);
	}

	public function getPrimaryVersion() : int{
		return $this->primaryVersion;
	}
}
