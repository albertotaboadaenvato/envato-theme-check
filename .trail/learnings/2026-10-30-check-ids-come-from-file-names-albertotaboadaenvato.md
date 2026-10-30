---
title: "Check ids come from file names, pass fixtures tolerate INFO, and escaping has no Envato trigger"
author: Albertotaboadaenvato
date: 2026-10-30
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 2, tokens: 16646 }
id: l-a63f7f8b
scope: repo
status: active
src: agent
touches:
  - docs/reports/quickstart-run-2026-10-29.md
  - readme.md
  - checks/class-admin-menu-check.php
  - checks/class-bad-things-check.php
  - checks/class-basic-check.php
  - checks/class-block-patterns-check.php
  - checks/class-cdn-check.php
  - checks/class-comment-pagination-check.php
  - checks/class-comment-reply-check.php
  - checks/class-comments-check.php
---

## Decided
Check identifiers come from the check file name, not the class name, because the two diverge (the envato check declares class Themeforest). A -pass fixture is green when the check emits nothing above INFO: some checks (textdomain) always print an informational line, and INFO is not a defect.

Envato triggers are appended only from the committed mapping; the cloud rules.yml has no trigger for escaping (the old :tfescapealiases never existed there, :tfxss is a payload string and :tfdatasanitized is about input), so escaping findings carry no trigger. PHP compatibility maps to :tfphp.

## Tried and ruled out
Flagging dynamic-property creation in the PHP compatibility check: PHPCompatibility has no sniff for it and the ADR forbids hand-written token code, so it was dropped from the spec.

## Watch out
Hidden dot-folders never reach a check because WP_Theme::get_files skips them, so the directories check can only fire on a hidden-named regular file; a .git fixture cannot be committed either. Theme-level findings have no file/line, so allowlist entries use an empty file and line 0.

In the code:
config/triggers.json, config/rules.snapshot.yml (wp theme review sync-triggers), tests/Unit/CheckFixtureTest.php (INFO-tolerant pass rule), tests/Unit/CatalogueTest.php (ids from glob checks/class-*.php), tests/fixtures/reference/twentytwentyfive.allowlist.json
