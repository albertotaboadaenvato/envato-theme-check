---
title: "Text domain problems are recommendations, so they show as info rather than errors"
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-ffa9eb1b
scope: repo
status: active
src: init-review
---

## Decided
- Envato requirements only recommend text domains, so warnings were downgraded to info, and a redundant single-domain message was removed.
- In the code: Keep text domain messages as INFO level in the text domain check; wording should reflect Envato requirements, not wp.org translation support.

_From PRs #53, #50 here, Sep 27._
