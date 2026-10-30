---
title: Pattern matching on text cannot reliably detect newer PHP 8.4 problems
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-b9fa1147
scope: repo
status: active
src: init-sessions
---

## Watch out
- All 60 existing checks are regex over source strings, which cannot understand PHP syntax. Issues like implicit nullable parameters or dynamic properties need token-level analysis.
- In the code: For PHP 8.4 checks, use token-based analysis (token_get_all or the installed phpcs with PHPCSUtils and Modernize sniffs) instead of adding more regex checks under the themecheck interface.

_From past Claude sessions here, Oct 29._
