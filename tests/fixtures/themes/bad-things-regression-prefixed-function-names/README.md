---
expect: pass
source: learning l-989fece8; guards in c3962b0
---

Learning `l-989fece8` records that matching a keyword at the end of a function
name flagged legitimate custom functions (PR #36, echo/print). The bad-things
patterns carry the same risk: `system(`, `exec(` and `eval(` are suffixes of
ordinary prefixed theme functions and of `curl_exec()`. The `(?<!_)`,
`[^_]exec` and `(?<![_|a-z0-9|\.])` guards in place since `c3962b0` exist to
prevent that. This fixture holds prefixed functions whose names end in those
keywords; the bad-things check must stay silent on it.
