<?php

declare(strict_types=1);

namespace pocketmine\entity\ai\goal;

use pocketmine\entity\Mob;
use pocketmine\item\Armor;
use pocketmine\world\World;
use function floor;

final class SunlightBurnGoal extends Goal{

	public function __construct(
		private Mob $mob,
		int $priority = 1
	){
		parent::__construct($priority, 0);
	}

	public function canStart() : bool{
		return true;
	}

	public function shouldContinue() : bool{
		return true;
	}

	public function tick() : void{
		// Only check every 20 ticks (1 second) to optimize server performance
		if($this->mob->getWorld()->getServer()?->getTick() % 20 !== 0){
			return;
		}

		$this->checkBurn();
	}

	public function checkBurn() : void{
		$world = $this->mob->getWorld();
		$time = $world->getTimeOfDay();

		// Only burn during daytime (night is between sunset 12000 and sunrise 23000)
		if($time >= World::TIME_SUNSET && $time <= World::TIME_SUNRISE){
			return;
		}

		// Rain prevents daylight burning
		if(method_exists($world, "isRaining") && $world->isRaining()){
			return;
		}

		// Water submergence prevents daylight burning
		if($this->mob->isUnderwater()){
			return;
		}

		// Wearing any helmet prevents burning (instead damages helmet)
		if($this->mob->getArmorInventory()->getHelmet() instanceof Armor){
			return;
		}

		$loc = $this->mob->getLocation();
		$highestBlockY = $world->getHighestBlockAt((int) floor($loc->x), (int) floor($loc->z));

		// If highest solid block is at or below mob's feet, mob is directly exposed to sky
		if($highestBlockY !== null && $highestBlockY <= floor($loc->y)){
			$this->mob->setOnFire(8);
		}
	}
}
