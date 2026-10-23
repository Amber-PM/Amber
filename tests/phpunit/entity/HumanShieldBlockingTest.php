<?php

declare(strict_types=1);

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use ReflectionClass;
use ReflectionProperty;

class HumanShieldBlockingTest extends TestCase{

	private function createHuman() : Human{
		$human = (new ReflectionClass(Human::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Entity::class, "networkProperties"))->setValue($human, new EntityMetadataCollection());
		(new ReflectionProperty(Entity::class, "size"))->setValue($human, new EntitySizeInfo(1.8, 0.6, 1.62));
		(new ReflectionProperty(Entity::class, "scale"))->setValue($human, 1.0);
		$world = $this->createMock(\pocketmine\world\World::class);
		$world->method("isLoaded")->willReturn(true);
		(new ReflectionProperty(Entity::class, "location"))->setValue($human, new \pocketmine\entity\Location(0.0, 0.0, 0.0, $world, 0.0, 0.0));
		(new ReflectionProperty(Living::class, "effectManager"))->setValue($human, new \pocketmine\entity\effect\EffectManager($human));
		(new ReflectionProperty(Living::class, "armorInventory"))->setValue($human, new \pocketmine\inventory\ArmorInventory($human));
		(new ReflectionProperty(Human::class, "inventory"))->setValue($human, new PlayerInventory($human));
		(new ReflectionProperty(Human::class, "offHandInventory"))->setValue($human, new PlayerOffHandInventory($human));
		(new ReflectionProperty(Human::class, "enderInventory"))->setValue($human, new \pocketmine\inventory\PlayerEnderInventory($human));
		$human->getInventory()->getHeldItemIndexChangeListeners()->add(fn(int $oldIndex) => $human->updateBlockingState());
		$human->getInventory()->getListeners()->add(\pocketmine\inventory\CallbackInventoryListener::onAnyChange(fn() => $human->updateBlockingState()));
		$human->getOffHandInventory()->getListeners()->add(\pocketmine\inventory\CallbackInventoryListener::onAnyChange(fn() => $human->updateBlockingState()));
		(new ReflectionProperty(Living::class, "sneaking"))->setValue($human, false);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($human, true);
		return $human;
	}

	private function getGenericFlag(EntityMetadataCollection $collection, int $flagId) : bool{
		$propertyId = $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
		$realFlagId = $flagId % 64;
		$prop = $collection->getAll()[$propertyId] ?? null;
		if($prop instanceof \pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty){
			return (($prop->getValue() >> $realFlagId) & 1) === 1;
		}
		return false;
	}

	public function testBlockingRequiresSneakAndShield() : void{
		$human = $this->createHuman();

		// Case 1: Not sneaking, no shield -> not blocking
		self::assertFalse($human->isBlocking());

		// Case 2: Sneaking, no shield -> not blocking
		$human->setSneaking(true);
		self::assertFalse($human->isBlocking());

		// Case 3: Sneaking, shield in offhand -> automatically updates blocking to true
		$human->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		self::assertTrue($human->isBlocking());
		self::assertTrue($this->getGenericFlag($human->getNetworkProperties(), EntityMetadataFlags::BLOCKING));

		// Case 4: Stop sneaking -> blocking is false
		$human->setSneaking(false);
		self::assertFalse($human->isBlocking());
		self::assertFalse($this->getGenericFlag($human->getNetworkProperties(), EntityMetadataFlags::BLOCKING));

		// Case 5: Sneaking with shield in main hand -> blocking is true
		$human->getOffHandInventory()->setItem(0, VanillaItems::AIR());
		$human->getInventory()->setItemInHand(VanillaItems::SHIELD());
		$human->setSneaking(true);
		self::assertTrue($human->isBlocking());
		self::assertTrue($this->getGenericFlag($human->getNetworkProperties(), EntityMetadataFlags::BLOCKING));
	}

	public function testHasShieldEquipped() : void{
		$human = $this->createHuman();
		self::assertFalse($human->hasShieldEquipped());

		$human->getOffHandInventory()->setItem(0, VanillaItems::SHIELD());
		self::assertTrue($human->hasShieldEquipped());

		$human->getOffHandInventory()->setItem(0, VanillaItems::AIR());
		self::assertFalse($human->hasShieldEquipped());

		$human->getInventory()->setItemInHand(VanillaItems::SHIELD());
		self::assertTrue($human->hasShieldEquipped());
	}
}
