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

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function count;
use function in_array;

final class TypeConverterInstanceSharingTest extends TestCase{

	public function testGetInstanceReturnsTheSameObjectForTheSameProtocol() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			self::assertSame(TypeConverter::getInstance($protocolId), TypeConverter::getInstance($protocolId), "Protocol $protocolId");
			self::assertSame($protocolId, TypeConverter::getInstance($protocolId)->getProtocolId());
		}
	}

	public function testProtocolsWithTheSameDataFilesShareTranslatorsAndItemDictionaries() : void{
		$a = TypeConverter::getInstance(ProtocolInfo::PROTOCOL_1_26_50);
		$b = TypeConverter::getInstance(ProtocolInfo::PROTOCOL_1_26_51);

		self::assertNotSame($a, $b);
		self::assertSame($a->getBlockTranslator(), $b->getBlockTranslator());
		self::assertSame($a->getItemTypeDictionary(), $b->getItemTypeDictionary());
	}

	public function testChunkProtocolIdIsAnAcceptedProtocolAndAFixedPoint() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			$chunkProtocolId = TypeConverter::getInstance($protocolId)->getChunkProtocolId();

			self::assertTrue(in_array($chunkProtocolId, ProtocolInfo::ACCEPTED_PROTOCOL, true), "Protocol $protocolId");
			self::assertSame($chunkProtocolId, TypeConverter::getInstance($chunkProtocolId)->getChunkProtocolId(), "Protocol $protocolId");
			self::assertSame(TypeConverter::getInstance($protocolId)->getChunkProtocolId(), $chunkProtocolId);
		}
	}

	public function testChunkProtocolGroupMembersShareTheBlockTranslatorOfTheirRepresentative() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			$converter = TypeConverter::getInstance($protocolId);
			$representative = TypeConverter::getInstance($converter->getChunkProtocolId());

			self::assertSame($representative->getBlockTranslator(), $converter->getBlockTranslator(), "Protocol $protocolId");
		}
	}

	public function testItemTypeDictionariesAreLoadedOncePerDistinctList() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			TypeConverter::getInstance($protocolId);
		}

		self::assertLessThan(count(ProtocolInfo::ACCEPTED_PROTOCOL), ItemTypeDictionaryFromDataHelper::getLoadedCount());
	}
}