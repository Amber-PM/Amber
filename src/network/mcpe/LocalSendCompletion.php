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

namespace pocketmine\network\mcpe;

use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;

final class LocalSendCompletion{
	/** @var PromiseResolver<true>|null */
	private ?PromiseResolver $resolver;
	/** @var Promise<true> */
	private Promise $promise;
	private bool $submitted = false;
	private ?LocalSendResult $result = null;
	private ?bool $nativeCompleted = null;

	public function __construct(private float $deadline){
		$this->resolver = new PromiseResolver();
		$this->promise = $this->resolver->getPromise();
	}

	/** @return Promise<true> */
	public function getPromise() : Promise{ return $this->promise; }
	public function isPending() : bool{ return $this->resolver !== null; }

	public function submit(LocalCompletionPacketSender $sender, string $payload, bool $immediate) : void{
		if($this->submitted || !$this->isPending()){ return; }
		$this->submitted = true;
		$reference = \WeakReference::create($this);
		try{
			$this->result = $sender->sendWithLocalCompletion($payload, $immediate, static function(bool $success) use ($reference) : void{
				$operation = $reference->get();
				if($operation !== null && $operation->isPending() && $operation->nativeCompleted === null){
					$operation->nativeCompleted = $success;
					$operation->finishIfReady();
				}
			});
		}catch(\Throwable){
			$this->cancel();
			return;
		}
		$this->finishIfReady();
	}

	private function finishIfReady() : void{
		if($this->result === null || !$this->isPending()){ return; }
		if($this->result !== LocalSendResult::ACCEPTED || $this->nativeCompleted === false){
			$this->cancel();
		}elseif($this->nativeCompleted === true){
			$resolver = $this->resolver;
			$this->resolver = null;
			$resolver?->resolve(true);
		}
	}

	public function checkTimeout(float $now) : void{
		if($now >= $this->deadline){ $this->cancel(); }
	}

	public function cancel() : void{
		$resolver = $this->resolver;
		$this->resolver = null;
		$resolver?->reject();
	}
}
