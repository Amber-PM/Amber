<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\command\overload\StringArgumentParser;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\scheduler\BulkCurlTask;
use pocketmine\scheduler\BulkCurlTaskOperation;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\InternetException;
use pocketmine\YmlServerProperties;
use Symfony\Component\Filesystem\Path;
use function http_build_query;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use const CURLOPT_AUTOREFERER;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;

class TimingsCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"timings",
			KnownTranslationFactory::pocketmine_command_timings_description(),
			KnownTranslationFactory::pocketmine_command_timings_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TIMINGS);

		$this->addOverload(
			fn(CommandSender $sender, string $mode) => $this->enable($sender),
			null,
			["mode" => new StringArgumentParser(["on"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $mode) => $this->disable($sender),
			null,
			["mode" => new StringArgumentParser(["off"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $mode) => $this->reset($sender),
			null,
			["mode" => new StringArgumentParser(["reset"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $mode) => $this->writeReport($sender),
			null,
			["mode" => new StringArgumentParser(["merged", "report"])]
		);
		$this->addOverload(
			fn(CommandSender $sender, string $mode) => $this->paste($sender),
			null,
			["mode" => new StringArgumentParser(["paste"])]
		);
	}

	private function enable(CommandSender $sender) : bool{
		if(TimingsHandler::isEnabled()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_timings_alreadyEnabled());
			return true;
		}
		TimingsHandler::setEnabled();
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_enable());
		return true;
	}

	private function disable(CommandSender $sender) : bool{
		TimingsHandler::setEnabled(false);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_disable());
		return true;
	}

	private function reset(CommandSender $sender) : bool{
		if(!TimingsHandler::isEnabled()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_timings_timingsDisabled());
			return true;
		}
		TimingsHandler::reload();
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_reset());
		return true;
	}

	private function writeReport(CommandSender $sender) : bool{
		if(!TimingsHandler::isEnabled()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_timings_timingsDisabled());
			return true;
		}
		TimingsHandler::createReportFile(Path::join($sender->getServer()->getDataPath(), "timings"))->onCompletion(
			function(string $timingsFile) use ($sender) : void{
				Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_timingsWrite($timingsFile));
			},
			fn() => $sender->getServer()->getLogger()->error("Failed to create timings report file")
		);
		return true;
	}

	private function paste(CommandSender $sender) : bool{
		if(!TimingsHandler::isEnabled()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_timings_timingsDisabled());
			return true;
		}
		$timingsPromise = TimingsHandler::requestPrintTimings();
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_collect());
		$timingsPromise->onCompletion(
			fn(array $lines) => $this->uploadReport($lines, $sender),
			fn() => throw new AssumptionFailedError("This promise is not expected to be rejected")
		);
		return true;
	}

	private function uploadReport(array $lines, CommandSender $sender) : void{
		$data = [
			"browser" => $agent = $sender->getServer()->getName() . " " . $sender->getServer()->getPocketMineVersion(),
			"data" => implode("\n", $lines),
			"private" => "true"
		];

		$host = $sender->getServer()->getConfigGroup()->getPropertyString(YmlServerProperties::TIMINGS_HOST, "timings.pmmp.io");

		$sender->getServer()->getAsyncPool()->submitTask(new BulkCurlTask(
			[new BulkCurlTaskOperation(
				"https://$host?upload=true",
				10,
				[],
				[
					CURLOPT_HTTPHEADER => [
						"User-Agent: $agent",
						"Content-Type: application/x-www-form-urlencoded"
					],
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => http_build_query($data),
					CURLOPT_AUTOREFERER => false,
					CURLOPT_FOLLOWLOCATION => false
				]
			)],
			function(array $results) use ($sender, $host) : void{
				if($sender instanceof Player && !$sender->isOnline()){
					return;
				}
				$result = $results[0];
				if($result instanceof InternetException){
					$sender->getServer()->getLogger()->logException($result);
					return;
				}
				$response = json_decode($result->getBody(), true);
				if(is_array($response) && isset($response["id"]) && (is_int($response["id"]) || is_string($response["id"]))){
					$url = "https://" . $host . "/?id=" . $response["id"];
					if(isset($response["access_token"]) && is_string($response["access_token"])){
						$url .= "&access_token=" . $response["access_token"];
					}else{
						$sender->getServer()->getLogger()->warning("Your chosen timings host does not support private reports. Anyone will be able to see your report if they guess the ID.");
					}
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_timingsRead($url));
				}else{
					$sender->getServer()->getLogger()->debug("Invalid response from timings server (" . $result->getCode() . "): " . $result->getBody());
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_pasteError());
				}
			}
		));
	}
}
