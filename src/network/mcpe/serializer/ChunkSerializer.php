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

namespace pocketmine\network\mcpe\serializer;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\block\tile\Spawnable;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\LegacyBiomeIdToStringIdMap;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use function chr;
use function count;

final class ChunkSerializer{
	private function __construct(){
		//NOOP
	}

	/**
	 * Returns the min/max subchunk index expected in the protocol.
	 * This has no relation to the world height supported by PM.
	 *
	 * @phpstan-param DimensionIds::* $dimensionId
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public static function getDimensionChunkBounds(int $dimensionId) : array{
		return match($dimensionId){
			DimensionIds::OVERWORLD => [-4, 19],
			DimensionIds::NETHER => [0, 7],
			DimensionIds::THE_END => [0, 15],
			default => throw new \InvalidArgumentException("Unknown dimension ID $dimensionId"),
		};
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public static function getDimensionChunkBoundsForProtocol(int $dimensionId, int $protocolId) : array{
		if($protocolId <= ProtocolInfo::PROTOCOL_1_17_40 && $dimensionId === DimensionIds::OVERWORLD){
			return [0, 15];
		}
		return self::getDimensionChunkBounds($dimensionId);
	}

	/**
	 * Returns the number of subchunks that will be sent from the given chunk.
	 * Chunks are sent in a stack, so every chunk below the top non-empty one must be sent.
	 *
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function getSubChunkCount(Chunk $chunk, int $dimensionId, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : int{
		//if the protocol world bounds ever exceed the PM supported bounds again in the future, we might need to
		//polyfill some stuff here
		[$minSubChunkIndex, $maxSubChunkIndex] = self::getDimensionChunkBoundsForProtocol($dimensionId, $protocolId);
		for($y = $maxSubChunkIndex, $count = $maxSubChunkIndex - $minSubChunkIndex + 1; $y >= $minSubChunkIndex; --$y, --$count){
			if($chunk->getSubChunk($y)->isEmptyFast()){
				continue;
			}
			return $count;
		}

		return 0;
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 * @return string[]
	 */
	public static function serializeSubChunks(Chunk $chunk, int $dimensionId, TypeConverter $typeConverter) : array{
		$stream = new ByteBufferWriter();
		$subChunks = [];
		$protocolId = $typeConverter->getProtocolId();

		$subChunkCount = self::getSubChunkCount($chunk, $dimensionId, $protocolId);
		$writtenCount = 0;

		[$minSubChunkIndex, ] = self::getDimensionChunkBoundsForProtocol($dimensionId, $protocolId);
		for($y = $minSubChunkIndex; $writtenCount < $subChunkCount; ++$y, ++$writtenCount){
			$stream->clear();
			self::serializeSubChunk($chunk->getSubChunk($y), $typeConverter->getBlockTranslator(), $stream, false);
			$subChunks[] = $stream->getData();
		}

		return $subChunks;
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function serializeFullChunk(Chunk $chunk, int $dimensionId, TypeConverter $typeConverter, ?string $tiles = null) : string{
		$stream = new ByteBufferWriter();
		$protocolId = $typeConverter->getProtocolId();

		foreach(self::serializeSubChunks($chunk, $dimensionId, $typeConverter) as $subChunk){
			$stream->writeByteArray($subChunk);
		}

		if($protocolId <= ProtocolInfo::PROTOCOL_1_17_40){
			$stream->writeByteArray(self::serialize2DBiomes($chunk));
		}else{
			self::serializeBiomes($chunk, $dimensionId, $stream);
		}
		self::serializeChunkData($chunk, $stream, $typeConverter, $tiles);

		return $stream->getData();
	}

	public static function serialize2DBiomes(Chunk $chunk) : string{
		$biomes2d = "";
		$biomeArray = $chunk->getSubChunk(0)->getBiomeArray();
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$biomes2d .= chr($biomeArray->get($x, 0, $z) & 0xFF);
			}
		}
		return $biomes2d;
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function serializeBiomes(Chunk $chunk, int $dimensionId, ByteBufferWriter $stream) : void{
		[$minSubChunkIndex, $maxSubChunkIndex] = self::getDimensionChunkBounds($dimensionId);
		$biomeIdMap = LegacyBiomeIdToStringIdMap::getInstance();
		//all biomes must always be written :(
		for($y = $minSubChunkIndex; $y <= $maxSubChunkIndex; ++$y){
			self::serializeBiomePalette($chunk->getSubChunk($y)->getBiomeArray(), $biomeIdMap, $stream);
		}
	}

	public static function serializeBorderBlocks(ByteBufferWriter $stream) : void {
		Byte::writeUnsigned($stream, 0); //border block array count
		//Border block entry format: 1 byte (4 bits X, 4 bits Z). These are however useless since they crash the regular client.
	}

	public static function serializeChunkData(Chunk $chunk, ByteBufferWriter $stream, TypeConverter $typeConverter, ?string $tiles = null) : void{
		self::serializeBorderBlocks($stream);

		if($tiles !== null){
			$stream->writeByteArray($tiles);
		}else{
			$stream->writeByteArray(self::serializeTiles($chunk, $typeConverter));
		}
	}

	public static function serializeSubChunk(SubChunk $subChunk, BlockTranslator $blockTranslator, ByteBufferWriter $stream, bool $persistentBlockStates) : void{
		$layers = $subChunk->getBlockLayers();
		Byte::writeUnsigned($stream, 8); //version

		Byte::writeUnsigned($stream, count($layers));

		$blockStateDictionary = $blockTranslator->getBlockStateDictionary();

		foreach($layers as $blocks){
			$bitsPerBlock = $blocks->getBitsPerBlock();
			$words = $blocks->getWordArray();
			Byte::writeUnsigned($stream, ($bitsPerBlock << 1) | ($persistentBlockStates ? 0 : 1));
			$stream->writeByteArray($words);
			$palette = $blocks->getPalette();

			if($bitsPerBlock !== 0){
				VarInt::writeSignedInt($stream, count($palette)); //yes, this is intentionally zigzag
			}
			if($persistentBlockStates){
				$nbtSerializer = new NetworkNbtSerializer();
				foreach($palette as $p){
					//TODO: introduce a binary cache for this
					$state = $blockStateDictionary->generateDataFromStateId($blockTranslator->internalIdToNetworkId($p));
					if($state === null){
						$state = $blockTranslator->getFallbackStateData();
					}

					$stream->writeByteArray($nbtSerializer->write(new TreeRoot($state->toNbt())));
				}
			}else{
				//we would use writeSignedIntArray() here, but the gains of writing in batch are negated by the cost of
				//allocating a temporary array for the mapped palette IDs, especially for small palettes
				foreach($palette as $p){
					VarInt::writeSignedInt($stream, $blockTranslator->internalIdToNetworkId($p));
				}
			}
		}
	}

	private static function serializeBiomePalette(PalettedBlockArray $biomePalette, LegacyBiomeIdToStringIdMap $biomeIdMap, ByteBufferWriter $stream) : void{
		$biomePaletteBitsPerBlock = $biomePalette->getBitsPerBlock();
		Byte::writeUnsigned($stream, ($biomePaletteBitsPerBlock << 1) | 1); //the last bit is non-persistence (like for blocks), though it has no effect on biomes since they always use integer IDs
		$stream->writeByteArray($biomePalette->getWordArray());

		$biomePaletteArray = $biomePalette->getPalette();
		if($biomePaletteBitsPerBlock !== 0){
			VarInt::writeSignedInt($stream, count($biomePaletteArray));
		}

		foreach($biomePaletteArray as $p){
			//we would use writeSignedIntArray() here, but the gains of writing in batch are negated by the cost of
			//allocating a temporary array for the mapped palette IDs, especially for small palettes
			VarInt::writeSignedInt($stream, $biomeIdMap->legacyToString($p) !== null ? $p : BiomeIds::OCEAN);
		}
	}

	public static function serializeTiles(Chunk $chunk, TypeConverter $typeConverter) : string{
		$stream = new ByteBufferWriter();
		foreach($chunk->getTiles() as $tile){
			if($tile instanceof Spawnable){
				$stream->writeByteArray($tile->getSerializedSpawnCompound($typeConverter)->getEncodedNbt());
			}
		}

		return $stream->getData();
	}
}
