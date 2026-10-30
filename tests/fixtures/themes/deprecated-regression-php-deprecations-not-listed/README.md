---
expect: pass
source: learning l-4fed82bd
---

Learning `l-4fed82bd` ("The deprecated-function list covers WordPress only,
not PHP itself") records that the deprecated check is a hand-maintained list
of WordPress functions and must not be extended for PHP deprecations, which
belong to the separate PHP-compatibility check. This fixture holds code that
uses PHP-deprecated functions (`utf8_encode()`, `strftime()`) and a method call
that shares its name with a deprecated WordPress function
(`WP_Query::next_post()`); the deprecated check must stay silent on it so PHP
rules never leak into the WordPress list.
