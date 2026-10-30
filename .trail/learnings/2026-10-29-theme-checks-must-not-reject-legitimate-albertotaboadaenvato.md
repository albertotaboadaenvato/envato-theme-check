---
title: Theme checks must not reject legitimate functions whose names end in echo or print
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-989fece8
scope: repo
status: active
src: init-review
---

## Watch out
- The echo/print check matched any function name ending in those words, flagging valid custom functions. It was changed to require a non-identifier character before the keyword.
- In the code: In checks/themeforest.php use the regex `/[^a-zA-Z0-9_\x7f-\xff](echo|print)\s*\(?\s*\$/` so only real echo/print are matched.

_From PR #36 here, May 13._
