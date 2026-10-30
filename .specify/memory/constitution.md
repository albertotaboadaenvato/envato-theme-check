<!--
Sync Impact Report
- Version change: 1.0.0 → 1.0.1 (PATCH: clarification of Principle III)
- Modified principles: III "Every Check Is Proven by Fixtures" — reference themes are
  committed snapshots under tests/fixtures/reference/ (initially twentytwentyfive); the
  non-redistributable `moveora` is no longer named; optional report-only local sweep added.
- Added sections: none
- Removed sections: none
- Templates: plan/spec/tasks templates read this file at runtime; no edits required.
  CLAUDE.md / AGENTS.md / ORCHESTRATION.md updated in the same change (psycare → snapshot).
- Follow-up TODOs: none
-->

# Envato Theme Check Constitution

## Core Principles

### I. Static First, AI Optional

The plugin MUST deliver a complete, useful theme review with no AI provider, API key or
network access configured. The AI Reviewer module is an add-on behind an explicit
enabled/disabled setting; when disabled, the plugin MUST register no AI UI and MUST make
zero outbound HTTP requests. No static check may depend on an LLM or on a remote fetch at
runtime. Rationale: reviewers need deterministic, offline-capable results, and the project
goal is to remove the dependency on AI, not to hide it.

### II. Spec Before Test Before Code (NON-NEGOTIABLE)

Every externally observable behaviour MUST be described in the spec (`docs/SPEC.md` for
checks and plugin behaviour; `specs/<feature>/spec.md` for features in flight) before any
test is written, and a failing acceptance test MUST exist before any implementation code.
Spec diffs are presented to and approved by the user before tests or code are started. Bug
fixes follow the same order. Tooling, docs, fixtures-only and pure refactors are exempt from
the spec step but MUST still name an observable acceptance command. Rationale: the repo has
already reverted behaviour twice for lack of tests; the order is what prevents a third time.

### III. Every Check Is Proven by Fixtures

Each registered check MUST have at least one positive fixture theme that produces its
documented finding and one negative fixture that produces none, both exercised by the
automated suite. Checks MUST additionally pass a false-positive sweep over the reference
themes committed as snapshots under `tests/fixtures/reference/` (initially
`twentytwentyfive`); any finding on a reference theme MUST be listed in that theme's committed
allowlist (check, file:line, reason, confirmer), and stale allowlist entries MUST fail the
suite. Reference themes are never edited and MUST be redistributable (GPL); proprietary themes
MAY be swept locally in report-only mode and never gate the suite. Rationale: loose
regexes have shipped false positives against real themes; fixtures and sweeps are the only
guard that scales to 60+ checks.

### IV. Token-Based Checks, Not Regex, for PHP

New checks that inspect PHP source MUST use token or sniff analysis (PHP_CodeSniffer /
PHPCSUtils / `token_get_all`), preferring to wrap an existing WordPress-Coding-Standards or
PHPCompatibilityWP sniff over writing new logic. Regex is permitted only for non-PHP content
(CSS, `style.css` headers, templates, file lists). Existing regex checks are kept, catalogued
and fixtured; they migrate check by check, never in bulk. Rationale: regex cannot distinguish
code from strings or comments, which is exactly where the historical false positives came from,
and PHP 8.x rules are unexpressible as patterns.

### V. Admin and CLI Parity

The admin page and the WP-CLI command MUST produce identical finding sets for the same theme:
same file set (including parent theme files), same exclusions (hidden folders, bundled
third-party libraries, development directories), same content preparation, same memory
policy. Any change to the check runner MUST be verified on both paths by the suite.
Rationale: the CLI has drifted from the admin path repeatedly and once shipped a fatal; parity
is a tested invariant, not a convention.

### VI. Modern, Strict, Secure PHP

All plugin PHP MUST target PHP 8.1 as the floor, declare `strict_types=1`, use native and
explicit nullable types, declare every class property, and pass PHPCompatibility analysis for
PHP 8.1–8.4 and PHPStan at the project level with zero errors. WordPress security rules apply
everywhere: sanitise input, escape output, nonce plus `manage_options` on every admin/AJAX
entry point, assets via `wp_enqueue_*`, secrets encrypted at rest and never logged or placed in
URLs where avoidable. Rationale: the plugin is a reviewer's tool for enforcing these standards
on others and must hold itself to them.

## Quality Gates & Tooling

- Three commands MUST exist and pass on the main branch: test suite (PHPUnit with fixture
  themes), coding-standard analysis (PHPCS with `WordPress` + `PHPCompatibilityWP`), static
  type analysis (PHPStan). Any PR that turns one red is not mergeable.
- After every PHP edit: `php -l` then PHPCS on the touched file.
- Every finding that maps to an Envato snippet MUST end with `` Trigger: `:tf<name>` ``. The
  check → trigger mapping is a committed file, the only source static checks read at runtime;
  a maintainer command refreshes it from the cloud `rules.yml` and reports the diff.
- Adding or changing a check without its catalogue entry in `docs/SPEC.md` and its fixtures
  fails the quality gate.
- Architectural decisions (new engine, domain restructure, new external integration) MUST be
  recorded as an ADR under `docs/decisions/` before the first dependent change merges.
- Flaky or pre-existing failing tests are never dismissed; they are fixed or explicitly tracked.

## Development Workflow & Orchestration

- Features flow through Spec Kit in order: specify → clarify → plan → tasks → analyze →
  implement. No implementation worker is dispatched before `tasks.md` exists and `analyze`
  reports no critical gaps.
- Work is executed as supervised Orca runs as defined in `ORCHESTRATION.md`: one long-lived
  orchestrator that only plans, routes, writes prose and approves spec diffs with the user;
  workers receive exactly one task each (Target / Change / Constraints / Ownership /
  Observable acceptance), edit only their owned files, report `worker_done` with evidence, and
  are released. Workers in the same wave never share files.
- Every agent in the repo obeys `AGENTS.md`: read the spec and `.trail/learnings/` before
  editing, ask instead of guessing, never commit/push/rebase unless the task says so, never
  touch reference themes or the upstream repository.
- Branch policy: all work on the fork `albertotaboadaenvato/envato-theme-check`; the upstream
  repository visible to buyers is never pushed to. Commits only when the user asks.

## Governance

This constitution supersedes `CLAUDE.md`, `AGENTS.md`, `ORCHESTRATION.md` and any skill or
agent instruction where they conflict; those documents implement it and MUST be updated in the
same change that amends a principle they describe.

Amendments: propose the change as a diff to this file with rationale; the user approves; the
version is bumped and the Sync Impact Report at the top is updated. MAJOR for removing or
redefining a principle, MINOR for adding a principle or materially expanding guidance, PATCH
for clarifications. Every `/speckit-plan` MUST include a Constitution Check listing each
principle with pass/fail and a justification for any deviation; `/speckit-analyze` treats a
constitution violation as CRITICAL. Reviews of PRs verify compliance with Principles II–VI as
a checklist, not as judgement.

**Version**: 1.0.1 | **Ratified**: 2026-10-29 | **Last Amended**: 2026-10-29
