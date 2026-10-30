---
title: "Escaping findings now carry the :tfwpvalidate snippet trigger, and triggers can be mapped per finding"
author: Albertotaboadaenvato
date: 2026-10-30
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 1, tokens: 30679 }
id: l-c9b378f5
scope: repo
status: active
src: agent
touches:
  - docs/SPEC.md
  - config/triggers.json
  - config/triggers.schema.json
  - checkbase.php
  - tests/Unit/TriggerMappingTest.php
---

## Decided
- Escaping findings end with `Trigger: :tfwpvalidate` (the generic "data validation" snippet). This supersedes only the sentence in the check-ids learning (l-a63f7f8b) saying escaping has no cloud trigger: `:tfescapealiases` never existed in rules.yml, but `:tfwpvalidate` does and reviewers use it for unescaped output. The rest of that learning (ids come from file names) still holds.
- Trigger keys may be `check-id` or `check-id/finding_key` (e.g. `basic/wp_head`); a composite key with no entry falls back to the bare check id. Multi-finding checks (basic, bad-things, envato) use composite keys; the allowed keys are the per-finding table in docs/SPEC.md and CatalogueTest enforces it.
- Keys for findings the code cannot detect yet (envato/wp_load, envato/svg, envato/bfi_thumb, envato/unyson, widgets/sidebar_guard) stay in the SPEC table marked "(no static finding yet)" and out of triggers.json; never add a detection just to satisfy a trigger.

## Watch out
- Several rules.yml entries are mislabelled at the source (`:tfwptitletag`, `:tfjsfooter`, `:tf64`, `:tfpath` carry another snippet's text). Do not map title checks to `:tfwptitletag` until the cloud file is fixed.
- The snapshot is the raw cloud file; only the parsed list is decoded. The Uncovered table in SPEC is regenerated from the parser output, not by hand.

In the code: `config/triggers.json`, `config/triggers.schema.json`, `tc_trigger()` in `checkbase.php`, `tests/Unit/TriggerMappingTest.php`, `ThemeCheckCLI::parse_rules_yml()`.
