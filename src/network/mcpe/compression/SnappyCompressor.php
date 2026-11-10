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

namespace pocketmine\network\mcpe\compression;

use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\utils\Utils;
use function extension_loaded;
use function ord;
use function snappy_compress;
use function snappy_uncompress;
use function strlen;

/**
 * Snappy compressor. Uses far less CPU than zlib, but its output is larger: about 1.2x on small batches and about
 * 2x on chunk data. Requires ext-snappy.
 */
final class SnappyCompressor implements Compressor{

	public static function isAvailable() : bool{
		return extension_loaded("snappy");
	}

	public function __construct(
		private ?int $minCompressionSize = ZlibCompressor::DEFAULT_THRESHOLD,
		private int $maxDecompressionSize = ZlibCompressor::DEFAULT_MAX_DECOMPRESSION_SIZE
	){}

	public function getCompressionThreshold() : ?int{
		return $this->minCompressionSize;
	}

	/**
	 * @throws DecompressionException
	 */
	public function decompress(string $payload) : string{
		//Snappy streams start with the uncompressed length as a little-endian varint (max 32 bits).
		//Checking it up front avoids allocating attacker-controlled amounts of memory.
		$length = 0;
		$shift = 0;
		$len = strlen($payload);
		for($i = 0; $i < 5; ++$i){
			if($i >= $len){
				throw new DecompressionException("Truncated snappy header");
			}
			$byte = ord($payload[$i]);
			$length |= ($byte & 0x7f) << $shift;
			if(($byte & 0x80) === 0){
				break;
			}
			$shift += 7;
			if($i === 4){
				throw new DecompressionException("Invalid snappy header");
			}
		}
		if($length > $this->maxDecompressionSize){
			throw new DecompressionException("Decompressed size $length exceeds limit of $this->maxDecompressionSize");
		}

		$result = @snappy_uncompress($payload);
		if($result === false){
			throw new DecompressionException("Failed to decompress data");
		}
		return $result;
	}

	public function compress(string $payload) : string{
		return Utils::assumeNotFalse(snappy_compress($payload), "Snappy compression failed");
	}

	public function getNetworkId() : int{
		return CompressionAlgorithm::SNAPPY;
	}
}
