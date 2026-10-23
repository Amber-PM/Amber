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

namespace pmmp\thread {
	if(!class_exists(ThreadSafe::class, false)){
		class ThreadSafe{
			public function synchronized(\Closure $closure, ...$args) : mixed{
				return $closure(...$args);
			}
			public function wait(int $timeout = 0) : bool{ return true; }
			public function notify() : bool{ return true; }
			public function notifyOne() : bool{ return true; }
		}
	}

	if(!class_exists(Runnable::class, false)){
		abstract class Runnable extends ThreadSafe{
			abstract public function run() : void;
		}
	}

	if(!class_exists(Thread::class, false)){
		class Thread extends Runnable{
			public function run() : void{}
			public function start(int $options = 0) : bool{ return true; }
			public function join() : bool{ return true; }
			public function isStarted() : bool{ return true; }
			public function isJoined() : bool{ return true; }
		}
	}

	if(!class_exists(Worker::class, false)){
		class Worker extends Thread{
			public function stack(Runnable $task) : int{ return 0; }
			public function unstack() : ?Runnable{ return null; }
			public function getStacked() : int{ return 0; }
			public function isWorking() : bool{ return false; }
			public function shutdown() : bool{ return true; }
		}
	}

	if(!class_exists(ThreadSafeArray::class, false)){
		class ThreadSafeArray extends ThreadSafe implements \ArrayAccess, \Countable, \IteratorAggregate{
			/** @var array<mixed, mixed> */
			private array $data = [];

			public function offsetExists(mixed $offset) : bool{ return isset($this->data[$offset]); }
			public function offsetGet(mixed $offset) : mixed{ return $this->data[$offset] ?? null; }
			public function offsetSet(mixed $offset, mixed $value) : void{
				if($offset === null){
					$this->data[] = $value;
				}else{
					$this->data[$offset] = $value;
				}
			}
			public function offsetUnset(mixed $offset) : void{ unset($this->data[$offset]); }
			public function count() : int{ return count($this->data); }
			public function getIterator() : \Traversable{ return new \ArrayIterator($this->data); }
		}
	}
}

namespace pmmp\encoding {
	if(!class_exists(BE::class, false)){
		final class BE{
			public static function packSignedLong(int $v) : string{ return pack('J', $v); }
			public static function unpackSignedLong(string $v) : int{ return unpack('J', $v)[1]; }
			public static function packUnsignedInt(int $v) : string{ return pack('N', $v); }
			public static function unpackUnsignedInt(string $v) : int{ return unpack('N', $v)[1]; }
			public static function packSignedInt(int $v) : string{ return pack('N', $v); }
			public static function unpackSignedInt(string $v) : int{ return unpack('l', $v)[1]; }
			public static function packSignedShort(int $v) : string{ return pack('s', $v); }
			public static function unpackSignedShort(string $v) : int{ return unpack('s', $v)[1]; }
			public static function packUnsignedShort(int $v) : string{ return pack('n', $v); }
			public static function unpackUnsignedShort(string $v) : int{ return unpack('n', $v)[1]; }
		}
	}

	if(!class_exists(LE::class, false)){
		final class LE{
			public static function packSignedLong(int $v) : string{ return pack('P', $v); }
			public static function unpackSignedLong(string $v) : int{ return unpack('P', $v)[1]; }
			public static function packUnsignedInt(int $v) : string{ return pack('V', $v); }
			public static function unpackUnsignedInt(string $v) : int{ return unpack('V', $v)[1]; }
			public static function packSignedInt(int $v) : string{ return pack('V', $v); }
			public static function unpackSignedInt(string $v) : int{ return unpack('l', $v)[1]; }
			public static function packSignedShort(int $v) : string{ return pack('v', $v); }
			public static function unpackSignedShort(string $v) : int{ return unpack('v', $v)[1]; }
			public static function packUnsignedShort(int $v) : string{ return pack('v', $v); }
			public static function unpackUnsignedShort(string $v) : int{ return unpack('v', $v)[1]; }
		}
	}
}

namespace {
	if(!function_exists('morton2d_encode')){
		function morton2d_encode(int $x, int $y) : int{
			$res = 0;
			for($i = 0; $i < 32; ++$i){
				$res |= (($x & (1 << $i)) << $i) | (($y & (1 << $i)) << ($i + 1));
			}
			return $res;
		}
	}
	if(!function_exists('morton2d_decode')){
		/** @return array{int, int} */
		function morton2d_decode(int $code) : array{
			$x = 0;
			$y = 0;
			for($i = 0; $i < 32; ++$i){
				$x |= (($code >> (2 * $i)) & 1) << $i;
				$y |= (($code >> (2 * $i + 1)) & 1) << $i;
			}
			return [$x, $y];
		}
	}

	require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}
