---
title: Keep the unit-test WordPress function doubles out of PHPStan analysis
author: Albertotaboadaenvato
date: 2026-10-30
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 8, tokens: 115974 }
id: l-c3f9bd5b
scope: repo
status: active
src: agent
touches:
  - main.php
---

## Decided
- PHPStan excludes the unit bootstrap that defines passthrough WordPress functions. When it was analysed, PHPStan used those doubles instead of the real WordPress stubs: every add_action()/add_filter() call was reported as "has no effect", along with dozens of missing-type errors in the doubles themselves.

## Watch out
- The stubs type WP_Theme::get() as always false for headers they don't know. The plugin adds License, License URI and Template Version through extra_theme_headers, so reads of those need a string|false @var, not an ignore.
- Don't let phpcbf auto-fix loose comparisons (== to ===): that changes behaviour in the legacy checks. Run it with that sniff excluded.

In the code: phpstan.neon excludePaths tests/bootstrap-unit.php; main.php $license/$license_uri; vendor/bin/phpcbf --exclude=Universal.Operators.StrictComparisons
