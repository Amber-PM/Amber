<?php

declare(strict_types=1);

namespace pocketmine\form;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
require_once __DIR__ . '/../../../src/form/ClosableForm.php';
require_once __DIR__ . '/../../../src/form/ProtocolAwareForm.php';
require_once __DIR__ . '/../../../src/form/FormCloseReason.php';
require_once __DIR__ . '/../../../src/form/BaseForm.php';
require_once __DIR__ . '/../../../src/form/MenuForm.php';
require_once __DIR__ . '/../../../src/form/ConfirmForm.php';
require_once __DIR__ . '/../../../src/form/CustomFormResponse.php';
require_once __DIR__ . '/../../../src/form/CustomForm.php';

final class FormBuilderTest extends TestCase{
	private function player() : Player{
		//never used by the forms beyond being passed to callbacks; marked closed so its destructor does nothing
		$player = (new \ReflectionClass(Player::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, new \PrefixedLogger(\GlobalLogger::get(), "test"));
		return $player;
	}

	private function menu(?int &$clicked, ?int &$submitted) : MenuForm{
		return (new MenuForm("Games", "Pick one"))
			->header("Solo")
			->button("Parkour", "textures/items/feather", function(Player $p) use (&$clicked) : void{ $clicked = 0; })
			->divider()
			->label("Team games")
			->button("Bed Wars", "https://example.com/b.png")
			->onSubmit(function(Player $p, int $button) use (&$submitted) : void{ $submitted = $button; });
	}

	public function testMenuFormUsesElementsOnNewClients() : void{
		$clicked = $submitted = null;
		$json = $this->menu($clicked, $submitted)->serializeFor(ProtocolInfo::PROTOCOL_1_21_70);
		self::assertSame("form", $json["type"]);
		self::assertSame("Pick one", $json["content"]);
		self::assertSame([
			["type" => "header", "text" => "Solo"],
			["type" => "button", "text" => "Parkour", "image" => ["type" => "path", "data" => "textures/items/feather"]],
			["type" => "divider", "text" => ""],
			["type" => "label", "text" => "Team games"],
			["type" => "button", "text" => "Bed Wars", "image" => ["type" => "url", "data" => "https://example.com/b.png"]],
		], $json["elements"]);
	}

	public function testMenuFormFallsBackToBodyTextOnOldClients() : void{
		$clicked = $submitted = null;
		$json = $this->menu($clicked, $submitted)->serializeFor(ProtocolInfo::PROTOCOL_1_21_60);
		self::assertSame("Pick one\nSolo\nTeam games", $json["content"]);
		self::assertSame([
			["text" => "Parkour", "image" => ["type" => "path", "data" => "textures/items/feather"]],
			["text" => "Bed Wars", "image" => ["type" => "url", "data" => "https://example.com/b.png"]],
		], $json["buttons"]);
		self::assertArrayNotHasKey("elements", $json);
	}

	public function testMenuFormButtonIndexesCountButtonsOnly() : void{
		$clicked = $submitted = null;
		$form = $this->menu($clicked, $submitted);
		$form->handleResponse($this->player(), 0);
		self::assertSame(0, $clicked);
		self::assertSame(0, $submitted);
		$form->handleResponse($this->player(), 1);
		self::assertSame(1, $submitted);
	}

	public function testMenuFormRejectsInvalidAnswers() : void{
		$clicked = $submitted = null;
		$form = $this->menu($clicked, $submitted);
		foreach([2, -1, "0", 1.0, [0]] as $bad){
			try{
				$form->handleResponse($this->player(), $bad);
				self::fail("accepted " . var_export($bad, true));
			}catch(FormValidationException){
			}
		}
		self::assertNull($submitted);
	}

	public function testCloseReasons() : void{
		$reasons = [];
		$form = (new ConfirmForm("t", "b"))->onClose(function(Player $p, FormCloseReason $r) use (&$reasons) : void{ $reasons[] = $r; });
		$form->handleResponse($this->player(), null);
		$form->handleClose($this->player(), FormCloseReason::BUSY);
		self::assertSame([FormCloseReason::CLOSED, FormCloseReason::BUSY], $reasons);
	}

	public function testConfirmForm() : void{
		$answer = null;
		$form = (new ConfirmForm("Leave", "Sure?", "Leave", "Stay"))->onSubmit(function(Player $p, bool $a) use (&$answer) : void{ $answer = $a; });
		self::assertSame(["type" => "modal", "title" => "Leave", "content" => "Sure?", "button1" => "Leave", "button2" => "Stay"], $form->serializeFor(ProtocolInfo::CURRENT_PROTOCOL));
		$form->handleResponse($this->player(), false);
		self::assertFalse($answer);
		$this->expectException(FormValidationException::class);
		$form->handleResponse($this->player(), 1);
	}

	private function settings(?CustomFormResponse &$response) : CustomForm{
		return (new CustomForm("Settings"))
			->header("Chat")
			->toggle("mentions", "Mentions", true)
			->dropdown("lang", "Language", ["English", "Español"], 1)
			->divider()
			->input("nick", "Nickname", "Steve")
			->slider("volume", "Volume", 0, 100, 5, 50)
			->stepSlider("size", "Size", ["S", "M", "L"])
			->onSubmit(function(Player $p, CustomFormResponse $r) use (&$response) : void{ $response = $r; });
	}

	public function testCustomFormSerialization() : void{
		$response = null;
		$form = $this->settings($response);
		$new = $form->serializeFor(ProtocolInfo::PROTOCOL_1_21_70)["content"];
		self::assertSame(["type" => "header", "text" => "Chat"], $new[0]);
		self::assertSame(["type" => "divider", "text" => ""], $new[3]);
		self::assertSame(["type" => "dropdown", "text" => "Language", "options" => ["English", "Español"], "default" => 1], $new[2]);
		$old = $form->serializeFor(ProtocolInfo::PROTOCOL_1_21_60)["content"];
		self::assertSame("label", $old[0]["type"]);
		self::assertSame(["type" => "label", "text" => ""], $old[3]);
		self::assertCount(7, $old);
	}

	public function testCustomFormAnswers() : void{
		$response = null;
		$form = $this->settings($response);
		$form->handleResponse($this->player(), [null, false, 0, null, "Alex", 35, 2]);
		self::assertNotNull($response);
		self::assertTrue($response->has("mentions"));
		self::assertFalse($response->has("non_existent"));
		self::assertFalse($response->getBool("mentions"));
		self::assertSame(0, $response->getInt("lang"));
		self::assertSame("Alex", $response->getString("nick"));
		self::assertSame(35.0, $response->getFloat("volume"));
		self::assertSame(2, $response->getInt("size"));
	}

	public function testCustomFormMissingElementThrows() : void{
		$response = null;
		$form = $this->settings($response);
		$form->handleResponse($this->player(), [null, false, 0, null, "Alex", 35, 2]);
		self::assertNotNull($response);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('No answer for form element "missing"');
		$response->getString("missing");
	}

	public function testCustomFormRejectsInvalidAnswers() : void{
		foreach([
			[null, false, 0, null, "Alex", 35],             //too few
			[null, "yes", 0, null, "Alex", 35, 2],          //toggle not bool
			[null, false, 2, null, "Alex", 35, 2],          //dropdown out of range
			[null, false, 0, null, 5, 35, 2],               //input not text
			[null, false, 0, null, "Alex", 101, 2],         //slider out of range
			[null, false, 0, null, "Alex", 35, -1],         //step out of range
			["a" => 1],                                     //not a list
		] as $bad){
			$response = null;
			try{
				$this->settings($response)->handleResponse($this->player(), $bad);
				self::fail("accepted " . json_encode($bad));
			}catch(FormValidationException){
			}
			self::assertNull($response);
		}
	}

	public function testDuplicateIdsRejected() : void{
		$this->expectException(\InvalidArgumentException::class);
		(new CustomForm("x"))->toggle("a", "A")->input("a", "A");
	}
}
