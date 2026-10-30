---
title: Hidden folders like .git are not excluded from theme checks
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-e49b4a3a
scope: repo
status: active
src: init-review
---

## Tried and ruled out
- A proposal to skip hidden directories was closed because finished themes should not contain hidden files or folders at all. Flagging them is intended.
- In the code: Do not add hidden-directory exclusion to the file scanning logic. Hidden files present in a submitted theme are meant to be reported.

_From PR #68 here, Jun 21._
