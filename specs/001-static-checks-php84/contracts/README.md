# Contracts

External surfaces this feature adds or changes. Internal PHP APIs are in `data-model.md`.

## 1. WP-CLI

```
wp theme review check <slug> [--format=true]
```
Unchanged signature. **Changed behaviour**: output findings identical to the admin page for
the same theme (same file set, exclusions and content preparation). Exit code stays 0 even
when the review fails (the CLI prints `FAIL: THEME DID NOT PASS REVIEW`); a non-zero exit is
a candidate follow-up, not part of this feature (kept for byte-compatibility with existing
scripts).

```
wp theme review active [--format=true]
```
Unchanged signature. **Fixed**: runs against `wp_get_theme()->get_stylesheet()`; no undefined
function call.

```
wp theme review sync-triggers [--dry-run]
```
New. Fetches cloud `rules.yml` through `Cloud_Fetcher` (requires the AI module's cloud URL
config only, not an API key), rewrites `config/rules.snapshot.yml`, and prints:

```
Added:    :tfnewthing
Removed:  :tfoldthing
Renamed:  :tfold -> :tfnew   (heuristic: same text)
Unmapped: 12 triggers have no entry in config/triggers.json
```
Exit 0 on success, 1 on fetch/parse failure (snapshot untouched). `--dry-run` prints without
writing.

## 2. Check output format (HTML string in `getError()`)

```
<span class="tc-lead tc-required">REQUIRED</span>: <message> <optional tc_grep/tc_preg block> Trigger: <code>:tfxxx</code>
```
- Severity span is first; label text in English upper-case; message is `esc_html`/`wp_kses_post`
  safe.
- `Trigger: <code>…</code>` is the final segment when present; absent otherwise.
- CLI strips tags, so the plain form is `REQUIRED: <message> Trigger: :tfxxx`.

## 3. `config/triggers.json`

```json
{
  "$schema": "./triggers.schema.json",
  "plugin-territory":  { "trigger": ":tfcpt", "note": "CPT/shortcodes in theme" },
  "php-compatibility": { "trigger": ":tfphp" },
  "basic/wp_head":     { "trigger": ":tfwphead" }
}
```
Keys are `<check-id>` or `<check-id>/<finding_key>` (pattern `^[a-z0-9-]+(/[a-z0-9_]+)?$`);
`tc_trigger( 'basic/wp_head' )` falls back to `tc_trigger( 'basic' )` when the composite key is
absent. Unknown check ids, and composite keys not in the SPEC per-finding table, fail
`CatalogueTest`. Values: `trigger` (required, pattern `^:tf[a-z0-9_]+$`), `note` (optional).

## 4. Reference allowlist `tests/fixtures/reference/<slug>.allowlist.json`

Array of `{check, file, line, reason, confirmed_by}`; all fields required; `line` integer ≥ 0.
Theme-level findings (no file reference, e.g. `style-tags`, `textdomain`) use `file: ""` and
`line: 0`; file-level findings use the theme-relative path and the 1-based line.

## 5. Admin settings (Appearance → Theme Check → AI Review)

- New first field: **Enable AI Reviewer** (checkbox). Capability `manage_options`, nonce via
  Settings API.
- When unchecked: provider/key fields remain visible and editable; "Test key", "Run
  diagnostics" and the Theme Check page's AI panel are not rendered; `wp_ajax_tc_test_api_key`,
  `wp_ajax_tc_run_diagnostics`, `wp_ajax_tc_run_ai_review` are not registered.
- A notice under the checkbox states: "AI Reviewer is disabled. Static checks run normally and
  the plugin makes no outbound requests."

## 6. Test commands (project contract, FR-016)

| Purpose | Command | Pass condition |
|---|---|---|
| Unit suite | `composer test:unit` → `vendor/bin/phpunit --testsuite unit` | exit 0 |
| Integration suite | `composer test:integration` (needs Local WP) | exit 0 |
| Coding standards + PHP compat | `composer lint` → `vendor/bin/phpcs` (phpcs.xml) | 0 errors |
| Static analysis | `composer analyse` → `vendor/bin/phpstan analyse` | 0 errors |
| False-positive sweep | `vendor/bin/phpunit --testsuite sweep` | exit 0 |
