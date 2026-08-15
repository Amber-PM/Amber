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

namespace pocketmine\world\particle;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\ParticleIds;

class TerrainParticle extends ProtocolParticle{ // BlockParticle
	public function __construct(protected Block $b){}

	public function encode(Vector3 $pos) : array{
		$runtimeId = TypeConverter::getInstance($this->protocolId)->getBlockTranslator()->internalIdToNetworkId($this->b->getStateId());
		return [LevelEventPacket::standardParticle(ParticleIds::TERRAIN, $runtimeId, $pos, $this->protocolId)];
	}
}
