# Envato Theme Check
Plugin Name: Envato Theme Check

Author: Scott Parry

Author URI: https://scottparry.co/

Plugin URI: https://github.com/envato/Envato-Theme-Check

License: GPLv2

License URI: https://www.gnu.org/licenses/gpl-2.0.html


## Description
<p>The Envato Theme Check is a modified fork of the original Theme Check by Otto42 with additional Envato specific WordPress checks.</p>
<p>It is an easy way to test your theme and make sure it's up-to-date with the latest Envato review standards. With it, you can run all the same automated testing tools on your theme that Envato Reviewers use for WordPress theme submissions.</p>

<p>The tests are run through a simple admin menu and all results are displayed at once. This is very handy for theme developers, or anybody looking to make sure that their theme supports the latest WordPress theme standards and practices.</p>

## Frequently Asked Questions

#### Do I need to address WARNINGS and INFO notices?
<p>It is strongly recommended that WARNING, RECOMMENDED and INFO notices be resolved if possible. Some may be the result of an issue that is cause for rejection (Reviewers make this decision).</p>

#### What do I do if I find a bug, or think something is wrong with the plugin?
<p>You can submit an issue directly here: https://github.com/envato/envato-theme-check/issues</p>

#### Why does the PHP Compatibility check say it cannot run when I install from a git checkout?
<p>The PHP Compatibility check uses PHPCompatibilityWP, which is loaded from the plugin's <code>vendor/</code> directory. Release zips ship with <code>vendor/</code>, but a git checkout does not. Run <code>composer install</code> in the plugin directory first.</p>

## Changelog

#### 2.1.0 — 2026-10-30
<p>Requires PHP 8.1 or higher. The plugin uses strict types and is verified on PHP 8.1 to 8.4.</p>
<ul>
<li>The admin screen and WP-CLI now share one file collector. Parent-theme files are included, and exclusions and comment stripping are the same in both.</li>
<li><code>wp theme review active</code> is fixed. It previously ended in a fatal error.</li>
<li>New PHP Compatibility check (PHPCompatibilityWP). The range runs from the theme's "Requires PHP" value to PHP 8.4. Findings are REQUIRED, WARNING or INFO.</li>
<li>Removed the unused AI_Review_Check.</li>
<li>Added a PHPUnit fixture suite (61 checks, each with a pass and a fail fixture) and a false-positive sweep against a committed twentytwentyfive snapshot.</li>
<li>Added Composer dev tooling: PHPCS with WordPress and PHPCompatibilityWP rules, and PHPStan at level 6.</li>
</ul>
<p>Note: run <code>composer install</code> in a git checkout before using the PHP Compatibility check. Release zips include <code>vendor/</code>.</p>