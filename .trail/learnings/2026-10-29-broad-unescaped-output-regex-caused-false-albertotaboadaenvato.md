---
title: Broad unescaped-output regex caused false positives and was rolled back twice
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-b5461d37
scope: repo
status: active
src: init-review
---

## Watch out
- An unescaped translations check was reverted for too many false positives, and the improved-regex PR was also reverted. Loose regexes flag valid theme code.
- In the code: Before tightening or adding escaping/wpdb regexes in checks/, test against real themes for false positives. See reverts 2aed4d6 and f584859.

_From this repo's docs._
