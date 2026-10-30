---
title: Snippet triggers and the AI reviewer live in the separate envato-theme-check-reviewer plugin; this plugin only exposes a neutral finding-suffix filter
author: Albertotaboadaenvato
date: 2026-10-30
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 1, tokens: 2098 }
id: l-4b8b38bc
scope: repo
status: active
replaces: l-c9b378f5
src: agent
touches:
  - docs/SPEC.md
  - main.php
  - checkbase.php
---

## Decided
- The public plugin never prints `Trigger:` text and ships no AI code. Checks call `tc_finding_suffix( '<check-id>[/<finding-key>]' )`, which is just `apply_filters( 'themecheck_finding_suffix', '', $key )`. With no callback it returns ''.
- Everything reviewer-only (ai-review/, config/triggers.json, rules snapshot, `wp theme review-tools sync-triggers`, the :tfwpvalidate/:tfcpt/... mapping with `check/finding → check` fallback) moved to `../envato-theme-check-reviewer` (github albertotaboadaenvato/envato-theme-check-reviewer, private). See ADR 0002.
- Per-finding keys (`basic/wp_head`, `bad-things/base64`, `envato/server`, …) are a public contract listed in docs/SPEC.md §Extensibility; add a key there before using it in a check.

## Watch out
- The reason: Theme Check is installed by authors too, so reviewer options must not appear upstream. Do not reintroduce triggers.json or AI settings here, even behind a toggle.
- `main.php` fires `do_action( 'themecheck_after_results', $theme_slug )` for the add-on's panel; keep it when editing the results table.

In the code: `tc_finding_suffix()` in `checkbase.php`, `tests/Unit/FindingSuffixTest.php`, `docs/decisions/0002-reviewer-tooling-separate-plugin.md`.
