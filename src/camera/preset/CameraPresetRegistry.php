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

namespace pocketmine\camera\preset;

use pocketmine\network\mcpe\protocol\CameraPresetsPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\SingletonTrait;
use function array_keys;
use function count;
use function max;

final class CameraPresetRegistry{
	use SingletonTrait;

	public const MAX_CUSTOM_PRESETS = 64;

	/** @var array<string, CameraPreset> identifier -> preset definition (registration order) */
	private array $customPresets = [];

	/** @var array<string, int> identifier -> effective minimum protocol after inheritance validation */
	private array $effectiveMinProtocols = [];

	/** @var string[] topologically sorted custom preset identifiers (parents before children) */
	private array $topologicalOrder = [];

	private bool $frozen = false;

	/** @var array<int, array<string, int>> protocolId -> (identifier -> index) */
	private array $catalogIndices = [];

	public function __construct(){}

	public function register(CameraPreset $preset) : void{
		if($this->frozen){
			throw new \LogicException("Cannot register camera presets after startup phase has concluded");
		}
		if(count($this->customPresets) >= self::MAX_CUSTOM_PRESETS){
			throw new \OverflowException("Custom camera preset capacity exceeded (maximum " . self::MAX_CUSTOM_PRESETS . ")");
		}

		$id = $preset->getIdentifier();
		CameraPreset::validateIdentifier($id, true);

		if(isset($this->customPresets[$id]) || BuiltInCameraPresets::isBuiltin($id)){
			throw new \InvalidArgumentException("Camera preset identifier already registered: $id");
		}

		$this->customPresets[$id] = $preset;
		$this->catalogIndices = [];
	}

	public function get(string $identifier) : ?CameraPreset{
		return $this->customPresets[$identifier] ?? null;
	}

	public function isRegistered(string $identifier) : bool{
		return BuiltInCameraPresets::isBuiltin($identifier) || isset($this->customPresets[$identifier]);
	}

	public function freeze() : void{
		if($this->frozen){
			return;
		}

		$visited = [];
		$visiting = [];
		$sorted = [];
		$effectiveMin = [];

		foreach($this->customPresets as $id => $preset){
			if(!isset($visited[$id])){
				$this->dfsValidate($id, $visiting, $visited, $sorted, $effectiveMin);
			}
		}

		$this->topologicalOrder = $sorted;
		$this->effectiveMinProtocols = $effectiveMin;
		$this->catalogIndices = [];
		$this->frozen = true;
	}

	public function isFrozen() : bool{
		return $this->frozen;
	}

	/**
	 * @internal Used internally by PlayerCamera to resolve protocol-specific runtime preset ids
	 */
	public function getIndex(string $identifier, int $protocolId) : ?int{
		if(!$this->frozen){
			throw new \LogicException("Cannot query camera preset runtime IDs before registry freeze");
		}
		$this->ensureCatalogCompiled($protocolId);
		return $this->catalogIndices[$protocolId][$identifier] ?? null;
	}

	/**
	 * @internal Used internally by PreSpawnPacketHandler to dispatch protocol-specific presets packet
	 */
	public function getPresetsPacket(int $protocolId) : CameraPresetsPacket{
		if(!$this->frozen){
			throw new \LogicException("Cannot query camera presets packet before registry freeze");
		}
		$this->ensureCatalogCompiled($protocolId);
		$presets = [];
		foreach(array_keys($this->catalogIndices[$protocolId]) as $identifier){
			$presets[] = (BuiltInCameraPresets::isBuiltin($identifier)
				? CameraPreset::builtin($identifier)
				: $this->customPresets[$identifier])->toProtocol();
		}
		return CameraPresetsPacket::create($presets);
	}

	/**
	 * @param array<string, bool> $visiting
	 * @param array<string, bool> $visited
	 * @param string[]            $sorted
	 * @param array<string, int>  $effectiveMin
	 */
	private function dfsValidate(string $id, array &$visiting, array &$visited, array &$sorted, array &$effectiveMin) : void{
		$visiting[$id] = true;
		$preset = $this->customPresets[$id];
		$parent = $preset->getParent();

		if($parent === $id){
			throw new \InvalidArgumentException("Self-referencing parent detected in preset '$id'");
		}

		if(BuiltInCameraPresets::isBuiltin($parent)){
			if($parent === BuiltInCameraPresets::CONTROL_SCHEME_CAMERA){
				throw new \InvalidArgumentException("Camera preset '$id' cannot inherit from '$parent'");
			}
			$parentEffectiveMin = BuiltInCameraPresets::getMinimumProtocol($parent) ?? ProtocolInfo::PROTOCOL_1_20_0;
		}elseif(isset($this->customPresets[$parent])){
			if(isset($visiting[$parent])){
				throw new \InvalidArgumentException("Cyclic parent inheritance detected involving '$id' and '$parent'");
			}
			if(!isset($visited[$parent])){
				$this->dfsValidate($parent, $visiting, $visited, $sorted, $effectiveMin);
			}
			$parentEffectiveMin = $effectiveMin[$parent];
		}else{
			throw new \InvalidArgumentException("Unknown parent preset '$parent' referenced by '$id'");
		}

		unset($visiting[$id]);
		$visited[$id] = true;
		$sorted[] = $id;
		$effectiveMin[$id] = max($preset->getMinimumProtocol(), $parentEffectiveMin);
	}

	private function ensureCatalogCompiled(int $protocolId) : void{
		if(isset($this->catalogIndices[$protocolId])){
			return;
		}

		$indices = [];
		$idx = 0;

		foreach(BuiltInCameraPresets::getAll() as $builtinId){
			if(BuiltInCameraPresets::isAvailableOn($builtinId, $protocolId)){
				$indices[$builtinId] = $idx++;
			}
		}

		foreach($this->topologicalOrder as $customId){
			$customPreset = $this->customPresets[$customId];
			$effMin = $this->effectiveMinProtocols[$customId] ?? $customPreset->getMinimumProtocol();

			if($protocolId >= $effMin){
				$indices[$customId] = $idx++;
			}
		}

		$this->catalogIndices[$protocolId] = $indices;
	}

	/**
	 * @return string[]
	 */
	public function getRegisteredIdentifiers() : array{
		return array_keys($this->customPresets);
	}
}
