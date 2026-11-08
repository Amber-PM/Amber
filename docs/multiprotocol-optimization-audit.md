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

## Phase 4 - F-04 (investigation and characterization tests, no production change)

Findings come from reading the code. Nothing about load time or memory is measured yet.

| Classification | Location | Evidence | Minimal fix | Regression test |
| --- | --- | --- | --- | --- |
| CONFIRMED BY READING | `NetworkSession::setProtocolId` | Calls `TypeConverter::getInstance($protocolId)`; the constructor loads the block palette (`BlockStateDictionary::loadFromString`, one `upgrade()` per state), item dictionary, `ItemIdMetaDowngrader` and `ItemTranslator` on first use | Pending owner decision | `TypeConverterInstanceSharingTest` |
| CONFIRMED BY READING | `Server::startupPrepareNetworkInterfaces` | Only `TypeConverter::getInstance()` of `CURRENT_PROTOCOL` is preloaded; 32 protocols are accepted | Pending owner decision | None yet |
| ALREADY APPLIED | `BlockTranslator::$loaded`, `ItemTypeDictionaryFromDataHelper::$loaded`, `ChunkCache::request` | 32 protocols map to 23 block datasets and 27 item lists; chunk preparation is grouped by `getChunkProtocolId()` | None | `ChunkProtocolGroupTest`, `TypeConverterInstanceSharingTest` |
| CONFIRMED BY READING | `ChunkRequestTask::onRun` | Builds a full `TypeConverter` per chunk group in each worker, but `ChunkSerializer::serializeFullChunk` only uses `getBlockTranslator()` and tiles arrive pre-serialized | Needs a `ChunkSerializer` signature decision | None yet |
| CONFIRMED BY READING | Worker-side state | Data files and `Global*Handlers` statics are rebuilt per thread; the only main-thread-only state is `nativeSpectator`, set by the creation listener in `Server.php`, which only affects `coreGameModeToProtocol` | None | None |
| MEASURED (benchmark, one machine) | Worker memory | Loading all 32 protocols retains about 167 MB (sum of per-protocol deltas) with a 210 MB peak; `php -dmemory_limit=256M` completed. A dataset costs 4 to 10 MB. A worker serving every protocol would sit at about 82% of the 256 MB limit; not measured inside a real worker | Do not add worker memory | `TypeConverterLoadBenchmark.php` |
| PRE-EXISTING FAILURE | `ProtocolResourceIndependenceTest`, datasets 2193 and 9999 | `check-protocol-resources.php` l. 43 asserts a fixed `ACCEPTED_PROTOCOL` after replacing `CURRENT_PROTOCOL`, but `ProtocolInfo::ACCEPTED_PROTOCOL` contains `self::CURRENT_PROTOCOL` (l. 66 of the vendored file). Fails identically with the Phase 4 files stashed | Out of scope; not changed | The test itself |

Admission (`isProtocolAllowed`, `SessionStartPacketHandler`) and protocol selection are not touched.

## Phase 4 changes

- `tests/phpunit/network/mcpe/convert/TypeConverterInstanceSharingTest.php`: pins instance caching, shared translators and item dictionaries for protocols with the same data files, and that `getChunkProtocolId()` is a fixed point whose group members share the representative's `BlockTranslator`.
- `tests/bench/TypeConverterLoadBenchmark.php`: time and memory delta of loading each accepted protocol in order, plus the number of chunk groups. Run with `php tests/bench/TypeConverterLoadBenchmark.php [--blocks] [--protocols=a,b]`; use `php -dmemory_limit=256M` to reproduce the worker limit.
- No production file was modified.

## Phase 4 verification status

| Check | Result |
| --- | --- |
| `TypeConverterInstanceSharingTest` | PASSED on the owner machine: PHP 8.2.29, PHPUnit 10.5.63, 5 tests, 196 assertions (`-dmemory_limit=2G`, 22 s, 210 MB) |
| `tests/phpunit/network/mcpe/cache` | PASSED: 1 test, 97 assertions |
| `tests/phpunit/network/mcpe/convert` | 17 of 19 passed; the 2 failures are `ProtocolResourceIndependenceTest` (see PRE-EXISTING FAILURE) |
| `phpstan analyse` | `[OK] No errors` |
| `TypeConverterLoadBenchmark.php` | MEASURED on the owner machine, PHP 8.2.29: see Phase 4 measurements |
| Worker RAM against the 256 MB limit | Benchmark process completed under `-dmemory_limit=256M` with a 210 MB peak; NOT measured inside a real async worker |
## Phase 4 measurements

One machine, PHP 8.2.29 ZTS, one run per mode (`tests/bench/TypeConverterLoadBenchmark.php`, `tests/bench/BlockStateLoadProfile.php`). The three benchmark runs agree on shape; absolute numbers vary by about 10%.

| Measurement | Result |
| --- | --- |
| First `TypeConverter::getInstance` (includes global statics) | 2.0 to 2.1 s |
| Each further distinct block dataset | 0.8 to 1.2 s on the main thread |
| Protocols sharing an already loaded dataset | under 0.2 ms |
| Protocols with only a new item list | 15 to 26 ms |
| All 32 protocols | about 24 s, 28 chunk protocol groups, 27 item lists, about 167 MB retained, 210 MB peak |
| Blocks only against full converter | totals within noise; item side is small |
| Per dataset breakdown (17499 states) | NBT parse 616 ms, `upgrade()` 87 ms, `json_decode` 3 ms, `loadFromString` total 1141 ms |
| Per dataset breakdown (13141 states, 1.20.0) | NBT parse 464 ms, `upgrade()` 280 ms, total 1014 ms |
| Global handler statics, first use | 368 ms |

Reading: the NBT parse is about half of the load time and entry construction a quarter to a third; `upgrade()` is 7% on recent datasets and 28% on the oldest. No small safe change to the construction cost was found. A worker holding every protocol would use about 82% of the default 256 MB limit, so nothing that adds worker memory should be applied.
## Phase 4 implementation (option A)

| Classification | Location | Evidence | Minimal fix | Regression test |
| --- | --- | --- | --- | --- |
| APPLIED | `src/Server.php`, `getProtocolsToPreload` (private static), `startupPreloadAllowedProtocols`, call in `startupPrepareNetworkInterfaces` | When `network.allowed-protocols` is non-empty, `TypeConverter::getInstance` runs at startup for each accepted protocol that `isProtocolAllowed` admits. With an empty list nothing new is loaded | 24 lines added, none removed | `ServerProtocolPreloadTest` |
| NOT APPLIED | Preload every accepted protocol by default | About 24 s of startup and about 167 MB (measured in the benchmark) | None | None |
| NOT APPLIED | Cheaper dictionary construction | NBT parse is about half of each load and entry construction a quarter to a third; no small safe change found | None | None |
| NOT APPLIED | Worker builds only `BlockTranslator` instead of a full `TypeConverter` | Would save about 24 MB of peak and under 26 ms per item-only group; needs a `ChunkSerializer` signature change | None | None |

`isProtocolAllowed`, admission and protocol selection are unchanged. No configuration key, log message or public method was added.

## Phase 4 implementation changes

- `src/Server.php`: the helper filters `ProtocolInfo::ACCEPTED_PROTOCOL` through `isProtocolAllowed` and returns an empty list when `allowed-protocols` is empty; `startupPrepareNetworkInterfaces` calls the preload before loading the current protocol.
- `tests/phpunit/ServerProtocolPreloadTest.php`: empty list preloads nothing and never calls the filter; explicit list keeps accepted order; disabled and unknown protocols are excluded; listing every protocol preloads every one.

## Phase 4 implementation verification status

| Check | Result |
| --- | --- |
| `ServerProtocolPreloadTest` | PASSED on the owner machine: PHP 8.2.29, PHPUnit 10.5.63, 5 tests, 6 assertions |
| `ServerConfigGroupTest`, `ProtocolAdmissionTest` | PASSED: 3 tests / 7 assertions and 9 tests / 15 assertions |
| `php -l` on `Server.php` and the new test | No syntax errors |
| `phpstan analyse` | `[OK] No errors` |
| Benchmark with `--protocols=2169,2192` | Output not recorded |
| Startup time and first-login stall on a real server with a listed protocol | NOT MEASURED |
| Call from `startupPrepareNetworkInterfaces` | Not unit-tested: the `Server` constructor cannot be instantiated in a test |
| `ProtocolResourceIndependenceTest` (2193, 9999) | Pre-existing failure, unrelated to this phase |
| Phase 3 (F-05) | Not implemented in this branch |