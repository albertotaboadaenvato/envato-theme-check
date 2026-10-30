# Tasks: Static Checks First & PHP 8.4 Support

**Input**: Design documents from `/specs/001-static-checks-php84/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/README.md, quickstart.md

**Tests**: REQUIRED. Constitution II: every behaviour task is a pair — red test task first, implementation task second. Tooling/docs tasks name an acceptance command instead.

**Organization**: Phases by user story (P1 → P3). Each task names its owned files so it maps 1:1 to an Orca worker (ORCHESTRATION.md: one task, one worker). Tasks in the same wave never share files.

## Format: `[ID] [P?] [Story] Description — files`

- **[P]**: parallelizable (different files, no dependency on an unfinished task)
- **[Story]**: US1…US5 from spec.md
- **Worker hint** in parentheses: Scout = Haiku, Builder = Sonnet, Bulk = gpt-6.1-sol, Author = Opus, Sec = gpt-6-astra, Orch = orchestrator (prose only)

## Path Conventions

Plugin root = repo root. Tests under `tests/`, fixtures under `tests/fixtures/`, committed config under `config/`.

Check ids (**60** — `checkbase.php:34` loads `glob('checks/*.php')`, so `class-theme-support-title-tag.php` counts too; id = file name minus `class-` prefix and optional `-check.php` suffix; `fse-required-files` registers conditionally): admin-menu, bad-things, basic, block-patterns, cdn, comment-pagination, comment-reply, comments, constants, copyright-notice, customizer, deprecated, deprecated-param, deregister, directories, editor-style, envato, escaping, favicon, file, filesystem-http, fse-required-files, generated, gravatar, i18n, iframe, image-size, include, included-zip, line-endings, link, nav-menu, no-hidden-admin-bar, non-gpl, non-printable, php-short-tags, plugin-territory, post-format, post-pagination, post-thumbnail, screen-reader-text, screenshot, script-tag, search-form, site-url, style-css-header, style-tags, suggested-styles, tag, textdomain, theme-support, theme-support-title-tag, time-date, title, underscores, uri, version-php, version-tested, widgets, worms.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: tooling the constitution's quality gates require. Exempt from the spec step; each task has an acceptance command.

- [x] T001 Write ADR `docs/decisions/0001-phpcs-engine.md` (context: 60 regex checks, two reverted false-positive fixes; decision: PHPCS/PHPCompatibilityWP in-process for new checks; consequences: runtime vendor/, alpha dependency, bin-plugin fallback) — files: `docs/decisions/0001-phpcs-engine.md` (Orch, user approves)
- [x] T002 Create `composer.json` with runtime deps `squizlabs/php_codesniffer`, `phpcompatibility/php-compatibility:^10@alpha`, `phpcompatibility/phpcompatibility-wp`, dev deps `phpunit/phpunit:^10`, `brain/monkey`, `mockery/mockery`, `wp-coding-standards/wpcs:^3`, `phpstan/phpstan:^2`, `szepeviktor/phpstan-wordpress`, `php-stubs/wordpress-stubs`, `dealerdirect/phpcodesniffer-composer-installer`; scripts `test:unit`, `test:integration`, `test:sweep`, `lint`, `analyse`; PSR-4 `TC\\Tests\\` → `tests/`. If the solver rejects the PHPCompatibility/WPCS PHPCS majors, apply research R2 fallback (`bamarni/composer-bin-plugin`, `vendor-bin/compat/composer.json`) and append the outcome to `specs/001-static-checks-php84/research.md` §R2. Acceptance: `composer install && vendor/bin/phpcs -i` lists WordPress, PHPCompatibility, PHPCompatibilityWP — files: `composer.json`, `composer.lock`, `.gitignore` (add `vendor/`, `vendor-bin/*/vendor/`), optionally `vendor-bin/compat/composer.json` (Builder)
- [x] T003 [P] Create `phpcs.xml` (rulesets `WordPress`, `PHPCompatibilityWP` with `testVersion 8.1-`; text-domain `theme-check`; exclude `vendor/`, `vendor-bin/`, `tests/fixtures/`, `assets/`, `node_modules/`) and `phpstan.neon` (level 6, `szepeviktor/phpstan-wordpress` extension, paths `theme-check.php`, `main.php`, `checkbase.php`, `theme-check-cli.php`, `checks/`, `ai-review/`, `tests/` excluding `tests/fixtures/`, `phpstan-baseline.neon` include). Acceptance: both commands run to completion (errors allowed at this stage) — files: `phpcs.xml`, `phpstan.neon`, `phpstan-baseline.neon` (empty) (Builder)
- [x] T004 [P] Create `phpunit.xml` with suites `unit` (`tests/Unit`, bootstrap `tests/bootstrap-unit.php`), `integration` (`tests/Integration`, bootstrap `tests/bootstrap-integration.php`), `sweep` (`tests/Sweep`, bootstrap unit); `tests/bootstrap-unit.php` loading Composer autoload, Brain Monkey setup, stubbing `__`, `_e`, `esc_html__`, `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, `apply_filters` (passthrough), `do_action`, `sanitize_text_field`, then requiring `checkbase.php`; `tests/bootstrap-integration.php` requiring `{WP_ROOT:-../../..}/wp-load.php` read-only; `tests/Support/FixtureTheme.php` helper that turns a fixture dir into `[php, css, other]` arrays using `tc_strip_comments`, and `tests/Support/CheckRunner.php` that runs one check class and returns `getError()` stripped to `SEVERITY: message` lines. Acceptance: `vendor/bin/phpunit --testsuite unit` runs with 0 tests, exit 0 — files: `phpunit.xml`, `tests/bootstrap-unit.php`, `tests/bootstrap-integration.php`, `tests/Support/FixtureTheme.php`, `tests/Support/CheckRunner.php` (Builder)
- [x] T005 [P] Create `.github/workflows/ci.yml`: matrix php 8.1/8.3/8.4, steps `composer install`, `composer lint`, `composer analyse`, `composer test:unit`, `composer test:sweep`. Acceptance: YAML validates (`actionlint` or `yamllint` if available; otherwise `php -r "yaml_parse_file"` fallback or visual) — files: `.github/workflows/ci.yml` (Builder)
- [x] T006 [P] Create `docs/SPEC.md` skeleton: sections "Plugin behaviour" (admin page, CLI commands, AI toggle — copy FR-001…FR-012 as behaviour statements), "Check catalogue" with the table header `| id | class | engine | severity | trigger | detects | pass fixture | fail fixture |` and 59 rows with only `id` and `class` filled (source: `docs/reports/wave0-inventory.md` §1). Acceptance: 59 table rows — files: `docs/SPEC.md` (Bulk)

**Checkpoint**: `composer install`, `lint`, `analyse`, `test:unit` all execute.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: shared pieces every story's tests depend on.

- [x] T007 Red test `tests/Unit/CatalogueTest.php`: parses `docs/SPEC.md` table; asserts (a) set of ids == set derived from `glob('checks/class-*.php')` file names (id = file name minus `class-` and optional `-check.php`; 60 files; **not** the class name — `class-envato-check.php` declares `Themeforest`), and every registered `$themechecks` object maps back to one of those files via `ReflectionClass::getFileName()`; conditionally registered checks (`fse-required-files`) are expected present in the catalogue regardless of whether they registered in the test environment, (b) every row has non-empty engine/severity/detects/fixtures, (c) `tests/fixtures/themes/<id>-pass` and `<id>-fail` dirs exist, (d) every key in `config/triggers.json` is a known id. Expected: FAILS (empty columns, no fixtures, no triggers.json) — files: `tests/Unit/CatalogueTest.php` (Builder)
- [x] T008 [P] Red test `tests/Unit/CheckFixtureTest.php`: data provider over `tests/fixtures/themes/*-fail` and `*-pass`; for each, runs only that check via `CheckRunner`; `-fail` must yield ≥1 line whose severity matches SPEC.md row and `-pass` must yield 0 lines from that check. Expected: FAILS (no fixtures) — files: `tests/Unit/CheckFixtureTest.php` (Builder)
- [x] T009 [P] Create committed reference theme snapshot: copy `wp-content/themes/twentytwentyfive` (unmodified, GPL) to `tests/fixtures/reference/twentytwentyfive/` and an empty `tests/fixtures/reference/twentytwentyfive.allowlist.json` (`[]`). Acceptance: `style.css` header present; `git status` shows the dir — files: `tests/fixtures/reference/twentytwentyfive/**`, `tests/fixtures/reference/twentytwentyfive.allowlist.json` (Scout)
- [x] T010 [P] Red test `tests/Sweep/ReferenceThemeSweepTest.php`: runs all checks on each `tests/fixtures/reference/<slug>`; fails on findings not in `<slug>.allowlist.json` and on allowlist entries with no matching finding; if `TC_EXTRA_REFERENCE_DIR` is set, runs the same over its sub-dirs but only prints a report (no assertions). Expected: FAILS (unlisted findings on twentytwentyfive) — files: `tests/Sweep/ReferenceThemeSweepTest.php` (Builder)
- [x] T011 Update spec/constitution/docs wording for research R5 (done during `/speckit-analyze` remediation, 2026-10-29; constitution 1.0.1; pending user approval of the diff) — files: `CLAUDE.md`, `AGENTS.md`, `ORCHESTRATION.md`, `.specify/memory/constitution.md`, `specs/001-static-checks-php84/spec.md`, `data-model.md` (Orch)

**Checkpoint**: three red tests exist and fail for the right reasons; reference snapshot committed.

---

## Phase 3: User Story 4 — Every check documented and tested (Priority: P2, executed first) 🎯 MVP

> Executed before US1/US2 because every later story's green depends on `CatalogueTest`/`CheckFixtureTest` existing and the fixture corpus being in place.

**Goal**: 59 catalogue rows complete; 118 fixtures; regression fixtures for the 5 risky checks; sweep green with a reviewed allowlist.

**Independent Test**: `composer test:unit` green for `CatalogueTest` + `CheckFixtureTest`; `composer test:sweep` green.

### Catalogue + fixtures (bulk, split by id ranges so workers never share files)

- [x] T012 [P] [US4] Fill SPEC.md rows and create `<id>-pass/` + `<id>-fail/` fixtures (minimal `style.css` with Theme Name/Requires PHP/Tested up to/Text Domain + `index.php`, plus only the files needed to trigger the check) for ids **admin-menu … deregister** (14 ids). Severity/engine from `docs/reports/wave0-inventory.md` §1. Run `vendor/bin/phpunit --filter CheckFixtureTest` after each pair — files: `docs/SPEC.md` rows for these ids only, `tests/fixtures/themes/{admin-menu,bad-things,basic,block-patterns,cdn,comment-pagination,comment-reply,comments,constants,copyright-notice,customizer,deprecated,deprecated-param,deregister}-{pass,fail}/**` (Bulk)
- [x] T013 [P] [US4] Same for ids **directories … included-zip** (15 ids: directories, editor-style, envato, escaping, favicon, file, filesystem-http, fse-required-files, generated, gravatar, i18n, iframe, image-size, include, included-zip) — files: SPEC.md rows for these ids, `tests/fixtures/themes/<id>-{pass,fail}/**` (Bulk)
- [x] T014 [P] [US4] Same for ids **line-endings … screenshot** (15 ids: line-endings, link, nav-menu, no-hidden-admin-bar, non-gpl, non-printable, php-short-tags, plugin-territory, post-format, post-pagination, post-thumbnail, screen-reader-text, screenshot, script-tag, search-form) — files: SPEC.md rows for these ids, `tests/fixtures/themes/<id>-{pass,fail}/**` (Builder)
- [x] T015 [P] [US4] Same for ids **site-url … worms** (15 ids: site-url, style-css-header, style-tags, suggested-styles, tag, textdomain, theme-support, theme-support-title-tag, time-date, title, underscores, uri, version-php, version-tested, widgets, worms) — files: SPEC.md rows for these ids, `tests/fixtures/themes/<id>-{pass,fail}/**` (Builder)
- [x] T016 [US4] Regression fixtures for the 5 risky checks from `.trail/learnings/` and reverted commits `2aed4d6`, `f584859` (escaping false positives), plus one each for plugin-territory, deprecated, bad-things, i18n reproducing a real past false positive or miss; each dir has `README.md` citing the source; register them in SPEC.md "Regression fixtures" list and extend `CheckFixtureTest` provider to include `*-regression-*` dirs (expected outcome declared in the README front-matter `expect: pass|fail`). Acceptance: provider picks them up; all green or documented as known-red with issue link — files: `tests/fixtures/themes/{escaping,plugin-territory,deprecated,bad-things,i18n}-regression-*/**`, `tests/Unit/CheckFixtureTest.php`, `docs/SPEC.md` (Author)
- [x] T017 [US4] Triage the sweep: run `composer test:sweep`, for each finding on `twentytwentyfive` decide true positive (add allowlist entry with reason + `confirmed_by`) or false positive (open a follow-up row in `docs/SPEC.md` "Known false positives", do **not** change check logic in this task). Acceptance: `composer test:sweep` green; every allowlist entry has a reason — files: `tests/fixtures/reference/twentytwentyfive.allowlist.json`, `docs/SPEC.md` (Author, user reviews allowlist)

**Checkpoint**: `CatalogueTest`, `CheckFixtureTest`, sweep green. MVP: the plugin is now regression-protected.

---

## Phase 4: User Story 1 — Reliable review without AI (Priority: P1)

**Goal**: admin and CLI identical; CLI `active` works; triggers appended from committed mapping; static run makes zero requests.

**Independent Test**: `composer test:integration` green for `RunnerParityTest`, `CliActiveTest`, `StaticNoHttpTest`; `TriggerMappingTest` green; manual `wp theme review active`.

### Red tests

- [x] T018 [P] [US1] `tests/Integration/RunnerParityTest.php`: for each `tests/fixtures/themes/*` and `tests/fixtures/reference/*` registered via `register_theme_directory()`, compare normalised finding multisets from `run_themechecks_against_theme()` and from `ThemeCheckCLI::check()` captured through a `WP_CLI` logger shim (or by calling the shared collector once it exists). Also asserts a child-theme fixture `tests/fixtures/themes/parity-child/` (new, parent = `parity-parent/`) includes parent files on both paths, and that a `.git/config` file inside `parity-hidden/` is ignored on both. Expected: FAILS — files: `tests/Integration/RunnerParityTest.php`, `tests/fixtures/themes/parity-{parent,child,hidden}/**` (Author)
- [x] T019 [P] [US1] `tests/Integration/CliActiveTest.php`: invokes `ThemeCheckCLI::active()` with WP-CLI bootstrapped in test mode (or asserts via reflection that `active()` calls `check()` with `wp_get_theme()->get_stylesheet()` and no undefined function); `tests/Integration/StaticNoHttpTest.php`: hooks `pre_http_request` to count calls, runs `run_themechecks_against_theme()` on a fixture, asserts 0. Expected: `CliActiveTest` FAILS (undefined `tc_get_theme_data`) — files: `tests/Integration/CliActiveTest.php`, `tests/Integration/StaticNoHttpTest.php` (Author)
- [x] T020 [P] [US1] `tests/Unit/TriggerMappingTest.php`: `tc_trigger('plugin-territory')` returns `` Trigger: <code>:tfcpt</code>`` (HTML) and plain `Trigger: :tfcpt` after `strip_tags`; unknown id returns `''`; `config/triggers.json` validates against `config/triggers.schema.json`; every mapped check's `-fail` fixture output ends with its trigger. Expected: FAILS — files: `tests/Unit/TriggerMappingTest.php` (Builder)

### Implementation

- [x] T021 [US1] Extract `tc_collect_theme_files( WP_Theme $theme ): array` in `checkbase.php` per research R6 (parent files, `unset($files[0])`, hidden-folder regex, TGMPA/Merlin, dev-dir filter, `tc_strip_comments`, dir guard, `wp_raise_memory_limit('admin')`); make `run_themechecks_against_theme()` use it. Acceptance: `RunnerParityTest` admin side passes; `php -l`, phpcs clean — files: `checkbase.php`, `main.php` (Author)
- [x] T022 [US1] Rewrite `ThemeCheckCLI::check()` to call `tc_collect_theme_files()`; rewrite `active()` as `$this->check([wp_get_theme()->get_stylesheet()], $assoc_args)`; delete `$themename/$data` globals and the `tc_get_theme_data()` call; keep JSON output format unchanged. Acceptance: `RunnerParityTest`, `CliActiveTest` green; `wp theme review active` completes — files: `theme-check-cli.php` (Author; depends on T021)
- [x] T023 [US1] Create `config/triggers.json` (initial mapping: plugin-territory→`:tfcpt`, escaping→`:tfescapealiases`, plus any others defensible from the 4 known triggers; everything else absent; add `php-compatibility` only if a matching `:tf` trigger exists in `config/rules.snapshot.yml` after T025's first sync — otherwise leave unmapped and the check carries no trigger, per spec US2-4), `config/triggers.schema.json`, and `tc_trigger( string $check_id ): string` in `checkbase.php` (reads JSON once, static cache). Acceptance: `TriggerMappingTest` unit assertions on `tc_trigger` green — files: `config/triggers.json`, `config/triggers.schema.json`, `checkbase.php` (function only; coordinate with T021 by sequencing after it) (Builder; depends on T021)
- [x] T024 [US1] Append `. tc_trigger('<id>')` to every error string of the mapped checks (start with `class-plugin-territory-check.php`, `class-escaping-check.php`); update their SPEC.md trigger column. Acceptance: `TriggerMappingTest` fixture-output assertion green; `CheckFixtureTest` still green — files: `checks/class-plugin-territory-check.php`, `checks/class-escaping-check.php`, `docs/SPEC.md` (Builder; depends on T023)
- [x] T025 [US1] Add `wp theme review sync-triggers [--dry-run]` per contracts §1: uses `Cloud_Fetcher::get_rules_yml()` (no API key) — the subcommand MUST `require_once` the cloud classes itself (`ai-review/cloud/*.php`) because T031 stops the loader from loading them when AI is disabled; test case "AI disabled → sync still works" required — writes `config/rules.snapshot.yml`, diffs trigger names vs previous snapshot, lists unmapped triggers vs `triggers.json`; exits 1 on fetch/parse failure without writing. Red test first inside the same task: `tests/Unit/SyncTriggersTest.php` with a stubbed fetcher (added/removed/renamed/unmapped cases, failure case) — files: `theme-check-cli.php` (new subcommand method only), `tests/Unit/SyncTriggersTest.php`, `config/rules.snapshot.yml` (seeded by first real run, reviewed by user) (Author; depends on T022, T023)

**Checkpoint**: US1 integration + unit tests green; `StaticNoHttpTest` green.

---

## Phase 5: User Story 2 — PHP 8.x compatibility verdict (Priority: P1)

**Goal**: `PHP_Compatibility_Check` wrapping PHPCompatibilityWP in-process, range = theme `Requires PHP` → 8.4.

**Independent Test**: `vendor/bin/phpunit --filter PhpCompatibilityCheckTest` green; sweep still green with reviewed allowlist.

- [x] T026 [P] [US2] Red test `tests/Unit/PhpCompatibilityCheckTest.php` + fixtures: `php-compatibility-fail/` (`Requires PHP: 7.4`; files containing `create_function()`, `function f(Foo $x = null)`, `${var}` interpolation) expecting REQUIRED (create_function, 8.0), WARNING (implicit nullable, 8.4), WARNING (`${}`, 8.2), INFO "evaluated 7.4–8.4"; `php-compatibility-pass/` (`Requires PHP: 8.2`, uses an enum and readonly props) expecting INFO only; `php-compatibility-floor/` (`Requires PHP: 7.4`, uses an enum) expecting REQUIRED "enum unavailable before 8.1"; a file whose only `create_function` is inside a string literal and a comment → no finding. Suite is `unit` (no WP): pass `set_context(['theme' => $fake])` where `$fake` is an anonymous object exposing `get('RequiresPHP')`, and resolve the floor from it — do not type-hint `WP_Theme` in `set_context()`. Expected: FAILS (class missing) — files: `tests/Unit/PhpCompatibilityCheckTest.php`, `tests/fixtures/themes/php-compatibility-{fail,pass,floor}/**` (Author)
- [x] T027 [US2] Implement `checks/class-php-compatibility-check.php` (`PHP_Compatibility_Check implements themecheck`, `set_context()` reads `$context['theme']->get('RequiresPHP')`): per research R3, build `PHP_CodeSniffer\Config` (standards `PHPCompatibilityWP`, `runtime-set testVersion "{floor}-8.4"`, extensions php), run `Ruleset` on each PHP file via `DummyFile` with **original** content (re-read from path, not the stripped `$php_files` value), map messages to `tc-required` (errors) / `tc-warning` (warnings) with `tc_filename()`, line and PHP version from the sniff message, prefix an INFO line with the evaluated range, append `tc_trigger('php-compatibility')`. Dynamic properties are out of scope (PHPCompatibility has no sniff; no custom token code per ADR 0001). PHPCompatibility reports enums as "available since PHP 8.1 / 8.0 or earlier" — reword so the message names 8.1 explicitly. Register in `$themechecks`. Acceptance: `PhpCompatibilityCheckTest` green; `php -l`/phpcs/phpstan clean; runtime on `twentytwentyfive` < 20 s — files: `checks/class-php-compatibility-check.php` (Author; depends on T002, T026)
- [x] T028 [US2] Add SPEC.md row for `php-compatibility` (engine `sniff`), add `-pass/-fail` to `CatalogueTest` expectations (already by convention), re-run sweep and triage any new findings on `twentytwentyfive` into the allowlist with reasons. Acceptance: `CatalogueTest`, sweep green — files: `docs/SPEC.md`, `tests/fixtures/reference/twentytwentyfive.allowlist.json` (Author; depends on T027)

**Checkpoint**: PHP compat verdicts with file/line/version; no false positives on the reference theme.

---

## Phase 6: User Story 3 — AI module optional and silent when off (Priority: P2)

**Goal**: `enabled` flag; disabled = no AI UI, no AJAX handlers, zero outbound; one-time migration; dead `AI_Review_Check` removed.

**Independent Test**: `vendor/bin/phpunit --filter AiToggleTest` green; manual devtools check per quickstart §5.

- [x] T029 [P] [US3] Red test `tests/Integration/AiToggleTest.php`: (a) with option `enabled=false`, instantiate `AI_Review_Loader` and `init()`, then assert `has_action('wp_ajax_tc_run_ai_review')`, `…tc_test_api_key`, `…tc_run_diagnostics` are all false, `wp_script_is('tc-ai-review','enqueued')` false after firing `admin_enqueue_scripts` with `appearance_page_themecheck`, and `pre_http_request` counter is 0 across load + settings page render (`AI_Settings_Page::render()` output buffered) + a static run; (b) with `enabled=true` the three actions exist; (c) migration: option without `enabled` and a non-empty encrypted key → after `AI_Settings::maybe_migrate()` `enabled===true`, `schema_version===1`; without key → `false`; already-migrated option with `enabled=false` and a key → stays `false`; no option → `false`; (d) FR-011: store an encrypted key, toggle `enabled` false then true via the sanitize callback, assert `api_keys` bytes unchanged and decryptable. Expected: FAILS — files: `tests/Integration/AiToggleTest.php` (Author)
- [x] T030 [US3] Implement settings side: `enabled` + `schema_version` keys, sanitize, `AI_Settings::is_enabled(): bool`, `AI_Settings::maybe_migrate()` hooked on `plugins_loaded` priority 5, checkbox field first in the provider section, disabled notice text per contracts §5; hide "Test key"/"Run diagnostics" UI when disabled. Acceptance: `AiToggleTest` (c) green; phpcs clean — files: `ai-review/settings/class-ai-settings.php`, `ai-review/settings/class-ai-settings-page.php` (Builder; depends on T029)
- [x] T031 [US3] Gate the loader: in `AI_Review_Loader::init()` always load `settings/*` and init `AI_Settings` + `AI_Settings_Page`; load cloud/LLM/diagnostic/AJAX layers and `register_assets()` only when `AI_Settings::is_enabled()`; remove the AI anchor/panel output from `main.php` when disabled (guard via `class_exists('AI_Ajax_Runner')` or `AI_Settings::is_enabled()`). Delete `ai-review/check/class-ai-review-check.php` and its mention in `llm/interface-llm-client.php` docblock. Acceptance: `AiToggleTest` (a)(b) green; `StaticNoHttpTest` still green — files: `ai-review/class-ai-review-loader.php`, `main.php` (AI panel block only), `ai-review/check/class-ai-review-check.php` (delete), `ai-review/llm/interface-llm-client.php` (docblock) (Builder; depends on T030; **serialise after T021 because both touch `main.php`**)
- [x] T032 [US3] Security review of `ai-review/` diff: toggle bypass attempts (direct POST to AJAX actions when disabled, option tampering without `manage_options`), nonce + capability on the new settings field, no key in logs/URLs introduced, migration idempotence. Output: findings list with path:line; no edits — files: read-only over `ai-review/**`, `main.php` (Sec; depends on T031)

**Checkpoint**: AI off ⇒ silent; AI on ⇒ unchanged behaviour.

---

## Phase 7: User Story 5 — Plugin runs on PHP 8.4+ (Priority: P3)

**Goal**: `Requires PHP: 8.1`; strict types, explicit nullable, declared props everywhere; PHPCompat 8.1–8.4 and PHPStan L6 at 0 errors.

**Independent Test**: quickstart §7 commands; CI matrix green on 8.4.

- [x] T033 [P] [US5] Red test `tests/Unit/StrictTypesTest.php`: every tracked `*.php` outside `vendor/`, `tests/fixtures/` starts with `<?php` + `declare(strict_types=1);` (allow file docblock between); `theme-check.php` header contains `Requires PHP: 8.1`. Expected: FAILS (79 files) — files: `tests/Unit/StrictTypesTest.php` (Builder)
- [x] T034 [US5] Modernise root files: `theme-check.php` (header `Requires PHP: 8.1`, `Version` bump to 2.1.0, strict_types), `main.php`, `checkbase.php`, `theme-check-cli.php`: strict_types, native param/return types, explicit `?Type`, typed properties, `var` → `protected`. Acceptance: `StrictTypesTest` for these files, `phpstan` 0 errors on them without baseline, `php -l` — files: `theme-check.php`, `main.php`, `checkbase.php`, `theme-check-cli.php` (Author; **run after T021/T022/T025/T031 settle** — same files)
- [x] T035 [P] [US5] Modernise `checks/` A–I (ids admin-menu … included-zip, 29 files): strict_types, typed `$error = []`, typed `$theme`, `var` → `protected`, explicit nullable; no logic changes (fixtures prove it). Acceptance: `CheckFixtureTest` still green; phpstan 0 on these files — files: `checks/class-{admin-menu…included-zip}-check.php` (Builder)
- [x] T036 [US5] Modernise `checks/` L–W (ids line-endings … worms + php-compatibility, 31 files), same rules. Acceptance as T035 — files: `checks/class-{line-endings…worms}-check.php`, `checks/class-php-compatibility-check.php` (Builder; after T027)
- [x] T037 [US5] Modernise `ai-review/**` (14 files): strict_types, typed props, explicit nullable, return types; no behaviour change (`AiToggleTest` proves it). Acceptance: tests green; phpstan 0 — files: `ai-review/**/*.php` (Builder; after T031)
- [x] T038 [US5] Zero-out `phpstan-baseline.neon`, run `vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.1-8.4 --ignore=vendor,tests/fixtures .` and `composer analyse`; fix stragglers. Acceptance: quickstart §7 commands clean; `StrictTypesTest` green — files: `phpstan-baseline.neon` + any file still failing (Author; after T034–T037)

**Checkpoint**: CI 8.1/8.3/8.4 green.

---

## Phase 8: Polish & Cross-Cutting

- [x] T039 [P] Finalise `docs/SPEC.md` "Plugin behaviour" with the shipped CLI/admin/toggle behaviour and the trigger-sync workflow; add section "Uncovered triggers (AI-only)" = triggers in `config/rules.snapshot.yml` with no entry in `config/triggers.json` (FR-015b); add `docs/IMPROVEMENT-PLAN.md` status column — files: `docs/SPEC.md`, `docs/IMPROVEMENT-PLAN.md` (Orch)
- [x] T040 [P] Add `.claude/settings.json` PostToolUse hook (`php -l` + `vendor/bin/phpcs` on edited `*.php`; `vendor/bin/phpunit --filter <Class>` on `checks/*.php` edits) and `.claude/skills/new-check/SKILL.md` scaffolding check + fixtures + SPEC row + trigger entry — files: `.claude/settings.json`, `.claude/skills/new-check/SKILL.md` (Builder)
- [x] T041 Run `specs/001-static-checks-php84/quickstart.md` end to end on Local (PHP 8.3), including `time wp theme review check twentytwentyfive` for SC-006 (< 60 s), and record results in `docs/reports/quickstart-run-<date>.md`; any red item becomes a follow-up task — files: `docs/reports/quickstart-run-<date>.md` (Scout)
- [x] T042 Update `readme.md` changelog (2.1.0: parity fix, CLI active fix, PHP compat check, triggers, AI toggle, PHP 8.1 floor) — files: `readme.md` (Builder)

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 Setup** → no deps; T001 is prose (user approval) and gates T027 only.
- **Phase 2 Foundational** → needs T002–T004 (test harness) and T006 (SPEC skeleton).
- **Phase 3 US4** → needs Phase 2. Runs **first** among stories: all other greens depend on the fixture corpus.
- **Phase 4 US1** → needs Phase 3 (fixtures for parity) and T004.
- **Phase 5 US2** → needs T001, T002 (PHPCS runtime), Phase 3 (sweep/allowlist).
- **Phase 6 US3** → needs Phase 2 only; **T031 serialised after T021** (shared `main.php`).
- **Phase 7 US5** → mechanical; T034 after every other task that edits root files; T036 after T027; T037 after T031.
- **Phase 8** → after all.

### Within-story red/green

Every red test task (T007, T008, T010, T018–T020, T026, T029, T033) MUST be reported failing (command + output) before its implementation task is dispatched. Implementation task acceptance = that test green + full suite green + `php -l`/phpcs on touched files.

### Shared-file guards (ORCHESTRATION.md: no two live workers on one file)

| File | Touching tasks | Rule |
|---|---|---|
| `checkbase.php` | T021 → T023 → T034 | strictly sequential |
| `main.php` | T021 → T031 → T034 | strictly sequential |
| `theme-check-cli.php` | T022 → T025 → T034 | strictly sequential |
| `docs/SPEC.md` | T006, T012–T015 (disjoint rows), T016, T017, T024, T028, T039 | row-range ownership in T012–T015; others sequential |
| `twentytwentyfive.allowlist.json` | T017 → T028 | sequential |

## Parallel execution examples

**Wave A (after T002–T004)**: T005, T006, T007, T008, T009, T010 in parallel (6 workers, disjoint files).
**Wave B (US4 bulk)**: T012, T013, T014, T015 in parallel (4 workers, disjoint id ranges) → then T016 → T017.
**Wave C**: T018, T019, T020, T026, T029, T033 red tests in parallel (6 workers) while T021 (Opus) starts on `checkbase.php`.
**Wave D**: T022, T023 after T021; T027 after T026; T030 after T029 — three independent file sets.
**Wave E**: T024, T025, T028, T031 (after their deps), T032 after T031.
**Wave F (US5)**: T035, T036, T037 in parallel; T034 last among editors; then T038.
**Wave G**: T039, T040, T042 in parallel; T041 last.

## Implementation strategy

1. **MVP = Phase 1 + 2 + 3 (US4)**: nothing user-visible changes, but the plugin becomes regression-protected and every check is documented. Safe to merge alone.
2. **Increment 2 = US1**: the two bugs reviewers hit (CLI `active` fatal, CLI/admin drift) plus triggers. First user-visible value.
3. **Increment 3 = US2**: PHP compat check — the headline capability.
4. **Increment 4 = US3**: AI toggle; security-reviewed.
5. **Increment 5 = US5 + Polish**: mechanical modernisation last so it never conflicts with behaviour work.

Totals: 42 tasks (T011 done) — Setup 6, Foundational 5, US4 6, US1 8, US2 3, US3 4, US5 6, Polish 4. Red-test tasks: 9 (+2 embedded in T025, T026). Parallelizable `[P]`: 20.
