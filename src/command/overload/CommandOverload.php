<?php

declare(strict_types=1);

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use pocketmine\command\overload\attribute\ParserAttribute;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReflectionAttribute;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use function array_slice;
use function call_user_func_array;
use function count;
use function implode;
use function is_a;
use const PHP_INT_MAX;

final class CommandOverload{

	private string $senderClass;

	private array $parsers = [];

	private array $paramNames = [];

	private array $optional = [];

	private int $minTokens = 0;

	private int $maxTokens = 0;

	private bool $greedyTail = false;

	public function __construct(
		private \Closure $handler,
		private ?string $permission = null,
		array $explicitParsers = []
	){
		$reflection = new ReflectionFunction($handler);
		$params = $reflection->getParameters();
		if(count($params) === 0){
			throw new RuntimeException("Overload handler must declare at least a CommandSender parameter");
		}

		$senderParam = $params[0];
		$senderType = $senderParam->getType();
		if(!($senderType instanceof ReflectionNamedType) || $senderType->isBuiltin() || !is_a($senderType->getName(), CommandSender::class, true)){
			throw new RuntimeException("The first parameter of an overload handler must be typed as CommandSender or a subclass");
		}
		$this->senderClass = $senderType->getName();

		$sawOptional = false;
		$argParams = array_slice($params, 1);
		$lastIndex = count($argParams) - 1;
		foreach($argParams as $index => $param){
			$type = $param->getType();
			if(!($type instanceof ReflectionNamedType)){
				throw new RuntimeException("Parameter \${$param->getName()} must have a declared type");
			}

			$parser = $explicitParsers[$index] ?? $explicitParsers[$param->getName()] ?? self::createAttributeParser($param) ?? self::createDefaultParser($type->getName());
			$isGreedy = $parser instanceof GreedyStringArgumentParser;
			if($isGreedy){
				if($index !== $lastIndex){
					throw new RuntimeException("Parameter \${$param->getName()} must be the last parameter to consume the rest of the input");
				}
				$this->greedyTail = true;
			}

			$optional = $param->isOptional() || $type->allowsNull();
			if($optional){
				$sawOptional = true;
			}elseif($sawOptional){
				throw new RuntimeException("Required parameter \${$param->getName()} cannot follow an optional parameter");
			}

			$this->parsers[] = $parser;
			$this->paramNames[] = $param->getName();
			$this->optional[] = $optional;

			$consumed = $isGreedy ? 0 : $parser->getConsumedTokens();
			$this->maxTokens += $consumed;
			if(!$optional){
				$this->minTokens += $consumed;
			}
		}
	}

	private static function createAttributeParser(ReflectionParameter $param) : ?ArgumentParser{
		$attributes = $param->getAttributes(ParserAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
		if(count($attributes) === 0){
			return null;
		}
		if(count($attributes) > 1){
			throw new RuntimeException("Parameter \${$param->getName()} cannot have more than one parser attribute");
		}

		return $attributes[0]->newInstance()->createParser();
	}

	private static function createDefaultParser(string $typeName) : ArgumentParser{
		return match($typeName){
			"int" => new IntegerArgumentParser(),
			"float" => new FloatArgumentParser(),
			"string" => new StringArgumentParser(),
			"bool" => new BoolArgumentParser(),
			Player::class => new PlayerArgumentParser(),
			Vector3::class => new Vector3ArgumentParser(),
			default => throw new RuntimeException("No default argument parser registered for type $typeName"),
		};
	}

	public function getParameterDefinitions() : array{
		$definitions = [];
		foreach($this->paramNames as $i => $name){
			$definitions[] = ["name" => $name, "parser" => $this->parsers[$i], "optional" => $this->optional[$i]];
		}

		return $definitions;
	}

	public function acceptsSender(CommandSender $sender) : bool{
		return $sender instanceof $this->senderClass;
	}

	public function getMinTokens() : int{
		return $this->minTokens;
	}

	public function getMaxTokens() : int{
		return $this->greedyTail ? PHP_INT_MAX : $this->maxTokens;
	}

	public function getPermission() : ?string{
		return $this->permission;
	}

	public function getUsage(string $label) : string{
		$parts = ["/$label"];
		foreach($this->paramNames as $i => $name){
			$hint = "$name: " . $this->parsers[$i]->getTypeHint();
			$parts[] = $this->optional[$i] ? "[$hint]" : "<$hint>";
		}

		return implode(" ", $parts);
	}

	public function tryParse(CommandSender $sender, array $tokens) : ParseAttempt{
		$tokenCount = count($tokens);
		if($tokenCount < $this->minTokens || $tokenCount > $this->getMaxTokens()){
			return ParseAttempt::fail("Wrong number of arguments");
		}

		$values = [];
		$offset = 0;
		$remaining = $tokenCount;

		foreach($this->parsers as $i => $parser){
			$isGreedy = $parser instanceof GreedyStringArgumentParser;
			$consumed = $isGreedy ? $remaining : $parser->getConsumedTokens();
			if(!$isGreedy && $this->optional[$i] && $remaining < $consumed){
				break;
			}

			$slice = array_slice($tokens, $offset, $consumed);
			if(!$isGreedy && count($slice) < $consumed){
				return ParseAttempt::fail("Wrong number of arguments");
			}

			$result = $parser->parse($slice, $sender, $values);
			if(!$result->isOk()){
				return ParseAttempt::fail($result->getError());
			}

			$values[] = $result->getValue();
			$offset += $consumed;
			$remaining -= $consumed;
		}

		return ParseAttempt::ok($values);
	}

	public function call(CommandSender $sender, array $values) : mixed{
		return call_user_func_array($this->handler, [$sender, ...$values]);
	}
}
