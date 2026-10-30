# Envato Theme Check — Improvement Plan & Tooling

Goal: make the plugin a stronger **static** reviewer (no AI required at runtime),
keep the AI module as an **optional** add-on, and support **PHP 8.4+**.

Branch: `feature/albertotaboada/ai-improvement` on the fork
`albertotaboadaenvato/envato-theme-check` (never push to the upstream repo that
buyers see).

## 1. Current state (audited 2026-10-29)

| Area | Finding |
|---|---|
| Checks | 60 classes in `checks/`, all `preg_match` over raw file strings via `interface themecheck` (`checkbase.php`). Regex cannot understand PHP syntax. |
| Deprecations | `class-deprecated-check.php` is a hand-maintained list (~275 WP functions). No PHP-language deprecation coverage. |
| PHP 8.4 | No `Requires PHP` header, no `declare(strict_types=1)`, globals `$themechecks`/`$checkcount`, no explicit nullable types. Local runs PHP 8.3 — 8.4 cannot be verified yet. |
| Tests | None. No `composer.json`, no PHPUnit, no PHPStan, no `phpcs.xml`. |
| Tooling present | `phpcs` (global) with `WordPress`, `PHPCSUtils`, `Modernize` standards; `wp` CLI; Playwright MCP; `wp-plugin-development` skill. |
| Known regressions (trail) | Loose escaping regexes were reverted twice for false positives; WP-CLI path drifts from `check_main`; dynamic properties broke CLI on 8.2. |

## 2. Architectural decision to take first

**Adopt PHP_CodeSniffer as the check engine for new checks.**

- New checks are sniffs (token-based) or thin wrappers around existing sniffs
  from `WordPress-Coding-Standards` and `PHPCompatibilityWP`.
- The 60 regex checks keep working and migrate opportunistically.
- PHP 8.4 compatibility of *reviewed themes* comes almost free via
  `PHPCompatibilityWP` with `testVersion 8.1-`.
- Record this in `docs/decisions/0001-phpcs-engine.md` before writing the first
  new check.

Alternative rejected: keep adding regexes. Cheaper per check, but every PHP 8.x
rule (nullable params, dynamic props, `E_STRICT`, readonly, enums) would be a
new fragile regex with false-positive risk the repo has already paid for twice.

## 3. Project tooling (foundation, do first)

1. `composer.json` (dev only):
   `phpunit/phpunit`, `phpstan/phpstan`, `szepeviktor/phpstan-wordpress`,
   `squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`,
   `phpcompatibility/phpcompatibility-wp`, `dealerdirect/phpcodesniffer-composer-installer`.
2. `phpcs.xml`: `WordPress` + `PHPCompatibilityWP`, `testVersion 8.1-`,
   exclude `vendor/`, `assets/`.
3. `phpstan.neon`: level 6, WordPress stubs, paths `checks/`, `ai-review/`,
   root PHP files.
4. `tests/`: PHPUnit with **fixture themes** under `tests/fixtures/themes/<case>/`.
   Each check gets one positive and one negative fixture. This is the project's
   acceptance test.
5. `docs/SPEC.md` via `/spec-driven-dev:init`: one row per check — id, class,
   severity, Envato trigger, what it detects, fixture names.
6. Plugin header: add `Requires PHP: 8.1`, bump `Version`.

## 4. Claude Code automations

### Hooks (`.claude/settings.json`)

| Event | Match | Command | Why |
|---|---|---|---|
| PostToolUse | `Edit\|Write` on `*.php` | `php -l "$f" && phpcs --standard=phpcs.xml "$f"` | No tests exist today; a parse error white-screens wp-admin. |
| PostToolUse | `Edit\|Write` on `checks/*.php` | `vendor/bin/phpunit --filter <ClassName>` | A check is not done without its fixture green. |

### Skills (`.claude/skills/`)

| Skill | Invocation | Purpose |
|---|---|---|
| `/new-check <slug> <severity> <trigger>` | user-only | Scaffolds class + positive/negative fixtures + PHPUnit test + `SPEC.md` row. Enforces "wrap an existing sniff before writing one". |
| `/php-compat-audit [path]` | both | Runs `phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.4-` and summarises per file. Works on the plugin and on a theme under review; prototype of the in-plugin PHP-compat check. |
| `/ai-smoke` | user-only | Runs `tc_run_ai_review` against `themes/twentytwentyfive` with the active provider; prints parsed findings. Spends API credits, so never auto-invoked. |

### Subagents (`.claude/agents/`)

| Agent | Scope | Hard rule |
|---|---|---|
| `check-author` | Turn one Envato rule (snippet trigger) into fixture → red test → token-based implementation. | No regex for PHP syntax. If a WPCS/PHPCompatibility sniff covers it, wrap it. Test against `committed reference snapshots (`vendor/bin/phpunit --testsuite sweep`) for false positives. |
| `php-modernizer` | `checks/`, `checkbase.php`, `main.php`, `theme-check-cli.php`: strict types, native type hints, explicit `?Type`, `readonly`, `match`, replace globals with a registry. | Must pass phpstan level 6 and keep WP-CLI path in sync with `check_main`. |
| `wp-security-reviewer` | `ai-review/`: nonce + `manage_options` on every `wp_ajax_*`, `wp_kses_post`/`esc_html` on every LLM-derived string, no plaintext key in options/logs/JS. | Reports only; never edits. |

Existing `wordpress-master.md` stays as the broad WordPress consultant.

### MCP / plugins

- **Playwright** (installed): acceptance test for the admin UI at
  `wp-admin/themes.php?page=themecheck` after touching `main.php` or
  `ai-review/assets/`.
- **context7**: `claude mcp add context7 -- npx -y @upstash/context7-mcp` —
  PHPCS/PHPCSUtils sniff API is thinly documented and changed between 3.x and 4.x.
- No new plugins: `spec-driven-dev`, `ponytail`, `github`,
  `wp-plugin-development` already cover the workflow.

## 5. Roadmap (each row = one Orca worker task)

| # | Task | Owner model | Acceptance | Status (2026-10-29) |
|---|---|---|---|---|
| 1 | `composer.json`, `phpcs.xml`, `phpstan.neon`, `tests/bootstrap.php` | Sonnet | `composer install && vendor/bin/phpcs -i` lists WordPress + PHPCompatibilityWP | ✅ T002–T004 |
| 2 | `docs/SPEC.md` with all 60 existing checks catalogued | Haiku (inventory) → Orchestrator (review) | Every `checks/*.php` class appears once | ✅ T006/T012–T015 (61 checks) |
| 3 | ADR `docs/decisions/0001-phpcs-engine.md` | Orchestrator | Approved by user | ✅ T001 |
| 4 | Fixture themes + PHPUnit tests for 5 highest-risk regex checks (escaping, plugin-territory, deprecated, bad-things, i18n) | Opus | Tests green; sweep green | ✅ T016 (+ all 61 fixtured, T012–T015) |
| 5 | PHP 8.4 modernisation of core (`checkbase.php`, `main.php`, CLI) | Opus | phpstan level 6 clean; CLI and admin run identical results | ✅ T021/T022/T034 |
| 6 | New check: `PHP_Compatibility_Check` wrapping PHPCompatibilityWP | Opus | Fixture with `create_function`/implicit nullable flagged; clean theme passes | ✅ T026–T028 |
| 7 | New checks from Envato triggers not yet covered (list from `config.yml`) | check-author, one trigger per worker | Fixture pair per check | ⏳ next feature — 143 uncovered triggers listed in docs/SPEC.md |
| 8 | Hooks + skills from §4 | Sonnet | Hook fires on edit; `/new-check demo` scaffolds compiling code | ✅ T040 |
| 9 | AI module made optional: settings toggle, zero network calls when off | Sonnet | Static run with AI disabled makes no `wp_remote_*` call | ✅ T029–T032/T037 |

Order 1 → 3 → 2 → 4 → 5 → 6 → 7 in parallel waves; 8 and 9 whenever a worker is free.
