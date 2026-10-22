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
