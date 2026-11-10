<?php

declare(strict_types=1);

namespace pocketmine\world\format\io;

use PHPUnit\Framework\TestCase;

final class WorldProviderManagerTest extends TestCase{

	public function testLevelDbBlockCacheDefaultsToTheLevelDbDefault() : void{
		self::assertSame(0, (new WorldProviderManager())->getLevelDbBlockCacheBytes());
	}

	public function testLevelDbBlockCacheCanBeSet() : void{
		$manager = new WorldProviderManager();
		$manager->setLevelDbBlockCacheBytes(64 * 1024 * 1024);
		self::assertSame(64 * 1024 * 1024, $manager->getLevelDbBlockCacheBytes());

		$manager->setLevelDbBlockCacheBytes(0);
		self::assertSame(0, $manager->getLevelDbBlockCacheBytes());
	}

	public function testNegativeLevelDbBlockCacheIsRejected() : void{
		$manager = new WorldProviderManager();
		$manager->setLevelDbBlockCacheBytes(1024);

		try{
			$manager->setLevelDbBlockCacheBytes(-1);
			self::fail("A negative cache size must be rejected");
		}catch(\InvalidArgumentException){
			self::assertSame(1024, $manager->getLevelDbBlockCacheBytes(), "A rejected value must not change the setting");
		}
	}
}
