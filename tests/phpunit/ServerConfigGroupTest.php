<?php

declare(strict_types=1);

namespace pocketmine;

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Config;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ServerConfigGroupTest extends TestCase{
	public function testEducationSettingDefaultsToEnabledWhenMissing() : void{
		foreach([
			"settings:\n  force-language: false\n" => true,
			"education:\n  enabled: false\n" => false,
			"education:\n  enabled: true\n" => true
		] as $yaml => $expected){
			$yamlPath = tempnam(sys_get_temp_dir(), "amber-education-yml-");
			$propertiesPath = tempnam(sys_get_temp_dir(), "amber-education-properties-");
			if($yamlPath === false || $propertiesPath === false){
				throw new \RuntimeException("Unable to create temporary config files");
			}
			try{
				file_put_contents($yamlPath, $yaml);
				$config = new ServerConfigGroup(
					new Config($yamlPath, Config::YAML),
					new Config($propertiesPath, Config::PROPERTIES)
				);
				self::assertSame($expected, $config->getPropertyBool(YmlServerProperties::EDUCATION_ENABLED, true));
			}finally{
				unlink($yamlPath);
				unlink($propertiesPath);
			}
		}
	}
}
