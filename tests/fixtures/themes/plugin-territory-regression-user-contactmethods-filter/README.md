---
expect: fail
source: fa933f4
---

Commit `fa933f4` ("Modified user_contactmethods check") fixed a miss: the
original pattern `/[^a-z0-9](?<!_)user_contactmethods\s?\(/` only matched
`user_contactmethods` used as a function call, but themes extend contact
methods through the filter hook name, which is followed by a quote, not a
parenthesis, so `add_filter( 'user_contactmethods', ... )` went unflagged.
This fixture holds that real-world hook usage; the plugin-territory check must
flag it as REQUIRED.
