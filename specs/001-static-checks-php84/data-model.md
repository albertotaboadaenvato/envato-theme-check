# Data Model: Static Checks First & PHP 8.4 Support

No new database tables. Persistent state is one `wp_options` row plus committed files.

## Check

| Field | Type | Rules |
|---|---|---|
| id | string (kebab) | Unique; equals fixture dir prefix and `triggers.json` key. Derived from the **file name**: `checks/class-<id>-check.php` → `<id>` (class names diverge, e.g. `class-envato-check.php` → `class Themeforest`). Tests map a registered class to its id via `ReflectionClass::getFileName()` |
| class | string | Implements `themecheck`; registered in `$themechecks` |
| engine | enum `regex` \| `token` \| `sniff` \| `meta` | `meta` = file/header checks with no content parsing |
| severities | set of `required` \| `warning` \| `recommended` \| `info` | At least one |
| trigger | string `:tf[a-z0-9_]+` or null | Read from `triggers.json` at runtime |
| fixtures | `{pass: dir, fail: dir}` | Both MUST exist under `tests/fixtures/themes/` |

Relationships: 1 Check → 0..1 Trigger; 1 Check → 2..n Fixture themes; 1 Check → 0..n Findings.

## Finding

| Field | Type | Rules |
|---|---|---|
| severity | enum | Rendered as `<span class="tc-lead tc-{required\|warning\|recommended\|info}">LABEL</span>` |
| message | string (HTML-safe) | Ends with `` Trigger: `:tfx` `` when the check has a trigger |
| file | string | Theme-relative path via `tc_filename()` |
| line | int \| null | Required for `sniff` engine findings |
| check_id | string | Source check |

Invariant (FR-001): the multiset of `(severity, message)` for a theme is identical from admin and
CLI.

## Trigger

| Field | Type | Rules |
|---|---|---|
| name | `:tf…` | Unique within `rules.snapshot.yml` |
| text | string | Reviewer canned response (from cloud) |
| mapped_checks | list of check ids | Zero or more; "uncovered" when zero |

Files: `config/triggers.json` (check → trigger, hand-maintained), `config/rules.snapshot.yml`
(last cloud sync, machine-written by `sync-triggers`).

## Fixture theme

Directory `tests/fixtures/themes/<check-id>-{pass|fail}/` containing at minimum `style.css`
(with headers) and `index.php`. `-fail` MUST produce exactly the documented finding(s) of its
check; `-pass` MUST produce no finding above INFO from that check (some checks, e.g.
`textdomain`, always emit an informational line — INFO is not a defect). Regression fixtures:
`tests/fixtures/themes/<check-id>-regression-<slug>/` with a `README.md` citing the commit or
learning reproduced.

## Reference theme + Allowlist

`tests/fixtures/reference/<slug>/` (committed snapshot, never edited) and
`tests/fixtures/reference/<slug>.allowlist.json`:

```json
[
  { "check": "escaping", "file": "inc/template-tags.php", "line": 42,
    "reason": "echo of already-escaped helper", "confirmed_by": "reviewer-handle" }
]
```

Theme-level findings carry `file: ""`, `line: 0`. Rules: every finding on the reference theme must match one entry (`check`, `file`, `line`);
every entry must match one finding; otherwise the sweep fails naming the delta.

## PHP compatibility range

Computed per theme at check time: `floor = max(7.4, theme.RequiresPHP ?? 7.4)`, `ceiling =
8.4`. Emitted once as an INFO finding `"PHP compatibility evaluated for {floor}–{ceiling}"`.

## AI module state (`wp_options` → `tc_ai_review_settings`)

| Key | Type | Rules |
|---|---|---|
| enabled | bool | New. Default `false` on fresh install |
| provider | string | Existing |
| api_key_{openai,anthropic,gemini,deepseek} | string (sodium-encrypted) | Existing flat keys; untouched by toggle |
| schema_version | int | New. `1` after migration |

State transitions:

```
(no option)                  --install-->  {enabled:false, schema_version:1}
{…, no enabled key}          --upgrade-->  {enabled: any(api_keys non-empty), schema_version:1}
{enabled:false}  --admin toggles on-->     {enabled:true}   (keys preserved)
{enabled:true}   --admin toggles off-->    {enabled:false}  (keys preserved; AJAX handlers unregistered next load)
```

Migration runs once (guarded by `schema_version`), never overwrites an explicit value.
