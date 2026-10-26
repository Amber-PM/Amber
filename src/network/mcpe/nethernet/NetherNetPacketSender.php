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

namespace pocketmine\network\mcpe\nethernet;

use amber\nethernet\Channel;
use amber\nethernet\Connection;
use amber\nethernet\SendResult;
use pocketmine\network\mcpe\LocalCompletionPacketSender;
use pocketmine\network\mcpe\LocalSendResult;

final class NetherNetPacketSender implements LocalCompletionPacketSender{
	private bool $closed = false;
	private int $nextOperation = 0;
	private int $pendingBytes = 0;
	/** @var array<int, array{bytes: int, completed: \Closure(bool): void|null, admitted: bool|null, native: bool|null}> */
	private array $pending = [];

	/** @param \Closure(string): void $onFailure */
	public function __construct(
		private Connection $connection,
		private \Closure $onFailure,
		private int $maxPendingCallbacks = 128,
		private int $maxPendingBytes = 4 * 1024 * 1024
	){
		if($maxPendingCallbacks < 1 || $maxPendingBytes < 1){
			throw new \InvalidArgumentException('NetherNet send bounds must be positive');
		}
	}

	public function send(string $payload, bool $immediate, ?int $receiptId) : void{
		// local completion is not a remote ack
		if($this->submit($payload, $immediate, null) !== LocalSendResult::ACCEPTED){
			$this->fail('NetherNet reliable send rejected');
		}
	}

	public function sendWithLocalCompletion(string $payload, bool $immediate, \Closure $completed) : LocalSendResult{
		return $this->submit($payload, $immediate, $completed);
	}

	/** @param \Closure(bool): void|null $completed */
	private function submit(string $payload, bool $immediate, ?\Closure $completed) : LocalSendResult{
		if($this->closed){
			$completed?->__invoke(false);
			return LocalSendResult::DROPPED;
		}
		$bytes = strlen($payload);
		if(count($this->pending) >= $this->maxPendingCallbacks || $bytes > $this->maxPendingBytes - $this->pendingBytes){
			$completed?->__invoke(false);
			return LocalSendResult::BACKPRESSURE;
		}
		$id = $this->nextOperation++;
		$this->pending[$id] = ['bytes' => $bytes, 'completed' => $completed, 'admitted' => null, 'native' => null];
		$this->pendingBytes += $bytes;
		$reference = \WeakReference::create($this);
		try{
			$result = $this->connection->send($payload, Channel::RELIABLE, $immediate, static function(bool $success) use ($reference, $id) : void{
				$reference->get()?->nativeCompleted($id, $success);
			});
		}catch(\Throwable){
			$this->fail('NetherNet native send failed');
			return LocalSendResult::DROPPED;
		}
		$local = match($result){
			SendResult::ACCEPTED => LocalSendResult::ACCEPTED,
			SendResult::BACKPRESSURE => LocalSendResult::BACKPRESSURE,
			SendResult::DROPPED => LocalSendResult::DROPPED
		};
		if(isset($this->pending[$id])){
			$this->pending[$id]['admitted'] = $result === SendResult::ACCEPTED;
			$this->settle($id);
		}
		return $local;
	}

	private function nativeCompleted(int $id, bool $success) : void{
		if(!isset($this->pending[$id]) || $this->pending[$id]['native'] !== null){ return; }
		$this->pending[$id]['native'] = $success;
		$this->settle($id);
	}

	private function settle(int $id) : void{
		$operation = $this->pending[$id];
		if($operation['admitted'] === null){ return; } // callbacks may run inside send()
		if($operation['admitted'] && $operation['native'] === null){ return; }
		unset($this->pending[$id]);
		$this->pendingBytes -= $operation['bytes'];
		if($operation['admitted'] && $operation['native'] === false){
			$this->fail('NetherNet native send completion failed');
		}
		try{
			($operation['completed'])?->__invoke($operation['admitted'] && $operation['native'] === true);
		}catch(\Throwable){
			$this->fail('NetherNet send completion handler failed');
		}
	}

	private function fail(string $reason) : void{
		if($this->closed){ return; }
		$this->close($reason);
		($this->onFailure)($reason);
	}

	public function close(string $reason = 'unknown reason') : void{
		if($this->closed){ return; }
		$this->closed = true;
		$pending = $this->pending;
		$this->pending = [];
		$this->pendingBytes = 0;
		// detach before application or native code can reenter
		foreach($pending as $operation){
			try{ ($operation['completed'])?->__invoke(false); }catch(\Throwable){
				// one callback must not block the drain
			}
		}
		try{ $this->connection->close($reason); }catch(\Throwable){
			// already closed and drained locally
		}
	}
}
