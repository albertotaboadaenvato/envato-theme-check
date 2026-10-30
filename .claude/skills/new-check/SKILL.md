---
name: new-check
description: Scaffold a new static check spec-first (SPEC row, pass/fail fixtures, red test, then class, trigger, green + sweep).
argument-hint: <id> "<what it detects>" <severity> [:tftrigger]
user-invocable: true
disable-model-invocation: true
---

# /new-check `<id>` `"<detects>"` `<severity>` `[:tftrigger]`

`<id>` is kebab-case; class file is `checks/class-<id>-check.php`, class `<Id_Title_Case>_Check`.
`<severity>`: required|warning|recommended|info. Order is fixed (constitution II): spec → red → code → green.
Read `.trail/learnings/` first. Edit nothing outside the files below. Never commit.

1. **Spec row** — add to the `## Check catalogue` table in `docs/SPEC.md`, kept in id order:
   `| <id> | <Class> | sniff | <severity> | <:tftrigger or blank> | <detects> | <id>-pass | <id>-fail |`
   (engine `sniff`/`token` for PHP; `regex` only for CSS/style.css/templates, constitution IV).
   Show the diff to the user and wait for approval before step 2.
2. **Fixtures** — `tests/fixtures/themes/<id>-pass/` and `<id>-fail/`, each with:
   - `style.css`: copy `tests/fixtures/themes/cdn-pass/style.css`, change `Theme Name: Fixture <id>` and `Text Domain: fixture-<id>`.
   - `index.php`: `<?php` + `declare(strict_types=1);` + minimal code. Pass = clean code that is near-miss for the rule (string/comment mention is fine); fail = the smallest construct that must be flagged.
3. **RED** — run and show the output to the user; it must fail (SPEC row without class):
   `vendor/bin/phpunit --filter 'CatalogueTest|CheckFixtureTest'`
   (a skipped `CheckFixtureTest` is not red; `CatalogueTest` for `<id>` must fail.)
4. **Check class** — only now create `checks/class-<id>-check.php`. Copy the structure of `checks/class-php-compatibility-check.php` (sniff-wrapping via `DummyFile`/`Ruleset`; reuse its `ruleset()` approach with the one WPCS/PHPCompatibilityWP sniff that covers the rule — wrap, don't write new logic):
   ```php
   <?php
   declare(strict_types=1);

   final class <Class>_Check implements themecheck {
   	/** @var list<string> */
   	private array $error = array();

   	/** @param array<string,mixed> $context */
   	public function set_context( array $context ): void {}

   	/**
   	 * @param array<string,string> $php_files
   	 * @param array<string,string> $css_files
   	 * @param array<string,string> $other_files
   	 */
   	public function check( $php_files, $css_files, $other_files ): bool {
   		checkcount();
   		$trigger = tc_finding_suffix( '<id>' ); // '' unless an extension filters themecheck_finding_suffix.
   		// run sniff per file; on hit:
   		// $this->error[] = sprintf( '<span class="tc-lead tc-required">%s</span>: %s%s', __( 'REQUIRED', 'theme-check' ), esc_html( $msg ), $trigger );
   		return array() === $this->error;
   	}

   	/** @return list<string> */
   	public function getError(): array {
   		return $this->error;
   	}
   }
   ```
   Span class by severity: `tc-required` / `tc-warning` / `tc-recommended` / `tc-info`. Then `php -l` + `vendor/bin/phpcs checks/class-<id>-check.php`.
5. **Trigger** — none here: Envato triggers are mapped in the reviewer add-on via `themecheck_finding_suffix`.
6. **GREEN** — show both:
   `vendor/bin/phpunit` and `vendor/bin/phpunit --testsuite sweep`
   A sweep hit on `tests/fixtures/reference/*` means the check is too loose: tighten it; never add an allowlist entry without the user's confirmation.
