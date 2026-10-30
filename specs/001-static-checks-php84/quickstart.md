# Quickstart: validating the feature end-to-end

Prerequisites: PHP ≥ 8.1 (Local ships 8.3), Composer, the Local WordPress site at
`../../..` relative to the plugin, WP-CLI.

## 1. Tooling is in place

```bash
composer install
vendor/bin/phpcs -i          # expect: … WordPress, PHPCompatibility, PHPCompatibilityWP …
composer lint                # 0 PHPCompatibilityWP errors; WordPress-sniff residual tracked in docs/SPEC.md Follow-ups
composer analyse             # phpstan level 6, 0 errors
```

## 2. Every check is catalogued and fixtured (US4)

```bash
composer test:unit
# CatalogueTest: 61 check files == 61 rows in docs/SPEC.md == 122 pass/fail fixture dirs (+ regression + parity dirs)
# CheckFixtureTest: each <id>-fail emits its documented finding, each <id>-pass emits none
```

## 3. Admin and CLI agree (US1)

```bash
composer test:integration   # = vendor/bin/phpunit -c phpunit-integration.xml; bootstrap auto-finds Local's MySQL socket
# RunnerParityTest: findings(admin collector) === findings(CLI collector) for
#   tests/fixtures/themes/* and tests/fixtures/reference/*
bin/wp-local theme review active      # completes; no "undefined function"
bin/wp-local theme review check twentytwentyfive # same findings as Appearance > Theme Check
```

Manual: open `wp-admin/themes.php?page=themecheck`, run on `twentytwentyfive`, confirm every
finding that has a mapped trigger ends with `Trigger: :tf…`.

## 4. PHP compatibility check (US2)

```bash
vendor/bin/phpunit --filter PhpCompatibilityCheckTest
# php-compatibility-fail (Requires PHP: 7.4): REQUIRED create_function() removed in 8.0,
#   WARNING implicit nullable deprecated in 8.4, INFO "evaluated 7.4–8.4"
# php-compatibility-pass (Requires PHP: 8.2, uses enums): INFO only
```

## 5. AI module off means silent (US3)

```bash
vendor/bin/phpunit -c phpunit-integration.xml --filter AiToggleTest
# with enabled=false: 0 calls through pre_http_request across plugin load, settings page
#   render, static check run, and POST to each wp_ajax_tc_* action (which return 0/-1)
# migration: option without 'enabled' + stored key -> enabled=true; without key -> false
```

Manual: Appearance → Theme Check → AI Review, untick **Enable AI Reviewer**, save; the Theme
Check page shows no AI panel; browser devtools show no request to `storage.googleapis.com`
or any provider on "Run" .

## 6. False-positive sweep (US4 / FR-015)

```bash
vendor/bin/phpunit --testsuite sweep
# passes when every finding on tests/fixtures/reference/twentytwentyfive is allowlisted and
# every allowlist entry still matches
TC_EXTRA_REFERENCE_DIR=../../themes vendor/bin/phpunit --testsuite sweep   # local, report-only
```

## 7. Plugin on PHP 8.4 (US5)

```bash
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.1-8.4 \
  --extensions=php --ignore=vendor,tests/fixtures .   # 0 errors
grep -L 'declare(strict_types=1)' $(git ls-files '*.php' | grep -v fixtures)  # empty
```
CI matrix (8.1 / 8.3 / 8.4) runs steps 1, 2, 4, 6, 7.
