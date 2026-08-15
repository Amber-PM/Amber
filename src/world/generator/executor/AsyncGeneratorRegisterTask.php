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

namespace pocketmine\world\generator\executor;

use pocketmine\scheduler\AsyncTask;

class AsyncGeneratorRegisterTask extends AsyncTask{

	public function __construct(
		private readonly GeneratorExecutorSetupParameters $setupParameters,
		private readonly int $contextId
	){}

	public function onRun() : void{
		$setupParameters = $this->setupParameters;
		$generator = $setupParameters->createGenerator();
		ThreadLocalGeneratorContext::register(new ThreadLocalGeneratorContext($generator, $setupParameters->worldMinY, $setupParameters->worldMaxY), $this->contextId);
	}
}
