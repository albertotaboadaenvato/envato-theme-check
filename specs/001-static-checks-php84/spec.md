# Feature Specification: Static Checks First & PHP 8.4 Support

**Feature Branch**: `feature/albertotaboada/ai-improvement` (spec dir `001-static-checks-php84`)

**Created**: 2026-10-29

**Status**: Ready for implementation (analyze 2026-10-29 applied)

**Input**: User description: "Envato Theme Check plugin improvement: make the plugin a stronger static reviewer that does not require AI at runtime, add PHP 8.4+ support, and keep the AI Reviewer module as an optional add-on. Scope: (1) project tooling and test harness; (2) catalogue of the 59 registered static checks in docs/SPEC.md; (3) fix known defects from the Wave 0 inventory; (4) AI module enable/disable toggle with zero outbound requests when disabled; (5) PHP 8.4 modernisation of core files; (6) new token-based check engine with a PHP compatibility check and new checks emitting Envato snippet triggers; (7) positive/negative fixture themes per check and false-positive sweeps. Audience: internal Envato reviewers. Out of scope: changing the upstream repo, removing the AI module, new LLM providers."

**Evidence baseline**: `docs/reports/wave0-inventory.md` (Wave 0 Scout, 2026-10-29).

## Clarifications

### Session 2026-10-29

- Q: Where do static checks get the `:tf…` trigger text appended to findings? → A: A committed mapping file in the repo (check id → trigger), refreshable on demand from the cloud `rules.yml`; static review and tests never hit the network.
- Q: Which PHP versions does the compatibility check evaluate a reviewed theme against? → A: Floor = the theme's declared `Requires PHP` (7.4 if absent or lower), ceiling = 8.4; REQUIRED for constructs that fail at the ceiling, WARNING for deprecations.
- Q: How many of the 59 existing checks receive a positive/negative fixture pair in this feature? → A: All 59 receive the minimum pair (bulk-generated from the catalogue); the 5 highest-risk checks (escaping, plugin territory, deprecated, bad things, i18n) additionally receive reproduction fixtures for their historical regressions.
- Q: What is the AI module state on existing installs after upgrading to the version that introduces the toggle? → A: Enabled only if at least one API key is already stored; disabled otherwise. Fresh installs start disabled.
- Q: How is a finding on a reference theme recorded as a confirmed true positive so the false-positive sweep accepts it? → A: A committed allowlist file per reference theme (check id, file:line, reason, confirmer); the sweep fails on any unlisted finding and also on any allowlist entry that no longer occurs.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reliable review without AI (Priority: P1)

An Envato reviewer opens *Appearance > Theme Check*, selects a submitted theme and runs the check. All static results appear, each with its severity and, where Envato has a canned response, the snippet trigger (e.g. `Trigger: :tfcpt`) at the end of the message so the reviewer can paste it into the ticket. No AI provider, API key or internet connection is needed for this to work. The same theme checked from the command line yields the same findings.

**Why this priority**: This is the plugin's core job. Today CLI and admin diverge, the CLI `active` subcommand crashes, and no static finding carries a trigger, so reviewers fall back to the AI or to memory.

**Independent Test**: Run the admin check and the CLI check on the same fixture theme with no AI key configured; compare the two finding lists and confirm every finding that maps to a known Envato rule ends with its trigger.

**Acceptance Scenarios**:

1. **Given** a theme and no AI key configured, **When** the reviewer runs Theme Check from the admin page, **Then** all static findings display and no outbound network request is made.
2. **Given** the same theme, **When** the reviewer runs the CLI `check` command, **Then** the set of findings (message text and severity) is identical to the admin run.
3. **Given** the currently active theme, **When** the reviewer runs the CLI `active` command, **Then** it completes with findings instead of a fatal error.
4. **Given** a child theme, **When** checked via CLI, **Then** parent theme files are included exactly as in the admin run.
5. **Given** a finding that corresponds to an Envato snippet, **When** displayed, **Then** the message ends with `Trigger: :tf<name>`.

---

### User Story 2 - PHP 8.x compatibility verdict on submitted themes (Priority: P1)

A reviewer checks a theme that uses constructs removed or deprecated in PHP 8.0–8.4 (e.g. `create_function`, implicit nullable parameters, `${}` interpolation — limited to what the PHPCompatibility sniff set reports; dynamic-property creation is not covered). Theme Check reports each incompatibility with file, line, the PHP version that breaks it, and the applicable Envato trigger.

**Why this priority**: Envato must reject themes that fatal on current hosting PHP. This is the single biggest gap static review cannot close with the current regex list.

**Independent Test**: Run the check against a fixture theme containing one instance of each targeted construct; every instance is flagged with the right version; a clean modern theme produces no PHP-compat findings.

**Acceptance Scenarios**:

1. **Given** a theme declaring `Requires PHP: 7.4` with `create_function()`, **When** checked, **Then** a REQUIRED finding names the file and line and states removal in PHP 8.0.
2. **Given** a theme with `function f(Foo $x = null)`, **When** checked, **Then** a WARNING names the implicit nullable parameter and PHP 8.4.
3. **Given** a theme with no incompatible constructs, **When** checked, **Then** no PHP-compat finding is emitted beyond the INFO line stating the evaluated range.
3a. **Given** a theme declaring `Requires PHP: 8.2` that uses PHP 8.1 syntax (e.g. enums), **When** checked, **Then** no finding is emitted for that syntax; **Given** the same theme declaring `Requires PHP: 7.4`, **Then** a REQUIRED finding reports the syntax as unavailable at the declared floor.
4. **Given** the committed reference theme snapshot(s) under `tests/fixtures/reference/` (initially `twentytwentyfive`), **When** checked, **Then** the PHP-compat check emits no false positives (every finding is a real construct, verified by the reviewer and recorded in the allowlist). Where an Envato trigger exists for PHP compatibility it is appended; if none exists in the mapping, the finding carries no trigger.

---

### User Story 3 - AI module is optional and silent when off (Priority: P2)

An administrator opens the Theme Check settings and switches the AI Reviewer off. From then on the admin page shows no AI panel or buttons, the diagnostics and key-test actions are unavailable, and the plugin makes no outbound requests of any kind. Switching it back on restores the AI UI without re-entering keys.

**Why this priority**: Internal policy and the project goal require the plugin to be fully usable with zero external dependencies; today the module always loads and one button fetches remote rules without a key.

**Independent Test**: Toggle off, run a check, click through the settings page, and confirm zero outbound requests are recorded; toggle on and confirm the AI panel returns.

**Acceptance Scenarios**:

1. **Given** AI disabled, **When** any Theme Check admin page loads or a check runs, **Then** zero outbound HTTP requests occur and no AI UI elements render.
2. **Given** AI disabled, **When** an AI-only action is invoked directly (e.g. via its request endpoint), **Then** no AI handler is registered, so the request is rejected by the platform's default "no such action" response and no AI code runs.
3. **Given** AI enabled with a stored key, **When** the reviewer runs the AI review, **Then** results display alongside static results, each ending with its trigger (existing behaviour preserved).
4. **Given** a fresh install, **When** settings are first viewed, **Then** the AI module defaults to **disabled**.
5. **Given** an existing install with a stored API key, **When** the plugin is upgraded, **Then** the AI module is enabled and the key is still usable; **Given** an existing install with no stored key, **Then** it is disabled.

---

### User Story 4 - Every check is documented and protected by tests (Priority: P2)

A maintainer can open one document and see every static check: its identifier, what it detects, severity, trigger, and the fixture theme that proves it. Adding or changing a check without updating that document and its fixtures fails the project's quality gate.

**Why this priority**: 60 checks exist with no spec and no tests; two escaping-regex changes were reverted for false positives. Without this, every further change risks regressions.

**Independent Test**: Pick any three checks at random from the catalogue; each has a positive fixture that triggers it and a negative fixture that does not, and the automated suite passes.

**Acceptance Scenarios**:

1. **Given** the catalogue, **When** compared against the registered checks, **Then** every registered check appears exactly once with severity, trigger (or "none") and fixture names.
2. **Given** the test suite, **When** run, **Then** each check's positive fixture produces the documented finding and its negative fixture produces none.
3. **Given** a change that makes a check emit a finding on a committed reference theme that is not in that theme's allowlist, **When** the suite runs, **Then** it fails naming the check, file and line.
4. **Given** an allowlist entry whose finding no longer occurs (e.g. the reference theme was updated), **When** the suite runs, **Then** it fails naming the stale entry.

---

### User Story 5 - Plugin itself runs on PHP 8.4+ (Priority: P3)

The plugin declares its minimum PHP version, runs without deprecation notices or fatals on PHP 8.4, and the project's static analysis confirms it.

**Why this priority**: Hosting and Local environments are moving to 8.4; the plugin must not be the thing that breaks. Lower priority because Wave 0 found no immediate 8.4 fatals in plugin code.

**Independent Test**: Run the project's compatibility analysis targeted at PHP 8.4 and the full test suite under PHP 8.4; both report zero errors.

**Acceptance Scenarios**:

1. **Given** the plugin source, **When** compatibility analysis targets PHP 8.1 through 8.4, **Then** zero errors are reported.
2. **Given** the plugin header, **When** read by WordPress, **Then** it declares a minimum PHP version of 8.1 and WordPress refuses activation below it.
3. **Given** the test suite, **When** executed on PHP 8.4, **Then** all tests pass with no deprecation output.

---

### Edge Cases

- Theme contains thousands of files or very large PHP files: checks must still complete within the admin page's execution limit, matching the admin path's raised memory limit in the CLI.
- Theme has hidden folders (`.git`, `.github`, `.idea`): their contents must be excluded from all checks in both admin and CLI.
- Theme bundles third-party libraries (TGMPA, Merlin, vendor directories): existing exclusions remain and apply identically in both paths.
- The cloud rules file is unreachable or malformed during a maintainer refresh: the refresh fails with a clear message and the committed mapping is left untouched; static checks are unaffected because they never read the cloud.
- A trigger exists in the cloud rules but no static check covers it: it is listed in the catalogue as "AI-only / uncovered" so maintainers can prioritise it.
- A fixture theme is accidentally deleted: the suite fails loudly with the fixture name rather than silently skipping.
- Compatibility analysis flags a construct inside a string literal or a comment (e.g. a check that *detects* `create_function`): must not be reported as a finding.
- AI toggle is flipped while an AI review request is in flight: the in-flight request completes or fails cleanly; no new request starts.

## Requirements *(mandatory)*

### Functional Requirements

**Static review parity (US1)**

- **FR-001**: Admin and CLI check runs MUST produce identical finding multisets of `(severity, message, file)` for the same theme, including parent-theme files for child themes.
- **FR-002**: The CLI `active` command MUST complete successfully against the active theme.
- **FR-003**: Both paths MUST exclude hidden folders and already-excluded third-party bundles.
- **FR-004**: Every static finding that maps to an Envato snippet MUST end with `Trigger: :tf<name>`; findings with no mapped snippet carry no trigger text.
- **FR-004a**: The check → trigger mapping MUST live in a committed file in the repository and be the only source static checks read at runtime; a maintainer command MUST refresh that file from the cloud `rules.yml`, and the refresh MUST report triggers added, removed or renamed.
- **FR-005**: Running static checks MUST NOT initiate any outbound network request.

**PHP compatibility check (US2)**

- **FR-006**: The plugin MUST include a check that flags PHP constructs removed or deprecated in the version range [theme's declared `Requires PHP`, 8.4] in reviewed theme files, reporting file, line, affected PHP version and severity (REQUIRED for constructs that fail at PHP 8.4, WARNING for constructs deprecated within the range). If the theme declares no `Requires PHP` or declares lower than 7.4, the floor is 7.4. The check MUST also emit an INFO finding stating the range it evaluated.
- **FR-007**: The compatibility check MUST ignore occurrences inside string literals and comments.
- **FR-008**: The compatibility check is subject to the reference-theme sweep (FR-015/FR-015a) like every other check.

**AI module toggle (US3)**

- **FR-009**: Settings MUST expose an AI Reviewer enabled/disabled option. On a fresh install it defaults to disabled. On first upgrade of an existing install it is set once: enabled if at least one API key is stored, disabled otherwise; the migration runs exactly once and never overrides a value the admin has since chosen.
- **FR-010**: When disabled, the plugin MUST render no AI UI, register no AI request endpoints, and perform zero outbound requests.
- **FR-011**: Stored API keys MUST survive an off → on toggle cycle unchanged (still decryptable) and remain encrypted at rest.
- **FR-012**: The AI check implementation that is currently never registered MUST either be registered behind the toggle or removed; dead code is not acceptable.

**Catalogue and tests (US4)**

- **FR-013**: `docs/SPEC.md` MUST list every registered static check once with: identifier, class, what it detects, severity level(s), trigger (or "none"), positive fixture, negative fixture.
- **FR-014**: Every registered check (all 59, plus any new check) MUST have at least one positive fixture theme that produces its documented finding and one negative fixture that produces none, exercised by the automated suite. The 5 highest-risk checks (escaping, plugin territory, deprecated, bad things, i18n) MUST additionally have a reproduction fixture for each historical regression recorded in the repository's learnings and reverted commits.
- **FR-015**: The automated suite MUST include a false-positive sweep over every committed reference theme snapshot under `tests/fixtures/reference/` (initially `twentytwentyfive`) that fails on any finding not present in that theme's allowlist. Additional, non-redistributable themes MAY be swept locally in report-only mode via an environment variable pointing at a directory; they never gate the suite.
- **FR-015b**: The catalogue MUST list cloud triggers that no static check maps to, under an "Uncovered triggers (AI-only)" section, regenerated from the last synced rules snapshot.
- **FR-015a**: Each reference theme MUST have a committed allowlist of confirmed true positives; every entry records check identifier, file and line, reason, and who confirmed it. The sweep MUST also fail when an allowlist entry no longer matches any finding, so stale entries cannot accumulate.
- **FR-016**: The project MUST provide one command each for: running the test suite, running coding-standard analysis, and running static type analysis; all three MUST pass on the main branch.

**Plugin PHP 8.4 readiness (US5)**

- **FR-017**: The plugin header MUST declare a minimum PHP version of 8.1.
- **FR-018**: All plugin PHP files MUST pass compatibility analysis for PHP 8.1–8.4 with zero errors.
- **FR-019**: All plugin PHP files MUST declare strict typing, use explicit nullable types, and declare every class property.

**Governance**

- **FR-020**: Adding or modifying a check without the matching catalogue entry and fixtures MUST fail the quality gate.
- **FR-021**: The decision to adopt a token/sniff-based engine for new checks MUST be recorded as an architectural decision record before the first new check is merged.

### Key Entities

- **Check**: a named static rule; attributes: identifier, class, severity level(s), description, trigger (optional), engine kind (regex / token), fixtures.
- **Finding**: output of a check; attributes: severity, message, file, line (optional), trigger (optional).
- **Trigger**: an Envato snippet identifier (`:tf…`) with its canned reviewer text; canonical source is the cloud rules file, runtime source for static checks is the committed mapping file (check id → trigger).
- **Fixture theme**: a minimal theme directory that deterministically provokes (positive) or avoids (negative) one or more checks.
- **Reference theme**: a real theme committed as a snapshot under `tests/fixtures/reference/<slug>/` (initially `twentytwentyfive`), used only for false-positive sweeps; never edited.
- **Allowlist entry**: a confirmed true positive on a reference theme; attributes: check identifier, file, line, reason, confirmer. One allowlist per reference theme, committed.
- **AI module state**: enabled/disabled flag plus encrypted provider keys and active provider.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of registered checks appear in the catalogue with severity, trigger status and both fixtures; verified by an automated comparison.
- **SC-002**: Admin and CLI runs on every committed fixture theme and reference theme produce identical finding sets (0 differences).
- **SC-003**: The PHP compatibility check detects 100% of the constructs in its positive fixture and 0 findings in its negative fixture; 0 unconfirmed findings on the reference themes.
- **SC-004**: With the AI module disabled, 0 outbound requests are observed across: plugin load, settings page, static check run, and direct invocation of each AI endpoint.
- **SC-005**: Compatibility analysis of the plugin for PHP 8.1–8.4 reports 0 errors; the unit and sweep suites pass on PHP 8.1, 8.3 and 8.4 in CI; the integration suite passes on the local WordPress install (PHP 8.3 today, 8.4 when available).
- **SC-006**: A reviewer can obtain a complete static review including triggers for a typical theme (~300 files, e.g. `twentytwentyfive`) in under 60 seconds without any AI configuration; measured once via the CLI and recorded in the quickstart run report.
- **SC-007**: The two previously reverted escaping-regex regressions would be caught: a reproduction fixture for each is in the suite and passes.

## Assumptions

- Minimum supported PHP for the plugin is 8.1 (WordPress core's recommended floor); 7.x hosting is out of scope.
- The authoritative trigger list remains the cloud `rules.yml` used by the AI module; the static side reads only the committed mapping file (refreshed on demand by a maintainer), so neither static review nor tests ever hit the network.
- "Zero outbound requests" is verified by intercepting the WordPress HTTP layer during tests, not by network capture.
- Reference themes are committed snapshots under `tests/fixtures/reference/`, so the suite does not depend on the volatile live `wp-content/themes/` directory (it changed twice during planning). Proprietary themes previously named in docs (`psycare`, `moveora`) are not redistributable and are swept only locally, report-only.
- Local development currently runs PHP 8.3; PHP 8.4 verification will use a CI matrix or a second local PHP until Local ships 8.4.
- The existing 60 regex checks are not rewritten in this feature; they are catalogued, fixtured and kept. Migration to the token engine happens check by check in later features.
- Check identifiers are derived from the check **file name** (`checks/class-<id>-check.php`), not the class name, because the two diverge (`class-envato-check.php` declares `class Themeforest`). Checks registered conditionally (e.g. `fse-required-files`) are still catalogued and fixtured.
- The project constitution (`.specify/memory/constitution.md`, v1.0.x) governs this spec.
- Spec-driven order (spec → failing test → implementation) applies to every behaviour change in this feature; tooling-only tasks are exempt but still need an observable acceptance command.
