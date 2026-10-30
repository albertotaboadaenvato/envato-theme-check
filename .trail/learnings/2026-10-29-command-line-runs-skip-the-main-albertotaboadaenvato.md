---
title: "Command line runs skip the main check setup, so theme variables must be recreated"
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-7fa2b1e4
scope: repo
status: active
src: init-review
---

## Watch out
- The WP-CLI path never ran the main check routine, so the theme name and data variables were missing, and later the theme context was missing too. Third-party bundled files were also not being excluded there.
- In the code: When changing check_main or run_themechecks, keep the WP-CLI path in sync: set $themename/$data, pass the theme into run_themechecks() so checks can use $this->theme, and keep the TGMPA/Merlin exclusion.

_From PRs #53, #51 here, Sep 27._
