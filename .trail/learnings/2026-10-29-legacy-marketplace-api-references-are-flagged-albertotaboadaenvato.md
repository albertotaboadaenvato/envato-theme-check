---
title: Legacy marketplace API references are flagged as errors in new themes
author: Albertotaboadaenvato
date: 2026-10-29
standing: true
id: l-0ce6c61d
scope: repo
status: active
src: init-review
---

## Decided
- The legacy API is being retired, so new themes should not reference its domain or API key page.
- In the code: Checks flag the string `marketplace.envato.com` and `/api_keys/edit` as errors.

_From PR #55 here, Oct 29._
