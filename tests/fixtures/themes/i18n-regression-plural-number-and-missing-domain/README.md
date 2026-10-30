---
expect: pass
source: 4722000 / 02d1041 (issue #18), 1d517cb; learning l-ffa9eb1b
---

Issue #18 (`4722000`, reverted in `02d1041` "due to breaking") and `1d517cb`
("More than one domain false warning fixed") show two shapes that the
translation checks got wrong: the `$number` argument of `_n()` is a legitimate
variable, and calls without a text domain are a recommendation-level text
domain matter (learning `l-ffa9eb1b`, INFO in the textdomain check), not an
i18n finding. This fixture holds plural calls with a variable count, a
translation without a domain and the recommended placeholder pattern; the i18n
check must emit no finding above INFO on it.
