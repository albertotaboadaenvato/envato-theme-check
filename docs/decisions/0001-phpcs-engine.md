# ADR 0001: PHP_CodeSniffer as the engine for new PHP checks

**Status**: Accepted (2026-10-29)
**Deciders**: Alberto Taboada (Envato), with the planning session recorded in
`specs/001-static-checks-php84/research.md` §R2–R3.

## Context

The plugin has 60 check files; all but two inspect PHP source with regular expressions.
Regex cannot tell code from strings or comments, which is exactly where the plugin's
historical false positives came from: two escaping-regex changes (`2aed4d6`, `f584859`) were
reverted after flagging legitimate output in real themes. The next capability reviewers need —
a PHP 8.0–8.4 compatibility verdict on submitted themes — is not expressible as patterns at
all (implicit nullable parameters, dynamic properties, removed functions inside strings,
version-dependent syntax).

Reviewer hosts do not allow `exec`/`proc_open`, so the engine must run in-process.

## Decision

New checks that inspect PHP source use **PHP_CodeSniffer run in-process** (`Config` +
`Ruleset` + `DummyFile`), wrapping existing sniff sets rather than writing new logic:

- `PHPCompatibilityWP` for the PHP-version check (`checks/class-php-compatibility-check.php`),
  `testVersion = {theme Requires PHP, min 7.4}-8.4`.
- `WordPress-Coding-Standards` sniffs for future security/escaping checks, migrated one
  check at a time (constitution IV); existing regex checks are kept, catalogued and fixtured.

Consequences for packaging: `squizlabs/php_codesniffer`, `phpcompatibility/php-compatibility`
(`^10@alpha`, the only branch with PHP 8.4 rules) and `phpcompatibility/phpcompatibility-wp`
become **runtime** dependencies shipped in `vendor/` (plugin zip grows by roughly 10 MB).
If Composer cannot reconcile PHPCS majors between runtime and dev tooling,
`bamarni/composer-bin-plugin` isolates the dev set (`vendor-bin/`).

## Alternatives considered

- **Keep regex, add more patterns** — rejected: the false-positive history is the problem
  being solved; PHP 8.x rules cannot be patterns.
- **Hand-rolled `token_get_all` walkers** — rejected: reimplements what PHPCompatibility
  already maintains across every PHP release; maintenance cost lands on reviewers.
- **Shell out to a `phpcs` binary** — rejected: unavailable on reviewer hosts; also forks the
  configuration between CLI and admin paths (constitution V).
- **PHPStan as engine** — rejected: needs a bootstrap of the theme under analysis and WordPress
  stubs per theme; slow and fragile for arbitrary submissions.

## Consequences

- Positive: findings carry file + line + PHP version; strings and comments are ignored for
  free; rules track upstream PHP releases via dependency updates.
- Negative: larger plugin package; dependency on an alpha release until PHPCompatibility 10
  is tagged stable; PHPCS adds up to ~20 s on a ~300-file theme (measured in T027/T041).
- Follow-up: every new sniff-based check still needs its pass/fail fixtures and catalogue row
  (constitution III); migration of an existing regex check is a separate spec change each time.
