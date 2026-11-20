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

namespace pocketmine\crafting;

use Logger;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\ExperienceManager;
use pocketmine\inventory\transaction\SmithingTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Armor;
use pocketmine\item\ArmorTrim;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use ReflectionProperty;

final class SmithingTableTest extends TestCase{

	private function createMockPlayer() : Player{
		$player = $this->getMockBuilder(Player::class)
			->disableOriginalConstructor()
			->onlyMethods(["getXpManager", "isCreative", "onDispose"])
			->getMock();

		$xpManager = $this->createMock(ExperienceManager::class);
		$player->method("getXpManager")->willReturn($xpManager);
		$player->method("isCreative")->willReturn(false);

		(new ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createMock(Logger::class));

		return $player;
	}

	public function testNetheriteTransformPreservesAttributes() : void{
		$recipe = new SmithingTransformRecipe(
			new ExactRecipeIngredient(VanillaItems::NETHERITE_UPGRADE_SMITHING_TEMPLATE()),
			new ExactRecipeIngredient(VanillaItems::DIAMOND_SWORD()),
			new ExactRecipeIngredient(VanillaItems::NETHERITE_INGOT()),
			VanillaItems::NETHERITE_SWORD()
		);

		$sword = VanillaItems::DIAMOND_SWORD()
			->setCustomName("Dragon Slayer")
			->setLore(["Forged in ancient fires"])
			->setDamage(250)
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5))
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));

		$transformed = $recipe->transform($sword);

		self::assertSame(ItemTypeIds::NETHERITE_SWORD, $transformed->getTypeId());
		self::assertInstanceOf(Durable::class, $transformed);
		self::assertSame("Dragon Slayer", $transformed->getCustomName());
		self::assertSame(["Forged in ancient fires"], $transformed->getLore());
		self::assertSame(250, $transformed->getDamage());
		self::assertTrue($transformed->hasEnchantment(VanillaEnchantments::SHARPNESS(), 5));
		self::assertTrue($transformed->hasEnchantment(VanillaEnchantments::UNBREAKING(), 3));
	}

	public function testNetheriteArmorPreservesTrim() : void{
		$recipe = new SmithingTransformRecipe(
			new ExactRecipeIngredient(VanillaItems::NETHERITE_UPGRADE_SMITHING_TEMPLATE()),
			new ExactRecipeIngredient(VanillaItems::DIAMOND_CHESTPLATE()),
			new ExactRecipeIngredient(VanillaItems::NETHERITE_INGOT()),
			VanillaItems::NETHERITE_CHESTPLATE()
		);

		$armor = VanillaItems::DIAMOND_CHESTPLATE();
		self::assertInstanceOf(Armor::class, $armor);
		$armor->setTrim(new ArmorTrim(ArmorTrim::MATERIAL_AMETHYST, ArmorTrim::PATTERN_COAST));

		$transformed = $recipe->transform($armor);
		self::assertInstanceOf(Armor::class, $transformed);
		self::assertSame(ItemTypeIds::NETHERITE_CHESTPLATE, $transformed->getTypeId());

		$trim = $transformed->getTrim();
		self::assertNotNull($trim);
		self::assertSame(ArmorTrim::MATERIAL_AMETHYST, $trim->getMaterial());
		self::assertSame(ArmorTrim::PATTERN_COAST, $trim->getPattern());
	}

	public function testArmorTrimRecipe() : void{
		$recipe = new SmithingTrimRecipe(
			new TagWildcardRecipeIngredient("minecraft:trim_templates"),
			new TagWildcardRecipeIngredient("minecraft:trimmable_armors"),
			new TagWildcardRecipeIngredient("minecraft:trim_materials")
		);

		$helmet = VanillaItems::DIAMOND_HELMET()
			->setCustomName("King's Crown")
			->setDamage(50);
		self::assertInstanceOf(Armor::class, $helmet);

		$template = VanillaItems::SENTRY_ARMOR_TRIM_SMITHING_TEMPLATE();
		$material = VanillaItems::GOLD_INGOT();

		$trimmed = $recipe->applyTrim($helmet, $template, $material);
		self::assertNotNull($trimmed);
		self::assertInstanceOf(Armor::class, $trimmed);

		self::assertSame("King's Crown", $trimmed->getCustomName());
		self::assertSame(50, $trimmed->getDamage());

		$trim = $trimmed->getTrim();
		self::assertNotNull($trim);
		self::assertSame(ArmorTrim::MATERIAL_GOLD, $trim->getMaterial());
		self::assertSame(ArmorTrim::PATTERN_SENTRY, $trim->getPattern());

		// Verify NBT serialization
		$nbt = $trimmed->getNamedTag();
		self::assertTrue($nbt->getTag(Armor::TAG_TRIM) !== null);
		$trimTag = $nbt->getCompoundTag(Armor::TAG_TRIM);
		self::assertNotNull($trimTag);
		self::assertSame(ArmorTrim::MATERIAL_GOLD, $trimTag->getString(Armor::TAG_TRIM_MATERIAL));
		self::assertSame(ArmorTrim::PATTERN_SENTRY, $trimTag->getString(Armor::TAG_TRIM_PATTERN));
	}

	public function testSmithingTransactionTransform() : void{
		$player = $this->createMockPlayer();
		$table = VanillaBlocks::SMITHING_TABLE();

		$recipe = new SmithingTransformRecipe(
			new ExactRecipeIngredient(VanillaItems::NETHERITE_UPGRADE_SMITHING_TEMPLATE()),
			new ExactRecipeIngredient(VanillaItems::DIAMOND_PICKAXE()),
			new ExactRecipeIngredient(VanillaItems::NETHERITE_INGOT()),
			VanillaItems::NETHERITE_PICKAXE()
		);

		$template = VanillaItems::NETHERITE_UPGRADE_SMITHING_TEMPLATE();
		$pickaxe = VanillaItems::DIAMOND_PICKAXE()->setDamage(15);
		$ingot = VanillaItems::NETHERITE_INGOT();

		$tx = new SmithingTransaction($player, $table, $recipe, $template, $pickaxe, $ingot);
		$result = $tx->getResult();

		self::assertSame(ItemTypeIds::NETHERITE_PICKAXE, $result->getTypeId());
		self::assertInstanceOf(Durable::class, $result);
		self::assertSame(15, $result->getDamage());
	}

	public function testSmithingTransactionInvalidIngredientThrows() : void{
		$player = $this->createMockPlayer();
		$table = VanillaBlocks::SMITHING_TABLE();

		$recipe = new SmithingTransformRecipe(
			new ExactRecipeIngredient(VanillaItems::NETHERITE_UPGRADE_SMITHING_TEMPLATE()),
			new ExactRecipeIngredient(VanillaItems::DIAMOND_PICKAXE()),
			new ExactRecipeIngredient(VanillaItems::NETHERITE_INGOT()),
			VanillaItems::NETHERITE_PICKAXE()
		);

		$template = VanillaItems::NETHERITE_UPGRADE_SMITHING_TEMPLATE();
		$ironSword = VanillaItems::IRON_SWORD(); // Wrong input!
		$ingot = VanillaItems::NETHERITE_INGOT();

		$this->expectException(TransactionValidationException::class);
		new SmithingTransaction($player, $table, $recipe, $template, $ironSword, $ingot);
	}
}
