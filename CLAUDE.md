# Envato Theme Check — Project Context

## 1. What this is

WordPress plugin used by Envato reviewers to statically analyse themes submitted
to ThemeForest / Elements. ~60 checks under `checks/` implement
`interface themecheck` (`check()` + `getError()`) and are loaded into the global
`$themechecks` array from `checkbase.php`. Results render in
`Appearance > Theme Check` (`main.php`) and via WP-CLI (`theme-check-cli.php`).

Reviewer-only tooling (AI Reviewer, Envato snippet triggers, `sync-triggers`)
lives in the separate plugin `../envato-theme-check-reviewer` (see §6 and
ADR 0002). This plugin ships none of it: with no callbacks on
`themecheck_finding_suffix` / `themecheck_after_results`, output is unchanged.

Repo: fork `albertotaboadaenvato/envato-theme-check`, branch
`feature/albertotaboada/ai-improvement`. Never push to the upstream repo.

## 2. Current objective

1. **Expand and harden static checks** so reviewers do not depend on AI.
2. **PHP 8.4+ support** for the plugin itself and PHP-compat detection in
   reviewed themes.
3. Keep the AI module working, optional, and secure.

Plan, roadmap and decisions: `docs/IMPROVEMENT-PLAN.md`. Who does what:
`ORCHESTRATION.md`. Rules for any agent in this repo: `AGENTS.md`.

## 3. Spec-driven development (mandatory)

- **Spec document:** `docs/SPEC.md` — one section per check (id, class,
  severity, Envato snippet trigger, what it detects, fixture names) plus admin
  UI / CLI / AI module behaviour. If it does not exist yet, run
  `/spec-driven-dev:init` first.
- **Acceptance test:** a PHPUnit test in `tests/` that runs a check (or the
  full runner) against a **fixture theme** in `tests/fixtures/themes/<case>/`
  and asserts the exact error strings. For admin UI changes, a Playwright run
  against `wp-admin/themes.php?page=themecheck` on the Local site.
- Every check needs a positive fixture (must flag) and a negative fixture
  (must stay silent). Additionally the sweep suite runs every check against the
  committed reference snapshots in `tests/fixtures/reference/` (initially
  `twentytwentyfive`) with a per-theme allowlist — loose regexes have been
  reverted twice in this repo. Proprietary themes: local report-only via
  `TC_EXTRA_REFERENCE_DIR=../../themes`.
- Order is always: spec → failing test → implementation → green suite.

## 4. Commands

```bash
composer install                       # dev deps (once tooling task lands)
vendor/bin/phpunit                     # acceptance + unit tests
vendor/bin/phpcs                       # WordPress + PHPCompatibilityWP (phpcs.xml)
vendor/bin/phpstan analyse             # level 6
php -l <file>                          # syntax check — run after every PHP edit
wp theme-check run <slug>              # CLI path; must match admin results
bin/wp-local theme review active       # wp-cli with Local's MySQL socket auto-resolved
```

Until `composer.json` exists, use the global `phpcs --standard=WordPress`.

## 5. Coding rules

- `declare(strict_types=1)` and native type hints in all new/modified PHP.
  Explicit `?Type` for nullable params (PHP 8.4 deprecates implicit nullable).
  Declare every class property; no dynamic properties.
- **New checks are token-based, not regex.** Prefer wrapping an existing
  `WordPress-Coding-Standards` or `PHPCompatibilityWP` sniff over writing new
  logic. Regex only for non-PHP files (CSS, `style.css` headers, templates).
- Error format: `<span class="tc-lead tc-required">REQUIRED</span>: ...`,
  `tc-warning`, `tc-info`, `tc-recommended`. End every message with the Envato
  trigger when one exists: `` Trigger: `:tfxxx` ``.
- Keep the WP-CLI path in sync with `check_main()`: set `$themename`/`$data`,
  pass the theme so checks can use `$this->theme`, keep the TGMPA/Merlin
  exclusion, exclude hidden folders.
- WPCS everywhere: sanitise input, escape output, nonce + `manage_options` on
  every admin/AJAX entry point. `wp_enqueue_*` for assets.
- Do not modify legacy core files (`checkbase.php`, `main.php`) beyond what the
  task requires; the AI module hooks in via `themecheck_checks_loaded`.

## 6. Extension points (consumed by `envato-theme-check-reviewer`)

- `tc_finding_suffix( '<check-id>[/<finding-key>]' )` — every finding message
  ends with its result; it applies `themecheck_finding_suffix` and returns `''`
  by default. New per-finding keys are a public contract: add them to
  `docs/SPEC.md` §Extensibility.
- `do_action( 'themecheck_after_results', $themename )` under the results table;
  `themecheck_checks_loaded` after `$themechecks` is populated.
- **This plugin makes zero outbound requests.** `StaticNoHttpTest` enforces it.
- Error format stays `<span class="tc-lead tc-required">REQUIRED</span>: ...`;
  never hard-code `Trigger:` text here.

## 7. Decisions & learnings

- Architectural decisions: `docs/decisions/` (ADR per file). The first one to
  record: PHPCS as the engine for new checks.
- Session learnings: `.trail/learnings/`. Read them before touching `checks/`,
  the CLI path, or escaping/wpdb regexes.
