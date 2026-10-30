---
title: Declare class properties explicitly to avoid fatal errors on newer PHP versions
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-593fd98d
scope: repo
status: active
src: init-review
---

## Watch out
- The command line tool broke on PHP 8.2+ because of an undeclared dynamic property, plus null and missing array key errors in checks.
- In the code: Declare `$fetcher` on ThemeCheckCLI and guard `$this->theme` with null checks and isset() on array access in check classes.

_From PR #77 here, Jan 6._
