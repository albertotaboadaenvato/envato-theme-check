# Envato Theme Check — Specification

This file specifies plugin behaviour and catalogues the static theme checks.
CatalogueTest enforces the check catalogue and its fixture coverage.
Feature-level requirements and plans live with the project planning documents (Spec Kit, feature 001).

## Plugin behaviour

### Admin page

- Appearance > Theme Check runs a static review of the selected theme without an AI provider, API key or outbound network request. The form posts with a nonce (`themecheck-nonce`) verified by `check_admin_referer()` before any check runs; the theme slug is read with `sanitize_text_field( wp_unslash( … ) )`.
- Admin and CLI runs produce identical finding multisets of severity, message and file for the same theme, including parent-theme files for child themes.
- Both paths share one file collector (`tc_collect_theme_files()` in `checkbase.php`): parent-theme files, the TGMPA/Merlin exclusion, the `tc_skip_development_directories` filter, comment stripping and the raised memory limit are identical. Hidden (dot-prefixed) files and folders never reach a check because `WP_Theme::get_files()` already skips them.
- The PHP compatibility check reports removed or deprecated constructs from the theme's declared `Requires PHP` version through PHP 8.4. The floor is 7.4 when the declaration is absent or lower than 7.4.
- Compatibility findings identify the file, line and affected PHP version, with REQUIRED severity for constructs that fail at PHP 8.4 and WARNING for constructs deprecated within the evaluated range. An INFO finding states the evaluated range.
- The compatibility check ignores occurrences inside string literals and comments and participates in the reference-theme false-positive sweep like every other check.

### WP-CLI

- `wp theme review check <theme>` runs the same static review as the admin page, including parent-theme files, exclusions and findings, without outbound network requests.
- `wp theme review active` reviews the active theme's stylesheet (child theme when one is active).
- Both commands print `PASS`/`FAIL: THEME DID NOT PASS REVIEW` and exit 0 either way (`--format=true` prints JSON). A non-zero exit on failure is a tracked follow-up.

### Extensibility

- Every finding message passes through `tc_finding_suffix( '<check-id>[/<finding-key>]' )`, which returns `apply_filters( 'themecheck_finding_suffix', '', $key )`. The public plugin registers no callback, so the filter returns `''` and messages are unchanged; admin and CLI apply it identically. Finding keys are the check id, or `check-id/finding_key` where one check emits findings that an extension may want to tell apart (`basic/wp_head`, `bad-things/base64`, `envato/server`, …). `CatalogueTest` fails on a key whose check id does not exist; `FindingSuffixTest` asserts that a callback's return value lands at the end of the message and that no message carries a suffix without one.
- Extensions load after `themecheck_checks_loaded`. Envato's internal reviewer tooling (snippet triggers, AI review) lives in a separate plugin and is out of scope of this document.

## Check catalogue

| id | class | engine | severity | detects | pass fixture | fail fixture |
| --- | --- | --- | --- | --- | --- | --- |
| admin-menu | Admin_Menu_Check | regex | warning, recommended | Deprecated user levels and disallowed admin page functions in PHP. | admin-menu-pass | admin-menu-fail |
| bad-things | Bad_Things_Check | regex | required | Prohibited PHP functions, server settings, advertising code and affiliate references. | bad-things-pass | bad-things-fail |
| basic | Basic_Check | regex | required | Missing document markup, core template hooks, template classes or automatic feed support. | basic-pass | basic-fail |
| block-patterns | Block_Patterns_Check | regex | recommended | Missing references to block pattern and block style registration in PHP. | block-patterns-pass | block-patterns-fail |
| cdn | CDN_Check | regex | required | Known CDN domains in PHP or CSS source used to load external resources. | cdn-pass | cdn-fail |
| comment-pagination | Comment_Pagination_Check | regex | recommended | Missing references to WordPress comment pagination functions. | comment-pagination-pass | comment-pagination-fail |
| comment-reply | Comment_Reply_Check | regex | recommended, info | Missing enqueue of the comment-reply script, with an informational fallback for other references. | comment-reply-pass | comment-reply-fail |
| comments | Comments_Check | regex | recommended | Missing comments_template(), wp_list_comments() or comment_form() calls. | comments-pass | comments-fail |
| constants | Constants_Check | regex | required | Deprecated WordPress path, custom header and custom background constants in PHP. | constants-pass | constants-fail |
| copyright-notice | Copyright_Notice_Check | regex | warning | Missing copyright or (C) text in style.css or selected readme/license files; the fail fixture uses Theme Name: Fixture notice and Text Domain: fixture-notice to avoid matching its check id. | copyright-notice-pass | copyright-notice-fail |
| customizer | Customizer_Check | regex | required | Customizer add_setting() calls with missing or empty sanitization callbacks. | customizer-pass | customizer-fail |
| deprecated | Deprecated_Check | regex | warning | Calls to functions in the maintained WordPress deprecated-function list. | deprecated-pass | deprecated-fail |
| deprecated-param | Deprecated_Param_Check | regex | warning | Deprecated arguments passed to bloginfo(), get_bloginfo() or get_option(). | deprecated-param-pass | deprecated-param-fail |
| deregister | Deregister_Check | regex | warning | References to wp_deregister_script() in theme PHP files. | deregister-pass | deregister-fail |
| directories | Directories_Check | meta | required | File or directory basenames exactly matching .git, .svn, .hg or .bzr in the supplied file lists. | directories-pass | directories-fail |
| editor-style | Editor_Style_Check | regex | recommended | No add_editor_style reference in PHP files. | editor-style-pass | editor-style-fail |
| envato | Themeforest | regex | required, warning | Reviewer-specific forbidden API, code, CSS and malware patterns, plus potentially unsafe dynamic output. | envato-pass | envato-fail |
| escaping | Escaping_Check | regex | required, warning | Unescaped theme options or URLs and escaping functions used in the wrong HTML context. | escaping-pass | escaping-fail |
| favicon | Favicon_Check | regex | info | Hard-coded favicon link tags or Microsoft tile image meta tags in PHP files. | favicon-pass | favicon-fail |
| file | File_Check | regex | required | Forbidden production file names or missing index.php, style.css or readme.txt, with index.php optional for block themes. | file-pass | file-fail |
| filesystem-http | Filesystem_HTTP_Check | regex | warning, info | Direct filesystem or HTTP operations and WP_Filesystem calls requiring manual review. | filesystem-http-pass | filesystem-http-fail |
| fse-required-files | FSE_Required_Files_Check | meta | required | Missing theme.json or templates/index.html (including the legacy block-templates location) in a block theme. | fse-required-files-pass | fse-required-files-fail |
| generated | Generated_Check | regex | warning | Known Artisteer, Lubith, Templatetoaster or wpthemegenerator signatures in PHP. | generated-pass | generated-fail |
| gravatar | Gravatar_Check | regex | recommended | No get_avatar or wp_list_comments reference in PHP files. | gravatar-pass | gravatar-fail |
| i18n | I18N_Check | token | recommended | PHP variables inside translation function calls, recommending placeholders. | i18n-pass | i18n-fail |
| iframe | Iframe_Check | regex | info | Iframe elements in PHP files for manual review. | iframe-pass | iframe-fail |
| image-size | Image_Size_Check | meta | warning | Image files at least 500 KB in size. | image-size-pass | image-size-fail |
| include | Include_Check | regex | info | Include or require statements outside functions.php that may need get_template_part instead. | include-pass | include-fail |
| included-zip | Included_Zip_Check | regex | required, warning | ZIP file names bundled in the theme, reported as REQUIRED text with a warning CSS class. | included-zip-pass | included-zip-fail |
| line-endings | Line_Endings_Check | regex | required | Both CRLF and LF line endings in one PHP, CSS, txt or js file. | line-endings-pass | line-endings-fail |
| link | Link_Check | regex | info | Hard-coded anchor links in PHP files other than Theme URI or Author URI. | link-pass | link-fail |
| nav-menu | Nav_Menu_Check | regex | recommended, required, warning | Missing nav_menu use, add_theme_support( menus ), or wp_nav_menu() without theme_location. | nav-menu-pass | nav-menu-fail |
| no-hidden-admin-bar | No_Hidden_Admin_Bar_Check | regex | warning | show_admin_bar filter or call in PHP, or #wpadminbar in CSS. | no-hidden-admin-bar-pass | no-hidden-admin-bar-fail |
| non-gpl | Non_GPL_Check | regex | required | References to non-GPL-compatible asset sites in non-PHP/CSS files. | non-gpl-pass | non-gpl-fail |
| non-printable | Non_Printable_Check | regex | warning | Non-printable control characters in PHP files. | non-printable-pass | non-printable-fail |
| php-compatibility | PHP_Compatibility_Check | sniff | required, warning, info | PHP constructs removed/deprecated between the theme's Requires PHP floor (min 7.4) and 8.4, via PHPCompatibilityWP; always emits the evaluated range as INFO. | php-compatibility-pass | php-compatibility-fail |
| php-short-tags | PHP_Short_Tags_Check | regex | warning | PHP short open tags other than php or xml. | php-short-tags-pass | php-short-tags-fail |
| plugin-territory | Plugin_Territory_Check | regex | required | Plugin-territory functions, filters and remove_action of head/redirect hooks. | plugin-territory-pass | plugin-territory-fail |
| post-format | Post_Format_Check | regex | info | add_theme_support( post-formats ) with no get_post_format, has_post_format or .format CSS. | post-format-pass | post-format-fail |
| post-pagination | Post_Pagination_Check | regex | recommended | No post pagination function used in the theme. | post-pagination-pass | post-pagination-fail |
| post-thumbnail | Post_Thumbnail_Check | regex | recommended | No the_post_thumbnail() call or post-thumbnails support. | post-thumbnail-pass | post-thumbnail-fail |
| screen-reader-text | Screen_Reader_Text_Check | regex | info | No .screen-reader-text class styled in theme CSS. | screen-reader-text-pass | screen-reader-text-fail |
| screenshot | Screenshot_Check | meta | required, recommended | Missing screenshot, or wrong size or non-4:3 ratio. | screenshot-pass | screenshot-fail |
| script-tag | Script_Tag_Check | regex | recommended | Hard-coded script tag in header.php or footer.php. | script-tag-pass | script-tag-fail |
| search-form | Search_Form_Check | regex | warning | Direct searchform.php include or hard-coded role="search" form. | search-form-pass | search-form-fail |
| site-url | Site_URL_Check | regex | info | Use of site_url() or get_site_url() in PHP files, suggesting home_url(). | site-url-pass | site-url-fail |
| style-css-header | Style_CSS_Header_Check | regex | required | Missing required style.css header fields (Theme Name, Description, Author, Version, License, License URI, Text Domain, Tested up to, Requires PHP) or a forbidden Update URI. | style-css-header-pass | style-css-header-fail |
| style-tags | Style_Tags_Check | meta | info, required | Missing, unknown, deprecated, duplicate or over-limit (3 subject) style.css tags, and full-site-editing on a non-block theme. | style-tags-pass | style-tags-fail |
| suggested-styles | Suggested_Styles_Check | regex | recommended | Missing Theme URI or Author URI header and missing WordPress core CSS classes (.sticky, .alignleft, .wp-caption, etc.). | suggested-styles-pass | suggested-styles-fail |
| tag | Tag_Check | regex | recommended | No tag display function (the_tags, the_taxonomies, get_the_tag_list, get_the_term_list) in the theme. | tag-pass | tag-fail |
| textdomain | TextDomain_Check | token | warning, required, info | Translation functions with missing or surplus arguments or domains, a text domain not matching the theme slug, and a directory not matching the theme-name slug; always reports the domain summary as info or warning. | textdomain-pass | textdomain-fail |
| theme-support | Theme_Support_Check | regex | recommended | Missing add_theme_support() for custom-header, custom-background, custom-logo, html5, responsive-embeds, align-wide or wp-block-styles. | theme-support-pass | theme-support-fail |
| theme-support-title-tag | Theme_Support_Title_Tag_Check | regex | required | Missing add_theme_support( "title-tag" ). | theme-support-title-tag-pass | theme-support-title-tag-fail |
| time-date | Time_Date_Check | regex | info | Hard-coded date format strings passed to date_i18n(), the_date() or the_time(). | time-date-pass | time-date-fail |
| title | Title_Check | regex | recommended | Use of wp_title() or a hard-coded <title> tag instead of title-tag support. | title-pass | title-fail |
| underscores | Underscores_Check | meta | required | Unmodified Underscores starter theme: underscores.me Theme or Author URI, the _s readme text, or Underscores.me in footer.php. | underscores-pass | underscores-fail |
| uri | URI_Check | meta | required | A wordpress.org or w.org Theme URI, which is reserved for default themes. | uri-pass | uri-fail |
| version-php | Version_Requires_PHP_Check | meta | recommended | Requires PHP header with a patch version (more than one dot). | version-php-pass | version-php-fail |
| version-tested | Version_Tested_Upto_Check | meta | recommended | Tested up to header with a patch version (more than one dot). | version-tested-pass | version-tested-fail |
| widgets | Widgets_Check | regex | recommended, required | No sidebars at all, register_sidebar() without dynamic_sidebar() or the reverse, or sidebars not registered on the widgets_init action. | widgets-pass | widgets-fail |
| worms | Worms_Check | regex | required | Known hacker-script signatures (wshell.php, ShellBOT, uname -a, Pharma Hack patterns) in PHP or other files. | worms-pass | worms-fail |

61 checks

61 check files; fse-required-files registers conditionally (checkbase.php:484)

## Regression fixtures

Each `<id>-regression-<slug>` dir under `tests/fixtures/themes/` declares `expect: pass|fail` in its README.md front-matter. `pass` means no finding above INFO, and `fail` means at least one finding at a severity listed in the catalogue.

- `escaping-regression-unescaped-translations`, expect pass, source `2aed4d6`. The reverted `(^|\s)(__|_e|_n|...)\(` regex fired on translation calls that were escaped or assigned and escaped later.
- `escaping-regression-checks-improvement`, expect pass, source `f584859` (reverts PR #46) and learning `l-989fece8`. The reverted wider regexes fired on custom `*_echo`/`*_print` functions, e-mail addresses (`@`) and static or already-prepared `$wpdb` queries.
- `plugin-territory-regression-user-contactmethods-filter`, expect fail, source `fa933f4`. The original call-only regex missed `add_filter( 'user_contactmethods', ... )`.
- `deprecated-regression-php-deprecations-not-listed`, expect pass, source learning `l-4fed82bd`. The list is WordPress-only, so PHP deprecations (`utf8_encode`, `strftime`) and `WP_Query::next_post()` are not flagged.
- `bad-things-regression-prefixed-function-names`, expect pass, source learning `l-989fece8` and the guards in `c3962b0`. Prefixed functions that end in `system`/`exec`/`eval`, and `curl_exec`, are not flagged.
- `i18n-regression-plural-number-and-missing-domain`, expect pass, source `4722000`/`02d1041` (issue #18), `1d517cb` and learning `l-ffa9eb1b`. The `_n()` count variable and a domain-less call are not i18n findings.

## Known false positives

| Check | Theme / file | Finding | Why it is a false positive | Tracked in |
|---|---|---|---|---|
| textdomain | twentytwentyfive (theme-level) | WARNING wrong directory, correct slug `twenty-twenty-five` | `$exceptions` in `checks/class-textdomain-check.php` stops at `twentytwentyone`, so newer core themes (`twentytwentytwo`…`twentytwentyfive`) get compared against `sanitize_title_with_dashes( 'Twenty Twenty-Five' )` = `twenty-twenty-five` (same in real WP, not a harness stub artefact). Follow-up: extend the core-theme exception list. | `twentytwentyfive.allowlist.json` (reason "known FP, tracked") |

## Follow-ups (not in scope of 001-static-checks-php84)

| # | Item | Source |
|---|---|---|
| 1 | `bad-things` misses `return exec(` / `echo exec(` — `[^_]exec` needs a non-alphanumeric char two places back | T016 |
| 2 | `escaping` regression fixtures only exercise `Escaping_Check`; the reverted regexes also live in `class-envato-check.php` — add `envato-regression-*` twins | T016 |
| 3 | `textdomain` core-theme exception list stops at `twentytwentyone` (Known false positives) | T017 |
| 4 | `Directories_Check` can only fire on a hidden-named *file* basename; dot-folders never reach checks (`WP_Theme::scandir`) — decide whether to retire it | T018/T021 |
| 5 | CLI exits 0 on `FAIL` — consider `WP_CLI::halt(1)` behind a flag | T022 |
| 6 | `fse-required-files` registers conditionally, so `CheckFixtureTest` skips it; needs an FSE-aware runner in the unit harness | T013 |
| 7 | 24 `WordPress.PHP.StrictComparisons` lint findings left in legacy checks; migrate to `===` when each check is touched | T038 |
| 9 | `vendor/` is 120 MB with dev deps; release zips must be built with `composer install --no-dev` (~10 MB) | T002 |
| 11 | `style-tags` emits `INFO Themes that use the tag…` without the `: ` separator; normalise the label format across checks | manual run |
| 12 | CLI output keeps HTML entities (`&lt;title&gt;`, `&#039;`) from messages escaped inside checks; decode once in `ThemeCheckCLI` | manual run |
