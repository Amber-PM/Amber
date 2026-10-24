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

namespace pocketmine\world\weather;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\world\World;
use function max;
use function min;
use function mt_rand;

class WeatherEngine{

	private WeatherState $state = WeatherState::CLEAR;
	private int $duration = 24000;
	private float $rainLevel = 0.0;
	private float $thunderLevel = 0.0;

	public function __construct(
		private World $world,
		private int $dimensionId = DimensionIds::OVERWORLD
	){
		if($this->dimensionId !== DimensionIds::OVERWORLD){
			$this->state = WeatherState::CLEAR;
			$this->rainLevel = 0.0;
			$this->thunderLevel = 0.0;
		}
	}

	public function getDimensionId() : int{
		return $this->dimensionId;
	}

	public function setDimensionId(int $dimensionId) : void{
		$this->dimensionId = $dimensionId;
		if($dimensionId !== DimensionIds::OVERWORLD){
			$this->setState(WeatherState::CLEAR, 1000000);
		}
	}

	public function getState() : WeatherState{
		return $this->state;
	}

	public function getDuration() : int{
		return $this->duration;
	}

	public function setDuration(int $duration) : void{
		$this->duration = max(0, $duration);
	}

	public function isRaining() : bool{
		return $this->state === WeatherState::RAIN || $this->state === WeatherState::THUNDER;
	}

	public function isThundering() : bool{
		return $this->state === WeatherState::THUNDER;
	}

	public function getRainLevel() : float{
		return $this->rainLevel;
	}

	public function getThunderLevel() : float{
		return $this->thunderLevel;
	}

	public function setState(WeatherState $state, int $durationTicks = 24000) : void{
		if($this->dimensionId !== DimensionIds::OVERWORLD && $state !== WeatherState::CLEAR){
			return;
		}

		$this->state = $state;
		$this->duration = max(1, $durationTicks);

		$this->broadcastWeather();
	}

	public function tick() : void{
		if($this->dimensionId !== DimensionIds::OVERWORLD){
			return;
		}

		if(--$this->duration <= 0){
			if($this->state === WeatherState::CLEAR){
				$nextState = mt_rand(1, 10) <= 8 ? WeatherState::RAIN : WeatherState::THUNDER;
				$nextDuration = $nextState === WeatherState::RAIN ? mt_rand(12000, 24000) : mt_rand(3600, 12000);
			}else{
				$nextState = WeatherState::CLEAR;
				$nextDuration = mt_rand(12000, 168000);
			}

			$this->setState($nextState, $nextDuration);
		}

		// Smooth levels adjustment
		$targetRain = $this->isRaining() ? 1.0 : 0.0;
		$targetThunder = $this->isThundering() ? 1.0 : 0.0;

		if($this->rainLevel < $targetRain){
			$this->rainLevel = (float) min($targetRain, $this->rainLevel + 0.01);
		}elseif($this->rainLevel > $targetRain){
			$this->rainLevel = (float) max($targetRain, $this->rainLevel - 0.01);
		}

		if($this->thunderLevel < $targetThunder){
			$this->thunderLevel = (float) min($targetThunder, $this->thunderLevel + 0.01);
		}elseif($this->thunderLevel > $targetThunder){
			$this->thunderLevel = (float) max($targetThunder, $this->thunderLevel - 0.01);
		}
	}

	public function broadcastWeather() : void{
		$rainPacket = $this->isRaining()
			? LevelEventPacket::create(LevelEvent::START_RAIN, $this->duration, new Vector3(0, 0, 0))
			: LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, new Vector3(0, 0, 0));

		$thunderPacket = $this->isThundering()
			? LevelEventPacket::create(LevelEvent::START_THUNDER, $this->duration, new Vector3(0, 0, 0))
			: LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, new Vector3(0, 0, 0));

		$this->world->broadcastPacketToViewers(new Vector3(0, 0, 0), $rainPacket);
		$this->world->broadcastPacketToViewers(new Vector3(0, 0, 0), $thunderPacket);
	}

	public function sendWeather(Player $player) : void{
		$rainPacket = $this->isRaining()
			? LevelEventPacket::create(LevelEvent::START_RAIN, $this->duration, new Vector3(0, 0, 0))
			: LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, new Vector3(0, 0, 0));

		$thunderPacket = $this->isThundering()
			? LevelEventPacket::create(LevelEvent::START_THUNDER, $this->duration, new Vector3(0, 0, 0))
			: LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, new Vector3(0, 0, 0));

		$player->getNetworkSession()->sendDataPacket($rainPacket);
		$player->getNetworkSession()->sendDataPacket($thunderPacket);
	}
}
