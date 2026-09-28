<?php
declare(strict_types=1);

namespace pocketmine\addon;

use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use ReflectionClass;

final class ScriptHostDynamicPropertyTest extends TestCase {

	public function testPackNamespacedDynamicProperties() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());

		$method = $rc->getMethod("handle");

		// pack a sets "gold" = 100 on world
		$method->invoke($host, "sdp", ["scope" => "world", "key" => "gold", "v" => 100, "pack" => "packA"]);

		// pack B sets "gold" = 200 on world
		$method->invoke($host, "sdp", ["scope" => "world", "key" => "gold", "v" => 200, "pack" => "packB"]);

		// pack A reads "gold" -> 100
		$valA = $method->invoke($host, "dp", ["scope" => "world", "key" => "gold", "pack" => "packA"]);
		self::assertSame(100, $valA, "Pack A must read its own namespaced value");

		// pack B reads "gold" -> 200
		$valB = $method->invoke($host, "dp", ["scope" => "world", "key" => "gold", "pack" => "packB"]);
		self::assertSame(200, $valB, "Pack B must read its own namespaced value");

		// pack A gets IDs -> ["gold"]
		$idsA = $method->invoke($host, "dpids", ["scope" => "world", "pack" => "packA"]);
		self::assertEquals(["gold"], $idsA, "Pack A must only see its own property IDs");

		// pack B gets IDs -> ["gold"]
		$idsB = $method->invoke($host, "dpids", ["scope" => "world", "pack" => "packB"]);
		self::assertEquals(["gold"], $idsB, "Pack B must only see its own property IDs");

		// pack A clears dynamic properties
		$method->invoke($host, "dpclear", ["scope" => "world", "pack" => "packA"]);

		// pack A property is over
		$valACleared = $method->invoke($host, "dp", ["scope" => "world", "key" => "gold", "pack" => "packA"]);
		self::assertNull($valACleared, "Pack A's property must be cleared");

		// pack B property is still intact
		$valBIntact = $method->invoke($host, "dp", ["scope" => "world", "key" => "gold", "pack" => "packB"]);
		self::assertSame(200, $valBIntact, "Pack B's property must remain unaffected when Pack A clears");
	}

	public function testSameDisplayNameDifferentUuidDynamicPropertyIsolation() : void {
		$rc = new ReflectionClass(ScriptHost::class);
		$host = $rc->newInstanceWithoutConstructor();

		$rc->getProperty("running")->setValue($host, true);
		$logger = $this->createMock(\Logger::class);
		$rc->getProperty("logger")->setValue($host, $logger);
		$rc->getProperty("packs")->setValue($host, []);
		$rc->getProperty("entityTags")->setValue($host, new \WeakMap());

		$method = $rc->getMethod("handle");

		$uuidA = "11111111-1111-1111-1111-111111111111";
		$uuidB = "22222222-2222-2222-2222-222222222222";

		// both packs share the same display name but have distinct uuids
		$method->invoke($host, "sdp", ["scope" => "world", "key" => "foo", "v" => "valA", "pack" => $uuidA]);
		$method->invoke($host, "sdp", ["scope" => "world", "key" => "foo", "v" => "valB", "pack" => $uuidB]);

		$resA = $method->invoke($host, "dp", ["scope" => "world", "key" => "foo", "pack" => $uuidA]);
		$resB = $method->invoke($host, "dp", ["scope" => "world", "key" => "foo", "pack" => $uuidB]);

		self::assertSame("valA", $resA, "Pack A must read valA by UUID");
		self::assertSame("valB", $resB, "Pack B must read valB by UUID");

		$idsA = $method->invoke($host, "dpids", ["scope" => "world", "pack" => $uuidA]);
		self::assertEquals(["foo"], $idsA);

		$method->invoke($host, "dpclear", ["scope" => "world", "pack" => $uuidA]);
		self::assertNull($method->invoke($host, "dp", ["scope" => "world", "key" => "foo", "pack" => $uuidA]));
		self::assertSame("valB", $method->invoke($host, "dp", ["scope" => "world", "key" => "foo", "pack" => $uuidB]));
	}
}
