---
title: "PHP 8.4 support cannot be verified locally yet, and there are no tests"
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-2d64eedf
scope: repo
status: active
src: init-sessions
---

## Watch out
- The local machine runs PHP 8.3 and the project has no composer, phpunit or phpstan. Changes aimed at 8.4 would ship unverified unless an 8.4 runtime or test harness is added first.
- In the code: Before claiming 8.4 compatibility, get a PHP 8.4 runtime (docker or similar) and add a minimal test harness. `php -v` locally reports 8.3.

_From past Claude sessions here, Oct 29._
