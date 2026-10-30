---
title: "WordPress core already hides dot-folders from theme file lists, so hidden-folder exclusion changes nothing"
author: Albertotaboadaenvato
date: 2026-10-29
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 1, tokens: 1853 }
id: l-54ff0589
scope: repo
status: active
src: agent
---

## Decided
- The hidden-folder parity fixture uses `.github/`, not `.git/`. Git refuses to track any path with a `.git` component, so a `.git/config` fixture would vanish on clone and the test would pass without testing anything.

## Watch out
- WP_Theme::get_files() (via WP_Theme::scandir) skips every entry whose name starts with "." as well as CVS, node_modules, vendor and bower_components. So both runners never see `.git/`, `.github/` or `.svn`. A hidden-folder regex in a shared collector adds nothing, and Directories_Check can only fire through the unit-test FixtureTheme iterator, never in a real admin or CLI run.
- Parity tests must treat CLI output as plain text. The CLI calls strip_tags() and then htmlspecialchars_decode(), so calling strip_tags() again deletes the `<?php … ?>` examples and produces about 60 false diffs.
- Check objects keep their errors across check() calls, and tc_adapt_checks_for_fse_themes() mutates $themechecks. Rebuild fresh check instances before every run in tests.

In the code: wp-includes/class-wp-theme.php scandir() `'.' === $result[0]`; tests/Integration/RunnerParityTest.php reset_checks(), normalise().
