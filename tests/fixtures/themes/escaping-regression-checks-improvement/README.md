---
expect: pass
source: f584859 (reverts PR #46, 1c2d09e / aa90e32); learning l-989fece8
---

Commit `f584859` reverted PR #46 ("Improved Regex"), which widened the
escaping and wpdb regexes in `checks/themeforest.php` (now
`checks/class-envato-check.php`): an echo/print pattern with an optional
leading boundary that matched custom functions whose names end in `echo` or
`print` (learning l-989fece8), an error-suppression pattern `@(?!media|...)`
that matched e-mail addresses in strings, and `->get_results(` / `->query(`
patterns that flagged any `$wpdb` call without `prepare` on the same line,
including static queries and already-prepared ones. This fixture holds that
legitimate, already-escaped code; the escaping check must stay silent on it.
