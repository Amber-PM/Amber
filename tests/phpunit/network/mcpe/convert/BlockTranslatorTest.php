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

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class BlockTranslatorTest extends TestCase{

	/**
	 * @doesNotPerformAssertions
	 */
	public function testAllBlockStatesSerialize() : void{
		$blockTranslator = TypeConverter::getInstance()->getBlockTranslator();
		foreach(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $state){
			$blockTranslator->internalIdToNetworkId($state->getStateId());
		}
	}

	/**
	 * Blocks an older client lacks are sent as a similar block it has, never as the "update!" block.
	 */
	public function testEveryStateHasAStandInOnEveryProtocol() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			$blockTranslator = TypeConverter::getInstance($protocolId)->getBlockTranslator();
			$fallback = $blockTranslator->getBlockStateDictionary()->lookupStateIdFromData($blockTranslator->getFallbackStateData());
			foreach(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $stateId => $state){
				if($state->getTypeId() !== BlockTypeIds::INFO_UPDATE){
					self::assertNotSame($fallback, $blockTranslator->internalIdToNetworkId($stateId), $state->getName() . " is sent as an update block to protocol $protocolId");
				}
			}
		}
	}
}
