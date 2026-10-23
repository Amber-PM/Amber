# Redstone Phase 8 release audit

Audited branch: `feature/redstone`. Original redstone base: `95d907a`.
Phase 7 closing revision: `5f3c05e03680a7502a43370228fcd817857c45aa`.
The Phase 8 closing revision is the commit containing this report.

## Findings

All five correctness/cost findings below were reproduced with focused RED tests
before the corresponding fix. Frozen phases were reopened only for these repros.

| Classification | Location | Evidence | Minimal fix | Regression test |
| --- | --- | --- | --- | --- |
| BUG CONFIRMED | `src/world/redstone/RedstoneEngine.php`, due timer processing | Budget 1 parked 1,000 due unloaded timers in one tick; independent reviewer reproduced 10,000 | Spend the shared budget before parking or invoking a valid due event | `testDueUnloadedTimersParkWithinSharedBudget` |
| BUG CONFIRMED | `src/world/redstone/WireNetwork.php`, discovery/source/propagation/apply | Unloading during settle consumed unavailable topology as air, released the continuation, and left signal 0 after reload instead of 15 | Retain pending nodes until the fixed read halo is loaded; retry checks remain metered | `testWireSettlementSurvivesChunkUnloadInEveryReadPhase` covers all four read phases and empty final ownership/alias collections |
| BUG CONFIRMED | `src/world/redstone/WireNetwork.php`, phase transitions | One last discovery step built all 2,000 source queue entries; independent 20,000-wire probe allocated 532,416 bytes | Accumulate source/apply queues during metered discovery/merge; retain edges/buckets for existing metered cleanup | `testPhaseTransitionsDoNotBuildWholeWireQueues` |
| BUG CONFIRMED | `src/world/redstone/ContainerWatch.php`, watch rotation | One entry in a 20,000-watcher index transiently allocated 1,312,792 bytes | Rotate the live index without a by-value foreach snapshot | `testContainerRotationDoesNotCopyAllWatchers` |
| BUG CONFIRMED | `src/world/redstone/RedstoneEngine.php`, parked timer rotation/awakening | First awakened event in a 20,000-entry bucket transiently allocated 1,312,856 bytes | Read/remove live entries in bounded loops; release temporary bucket references immediately | `testParkedAwakeningDoesNotCopyTheChunkBucket` |
| NONBLOCKING CLEANUP | Timer fuzz and wire continuation tests | Two settlement loops had no iteration bound and could hang CI on a regression | Bound both loops and preserve final convergence assertions/workload sizes | Full redstone suite; timer fuzz rerun |
| NONBLOCKING CLEANUP | Master and phase mutation stress entry points | FAIL results were printed but process exit status stayed successful | Return exit 1 for failures | Both entry points tested with temporary forced-failure copies: FAIL output and exit 1 |
| KEEP AS-IS | Public helpers and active runtime indexes | No proof supports removing public APIs or timer/ownership/watch indexes; each active index has a distinct role | Preserve APIs and architecture | Existing scheduling, ownership, lifecycle and torch tests |
| KEEP AS-IS | Stress harness introspection and threshold tests | Internal state assertions detect cost/leak regressions that output-only assertions would miss; threshold tests specify Amber behavior, not vanilla parity | Preserve adversarial coverage and the unconfirmed threshold comment | Full redstone suite and independent review |

The PHP [foreach RFC](https://wiki.php.net/rfc/php7_foreach) documents
copy-on-write when a by-value iterator's array is modified. Cost repros used the
same PHP 8.3.29 runtime as verification. They observe the first world read, avoiding
confusion with PHPUnit's growing invocation history. The 64 KiB allocation guard
separates fixed callback overhead from the >1 MiB full-index copies.

## Exact cleanup

- Remove ten unused imports: four function imports from WireNetwork, one from the
  timer fuzz test, World from Grid/Line/Tree stress scripts, and two imports from
  StressHarness.
- Remove two write-only `propDone` reflection fields and their assignments from
  the standalone stress harnesses. Production `WireNetwork::$done` is retained.
- Remove two unused local alias-source snapshot reads in `checkInvariants()`.
  Final alias-source leak assertions are retained.
- Remove unused `startMem` / `memDelta` calculations from `tests/fuzz_timer.php`.
- Bound two test loops and propagate stress failures to exit status as above.
- No feature additions, configuration changes, public API redesign, or unrelated
  production cleanup. Copyright/license headers remain untouched.

## Independent reviews

Four independent read-only reviewers ran; none spawned subagents. The first three
reviewed full code, leftovers and tests. The fourth ran last and independently
reviewed the fixes, including the final snapshot-copy fixes.

- Final code reviewer: three confirmed findings above; no additional proven torch,
  world disposal, ContainerWatch semantics or timing defect.
- Cleanup reviewer: only proven dead imports/values removed; active indexes kept.
- Test reviewer: all original redstone tests passed in normal and random order
  (seed 8008). Standalone stress scripts remain outside normal PHPUnit CI.
- Release reviewer: no new concrete blocker. Independently passed all 23 master
  wire workloads, six phase mutation workloads, and the final eight adversarial
  budget tests (20 assertions).

## Final invariants

| Invariant | Result | Evidence |
| --- | --- | --- |
| budget | PASS | Parking and callbacks share the engine budget; wire queue construction, retries and cleanup are metered; rotation no longer copies complete indexes |
| wire ownership | PASS | After settled deferred work: continuations, owners, aliases and aliasSources are empty; guarded reassignment and invalidation tests remain green |
| timers | PASS | Expected-state validation, cancellation, metered parking and awakening remain green |
| torch feedback | PASS | Normal transitions remain independent, replacement identity is invalidated synchronously, original extinguish timestamps survive, no Java 160-tick recovery |
| chunk/world lifecycle | PASS | Unloaded wire work persists until reload; World onUnload clears subsystem state |
| memory cleanup | PASS | Final ownership/watch/timer cleanup assertions and allocation regression tests pass |

## Final verification

Runtime: PHP 8.3.29, PHPUnit 10.5.63, 512 MiB memory limit.
The external PHP scan directory was disabled for the commands; system settings
were not modified.

- Full redstone PHPUnit: **90 tests, 34,689 assertions**, PASS.
- Full repository PHPUnit (`--bootstrap vendor/autoload.php --fail-on-warning tests/phpunit`):
  **581 tests, 111,619 assertions**, PASS.
- PHPStan entire configured `src`/`generated` scope, repository level **2**: no errors.
- `git diff --check`: clean. The final staged patch was also checked.
- Phase 7 rerun: all six standalone phase mutation workloads PASS; timer fuzz
  **11 tests, 10,249 assertions**, PASS.
- Independent master stress: all **23 workloads**, exit 0; includes 10,000-wire
  networks, concurrent merges, topology mutations and final leak checks.
- Both modified stress runners return exit 1 in forced-failure probes.
- No push. The closing commit and clean worktree are verified after committing.

## Changed files

- `src/world/redstone/ContainerWatch.php`
- `src/world/redstone/RedstoneEngine.php`
- `src/world/redstone/WireNetwork.php`
- `tests/fuzz_timer.php`
- `tests/phpunit/world/redstone/RedstoneBudgetAdversaryTest.php`
- `tests/phpunit/world/redstone/RedstoneScheduledUpdateBudgetTest.php`
- `tests/phpunit/world/redstone/RedstoneTimerFuzzTest.php`
- `tests/phpunit/world/redstone/WireContinuationBudgetTest.php`
- `tests/stress/GridStressTest.php`
- `tests/stress/LineStressTest.php`
- `tests/stress/MasterWireStressSuite.php`
- `tests/stress/PhaseMutationStressTest.php`
- `tests/stress/StressHarness.php`
- `tests/stress/TreeLoopsStressTest.php`
- `docs/redstone-phase8-audit.md`

## Known limitations

```text
Torch burnout threshold = 8
UNCONFIRMED — REQUIRES REAL BEDROCK MEASUREMENT
```

Mocked tests establish accounting, convergence and cleanup, not production server
tick latency or full Bedrock parity. Unloaded continuations retain pending state
until reload or world clear. Torch feedback conservatively discards evidence when
other observed topology changes inside a watched chunk. The independently tested
standalone phase suite supplies the explicit phase-entry assertions missing from
the master suite's helper; no additional rewrite was justified.

```text
PHASE 8: FROZEN
REDSTONE ENGINE: RELEASE-READY
```
