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

namespace pocketmine\network\mcpe\cache;

use pmmp\encoding\ByteBufferWriter;
use PHPUnit\Framework\TestCase;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\FlowerPot;
use pocketmine\block\tile\ItemFrame;
use pocketmine\block\tile\MovingBlock;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function array_keys;
use function count;

final class ChunkProtocolGroupTest extends TestCase{

	public function testProtocolsSharingAChunkCacheEncodeIdentically() : void{
		$states = array_keys(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates());
		$chunk = new Chunk([], false);
		$i = 0;
		for($y = 0; $y < 128; ++$y){
			for($x = 0; $x < 16; ++$x){
				for($z = 0; $z < 16; ++$z){
					$chunk->setBlockStateId($x, $y, $z, $states[$i++ % count($states)]);
				}
			}
		}

		$world = $this->createMock(World::class);

		$itemFrame = new ItemFrame($world, new Vector3(2, 64, 3));
		$itemFrame->setItem(VanillaItems::DIAMOND_SWORD());
		$chunk->addTile($itemFrame);

		$flowerPot = new FlowerPot($world, new Vector3(4, 64, 5));
		$flowerPot->setPlant(VanillaBlocks::DANDELION());
		$chunk->addTile($flowerPot);

		$movingBlock = new MovingBlock($world, new Vector3(6, 64, 7));
		$movingBlock->setMovingBlock(VanillaBlocks::STONE());
		$chunk->addTile($movingBlock);

		$groups = [];
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocolId){
			$groups[TypeConverter::getInstance($protocolId)->getChunkProtocolId()][] = $protocolId;
		}
		self::assertLessThan(count(ProtocolInfo::ACCEPTED_PROTOCOL), count($groups), "some protocols should share chunk caches");

		$testCases = [
			[DimensionIds::OVERWORLD, new ChunkPosition(-3, 5)],
			[DimensionIds::NETHER, new ChunkPosition(2, -4)],
			[DimensionIds::THE_END, new ChunkPosition(0, 0)],
		];

		foreach($testCases as [$dimensionId, $chunkPos]){
			foreach($groups as $chunkProtocolId => $members){
				$expected = null;
				$repConverter = TypeConverter::getInstance($chunkProtocolId);
				$tiles = ChunkSerializer::serializeTiles($chunk, $repConverter);

				foreach($members as $protocolId){
					$payload = ChunkSerializer::serializeFullChunk($chunk, $dimensionId, TypeConverter::getInstance($protocolId), $tiles);
					$stream = new ByteBufferWriter();
					PacketBatch::encodePackets($stream, $protocolId, [LevelChunkPacket::create($chunkPos, $dimensionId, ChunkSerializer::getSubChunkCount($chunk, $dimensionId), null, false, [], $payload)]);
					$expected ??= $stream->getData();
					self::assertSame($expected, $stream->getData(), "protocol $protocolId encodes chunks differently from $chunkProtocolId in dimension $dimensionId");
				}
			}
		}
	}
}
