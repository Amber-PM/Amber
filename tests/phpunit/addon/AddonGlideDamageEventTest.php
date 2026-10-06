<?php

declare(strict_types=1);

namespace pocketmine\addon;

use Closure;
use PHPUnit\Framework\TestCase;
use pocketmine\addon\script\ScriptHost;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\RegisteredListener;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginManager;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AddonGlideDamageEventTest extends TestCase{
	private ScriptHost $host;
	private Player $player;
	private array $listeners = [];

	protected function setUp() : void{
		$this->host = (new ReflectionClass(ScriptHost::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(ScriptHost::class, "running"))->setValue($this->host, true);
		(new ReflectionProperty(ScriptHost::class, "subscriptions"))->setValue($this->host, ["after:entityHurt" => true, "after:entityDie" => true]);
		$manager = (new ReflectionClass(AddonManager::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(AddonManager::class, "scriptHost"))->setValue($manager, $this->host);
		$pluginManager = $this->createMock(PluginManager::class);
		$pluginManager->method("registerEvent")->willReturnCallback(function(string $event, Closure $handler, int $priority, Plugin $plugin, bool $handleCancelled = false) : RegisteredListener{
			$listener = new RegisteredListener($handler, $priority, $plugin, $handleCancelled, new TimingsHandler("Add-on glide damage test"));
			if($priority === EventPriority::MONITOR && ($event === EntityDamageEvent::class || $event === EntityDeathEvent::class)){
				HandlerListManager::global()->getListFor($event)->register($listener);
				$this->listeners[] = $listener;
			}
			return $listener;
		});
		$server = $this->createMock(Server::class);
		$server->method("getPluginManager")->willReturn($pluginManager);
		$runtime = (new ReflectionClass(AddonRuntime::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(PluginBase::class, "server"))->setValue($runtime, $server);
		$runtime->attach($manager);
		(new ReflectionMethod(AddonRuntime::class, "onEnable"))->invoke($runtime);
		$this->player = (new ReflectionClass(Player::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Player::class, "logger"))->setValue($this->player, $this->createMock(\Logger::class));
		(new ReflectionProperty(Entity::class, "id"))->setValue($this->player, 123);
		(new ReflectionProperty(Entity::class, "closed"))->setValue($this->player, true);
	}

	protected function tearDown() : void{
		foreach($this->listeners as $listener){
			HandlerListManager::global()->unregisterAll($listener);
		}
	}

	public function testWallDamageReachesHurtSubscriptionWithNativeCause() : void{
		(new EntityDamageEvent($this->player, EntityDamageEvent::CAUSE_FLY_INTO_WALL, 12))->call();
		$queue = (new ReflectionProperty(ScriptHost::class, "queue"))->getValue($this->host);
		self::assertCount(1, $queue);
		self::assertSame("entityHurt", $queue[0][0]);
		self::assertSame(123, $queue[0][1]["entity"]);
		self::assertSame(12.0, $queue[0][1]["damage"]);
		self::assertSame("flyIntoWall", $queue[0][1]["src"]["cause"]);
	}

	public function testWallDamageReachesPlayerDeathSubscriptionWithNativeCause() : void{
		$this->player->setLastDamageCause(new EntityDamageEvent($this->player, EntityDamageEvent::CAUSE_FLY_INTO_WALL, 24));
		(new PlayerDeathEvent($this->player, [], 0, ""))->call();
		$queue = (new ReflectionProperty(ScriptHost::class, "queue"))->getValue($this->host);
		self::assertCount(1, $queue);
		self::assertSame("entityDie", $queue[0][0]);
		self::assertSame(123, $queue[0][1]["entity"]);
		self::assertSame("flyIntoWall", $queue[0][1]["src"]["cause"]);
	}
}
