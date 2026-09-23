<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock;

use PHPUnit\Framework\TestCase;

final class BedrockDataFilesTest extends TestCase{
	public function testProtocol12650DataIsPresent() : void{
		foreach([
			BedrockDataFiles::CANONICAL_BLOCK_STATES_1_26_50_NBT,
			BedrockDataFiles::BLOCK_STATE_META_MAP_1_26_50_JSON,
			BedrockDataFiles::BLOCK_NETWORK_IDS_1_26_50_JSON,
			BedrockDataFiles::REQUIRED_ITEM_LIST_1_26_50_JSON,
			BedrockDataFiles::JIGSAW_STRUCTURES_1_26_50_JSON
		] as $path){
			self::assertFileExists($path);
		}
	}
}
