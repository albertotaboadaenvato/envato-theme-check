# AGENTS.md — rules for every agent working in this repo

Applies to Claude, Codex and any Orca-dispatched worker. `CLAUDE.md` holds the
full project context; this file is the short contract.

## Scope

- You receive **one task** with Target / Change / Constraints / Ownership /
  Observable acceptance. Do only that task. When done, send `worker_done` and
  stop; your terminal is released automatically.
- Edit only the files named under **Ownership**. Anything else is read-only.
- Stay inside this plugin directory. Themes under `wp-content/themes/` are
  fixtures for false-positive testing — never edit them.

## Before editing

1. Read `docs/SPEC.md` for the behaviour you touch. If it is missing or
   unclear, `ask` the coordinator — do not invent behaviour.
2. Read `.trail/learnings/` entries relevant to your files.
3. Confirm a failing test exists for the behaviour (or write it first).

## While editing

- PHP 8.1+ syntax, `declare(strict_types=1)`, explicit `?Type`, declared
  properties.
- New checks: token/sniff based. Regex only for non-PHP content.
- After every PHP edit: `php -l <file>`; then `phpcs` (repo `phpcs.xml` or
  global `--standard=WordPress`).
- Never commit, push, rebase or open PRs unless the task says so.
- Never `git stash`, `git checkout -- <file>` or `git reset` in the shared worktree:
  other workers are editing concurrently and a stash/pop reverts their files for
  the duration. For a "before" baseline, `git show HEAD:<file>` into the scratch dir.
- Never store or print API keys; never add `.env`-style secrets.

## Done means

- Tests you own are green: `vendor/bin/phpunit --filter <YourTest>`.
- No new phpcs errors in files you touched.
- `vendor/bin/phpunit --testsuite sweep` green (reference snapshots in
  `tests/fixtures/reference/`); report the command and its output.
- `worker_done` includes: outcome, files modified, test command + result,
  anything you could not verify, marked as such.

## Ask, don't guess

Use the `ask` command from your dispatch preamble for: unclear spec, a needed
edit outside your ownership, a failing test you did not cause, a design choice
with more than one reasonable answer.
