# Wave 0 — Evidence inventory: envato-theme-check

Read-only inventory. No existing file was edited, nothing installed, no composer run, no commits.
Only this file was created (plus the `docs/reports/` directory it lives in).
All commands were run from the plugin root: `wp-content/plugins/envato-theme-check`.
Branch at time of run: `feature/albertotaboada/ai-improvement` (HEAD `b4c6f15`).

Shell note: the sandbox `grep` is an alias for ugrep, and zsh does not word-split `$FILES`. Where a file list was used, it was written to a scratchpad file and piped through `xargs /usr/bin/grep`. The commands below show that form.

---

## Headline numbers

- **Checks:** 60 files in `checks/`. 59 are registered in the global `$themechecks` array. `class-fse-required-files-check.php` is intentionally not registered and is added conditionally by `checkbase.php:390`.
- **Triggers:** 5 distinct `:tf…` identifiers exist in the repo. **0 of 5 appear in `checks/`**, so 0 checks carry a `Trigger:` tag. 4 appear only in `ai-review/llm/` (prompt/interface text). 1 (`:tfxxx`) appears only as a placeholder in the plugin `CLAUDE.md`. The authoritative trigger list (`rules.yml`) is fetched remotely and is not cached in the repo, so full coverage cannot be computed locally.
- **PHP 8.4 risk grep:** 0 implicit-nullable params, 0 real deprecated-function calls (2 false positives), 0 `${}` interpolations, 0 `E_STRICT`, 0 argument-less `get_class()`, **79 of 79 PHP files lack `declare(strict_types=1)`**, and 0 property-declaration gaps found in the files that write `$this->…`.
- **Blocking findings for the stated goals:**
  1. `theme-check-cli.php:250-256` (`active`) calls `tc_get_theme_data()`, which is **defined nowhere** in the repo. `wp theme review active` will fatal.
  2. `class AI_Review_Check` is **never instantiated or registered**. The AI path runs only via AJAX (`class-ai-ajax-runner.php`). The `themecheck_checks_loaded` hook described in CLAUDE.md §3/§6 is not used by `ai-review/` at all.
  3. The AI module has **no enable/disable setting**. It loads unconditionally on `plugins_loaded` (`theme-check.php:27-31`). The only "off" state is deactivating the plugin, so CLAUDE.md §6 "with the module disabled, zero outbound requests" is satisfied only by the absence of any automatic request, not by a toggle.

---

## 1. CHECKS INVENTORY

Columns: file | class | severity levels emitted (from `tc-*` literals in the file) | input variables actually used in `check()` body (docblock `@param` lines excluded) | regex (`preg_*`) or token | Envato `:tf` trigger mentioned.

Severity counts are occurrences of `tc-required|tc-warning|tc-info|tc-recommended` in the file.

Command run:

```
cd "<plugin>" && for f in checks/*.php; do c=$(grep -m1 -oE '^class [A-Za-z0-9_]+' "$f" | awk '{print $2}'); sev=$(grep -oE 'tc-(required|warning|info|recommended)' "$f" | sort | uniq -c | awk '{printf "%s:%s ",$2,$1}'); tf=$(grep -oE ':tf[a-z0-9_]+' "$f" | sort -u | tr '\n' ' '); echo "$f | $c | $sev | preg=$(grep -cE 'preg_[a-z_]+\(' "$f") tok=$(grep -cE 'token_get_all|PHPCSUtils|PHP_CodeSniffer|phpcs' "$f") | $tf"; done
```

Input-usage and regex columns were produced with:

```
for f in checks/*.php; do c=$(grep -m1 -oE '^class [A-Za-z0-9_]+' "$f" | awk '{print $2}'); u=$(grep -vE '^\s*\*|public function check\(' "$f" | grep -oE '\$(php_files|css_files|other_files)' | sort | uniq -c | awk '{printf "%s=%s ",$2,$1}'); echo "$c | ${u:-none} | $(grep -oE 'preg_[a-z_]+' "$f" | sort | uniq -c | tr -s ' ' | tr '\n' ';')"; done
```

Trigger column: **no check file contains a `:tf` identifier** (the `:tf` grep returned empty for all 60).

| File | Class | Severities | Inputs used | Regex / token | Trigger |
|---|---|---|---|---|---|
| class-admin-menu-check.php | Admin_Menu_Check | recommended, warning | php | regex | — |
| class-bad-things-check.php | Bad_Things_Check | required | php, other | regex | — |
| class-basic-check.php | Basic_Check | required | php | regex | — |
| class-block-patterns-check.php | Block_Patterns_Check | recommended | php | none | — |
| class-cdn-check.php | CDN_Check | required | php, css | none | — |
| class-comment-pagination-check.php | Comment_Pagination_Check | recommended | php | none | — |
| class-comment-reply-check.php | Comment_Reply_Check | info, recommended | php | regex | — |
| class-comments-check.php | Comments_Check | recommended | php | regex | — |
| class-constants-check.php | Constants_Check | required | php | regex | — |
| class-copyright-notice-check.php | Copyright_Notice_Check | warning | css, other | regex | — |
| class-customizer-check.php | Customizer_Check | required (2) | php | regex (match, match_all, split×4, quote×2) | — |
| class-deprecated-check.php | Deprecated_Check | warning | php | regex | — |
| class-deprecated-param-check.php | Deprecated_Param_Check | warning | php | regex | — |
| class-deregister-check.php | Deregister_Check | warning | php, css, other | none | — |
| class-directories-check.php | Directories_Check | required | php, css, other | none | — |
| class-editor-style-check.php | Editor_Style_Check | recommended | php | none | — |
| class-envato-check.php | **Themeforest** (class name ≠ file) | warning (4) | php, css, other | regex (match×4) | — |
| class-escaping-check.php | Escaping_Check | required, warning (2) | php | regex | — |
| class-favicon-check.php | Favicon_Check | info | php | regex | — |
| class-file-check.php | File_Check | required (2) | php, css, other | regex (preg_grep); also 1 token/phpcs keyword hit, not inspected | — |
| class-filesystem-http-check.php | Filesystem_HTTP_Check | info, warning | php | regex (match_all) | — |
| class-fse-required-files-check.php | FSE_Required_Files_Check | required (2) | other | none | — (not auto-registered; see `checkbase.php:390`) |
| class-generated-check.php | Generated_Check | warning | php | none | — |
| class-gravatar-check.php | Gravatar_Check | recommended | php | none | — |
| class-i18n-check.php | I18N_Check | recommended | php | **token** (`token_get_all`, lines 31, 62) | — |
| class-iframe-check.php | Iframe_Check | info | php | regex | — |
| class-image-size-check.php | Image_Size_Check | warning | other | none | — |
| class-include-check.php | Include_Check | info | php | regex | — |
| class-included-zip-check.php | Included_Zip_Check | warning | other | regex (preg_grep) | — |
| class-line-endings-check.php | Line_Endings_Check | required (3) | php, css, other | regex (match×6) | — |
| class-link-check.php | Link_Check | info | php | regex | — |
| class-nav-menu-check.php | Nav_Menu_Check | required (3), recommended, warning | php (2) | regex | — |
| class-no-hidden-admin-bar-check.php | No_Hidden_Admin_Bar_Check | warning (2) | php, css | regex | — |
| class-non-gpl-check.php | Non_GPL_Check | required | other | none | — |
| class-non-printable-check.php | Non_Printable_Check | warning | php | regex | — |
| class-php-short-tags-check.php | PHP_Short_Tags_Check | warning | php | regex | — |
| class-plugin-territory-check.php | Plugin_Territory_Check | required (3) | php | regex | — |
| class-post-format-check.php | Post_Format_Check | info | php (2), css | regex | — |
| class-post-pagination-check.php | Post_Pagination_Check | recommended | php | none | — |
| class-post-thumbnail-check.php | Post_Thumbnail_Check | recommended (2) | php | none | — |
| class-screen-reader-text-check.php | Screen_Reader_Text_Check | info | css | regex | — |
| class-screenshot-check.php | Screenshot_Check | recommended, required (3) | other (2) | regex | — |
| class-script-tag-check.php | Script_Tag_Check | recommended | php | none | — |
| class-search-form-check.php | Search_Form_Check | warning (2) | php | regex | — |
| class-site-url-check.php | Site_URL_Check | info | php | none | — |
| class-style-css-header-check.php | Style_CSS_Header_Check | required (2) | css | regex | — |
| class-style-tags-check.php | Style_Tags_Check | info (6), required | other (3) | none | — |
| class-suggested-styles-check.php | Suggested_Styles_Check | recommended (2) | css | regex | — |
| class-tag-check.php | Tag_Check | recommended | php (2), css, other | none | — |
| class-textdomain-check.php | TextDomain_Check | info, required, warning (4) | php (2), css, other | **token** (`token_get_all`, lines 87, 97) | — |
| class-theme-support-check.php | Theme_Support_Check | recommended | php | regex | — |
| class-theme-support-title-tag.php | Theme_Support_Title_Tag_Check | required | php | regex | — |
| class-time-date-check.php | Time_Date_Check | info | php | regex | — |
| class-title-check.php | Title_Check | recommended (2) | php (2) | regex (match×2, replace×1) | — |
| class-underscores-check.php | Underscores_Check | required (3) | php, other | regex | — |
| class-uri-check.php | URI_Check | required | none (uses `$this->theme`) | none | — |
| class-version-php-check.php | Version_Requires_PHP_Check | recommended | none (uses `$this->theme`) | none | — |
| class-version-tested-check.php | Version_Tested_Upto_Check | recommended | none (uses `$this->theme`) | none | — |
| class-widgets-check.php | Widgets_Check | recommended, required (3) | php | regex | — |
| class-worms-check.php | Worms_Check | required | php (4), css, other (2) | regex | — |

Summary: 60 check files; 59 registered; 5 use `token_get_all` (I18N, TextDomain) or `preg_*` on PHP source; 0 carry a `:tf` trigger tag. Checks with no regex and no token parsing are the ones marked "none" (about 20), mostly string-contains or filename checks. Per CLAUDE.md §5, new checks should be PHPCS/token-based, and the existing estate is almost entirely regex.

---

## 2. TRIGGER COVERAGE

Command (verbatim):

```
grep -rhoE ':tf[a-z0-9_]+' . | sort -u
```

Output:

```
:tfcpt
:tfescapealiases
:tfglobal
:tfwpvalidate
:tfxxx
```

Location of each identifier (command: `for t in …; do grep -rlF "$t" . | …; done`):

```
:tfcpt: ai-review/llm/class-llm-claude.php ai-review/llm/class-llm-openai.php ai-review/llm/class-llm-gemini.php ai-review/llm/class-llm-deepseek.php
:tfescapealiases: ai-review/llm/class-llm-openai.php ai-review/llm/interface-llm-client.php ai-review/llm/class-llm-claude.php ai-review/llm/class-llm-gemini.php ai-review/llm/class-llm-deepseek.php
:tfglobal: ai-review/llm/class-llm-claude.php ai-review/llm/class-llm-gemini.php ai-review/llm/class-llm-openai.php ai-review/llm/class-llm-deepseek.php
:tfwpvalidate: ai-review/llm/class-llm-openai.php ai-review/llm/class-llm-claude.php ai-review/llm/class-llm-deepseek.php ai-review/llm/class-llm-gemini.php
:tfxxx: CLAUDE.md
```

Coverage table:

| Trigger | In `checks/` | Only in `ai-review/` | Only in docs / other | Status |
|---|---|---|---|---|
| `:tfcpt` | no | yes (4 LLM clients, prompt text) | no | **uncovered by static checks** |
| `:tfescapealiases` | no | yes (interface + 4 clients) | no | **uncovered** |
| `:tfglobal` | no | yes (4 clients) | no | **uncovered** |
| `:tfwpvalidate` | no | yes (4 clients) | no | **uncovered** |
| `:tfxxx` | no | no | yes (`CLAUDE.md`, placeholder example) | not a real trigger |

Result: **0 covered, 4 uncovered real triggers** (plus 1 placeholder). The four real triggers are named only inside LLM prompt strings as examples of what the model must emit. No check in `checks/` emits a `Trigger:` suffix.

Trigger list source: `ai-review/cloud/class-cloud-config.php` does **not** expose the list. It holds only URLs and filter names:

```
grep -n 'yml\|triggers\|https://' ai-review/cloud/class-cloud-config.php
6: * files (rules.yml and explanation.md) that are fetched from the cloud
16: *   tc_ai_rules_yml_url        — Full URL to the .yml rules file.
40:	 * Replace this via the `tc_ai_rules_yml_url` filter or by defining
45:	const DEFAULT_RULES_YML_URL = 'https://storage.googleapis.com/wp-theme-assets-8820/config_9a8b7c6d5e4f.yml';
55:	const DEFAULT_EXPLANATION_MD_URL = 'https://storage.googleapis.com/wp-theme-assets-8820/explanation_3b2a1c0d9e.md';
79:	 *   1. `tc_ai_rules_yml_url` filter return value.
85:	public static function get_rules_yml_url() {
95:		return esc_url_raw( apply_filters( 'tc_ai_rules_yml_url', $default ) );
```

Cached config: none in the repo. `find . -name 'config.yml'` (excluding vendor) returned only `.trail/cache/*` JSON/graph files, none of which contains trigger lists. I did not fetch the remote URL (read-only, and the review is local). So the authoritative trigger list is **not verifiable locally**. Note: `docs/` contains only `IMPROVEMENT-PLAN.md` and `superpowers/plans`, and neither mentions a trigger identifier.

---

## 3. PHP 8.4 RISK GREP

File set (command):

```
find . -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -not -path './.trail/*' -not -path './.agents/*' -not -path './.claude/*' -not -path './.gemini/*' | sort > <scratchpad>/files.txt
wc -l < <scratchpad>/files.txt
```

Output: `79` files.

Each grep below is `xargs /usr/bin/grep -nE '<pattern>' < files.txt`.

**Implicit nullable** (`function\s+\w+\([^)]*[A-Za-z_\\]+\s+\$\w+\s*=\s*null`): no output (0 matches).

Sanity broadening (`[A-Za-z_\\]+\s+\$\w+\s*=\s*null`):

```
./ai-review/cloud/class-cloud-config.php:189:		static $version = null;
```

That is a static local variable, not a parameter. **Result: 0 implicit-nullable params.**

**Deprecated / removed functions** (`\b(create_function|each|ereg|ereg_replace|split|spliti|utf8_encode|utf8_decode|mysql_[a-z_]+)\s*\(`):

```
./ai-review/settings/class-ai-settings-page.php:231:						$.each(response.data, function(i, test) {
./checks/class-envato-check.php:43:            '/create_function\s?\(/'                        => esc_html__( 'The create_function() function has been deprecated as of PHP 7.2.0 and must no longer be used', 'theme-check' ),
```

Both are false positives: line 231 is jQuery `$.each` in inline JS; line 43 is a string literal inside a check that *detects* `create_function`. **Result: 0 real.**

**`${}` string interpolation** (`\$\{[A-Za-z_]`): no output. **0.**

**`E_STRICT`**: no output. **0.**

**`get_class()` with no args** (`get_class\(\s*\)`): no output. **0.**

**Missing `declare(strict_types=1)`**:

```
xargs /usr/bin/grep -L 'declare(strict_types=1)' < files.txt | wc -l   →  79
wc -l < files.txt                                                        →  79
```

**79 of 79 files lack it.** Every file in the plugin, including `theme-check.php`, `main.php`, `checkbase.php`, `theme-check-cli.php`, all of `checks/`, and all of `ai-review/`. This violates CLAUDE.md §5 and AGENTS.md "PHP 8.1+ syntax, declare(strict_types=1)".

**Undeclared properties** (`$this->X =` in each file, then the matching declaration). Command:

```
xargs /usr/bin/grep -cE '\$this->\w+\s*=[^=]' < files.txt | grep -v ':0$'
```

15 files write `$this->…`. Output (abridged to file names with counts):

```
ai-review/class-ai-review-loader.php:1
ai-review/llm/class-llm-claude.php:1
ai-review/llm/class-llm-deepseek.php:1
ai-review/llm/class-llm-gemini.php:1
ai-review/llm/class-llm-openai.php:1
ai-review/settings/class-ai-settings-page.php:1
checks/class-copyright-notice-check.php:1
checks/class-link-check.php:1
checks/class-style-tags-check.php:2
checks/class-textdomain-check.php:3
checks/class-underscores-check.php:1
checks/class-uri-check.php:1
checks/class-version-php-check.php:1
checks/class-version-tested-check.php:1
theme-check-cli.php:1
```

Declaration check, command: `grep -nE '^\s*(public|protected|private|var)\s' <file>` for each of these. Result, file by file:

- `class-ai-review-loader.php` — `private $base_path` declared (line 52). OK.
- `llm/class-llm-*.php` (×4) — `private $api_key` declared (line 62/69). OK.
- `settings/class-ai-settings-page.php` — `private $hook_suffix` declared (line 50). OK.
- `checks/class-copyright-notice-check.php` — `protected $slug` (19). OK.
- `checks/class-link-check.php` — `protected $theme` (26). OK.
- `checks/class-style-tags-check.php` — `protected $tags`, `$wp_theme` (25, 32). OK.
- `checks/class-textdomain-check.php` — `protected $name`, `$slug`, `$is_wporg` (24, 31, 33). OK.
- `checks/class-underscores-check.php`, `class-uri-check.php`, `class-version-php-check.php`, `class-version-tested-check.php` — `protected $theme` declared. OK.
- `theme-check-cli.php` — `public $fetcher` (14). OK.

**Result: 0 undeclared-property writes found.** This is a file-level check (declaration present for every file with a `$this->` write), not a full data-flow proof. Dynamic reads of undeclared properties were not searched exhaustively.

**Other PHP 8.4 notes (not in the grep list but relevant):**
- `php -v` in this environment is **8.3.30**, so PHP 8.4 behaviour cannot be exercised locally (see §4).
- `checks/class-textdomain-check.php:36,61` uses `var $rules` / `var $exceptions`. Legal on 8.4, but it is the old declaration style and not "explicitly declared" as CLAUDE.md §5 requests.

---

## 4. TOOLING

Commands and outputs (verbatim):

```
$ php -v
PHP 8.3.30 (cli) (built: Jan 17 2026 00:18:43) (NTS clang 15.0.0)
Copyright (c) The PHP Group
Zend Engine v4.3.30, Copyright (c) Zend Technologies
    with Zend OPcache v8.3.30, Copyright (c), by Zend Technologies

$ phpcs --version
PHP_CodeSniffer version 3.13.5 (stable) by Squiz and PHPCSStandards

$ phpcs -i
The installed coding standards are MySource, PEAR, PSR1, PSR2, PSR12, Squiz, Zend, WordPress, WordPress-Core, WordPress-Docs, WordPress-Extra, PHPCSUtils, Modernize, NormalizedArrays and Universal

$ wp --version
WP-CLI 2.11.0

$ ls composer.json phpcs.xml phpunit.xml phpstan.neon tests/ 2>&1
ls: composer.json: No such file or directory
ls: phpcs.xml: No such file or directory
ls: phpstan.neon: No such file or directory
ls: phpunit.xml: No such file or directory
ls: tests/: No such file or directory

$ git log --oneline -10
b4c6f15 Improving theme check with AI
79867e5 feat: smart file aggregation with XML delimiters and expanded payload
feb421c feat: overhaul LLM prompts with strict schema, new persona, and increased token limits
05ec091 fix: remove /u flag from control character regex — null byte crashes preg_replace
39165e9 feat: rebuild AI settings page with proper WordPress admin UI
1058cf9 fix: sanitize LLM JSON responses before parsing in all four clients
932975f fix: add realpath validation, double-execution guard, and stale comment cleanup
3ba275e refactor: replace AI debug block with async anchor and localized JS config
78a5374 refactor: replace synchronous check engine with async AJAX runner and asset enqueuing
20e57cb feat: add async front-end controller for AI review module

$ git status --short
 M .DS_Store
 M .claude/.DS_Store
 M CLAUDE.md
 M ai-review/.DS_Store
 M docs/.DS_Store
?? .agents/skills/speckit-analyze/
?? .agents/skills/speckit-checklist/
?? .agents/skills/speckit-clarify/
?? .agents/skills/speckit-constitution/
?? .agents/skills/speckit-converge/
?? .agents/skills/speckit-implement/
?? .agents/skills/speckit-plan/
?? .agents/skills/speckit-specify/
?? .agents/skills/speckit-tasks/
?? .agents/skills/speckit-taskstoissues/
?? .claude/settings.json
?? .claude/skills/
?? .gemini/
?? .gitignore
?? .ignore
?? .specify/
?? .trail/
?? AGENTS.md
?? GEMINI.md
?? ORCHESTRATION.md
?? docs/IMPROVEMENT-PLAN.md
?? opencode.json
```

(`git status --short` also now shows `?? docs/reports/` from this report.)

Observations:
- **PHP runtime is 8.3.30**, not 8.4. The 8.4 requirement cannot be verified locally.
- **`phpcs -i` has no `PHPCompatibilityWP` or `PHPCompatibility`**. CLAUDE.md §4 expects `vendor/bin/phpcs` with PHPCompatibilityWP. Only the global WordPress standard is available.
- **No `composer.json`, `phpcs.xml`, `phpunit.xml`, `phpstan.neon`, or `tests/`.** CLAUDE.md's acceptance-test regime cannot run yet. There is no test suite at all.
- `docs/SPEC.md` (the spec named in CLAUDE.md §3 and AGENTS.md) **does not exist**. `docs/` contains `IMPROVEMENT-PLAN.md` and `superpowers/plans` only. There is also no `docs/decisions/`.
- Fixtures: `ls ../../themes` (wp-content/themes) returned `index.php moveora moveora-main-files twentytwentyfive`. **`psycare` is absent**, so the false-positive run named in CLAUDE.md §3 cannot be done locally as written.

---

## 5. CLI VS ADMIN

Compared: `theme-check-cli.php` (`ThemeCheckCLI::check()` L64-238, `::active()` L246-256) against `main.php:check_main()` L13-27 and the admin entry `theme-check.php:74-109` plus `checkbase.php:47-89` (`run_themechecks_against_theme()`), which is what `check_main()` calls.

| Setup step | Admin path | CLI path | Status |
|---|---|---|---|
| Theme object obtained | `wp_get_theme( $theme_slug )` — `main.php:21` | `$this->fetcher->get_check( $args[0] )` — `theme-check-cli.php:82` | Both present (different source, same type) |
| Theme object passed to `run_themechecks` via context | `checkbase.php:84-87` (`theme`, `slug`) | `theme-check-cli.php:105-108` (`theme`, `slug`) | **Present in both** |
| Slug in context | `$theme_slug` argument — `checkbase.php:86` | `$theme->get_stylesheet()` — `theme-check-cli.php:107` | Different source; equal in practice |
| `$themename` / `$data` globals set | **Not set** (`main.php` has no `$themename` or `$data`) | Set **only** in `active()` — `theme-check-cli.php:251-254` | **Asymmetric.** `check()` never sets them. No check in `checks/` reads them. |
| `tc_get_theme_data()` | Not called | Called at `theme-check-cli.php:254` | **Undefined function.** Defined nowhere in the repo. `wp theme review active` fatals. |
| Parent-theme files included | `get_files( null, -1, true )` — `checkbase.php:48-52` | `get_files( null, -1 )` — `theme-check-cli.php:83` | **Missing in CLI.** Child-theme runs see fewer files. |
| `unset( $files[0] )` (WP ticket 53599 workaround) | `checkbase.php:53` | Missing — `theme-check-cli.php:83-84` | **Missing in CLI** |
| TGMPA / Merlin exclusion | `checkbase.php:60` | `theme-check-cli.php:88` | Present in both |
| Hidden-folder exclusion (`.git` etc.) | **None** | **None** | Missing in both (matches learning: hidden folders not excluded) |
| PHP content transform | `tc_strip_comments()` — `checkbase.php:62-63` | `php_strip_whitespace()` — `theme-check-cli.php:91` | **Differs.** CLI strips whitespace (and comments via the engine) instead of the admin's `tc_strip_comments`. Check inputs differ between the two paths. |
| Non-PHP/CSS file read | `file_get_contents` — `checkbase.php:74` | `is_dir` guard then `file_get_contents` — `theme-check-cli.php:99` | Minor. CLI guards directories; admin does not. |
| Development-dir filter `tc_skip_development_directories` | Honoured — `checkbase.php:69-73` | Not honoured | **Missing in CLI** |
| `wp_raise_memory_limit()` | `theme-check.php:101` (admin page, before `check_main`) | Not called | **Missing in CLI** |
| `checkcount()` | Not called on the check_main path; `main.php:14,93` displays `$checkcount` | Called — `theme-check-cli.php:68` | Asymmetric (count display only) |
| `run_themechecks()` | `checkbase.php:80` | `theme-check-cli.php:110` | Present in both |
| Output | HTML via `main.php` | `WP_CLI::warning/success` | Intended difference |

Conclusion for §5: the two paths diverge in **file set** (no parent files in CLI), **content transform** (`php_strip_whitespace` vs `tc_strip_comments`), and **skip filter**. CLAUDE.md §5 says "keep the WP-CLI path in sync with `check_main()`", and this is not currently true. The `active` subcommand is broken outright.

---

## 6. AI MODULE OUTBOUND

Command (verbatim):

```
grep -rnE 'wp_remote_post|wp_safe_remote_get|wp_remote_get|curl_' --include='*.php' ai-review/
```

(Run with `/usr/bin/grep -rnE … .` from the plugin root; the output below excludes docblock-only mentions.)

Call sites:

| # | File:line | Call | Target | Who reaches it | Guard |
|---|---|---|---|---|---|
| 1 | `ai-review/llm/class-llm-openai.php:104` | `wp_remote_post` | OpenAI API | `AI_Ajax_Runner::handle()` (`ajax/class-ai-ajax-runner.php:158`) and `ajax_run_diagnostics()` (`settings/class-ai-settings.php:609`) | nonce + `manage_options` in caller; `LLM_Factory::create()` returns `WP_Error` if no key (`llm/class-llm-factory.php:61-64`) |
| 2 | `ai-review/llm/class-llm-claude.php:118` | `wp_remote_post` | Anthropic API | same | same |
| 3 | `ai-review/llm/class-llm-gemini.php:140` | `wp_remote_post` | Gemini API (key in query string) | same | same |
| 4 | `ai-review/llm/class-llm-deepseek.php:114` | `wp_remote_post` | DeepSeek API | same | same |
| 5 | `ai-review/settings/class-ai-settings.php:480` | `wp_remote_get` | Provider model-list URLs (`configs` L462-475) for key test | `ajax_test_api_key()` — `class-ai-settings.php:402-427` → `ping_provider()` | nonce (`tc_test_api_key`) + `manage_options`; requires saved key (L417). Provider URL comes from a fixed allowlist (`PROVIDERS`), not user input. |
| 6 | `ai-review/cloud/class-cloud-fetcher.php:197` | `wp_safe_remote_get` | `Cloud_Config` rules.yml / explanation.md (default `storage.googleapis.com`, overridable by filter) | (a) `ajax_run_diagnostics()` Test 1 — `class-ai-settings.php:539-540`, **no key check**; (b) `AI_Ajax_Runner::handle()` — `ajax-runner.php:171-172`, after LLM client succeeds; (c) `AI_Review_Check::check()` — `check/class-ai-review-check.php:119-120`, **dead code (see below)** | (a) nonce + `manage_options`; (b) nonce + `manage_options` + key configured; (c) n/a |
| 7 | `ai-review/cloud/class-cloud-config.php` | (no call; `wp_safe_remote_get` is named in docblocks and in the `tc_ai_cloud_request_args` filter) | — | — | — |

Other `wp_ajax_` endpoints (`class-ai-settings.php:81-82`, `class-ai-ajax-runner.php:112`) are registered for logged-in users only (no `wp_ajax_nopriv_`). `ajax_test_api_key` and `ajax_run_diagnostics` check nonce first and then `manage_options`. Confirmed in code at `class-ai-settings.php:403-405` and `:530-533`. The AJAX runner does the same at `class-ai-ajax-runner.php:122-124`.

**Dead code finding:** `AI_Review_Check` (`ai-review/check/class-ai-review-check.php:43`) implements `themecheck`, but nothing instantiates it or appends it to `$themechecks`. Its only match outside its own file is a docblock in `llm/interface-llm-client.php:6`. Its `http_api_debug` capture (L201) and `checkcount()` call (L92) are therefore never executed. The check engine therefore runs **no** LLM calls. AI runs only from the AJAX button path.

**Page-load / background requests:** none found.
- `class-ai-review-loader.php` calls `init()` on `plugins_loaded` (`theme-check.php:28-31`). It requires files and registers hooks; it makes no HTTP request.
- `wp_schedule|spawn_cron|wp_cron` do not appear in `ai-review/`.
- `Cloud_Fetcher` writes transients on success (`class-cloud-fetcher.php:149-150`) and on stale fallback (L173), but only when called from an action above.
- No `get_rules_yml` call exists on settings-page render (`class-ai-settings-page.php`).

**Can the plugin make outbound requests when AI is disabled?**

There is **no enabled/disabled setting** in the code. The AI module is always loaded. Answering the question as "AI disabled" = "no provider key saved and the admin does not click an AI button":

- **Page load / admin navigation:** no outbound request.
- **Theme check run (`check_main` or CLI `check`):** no outbound request, because `AI_Review_Check` is never registered.
- **Admin clicks "Run Full Diagnostics":** **yes, one outbound request** to the cloud rules URL (call site 6a), with no key required. This is the only ungated-by-key outbound request.
- **Admin clicks "Test key" without a saved key:** no request (L417 returns early).
- **Admin runs AI review without a key:** no request (factory guard).

So, with the module "disabled" in the operational sense (no keys, no AI clicks), the plugin makes **zero** outbound requests. But the *Diagnostics* button still fetches from Google Storage, and there is no code-level switch that turns the whole module off. CLAUDE.md §6's invariant ("with the module disabled, the plugin must make zero outbound requests") therefore cannot be enforced as written. It needs a real enable flag, and `AI_Review_Loader::init()` would need to honour it.

Additional observation (not a grep target): the Gemini client builds its request URL with the API key in the query string (`class-llm-gemini.php`, `$url = self::API_BASE . self::MODEL . ':generateContent?key=' …`), and `ping_provider()` does the same at `class-ai-settings.php:468`. The code comment states this is deliberate. Keys in URLs can end up in server and proxy logs. Flagged for the reviewer, not a finding of this inventory.

---

## Counts (for the worker_done summary)

- Checks: **60** files, **59** registered, **0** with a `:tf` trigger tag.
- Triggers: **5** identifiers repo-wide; **0 covered by checks/**; **4 uncovered real triggers** plus 1 placeholder (`:tfxxx`). Authoritative list not available locally.
- PHP 8.4 findings: **79/79** files missing `strict_types`; **0** implicit nullable; **0** real deprecated calls (2 false positives); **0** undeclared-property writes in spot-checked files.
- Blocking issues surfaced: `tc_get_theme_data()` undefined (CLI `active` fatals); `AI_Review_Check` never registered; no AI enable toggle; CLI vs admin divergence (parent files, content transform, dev filter, memory raise); no `docs/SPEC.md`, no tests, no phpunit/phpcs config, PHP runtime 8.3 not 8.4, `psycare` fixture absent.
