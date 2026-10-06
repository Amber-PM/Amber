<?php

declare(strict_types=1);

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;

final class GlidePhysicsTest extends TestCase{
	public function testRecordedBedrockFlightAndBoostMotion() : void{
		$fixtures = json_decode(file_get_contents(__DIR__ . "/fixtures/bedrock-glide-1.26.44.json"), true, flags: JSON_THROW_ON_ERROR);
		foreach(["normal", "boost"] as $mode){
			foreach($fixtures[$mode] as $frame){
				$motion = new Vector3(...array_values($frame["previousVelocity"]));
				$direction = new Vector3(...array_values($frame["previousDirection"]));
				$predicted = GlidePhysics::calculateMotion($motion, $direction, $mode === "boost");
				foreach(["x", "y", "z"] as $axis){
					self::assertEqualsWithDelta($frame["expectedMotion"][$axis], $predicted->$axis, 0.00001, $mode . " tick " . $frame["nextTick"] . " axis " . $axis);
				}
			}
		}
	}
}
