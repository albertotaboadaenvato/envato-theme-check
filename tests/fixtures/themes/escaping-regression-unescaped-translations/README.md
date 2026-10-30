---
expect: pass
source: 2aed4d6
---

Commit `2aed4d6` ("Revert unescaped translations check. Causing too many false
positives.") removed the REQUIRED regex `/(^|\s)(__|_e|_n|_x|_nx|_ex)\s*?\(/`
from `checks/themeforest.php`. It fired on every translation call preceded by
whitespace, including strings that are assigned and escaped later, passed to a
registration API, or wrapped in an escaping function. This fixture holds that
legitimate code; the escaping check must stay silent on it (no finding above
INFO), so the loose pattern cannot come back.
