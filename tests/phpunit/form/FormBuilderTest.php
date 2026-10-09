<?php

declare(strict_types=1);

namespace pocketmine\form;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;

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

	public function testMenuFormClientDerivedFixturesAcrossVersions() : void{
		$raw = file_get_contents(__DIR__ . "/fixtures/bedrock-menu-form-responses.json");
		self::assertNotFalse($raw);
		$fixtures = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

		foreach(["1.21.60", "1.21.70", "1.26.44"] as $version){
			$fixture = $fixtures[$version];
			$protocol = (int) $fixture["protocol"];

			// 1. Verify serialization matches client-accepted wire request structure
			$clicked = $submitted = null;
			$form = $this->menu($clicked, $submitted);
			$serialized = $form->serializeFor($protocol);
			self::assertSame($fixture["wireRequest"], $serialized, "Wire serialization mismatch for $version");

			// 2. Verify client responses
			foreach($fixture["responses"] as $resp){
				$clicked = $submitted = null;
				$closedReason = null;
				$testForm = $this->menu($clicked, $submitted)
					->onClose(function(Player $p, FormCloseReason $r) use (&$closedReason) : void{
						$closedReason = $r;
					});

				if($resp["wireData"] !== null){
					$wireData = json_decode($resp["wireData"], true, flags: JSON_THROW_ON_ERROR);
					$testForm->handleResponse($this->player(), $wireData);
					self::assertSame($resp["expectedClicked"], $clicked, "$version button click mismatch");
					self::assertSame($resp["expectedSubmitted"], $submitted, "$version submit mismatch");
				}elseif($resp["cancelReason"] !== null){
					$reason = $resp["cancelReason"] === ModalFormResponsePacket::CANCEL_REASON_USER_BUSY ? FormCloseReason::BUSY : FormCloseReason::CLOSED;
					$testForm->handleClose($this->player(), $reason);
					$expected = $resp["expectedCloseReason"] === "busy" ? FormCloseReason::BUSY : FormCloseReason::CLOSED;
					self::assertSame($expected, $closedReason, "$version close reason mismatch");
				}
			}

			// 3. For versions using elements (1.21.70+), verify that raw element index (4) is rejected
			if($protocol >= BaseForm::ELEMENTS_PROTOCOL){
				try{
					$form->handleResponse($this->player(), 4);
					self::fail("MenuForm should reject raw element index 4 on $version");
				}catch(FormValidationException){
					// Expected: button count is 2 (valid indices 0 and 1)
				}
			}
		}
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

	public function testSliderRejectsNonFiniteParameters() : void{
		$caught = 0;
		$cases = [
			[NAN, 100.0, 1.0, null],
			[0.0, INF, 1.0, null],
			[0.0, 100.0, NAN, null],
			[0.0, 100.0, INF, null],
			[0.0, 100.0, -1.0, null],
			[0.0, 100.0, 0.0, null],
			[0.0, 100.0, 1.0, NAN],
			[0.0, 100.0, 1.0, 150.0],
			[10.0, 5.0, 1.0, null],
		];
		foreach($cases as [$min, $max, $step, $default]){
			try{
				(new CustomForm("t"))->slider("s", "S", (float) $min, (float) $max, (float) $step, $default !== null ? (float) $default : null);
				self::fail("accepted invalid slider parameters: min=$min, max=$max, step=$step, default=" . var_export($default, true));
			}catch(\InvalidArgumentException){
				$caught++;
			}
		}
		self::assertSame(count($cases), $caught);
	}

	public function testSliderRejectsOffStepResponses() : void{
		$form = (new CustomForm("t"))->slider("s", "S", 0, 100, 5);
		$this->expectException(FormValidationException::class);
		$form->handleResponse($this->player(), [3.7]);
	}

	public function testPacketHandlerAndPlayerIntegration() : void{
		$player = $this->player();
		$session = $this->createMock(NetworkSession::class);
		$session->method("getLogger")->willReturn(new \PrefixedLogger(\GlobalLogger::get(), "test"));

		$handler = (new \ReflectionClass(InGamePacketHandler::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(InGamePacketHandler::class, "player"))->setValue($handler, $player);
		(new \ReflectionProperty(InGamePacketHandler::class, "session"))->setValue($handler, $session);
		(new \ReflectionProperty(InGamePacketHandler::class, "forceMoveSync"))->setValue($handler, false);

		$reasons = [];
		$form = (new ConfirmForm("t", "b"))->onClose(function(Player $p, FormCloseReason $r) use (&$reasons) : void{
			$reasons[] = $r;
		});

		$formsProp = new \ReflectionProperty(Player::class, "forms");

		// Test 1: ModalFormResponsePacket CANCEL_REASON_CLOSED
		$formsProp->setValue($player, [1 => $form]);
		$pkClosed = ModalFormResponsePacket::cancel(1, ModalFormResponsePacket::CANCEL_REASON_CLOSED);
		self::assertTrue($handler->handleModalFormResponse($pkClosed));
		self::assertSame([FormCloseReason::CLOSED], $reasons);
		self::assertFalse($player->hasPendingForm(1));

		// Test 2: ModalFormResponsePacket CANCEL_REASON_USER_BUSY
		$formsProp->setValue($player, [2 => $form]);
		$pkBusy = ModalFormResponsePacket::cancel(2, ModalFormResponsePacket::CANCEL_REASON_USER_BUSY);
		self::assertTrue($handler->handleModalFormResponse($pkBusy));
		self::assertSame([FormCloseReason::CLOSED, FormCloseReason::BUSY], $reasons);
		self::assertFalse($player->hasPendingForm(2));

		// Test 3: Legacy Form (without ClosableForm) fallback via packet handler and Player::onFormSubmit
		$legacyResponses = [];
		$legacyForm = new class(function(Player $p, mixed $data) use (&$legacyResponses) : void{
			$legacyResponses[] = $data;
		}) implements Form{
			public function __construct(private \Closure $handler){}

			public function handleResponse(Player $player, mixed $data) : void{
				($this->handler)($player, $data);
			}

			public function jsonSerialize() : array{
				return ["type" => "form", "title" => "Legacy", "content" => "", "buttons" => []];
			}
		};

		// 3a. Legacy form receives cancel (CLOSED) -> Player fallback routes to handleResponse($player, null)
		$formsProp->setValue($player, [10 => $legacyForm]);
		$pkLegacyClosed = ModalFormResponsePacket::cancel(10, ModalFormResponsePacket::CANCEL_REASON_CLOSED);
		self::assertTrue($handler->handleModalFormResponse($pkLegacyClosed));
		self::assertSame([null], $legacyResponses);
		self::assertFalse($player->hasPendingForm(10));

		// 3b. Legacy form receives cancel (USER_BUSY) -> Player fallback routes to handleResponse($player, null)
		$formsProp->setValue($player, [11 => $legacyForm]);
		$pkLegacyBusy = ModalFormResponsePacket::cancel(11, ModalFormResponsePacket::CANCEL_REASON_USER_BUSY);
		self::assertTrue($handler->handleModalFormResponse($pkLegacyBusy));
		self::assertSame([null, null], $legacyResponses);
		self::assertFalse($player->hasPendingForm(11));

		// 3c. Legacy form receives valid response data -> Player routes to handleResponse($player, 0)
		$formsProp->setValue($player, [12 => $legacyForm]);
		$pkLegacyValid = ModalFormResponsePacket::response(12, json_encode(0));
		self::assertTrue($handler->handleModalFormResponse($pkLegacyValid));
		self::assertSame([null, null, 0], $legacyResponses);
		self::assertFalse($player->hasPendingForm(12));

		// Test 4: CustomForm slider response via packet handler
		$sliderVal = null;
		$customForm = (new CustomForm("Settings"))
			->slider("vol", "Volume", 0, 100, 5)
			->onSubmit(function(Player $p, CustomFormResponse $r) use (&$sliderVal) : void{
				$sliderVal = $r->getFloat("vol");
			});

		$formsProp->setValue($player, [3 => $customForm]);
		$pkValid = ModalFormResponsePacket::response(3, json_encode([50.0]));
		self::assertTrue($handler->handleModalFormResponse($pkValid));
		self::assertSame(50.0, $sliderVal);
		self::assertFalse($player->hasPendingForm(3));

		// Test 5: Off-step response [3.7] via packet handler is rejected by Player::onFormSubmit
		$formsProp->setValue($player, [4 => $customForm]);
		$pkOffStep = ModalFormResponsePacket::response(4, json_encode([3.7]));
		self::assertTrue($handler->handleModalFormResponse($pkOffStep));
		// Slider callback was not invoked and form was removed due to validation failure
		self::assertSame(50.0, $sliderVal);
		self::assertFalse($player->hasPendingForm(4));
	}
}
