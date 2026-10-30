---
title: "The deprecated-function list covers WordPress only, not PHP itself"
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-4fed82bd
scope: repo
status: active
src: init-sessions
---

## Watch out
- The deprecation check is a hand-maintained list of about 275 WordPress entries. It will never flag PHP 8.x deprecations, so those gaps need a separate mechanism.
- In the code: Do not extend class-deprecated-check.php for PHP deprecations. Add PHP-version checks separately, for example with phpcs PHPCompatibility-style rules.

_From past Claude sessions here, Oct 29._
