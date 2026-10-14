<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe;

/** admission to a local reliable send queue, none of these outcomes proves remote delivery. */
enum LocalSendResult{
	case ACCEPTED;
	case BACKPRESSURE;
	case DROPPED;
}
