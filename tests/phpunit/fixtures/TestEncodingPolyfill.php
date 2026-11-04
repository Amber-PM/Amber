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

namespace pmmp\encoding {

	use function pack;
	use function unpack;

	if(!class_exists(BE::class, false)){
		class BE{
			public static function unpackSignedLong(string $b) : int{
				/** @var int $val */
				$val = unpack("J", $b)[1];
				return $val;
			}

			public static function packSignedLong(int $v) : string{
				return pack("J", $v);
			}

			public static function packSignedInt(int $v) : string{
				return pack("N", $v);
			}

			public static function unpackSignedInt(string $b) : int{
				/** @var int $val */
				$val = unpack("N", $b)[1];
				return $val >= 0x80000000 ? $val - 0x100000000 : $val;
			}
		}
	}

	if(!class_exists(LE::class, false)){
		class LE{
			public static function packSignedLong(int $v) : string{
				return pack("P", $v);
			}

			public static function unpackSignedLong(string $b) : int{
				/** @var int $val */
				$val = unpack("P", $b)[1];
				return $val;
			}

			public static function packSignedInt(int $v) : string{
				return pack("V", $v);
			}

			public static function unpackSignedInt(string $b) : int{
				/** @var int $val */
				$val = unpack("V", $b)[1];
				return $val >= 0x80000000 ? $val - 0x100000000 : $val;
			}
		}
	}

	if(!class_exists(DataDecodeException::class, false)){
		class DataDecodeException extends \RuntimeException{}
	}

	if(!class_exists(ByteBufferReader::class, false)){
		class ByteBufferReader{
			private int $offset = 0;
			public function __construct(private string $data, int $offset = 0){
				$this->offset = $offset;
			}
			public function getOffset() : int{ return $this->offset; }
			public function setOffset(int $offset) : void{ $this->offset = $offset; }
			public function getData() : string{ return $this->data; }
			public function getRemaining() : int{ return \strlen($this->data) - $this->offset; }
			public function getUnreadLength() : int{ return \strlen($this->data) - $this->offset; }
			public function readByteArray(int $len) : string{
				if($this->getRemaining() < $len){
					throw new DataDecodeException("Not enough bytes to read");
				}
				$res = \substr($this->data, $this->offset, $len);
				$this->offset += $len;
				return $res;
			}
			public function readByte() : int{
				return \ord($this->readByteArray(1));
			}
		}
	}

	if(!class_exists(ByteBufferWriter::class, false)){
		class ByteBufferWriter{
			public function __construct(private string $buffer = ""){}
			public function writeByteArray(string $bytes) : void{ $this->buffer .= $bytes; }
			public function writeByte(int $b) : void{ $this->buffer .= \chr($b); }
			public function getData() : string{ return $this->buffer; }
			public function reserve(int $len) : void{}
		}
	}

	if(!class_exists(VarInt::class, false)){
		class VarInt{
			public static function readUnsignedInt(ByteBufferReader $reader) : int{
				$value = 0;
				$shift = 0;
				while(true){
					$byte = $reader->readByte();
					$value |= ($byte & 0x7f) << $shift;
					if(($byte & 0x80) === 0){
						break;
					}
					$shift += 7;
					if($shift >= 35){
						throw new DataDecodeException("VarInt too big");
					}
				}
				return $value;
			}
			public static function writeUnsignedInt(ByteBufferWriter $writer, int $value) : void{
				while(($value & ~0x7f) !== 0){
					$writer->writeByte(($value & 0x7f) | 0x80);
					$value >>= 7;
				}
				$writer->writeByte($value & 0x7f);
			}
		}
	}
}

namespace pmmp\thread {
	if(!class_exists(Runnable::class, false)){
		abstract class Runnable{}
	}
	if(!class_exists(ThreadSafe::class, false)){
		abstract class ThreadSafe{}
	}
	if(!class_exists(ThreadSafeArray::class, false)){
		class ThreadSafeArray extends ThreadSafe{}
	}
	if(!class_exists(Thread::class, false)){
		abstract class Thread extends Runnable{}
	}
}

namespace {
	if(!function_exists('morton2d_encode')){
		function morton2d_encode(int $x, int $y) : int{
			$res = 0;
			for($i = 0; $i < 32; ++$i){
				$res |= (($x >> $i) & 1) << (2 * $i);
				$res |= (($y >> $i) & 1) << (2 * $i + 1);
			}
			return $res;
		}
	}

	if(!function_exists('morton2d_decode')){
		function morton2d_decode(int $m) : array{
			$x = 0;
			$y = 0;
			for($i = 0; $i < 32; ++$i){
				$x |= (($m >> (2 * $i)) & 1) << $i;
				$y |= (($m >> (2 * $i + 1)) & 1) << $i;
			}
			return [$x, $y];
		}
	}

	if(!function_exists('morton3d_encode')){
		function morton3d_encode(int $x, int $y, int $z) : int{
			$res = 0;
			for($i = 0; $i < 21; ++$i){
				$res |= (($x >> $i) & 1) << (3 * $i);
				$res |= (($y >> $i) & 1) << (3 * $i + 1);
				$res |= (($z >> $i) & 1) << (3 * $i + 2);
			}
			return $res;
		}
	}

	if(!function_exists('morton3d_decode')){
		function morton3d_decode(int $m) : array{
			$x = 0;
			$y = 0;
			$z = 0;
			for($i = 0; $i < 21; ++$i){
				$x |= (($m >> (3 * $i)) & 1) << $i;
				$y |= (($m >> (3 * $i + 1)) & 1) << $i;
				$z |= (($m >> (3 * $i + 2)) & 1) << $i;
			}
			return [$x, $y, $z];
		}
	}
}
