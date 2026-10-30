# Quickstart run: 2026-10-29

Task: T041 (`specs/001-static-checks-php84/tasks.md`). Every command block in
`specs/001-static-checks-php84/quickstart.md` §1–§7 executed on this machine. Recorded
actual vs expected without judging the quickstart's numbers.

Environment:
- PHP 8.3.30 (NTS, Herd CLI), PHPUnit 10.5.66, WP-CLI at `/usr/local/bin/wp`
- Local MySQL socket: `~/Library/Application Support/Local/run/q6pzIQ8MW/mysql/mysqld.sock`
  (passed as `-d mysqli.default_socket=…` for the integration suite and `wp`)
- Repo HEAD `b4c6f15`, worktree shared with other workers (123 modified/untracked entries
  at start; this run changed none of them except creating this file and the untracked
  `composer.lock` already present at start)
- Scratch outputs: `/private/tmp/claude-501/-Users-albertotabo-Local-Sites-wordpress-envato-app-public-wp-content-plugins-envato-theme-check/c7b51e8c-92ad-4201-95d7-08c39fd63208/scratchpad/`

## 1. Tooling is in place

Commands:
```bash
composer install
vendor/bin/phpcs -i
composer lint
composer analyse
```

`composer install` — exit 0
```
Verifying lock file contents can be installed on current platform.
Nothing to install, update or remove
Generating autoload files
```

`vendor/bin/phpcs -i` — exit 0
```
The installed coding standards are MySource, PEAR, PSR1, PSR2, PSR12, Squiz, Zend, PHPCompatibility, … PHPCompatibilityWP, … WordPress, WordPress-Core, WordPress-Docs and WordPress-Extra
```

`composer lint` — exit 2
```
PHPCBF CAN FIX THE 1 / 3 / 9 / 1 / 10 MARKED SNIFF VIOLATIONS AUTOMATICALLY (per file; 24 total)
Script vendor/bin/phpcs handling the lint event returned with error code 2
```
Summary run of the same ruleset (`vendor/bin/phpcs -q --report=summary`), exit 2:
`A TOTAL OF 177 ERRORS AND 64 WARNINGS WERE FOUND IN 60 FILES`
Error-bearing files include `checks/class-*.php` (e.g. `class-textdomain-check.php` 17 errors, `class-screenshot-check.php` 5) and `tests/Integration/AiToggleTest.php` (6 warnings).

`composer analyse` — exit 0
```
 [OK] No errors
```

**Result: FAIL (lint).** Expected `composer lint` 0 errors on plugin files; actual 177 errors / 64 warnings in 60 files. `phpcs -i`, `composer analyse` (phpstan, 95 files) pass.

## 2. Every check is catalogued and fixtured (US4)

Command: `composer test:unit`

Exit 0
```
Runtime:       PHP 8.3.30
Tests: 363, Assertions: 1497, Skipped: 2.
OK, but some tests were skipped!
```
Skips (both `CheckFixtureTest`, `fse-required-files`):
`No registered check class for id 'fse-required-files'`

Expected vs actual (CatalogueTest passes, so the suite's own counts are self-consistent):

| Item | Quickstart expected | Actual |
|---|---|---|
| Registered checks | 59 | 61 check classes (`checks/class-*.php`) |
| `docs/SPEC.md` catalogue rows | 59 | 61 |
| Fixture dirs | 118 | 132 total; 61 `-fail` + 61 `-pass` = 122 check fixtures (+10 regression/parity dirs) |

Notes:
- `docs/SPEC.md:107` says `fse-required-files` registers conditionally at `checkbase.php:390`. Line 390 is tokenizer code; the registration is `checkbase.php:484`. This is why the two fixture cases skip.
- Quickstart says CheckFixtureTest should run every `-fail` and `-pass` case; 2 are skipped (above).

**Result: PASS (suite green) with count discrepancy.** Counts recorded, not judged.

## 3. Admin and CLI agree (US1)

Command: `php -d mysqli.default_socket=$SOCK vendor/bin/phpunit -c phpunit-integration.xml`
(`composer test:integration` does not pass the socket override; invoked directly with the same config.)

Exit 0
```
Tests: 75, Assertions: 337
OK (75 tests, 337 assertions)
```

RunnerParity filter (`--filter RunnerParityTest`), exit 0
```
✔ Admin and cli produce the same findings with data set "worms-fail" … "twentytwentyfive"
✔ Child theme includes parent files on both paths
✔ Hidden folder contents are excluded on both paths
OK (66 tests, 266 assertions)
```

`wp theme review active` (via `php -d mysqli.default_socket=$SOCK $(which wp) --path=../../.. theme review active`) — exit 0, 22 lines, active theme `elecgreen`.
- `grep -ci "undefined function"` → 0.
- Output is a list of `WARNING: A menu without a theme_location …` lines and a final `FAIL: THEME DID NOT PASS REVIEW`.

`wp theme review check twentytwentyfive` — exit 0, 6 lines:
```
WARNING: Your theme appears to be in the wrong directory for the theme name. … correct slug and text-domain is twenty-twenty-five. …
INFO: PHP compatibility evaluated for 7.4–8.4.
INFO: Only one text-domain is being used in this theme. … The domain found is twentytwentyfive.
INFO Themes that use the tag accessibility-ready will need to undergo an accessibility review. …
FAIL: THEME DID NOT PASS REVIEW
```

SC-006 timing (`time` around `wp theme review check twentytwentyfive`): **1.618 s wall-clock** (1.01 s user, 0.12 s sys). Target < 60 s: PASS.

Manual step (`wp-admin/themes.php?page=themecheck` on `twentytwentyfive`, confirm every mapped trigger ends `Trigger: :tf…`): **NOT RUN (manual, browser)**.

**Result: PASS (automated); manual admin comparison NOT RUN.** No "undefined function" in `wp theme review active`. Note: the CLI output contains no `Trigger: :tf…` strings (see follow-ups).

## 4. PHP compatibility check (US2)

Command: `vendor/bin/phpunit --filter PhpCompatibilityCheckTest`

Exit 0
```
....  4 / 4 (100%)
OK (4 tests, 24 assertions)
```
Expected messages (`create_function` REQUIRED, implicit-nullable WARNING, INFO "evaluated 7.4–8.4") were not inspected line-by-line; the passing assertions cover them.

**Result: PASS.**

## 5. AI module off means silent (US3)

Command as written in quickstart: `vendor/bin/phpunit --filter AiToggleTest`

Exit 0
```
.  1 / 1 (100%)
OK (1 test, 2 assertions)
```
Note: this ran under the default `phpunit.xml`, which picks up 1 test. `AiToggleTest` (7 test methods) lives in `tests/Integration/`, so the command as written does not exercise the quickstart's described behaviour.

Corrected run (same filter, integration config): `php -d mysqli.default_socket=$SOCK vendor/bin/phpunit -c phpunit-integration.xml --filter AiToggleTest`, exit 0
```
.......  7 / 7 (100%)
OK (7 tests, 63 assertions)
```

Manual step (Appearance → Theme Check → AI Review untick, save; devtools show no request to `storage.googleapis.com` or any provider on Run): **NOT RUN (manual, browser)**.

**Result: PASS (corrected integration run); quickstart command is incomplete; manual NOT RUN.**

## 6. False-positive sweep (US4 / FR-015)

Command: `vendor/bin/phpunit --testsuite sweep`

Exit 0
```
.S   2 / 2 (100%)
OK, but some tests were skipped!
Tests: 2, Assertions: 3, Skipped: 1.
```
The skip is `ReferenceThemeSweepTest` with `TC_EXTRA_REFERENCE_DIR is not set.` (expected guard). The `twentytwentyfive` sweep case passes.

Command: `TC_EXTRA_REFERENCE_DIR=../../themes vendor/bin/phpunit --testsuite sweep` (local, report-only)

Exit 1
```
Tests: 2, Assertions: 3, Warnings: 2.
OK, but there were issues!
… textdomain  :0  WARNING Your theme appears to be in the wrong directory … correct slug and text-domain is . …
… textdomain  :0  INFO Only one text-domain is being used in this theme. … The domain found is .
… (second reference theme) textdomain WARNING … correct slug and text-domain is twenty-twenty-five.
```
Extra run lists findings for the proprietary dirs in `../../themes` (`elecgreen` and a `twentytwentyfive` copy). Findings are recorded, not allowlisted, and not judged. The first theme yields an empty expected slug (`is .`); cause not investigated.

**Result: PASS (committed sweep); extra report-only run exit 1 with 2 PHPUnit warnings (recorded, see follow-ups).**

## 7. Plugin on PHP 8.4 (US5)

Command: `vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.1-8.4 --extensions=php --ignore=vendor,tests/fixtures .`

Exit 0. No errors reported.

Command: `grep -L 'declare(strict_types=1)' $(git ls-files '*.php' | grep -v fixtures)`

Output: empty (0 files missing the declaration; grep exit 1 = no matches).

Note: `git ls-files` excludes 18 untracked PHP files in the worktree; they were not included by the quickstart command, so the strict-types check does not cover them.

**Result: PASS.**

## Summary

| § | Section | Automated result | Manual | Verdict |
|---|---|---|---|---|
| 1 | Tooling | `phpcs -i` ok; `composer analyse` 0 errors; **`composer lint` exit 2, 177 errors / 64 warnings in 60 files** | — | **FAIL** |
| 2 | Catalogue / fixtures | `test:unit` 363 tests, green, 2 skipped; 61 checks/61 spec rows/122 check fixtures vs quickstart 59/118 | — | PASS (count discrepancy) |
| 3 | Admin and CLI agree | integration 75 green; RunnerParity 66 green; `wp theme review active` exit 0, no "undefined function"; `wp theme review check twentytwentyfive` exit 0; **SC-006 = 1.618 s** | admin comparison NOT RUN | PASS (automated) |
| 4 | PHP compat | PhpCompatibilityCheckTest 4/4 | — | PASS |
| 5 | AI toggle | quickstart command runs 1 test (wrong config); integration config 7/7 green | browser check NOT RUN | PASS (manual NOT RUN) |
| 6 | Sweep | sweep green (1 skipped, env guard); extra report-only run exit 1, 2 warnings | — | PASS (committed); extra run flagged |
| 7 | PHP 8.4 | PHPCompatibilityWP 8.1–8.4: 0 errors; strict_types check empty | — | PASS |

Overall: 5 of 7 sections green on automated steps; §1 lint is red; §5 and §3 have manual browser steps not run; SC-006 passes comfortably.

## Follow-ups

1. **§1 lint red:** `composer lint` reports 177 errors / 64 warnings in 60 files (24 auto-fixable with phpcbf), against the quickstart's "0 errors on plugin files". Decide whether lint scope or the quickstart expectation is stale, then fix or re-baseline. Includes `tests/Integration/AiToggleTest.php` (6 warnings).
2. **§2 counts:** quickstart expects 59 checks / 118 fixture dirs / 59 spec rows; actual is 61 / 122 check fixtures (132 dirs total) / 61 rows. Update quickstart or the counts in the spec.
3. **§2 skipped tests:** `fse-required-files` skips in CheckFixtureTest ("No registered check class"). `docs/SPEC.md:107` cites `checkbase.php:390`, which is not the registration line (`checkbase.php:484`). Correct the reference and make the fixture test find the conditional check.
4. **§3 CLI trigger strings:** `wp theme review check twentytwentyfive` output has no `Trigger: :tf…` suffixes, while quickstart §3 manual step says every mapped finding ends with one. Confirm whether the CLI path appends triggers or the admin only does.
5. **§5 quickstart command:** `vendor/bin/phpunit --filter AiToggleTest` runs 1 test under `phpunit.xml`; `AiToggleTest` is in `tests/Integration/`. Change the command to `vendor/bin/phpunit -c phpunit-integration.xml --filter AiToggleTest` (7 tests, green).
6. **§6 extra-dir run:** `TC_EXTRA_REFERENCE_DIR=../../themes` exits 1 with 2 PHPUnit warnings; one reference theme yields an empty expected text-domain slug (`is .`). Not investigated.
7. **Manual steps NOT RUN:** §3 admin vs CLI comparison (`wp-admin/themes.php?page=themecheck`), §5 AI-toggle browser/devtools check. Run in a browser to close these.
8. **§7 coverage gap:** strict_types grep uses `git ls-files`, excluding 18 untracked PHP files in the worktree. Consider `git ls-files --cached --others --exclude-standard`.
9. **Scratch hygiene (self-report):** `composer install` output was first redirected to `/tmp/x` (deleted after reading); no other files were written outside the scratchpad except this report.
