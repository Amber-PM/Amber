# Multiprotocol optimization audit

Audited branch: `dev-funao` (AmberPM, based on PocketMine-MP 5.44.2, protocols 589-2193).
Scope: findings F-01, F-07, F-05 and F-04, implemented one phase at a time.
Findings come from static analysis. None is measured unless a row says so.
No PHP runtime was available while writing Phase 1: no test, `phpstan` or benchmark was executed.

## Phase 1 - F-01

| Classification | Location | Evidence | Minimal fix | Regression test |
| --- | --- | --- | --- | --- |
| ALREADY APPLIED | `src/world/World.php`, `broadcastPacketToPlayersByTypeConverterUsingChunk` | Private method (l. 980) contains no `validateCallableSignature` call | None | `WorldBroadcastValidationTest::testPublicBroadcastQueuesValidClosures` |
| ALREADY APPLIED | `src/world/World.php`, `broadcastPacketToViewersByTypeConverter` | Public method (l. 968) still validates before queuing; `Bell.php` l. 150 stays validated | None | `WorldBroadcastValidationTest::testPublicBroadcastRejectsInvalidClosure` |
| ALREADY APPLIED | `src/world/World.php`, `addSound`, `addParticle`, `actuallyDoTick` | Internal calls use the private method (l. 765, 814, 1239) or `TypeConverter::broadcastByTypeConverterUnchecked` (l. 767, 816, 1254) | None | Existing code path; no behavior change |
| ALREADY APPLIED | `src/network/mcpe/convert/TypeConverter.php`, `broadcastByTypeConverter` / `broadcastByTypeConverterUnchecked` | Public method (l. 470) validates and delegates; `Unchecked` (l. 484) carries `@internal` in its docblock (option A of the original proposal) | None | `TypeConverterBroadcastValidationTest` |
| KEEP AS-IS | `Entity.php` l. 1781, `Human.php` l. 183, `PistonArm.php` l. 138 | External or semi-external closures reach `broadcastByTypeConverter`, which keeps validating | Preserve | `TypeConverterBroadcastValidationTest::testPublicBroadcastRejectsInvalidClosure` |
| TEST GAP | `tests/phpunit` | `grep` for `ByTypeConverter` and `validateCallableSignature` found no test of the public validation | Add tests only; no production change | The two test classes above |

Line numbers refer to the uploaded archive and were verified against the code.

## Phase 1 changes

- `tests/phpunit/world/WorldBroadcastValidationTest.php`: invalid closures (wrong return type, wrong parameter type) throw `TypeError` from `World::broadcastPacketToViewersByTypeConverter` and leave the chunk buffer empty; valid closures (typed and untyped parameter) are queued under the chunk hash of the position.
- `tests/phpunit/network/mcpe/convert/TypeConverterBroadcastValidationTest.php`: invalid closures throw `TypeError` from `broadcastByTypeConverter`; valid closures are accepted; `broadcastByTypeConverterUnchecked` does not validate.
- `tests/bench/BroadcastValidationBenchmark.php`: microbenchmark of validated and unchecked paths, run with `php tests/bench/BroadcastValidationBenchmark.php [iterations]`.
- No production file was modified.

## Verification status

| Check | Result |
| --- | --- |
| New PHPUnit tests | PASSED on the owner machine: PHP 8.2.29, PHPUnit 10.5.63, 7 tests, 8 assertions |
| Existing `tests/phpunit/world`, `tests/phpunit/network`, redstone and piston tests | NOT EXECUTED |
| `phpstan` | NOT EXECUTED |
| Microbenchmark | NOT EXECUTED; the script is delivered as is, and the World comparison includes `ReflectionMethod::invoke` cost on the private path |
| Claim that F-01 avoids reflection on internal paths | Confirmed by reading the code, not measured |

Command for the new tests: `vendor/bin/phpunit --bootstrap vendor/autoload.php tests/phpunit/world/WorldBroadcastValidationTest.php tests/phpunit/network/mcpe/convert/TypeConverterBroadcastValidationTest.php`.

## Phase 2 - F-07

| Classification | Location | Evidence | Minimal fix | Regression test |
| --- | --- | --- | --- | --- |
| CONFIRMED BY READING | `src/world/World.php`, `addSound` and `addParticle` | Four comparisons `$players === $this->getViewersForPosition($pos)` (two per method) re-query the chunk listeners and compare arrays element by element, even when `$players` came from that same query | When `$players` arrived as `null` and no event replaced the recipients, take the chunk route without the lookup and comparison | `WorldSoundParticleRoutingTest` |
| NOT APPLIED | Same methods, literal proposal (decide the route only from "arrived as `null`") | Chunk route buffers and sends at the end of the tick; the filtered route sends immediately. A plugin passing an array equal to the viewers, or a `WorldSoundEvent`/`WorldParticleEvent` handler leaving the recipients unchanged, takes the chunk route today. Forcing the filtered route changes delivery timing and ordering against other buffered packets | Keep the original comparison for explicit arrays and for any case where an event handler ran | `testSoundWithTranslatorAndExplicitViewersIsBufferedByChunk` and the equivalent tests |
| KEEP AS-IS | `filterViewersForPosition` | Used only when the recipients differ from the viewers | Preserve | `*AndOtherPlayersIsNotBufferedByChunk` tests |

Equivalence argument for the applied part: with `$players === null` and no handlers, `$players` is the value returned by `getViewersForPosition($pos)` a few statements earlier. Nothing between that call and the comparison mutates the chunk listeners (closure creation and `encode()` do not run listeners code), so the comparison is always true. The shortcut therefore yields the same route, recipients, order and duplicates.

## Phase 2 changes

- `src/world/World.php`: `addSound` and `addParticle` store `$defaultViewers = $players === null` before the default assignment, set it to `false` right after `getRecipients()` is applied, and test `$defaultViewers || $players === $this->getViewersForPosition($pos)` in the four routing conditions. No signature, event or packet change.
- `tests/phpunit/world/WorldSoundParticleRoutingTest.php`: for sounds and particles, with and without translators, checks that `null`, an explicit array equal to the viewers, and an array of other players take the chunk route, the chunk route and the filtered route respectively.
- `tests/bench/SoundRoutingBenchmark.php`: `addSound` with `null` versus explicit viewers over N players (default 100); run with `php tests/bench/SoundRoutingBenchmark.php [iterations] [players]`.

## Phase 2 verification status

| Check | Result |
| --- | --- |
| `WorldSoundParticleRoutingTest` | PASSED on the owner machine: PHP 8.2.29, PHPUnit 10.5.63, 12 tests, 12 assertions. Players are an anonymous `Player` subclass with empty constructor and destructor; `createMock(Player::class)` failed because the real destructor ran on the mock |
| Existing `tests/phpunit/world` (includes sound, redstone and hopper) | PASSED on the owner machine: 175 tests, 37005 assertions |
| Rest of `tests/phpunit` (including `tests/phpunit/block/piston` and `tests/phpunit/network`) | NOT EXECUTED |
| `php -l` and `phpstan` | `php -l` on `World.php` and the new files: no syntax errors. `phpstan analyse`: `[OK] No errors` on the owner machine |
| Benchmark, 100 players, 200000 calls, PHP 8.2.29 ZTS | MEASURED on one machine, two runs: null players 426.11 ms and 435.96 ms; explicit viewers (comparison still executed) 539.74 ms and 514.36 ms; difference 113.62 ms and 78.41 ms, about 390 to 570 ns per call. Synthetic: one chunk, no real sessions, isolated routing cost; not a TPS measurement |
| Claim that the default path skips one chunk lookup and one array comparison per call | Confirmed by reading the code; the benchmark above measures the cost of that comparison in isolation |
