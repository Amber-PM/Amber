<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe;

/** Optional capability for transports with reliable ordering but no remote packet receipts */
interface LocalCompletionPacketSender extends PacketSender{
	/**
	 * Sends on the reliable ordered channel; 
	 * ACCEPTED admits the batch but possibly still queued.
	 * $completed(true) means ALL frames were submitted to the native backend, NOT received remotely
	 * Failure/close must call $completed(false). The callback may occur before this method returns
	 * BACKPRESSURE and DROPPED reject admission, callers must not regard a callback as success
	 * Implementations must bound their native queues and release callbacks during shutdown.
	 *
	 * @param \Closure(bool) : void $completed
	 */
	public function sendWithLocalCompletion(string $payload, bool $immediate, \Closure $completed) : LocalSendResult;
}
