# Research: Static Checks First & PHP 8.4 Support

All NEEDS CLARIFICATION items from Technical Context resolved below. Each entry: Decision /
Rationale / Alternatives.

## R1. Test harness: two suites, no test database

**Decision**: PHPUnit 10 with two suites.
- `unit` — Brain Monkey + Mockery stubs for WP functions (`__`, `esc_html__`, `apply_filters`,
  `wp_get_theme`…). Covers every check class directly: feed `[$path => $content]` arrays from
  fixture themes, assert `getError()` strings. No WordPress, no DB. Runs in CI on PHP 8.1–8.4.
- `integration` — bootstrap loads the sibling Local WordPress install via `wp-load.php`
  (`WP_ROOT` env, default `../../..`). Covers runner parity (admin vs CLI collectors), the AI
  toggle's zero-request invariant (`pre_http_request` interceptor), settings migration and the
  CLI `active` command. Read-only against the live DB; no writes.
  `ponytail:` loads the live install instead of `wp-phpunit` test DB — upgrade to
  `syde/wp-phpunit-integration` the first time a test needs to write to the DB.

**Rationale**: 57 of 59 checks only need string arrays and i18n stubs; forcing them through a
WordPress bootstrap makes the suite slow and host-dependent. Parity and HTTP interception
genuinely need `WP_Theme` and the HTTP API, so a thin integration layer is unavoidable.

**Alternatives**: WP_Mock (lower friction for legacy, weaker Mockery integration — rejected);
full `wp-phpunit` for everything (needs MySQL in CI; nothing here writes — rejected);
WordPress Playground CLI (promising, immature for CI on this project — deferred).

## R2. PHPCompatibility version and PHPCS compatibility

**Decision**: `phpcompatibility/php-compatibility:^10.0@alpha` (first line with PHP 8.3+ sniffs)
plus `phpcompatibility/phpcompatibility-wp` at the version its composer constraints accept,
together with `wp-coding-standards/wpcs:^3` on the PHPCS line both allow. The tooling task's
acceptance is `vendor/bin/phpcs -i` listing `WordPress`, `PHPCompatibility`,
`PHPCompatibilityWP`. **Fallback if the PHPCS major versions conflict** (10.x alpha2 lists
PHPCS 4.0.1+, WPCS 3.x is PHPCS 3.x): isolate PHPCompatibility in its own namespace with
`bamarni/composer-bin-plugin` (`vendor-bin/compat/`) and point the in-plugin check at that
binary/autoloader. Decide inside the tooling task; record the outcome in `research.md` §R2.

**Outcome (T002)**: no conflict. On PHP 8.3 Composer resolved `squizlabs/php_codesniffer`
3.13.6, `phpcompatibility/php-compatibility` 10.0.0-alpha2, `phpcompatibility/phpcompatibility-wp`
and `wp-coding-standards/wpcs` 3.4.1 in one tree; `vendor/bin/phpcs -i` lists `WordPress`,
`PHPCompatibility` and `PHPCompatibilityWP`. The bamarni/`vendor-bin` fallback was not needed
and was not created.

**Rationale**: PHPCompatibility 9.x stops at PHP 8.0-era sniffs and would make the
PHP_Compatibility_Check miss everything the spec cares about (implicit nullable, 8.3/8.4
deprecations). The alpha is the only line that covers them. Search results confirm 10.0 is
pre-release with breaking changes and a moving PHPCS floor; the bin-plugin fallback removes the
dependency-solver risk from the critical path.

**Alternatives**: 9.3.5 stable (insufficient coverage — rejected); writing our own 8.x sniffs
(constitution IV says wrap, don't reinvent — rejected); PHPStan on reviewed themes (needs
autoload of arbitrary theme code — rejected).

## R3. Invoking PHPCS from inside the plugin (runtime dependency)

**Decision**: `PHP_Compatibility_Check` runs PHPCS **in-process** via
`PHP_CodeSniffer\Config` + `Ruleset` + `Files\DummyFile` (or `LocalFile`) per theme PHP file,
with `testVersion = "{floor}-8.4"`, standard `PHPCompatibilityWP`, and maps sniff `error` →
`tc-required`, `warning` → `tc-warning`. PHPCS and PHPCompatibility therefore move to
`require` (not `require-dev`); the plugin ships with `vendor/`. Files are fed as **original
content** (not `tc_strip_comments` output) so PHPCS line numbers match the theme.

**Rationale**: shelling out to `vendor/bin/phpcs` is blocked on many reviewer hosts
(`exec` disabled) and would break the "no exec" posture the plugin enforces on themes.
In-process is also what makes the unit suite able to run the check with no WordPress.

**Alternatives**: `exec('phpcs --report=json')` (rejected above); regex per deprecated
construct (constitution IV — rejected).

## R4. Trigger mapping file

**Decision**: `config/triggers.json` — `{ "<check-id>": { "trigger": ":tfcpt", "note": "" } }`,
plus `config/rules.snapshot.yml` (last synced cloud file, for diffing). A new WP-CLI subcommand
`wp theme review sync-triggers` fetches `rules.yml` via the existing `Cloud_Fetcher`, rewrites
the snapshot, and prints added/removed/renamed triggers; it never edits `triggers.json`
automatically (a human maps check → trigger). Checks call `tc_trigger( 'check-id' )`, which
returns `" Trigger: `:tfx`"` or `""`.

**Rationale**: clarification Q1. JSON is readable in PR diffs, needs no YAML parser at runtime,
and the human-in-the-loop mapping keeps the snapshot diff as the review artifact.

**Alternatives**: trigger constant per check class (no central view, no sync — rejected);
runtime cloud fetch with cache (network dependency — rejected by Q1).

## R5. Reference themes and false-positive sweep

**Decision**: Reference themes are **committed snapshots** under
`tests/fixtures/reference/<slug>/`, not the live `wp-content/themes/` directory. Seed with
`twentytwentyfive` (GPL, from WordPress core). Proprietary themes such as `moveora`/`psycare`
cannot be committed; the sweep accepts an optional `TC_EXTRA_REFERENCE_DIR` env var and runs
over whatever is there locally, reporting but **not failing** CI for extra themes. Allowlists:
`tests/fixtures/reference/<slug>.allowlist.json` (array of `{check, file, line, reason,
confirmed_by}`); the sweep fails on unlisted findings and on stale entries.

**Rationale**: during this session the live themes directory changed twice (`psycare` →
`moveora` → gone). A test that depends on it is flaky by construction. **This amends the spec
assumption "reference themes are available at `wp-content/themes/`"** — flagged for the user in
the plan completion report; spec/constitution wording to be updated in the tasks phase.

**Alternatives**: symlink to live themes (same volatility — rejected); download themes at test
time (network, licensing — rejected).

## R6. Runner unification (admin/CLI parity)

**Decision**: extract `tc_collect_theme_files( WP_Theme $theme ): array{php,css,other}` into
`checkbase.php` and make both `run_themechecks_against_theme()` and `ThemeCheckCLI::check()`
call it. The collector owns: parent files (`get_files(null,-1,true)`), `unset($files[0])`
workaround, hidden-folder exclusion (`/\/\.[^\/]+\//`), TGMPA/Merlin exclusion, dev-dir
filter, `tc_strip_comments` for PHP, directory guard for other files. `wp_raise_memory_limit(
'admin' )` moves into the collector. `active()` becomes
`$this->check( [ wp_get_theme()->get_stylesheet() ], $assoc_args )` — the `$themename/$data`
globals and `tc_get_theme_data()` are deleted (no check reads them; Wave 0 §5).

**Rationale**: one code path is the only way parity stays true (constitution V).
`php_strip_whitespace` in the CLI also stripped newlines, changing line-based `tc_preg` output.

## R7. AI module toggle and migration

**Decision**: new key `enabled` (bool) inside the existing `tc_ai_review_settings` option.
`AI_Review_Loader::init()` loads only `settings/*` when disabled (so the toggle itself can be
rendered), and skips cloud/LLM/diagnostic/AJAX layers and asset enqueue. One-time migration on
`plugins_loaded`: if option exists and has no `enabled` key → set `enabled = (any key
non-empty)`; fresh install → `false`. Disabled AJAX actions are **not registered**; a direct
POST therefore gets WordPress's default `0`/`-1`; the settings page shows an explicit notice.
`AI_Review_Check` (dead) is deleted; the AJAX runner is the single AI entry point (FR-012,
option "remove").

**Rationale**: clarification Q4; not registering handlers is both the simplest and the
strongest "zero requests" guarantee. Deleting dead code beats registering a second AI path.

## R8. PHPStan

**Decision**: `phpstan/phpstan:^2`, `szepeviktor/phpstan-wordpress`, `php-stubs/wordpress-stubs`,
level 6, paths: root `*.php`, `checks/`, `ai-review/`, `tests/`. Baseline file allowed for
legacy regex checks only; new/modified files must be baseline-free.

**Rationale**: standard WP stack; maintainer-abandonment warning noted — low switching cost
(stubs are generated files).

## R9. PHP 8.4 verification without a local 8.4

**Decision**: GitHub Actions matrix `php: [8.1, 8.3, 8.4]` running `phpunit --testsuite unit`,
`phpcs`, `phpstan`. Integration suite runs locally only (needs the Local site). Until CI exists,
`PHPCompatibility testVersion 8.1-8.4` over the plugin is the proxy.

## R10. Catalogue (`docs/SPEC.md`) kept honest by a test

**Decision**: `tests/Unit/CatalogueTest` parses `docs/SPEC.md` tables and asserts a 1:1 match
with registered check classes and with fixture directories on disk. Spec rows: `id | class |
severity | trigger | detects | pass-fixture | fail-fixture`.

## Sources

- [PHPCompatibility 10.0.0-alpha1 release](https://newreleases.io/project/packagist/phpcompatibility/php-compatibility/release/10.0.0-alpha1)
- [PHPCompatibility changelog (10.x)](https://cdn.jsdelivr.net/wp/brightleaf-digital-php-compatibility-scanner/trunk/vendor/phpcompatibility/php-compatibility/CHANGELOG.md)
- [PHPCompatibility repo](https://github.com/wimg/phpcompatibility)
- [Brain Monkey vs WP_Mock guide](https://helpmetest.com/blog/wordpress-plugin-testing-guide/)
- [syde/wp-phpunit-integration](https://packagist.org/packages/syde/wp-phpunit-integration)
- [WordPress Playground PHPUnit](https://wordpress.github.io/wordpress-playground/guides/phpunit-testing)
- [szepeviktor/phpstan-wordpress](https://packagist.org/packages/szepeviktor/phpstan-wordpress)
- [php-stubs/wordpress-stubs](https://packagist.org/packages/php-stubs/wordpress-stubs)
