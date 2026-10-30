# Implementation Plan: Static Checks First & PHP 8.4 Support

**Branch**: `feature/albertotaboada/ai-improvement` (spec dir `001-static-checks-php84`) | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-static-checks-php84/spec.md`

## Summary

Make the Envato Theme Check plugin a complete static reviewer that needs no AI: unify the
admin and CLI runners behind one file collector, give every one of the 59 checks a catalogue
row and a pass/fail fixture pair, append Envato triggers from a committed mapping, add a
PHPCS-powered `PHP_Compatibility_Check` (PHPCompatibilityWP, range = theme `Requires PHP` →
8.4), put the AI module behind an enabled flag that unregisters every AI entry point, and bring
the plugin's own code to PHP 8.1–8.4 strictness verified by PHPUnit, PHPCS and PHPStan.

## Technical Context

**Language/Version**: PHP 8.1 floor, verified through 8.4 (Local runs 8.3.30; 8.4 via CI matrix)

**Primary Dependencies**: WordPress ≥ 6.x, WP-CLI 2.11; runtime: `squizlabs/php_codesniffer`,
`phpcompatibility/php-compatibility:^10@alpha`, `phpcompatibility/phpcompatibility-wp`;
dev: `phpunit/phpunit:^10`, `brain/monkey`, `mockery/mockery`, `wp-coding-standards/wpcs:^3`,
`phpstan/phpstan:^2`, `szepeviktor/phpstan-wordpress`, `php-stubs/wordpress-stubs`,
`dealerdirect/phpcodesniffer-composer-installer`, fallback `bamarni/composer-bin-plugin` (R2)

**Storage**: `wp_options` row `tc_ai_review_settings` (+ `enabled`, `schema_version`); committed
files `config/triggers.json`, `config/rules.snapshot.yml`, fixture/reference themes, allowlists

**Testing**: PHPUnit suites `unit` (Brain Monkey, no WP), `integration` (loads Local
`wp-load.php`, read-only), `sweep` (reference themes). See research R1.

**Target Platform**: WordPress admin (Appearance → Theme Check) and WP-CLI on reviewer
machines; no `exec` available (PHPCS runs in-process, R3)

**Project Type**: WordPress plugin (single project) with a CLI surface

**Performance Goals**: full static review of a typical theme (~300 files) in < 60 s on the
admin page without raising `max_execution_time` (SC-006); PHPCS in-process adds ≤ 20 s for
such a theme

**Constraints**: zero outbound HTTP when AI disabled; static checks never read the network;
tests never read the network; reference themes committed (live `wp-content/themes/` is
volatile — R5); no edits to upstream repo; no commits by agents unless asked

**Scale/Scope**: 59 existing checks + 1 new; 118+ minimal fixtures + ~5 regression fixtures;
1 reference theme snapshot; ~80 PHP files to bring to strict types

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Pre-research | Post-design | Notes |
|---|---|---|---|
| I. Static first, AI optional | PASS | PASS | Toggle unregisters AJAX handlers; AI_Review_Check deleted; static path never fetches (R4, R7) |
| II. Spec → test → code | PASS | PASS | Every behaviour task in `tasks.md` is split red-test / implementation; tooling tasks name an acceptance command |
| III. Fixtures for every check | PASS | PASS | 59 pairs + regression fixtures + committed reference theme with allowlist (R5, R10) |
| IV. Token-based checks | PASS | PASS | New check wraps PHPCompatibilityWP sniffs in-process (R3); no new regex on PHP |
| V. Admin/CLI parity | PASS | PASS | Single `tc_collect_theme_files()` collector + `RunnerParityTest` (R6) |
| VI. Modern, strict, secure PHP | PASS | PASS | strict_types everywhere, PHPStan L6, PHPCompat 8.1–8.4 on plugin, AJAX nonce+cap unchanged |
| Quality gates (3 commands) | PASS | PASS | `composer test:unit`, `lint`, `analyse` (contracts §6) |
| ADR before engine change | **PENDING** | PENDING | `docs/decisions/0001-phpcs-engine.md` is the first task; blocks the new check |

No violations → Complexity Tracking left empty.

## Project Structure

### Documentation (this feature)

```text
specs/001-static-checks-php84/
├── plan.md              # This file
├── research.md          # Phase 0 — R1…R10
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/README.md  # Phase 1 — CLI, output format, config files, settings, test commands
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root = plugin root)

```text
theme-check.php                 # header: Requires PHP: 8.1; strict_types; AI loader behind toggle
checkbase.php                   # + tc_collect_theme_files(), tc_trigger(), hidden-folder exclusion
main.php                        # check_main() uses collector
theme-check-cli.php             # check/active use collector; + sync-triggers subcommand
checks/                         # 59 existing (strict_types, declared props) + class-php-compatibility-check.php
config/
├── triggers.json               # check-id → trigger (hand-maintained)
├── triggers.schema.json
└── rules.snapshot.yml          # last cloud sync
ai-review/
├── class-ai-review-loader.php  # honours enabled flag; migration
├── settings/class-ai-settings.php      # + enabled field, schema_version, sanitize
├── settings/class-ai-settings-page.php # + checkbox + disabled notice
└── check/class-ai-review-check.php     # DELETED (dead code, FR-012)
docs/
├── SPEC.md                     # check catalogue (FR-013) + plugin behaviour
└── decisions/0001-phpcs-engine.md
tests/
├── bootstrap-unit.php          # Brain Monkey
├── bootstrap-integration.php   # loads ../../../wp-load.php (WP_ROOT)
├── Unit/
│   ├── CatalogueTest.php
│   ├── CheckFixtureTest.php    # data provider over tests/fixtures/themes/*
│   ├── PhpCompatibilityCheckTest.php
│   └── TriggerMappingTest.php
├── Integration/
│   ├── RunnerParityTest.php
│   ├── AiToggleTest.php        # pre_http_request interceptor, migration
│   └── CliActiveTest.php
├── Sweep/ReferenceThemeSweepTest.php
└── fixtures/
    ├── themes/<check-id>-{pass,fail}/ , <check-id>-regression-<slug>/
    └── reference/twentytwentyfive/ , twentytwentyfive.allowlist.json
composer.json  phpcs.xml  phpstan.neon  phpunit.xml  .github/workflows/ci.yml
```

**Structure Decision**: single WordPress plugin; tests live beside the code; fixtures are data
under `tests/fixtures`. `config/` is new and holds only committed, human-readable data read at
runtime. No `src/` reorganisation — the legacy procedural layout stays to keep diffs reviewable
and `themecheck_checks_loaded` consumers working.

## Phase 0 — Research (complete)

See [research.md](research.md). Two items change earlier assumptions and need the user's
acknowledgement in the tasks phase:

1. **R5** — reference themes are committed snapshots (`twentytwentyfive`), not the live themes
   directory; proprietary themes are optional/report-only. Spec assumption and constitution
   principle III wording mention `moveora`; update both.
2. **R3** — PHPCS + PHPCompatibility become *runtime* dependencies shipped in `vendor/`
   (plugin zip grows by ~10 MB). Alternative is shelling out, which reviewer hosts block.

## Phase 1 — Design (complete)

- [data-model.md](data-model.md): Check, Finding, Trigger, Fixture, Reference/Allowlist, PHP
  range, AI module state + transitions.
- [contracts/README.md](contracts/README.md): CLI commands (incl. new `sync-triggers`), finding
  HTML/plain format, `triggers.json` and allowlist schemas, settings UI contract, the three
  project commands.
- [quickstart.md](quickstart.md): runnable validation per user story.

## Phase 2 — Task generation approach (for /speckit-tasks)

Order is dictated by constitution II and ORCHESTRATION.md waves:

1. **Foundation** (tooling, exempt from spec step): composer/phpcs/phpstan/phpunit config,
   bootstraps, CI workflow, ADR 0001, `docs/SPEC.md` skeleton. Acceptance: contracts §6 commands
   run (suite may be empty).
2. **Catalogue + fixtures** (bulk, parallelisable by check ranges): 59 rows, 118 fixture dirs,
   `CatalogueTest` and `CheckFixtureTest` red → green; regression fixtures for the 5 risky checks.
3. **Runner parity** (red test `RunnerParityTest` + `CliActiveTest` → collector → green).
4. **Triggers** (`TriggerMappingTest` red → `tc_trigger()`, `triggers.json`, `sync-triggers`).
5. **PHP_Compatibility_Check** (red `PhpCompatibilityCheckTest` with pass/fail fixtures → in-process
   PHPCS wrapper → green; allowlist for reference theme).
6. **AI toggle** (red `AiToggleTest` → settings field, loader gating, migration, delete dead
   check → green; security review by Astra).
7. **Plugin PHP 8.4 modernisation** (strict_types/typed props per file group; PHPStan baseline
   shrink; header). Mechanical, split by directory so workers never share files.
8. **Docs sync**: CLAUDE.md/AGENTS.md/constitution wording for R5; SPEC.md final.

Each behaviour task is emitted as two tasks (test, then implementation) with explicit file
ownership so they map 1:1 onto Orca workers.
