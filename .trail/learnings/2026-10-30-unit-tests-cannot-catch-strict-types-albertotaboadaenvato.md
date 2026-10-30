---
title: Unit tests cannot catch strict-types crashes from WordPress functions that return false
author: Albertotaboadaenvato
date: 2026-10-30
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 10, tokens: 154184 }
id: l-ff1cf829
scope: repo
status: active
src: agent
touches:
  - checks/class-image-size-check.php
---

## Watch out
- After strict_types landed in checks/, a check crashed with a TypeError (substr() given false) on every real theme, yet the unit suite stayed green. The unit bootstrap stubs WordPress functions with always-string results; real WordPress returns false (for example the mime type of an unknown extension such as README.md or theme.json). Only the integration suite (RunnerParity, CliActive) and the reference sweep against real WordPress surface this.
- When adding strict_types or native types to a check or root file, run the integration suite too, and cast any WordPress return value that can be false before passing it to a PHP internal string function.

In the code: checks/class-image-size-check.php substr( (string) $file['type'], 0, 6 ); tests/bootstrap-unit.php wp_check_filetype_and_ext() stub returns mime_content_type(); php -d mysqli.default_socket=<Local mysqld.sock> vendor/bin/phpunit -c phpunit-integration.xml
