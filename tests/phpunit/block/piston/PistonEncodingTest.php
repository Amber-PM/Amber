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

namespace pocketmine\block\piston;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\convert\BlockObjectToStateSerializer;
use pocketmine\data\bedrock\block\convert\BlockSerializerDeserializerRegistrar;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\data\bedrock\block\convert\VanillaBlockMappings;
use pocketmine\math\Facing;

final class PistonEncodingTest extends TestCase{
	public function testEveryFacingUsesBedrockPistonOrientation() : void{
		$serializer = new BlockObjectToStateSerializer();
		$deserializer = new BlockStateToObjectDeserializer();
		VanillaBlockMappings::init(new BlockSerializerDeserializerRegistrar($deserializer, $serializer));
		foreach([Facing::DOWN => 0, Facing::UP => 1, Facing::NORTH => 3, Facing::SOUTH => 2, Facing::WEST => 5, Facing::EAST => 4] as $facing => $encoded){
			foreach([VanillaBlocks::PISTON(), VanillaBlocks::STICKY_PISTON(), VanillaBlocks::PISTON_HEAD(), VanillaBlocks::PISTON_HEAD()->setSticky(true)] as $block){
				$block->setFacing($facing);
				$state = $serializer->serializeBlock($block);
				self::assertSame($encoded, $state->getState(BlockStateNames::FACING_DIRECTION)?->getValue(), $block->getName());
				self::assertSame($block->getStateId(), $deserializer->deserializeBlock($state)->getStateId());
			}
		}
	}
}
