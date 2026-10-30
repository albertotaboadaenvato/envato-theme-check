# ADR 0002: Reviewer tooling ships as a separate plugin behind a neutral filter

**Status**: Accepted (2026-10-30)
**Deciders**: Alberto Taboada (Envato)

## Context

Envato Theme Check is used by two audiences: ThemeForest/Elements authors, who run it on
their own themes before submitting, and Envato reviewers. The work on branch
`feature/albertotaboada/ai-improvement` added two things that only make sense for reviewers:
canned-response **snippet triggers** (`Trigger: :tfcpt`) appended to findings from
`config/triggers.json` plus a `sync-triggers` command that reads the internal `rules.yml`,
and the **AI Reviewer** module (`ai-review/`, LLM providers, cloud rules, settings page).
Neither belongs in the plugin that authors install from the upstream repository, and the
upstream `master` has none of it. The static improvements (shared file collector, CLI
fix, PHP compatibility check, PHP 8.1 floor, test suite) do belong there.

## Decision

- The public plugin exposes one extension point per finding:
  `tc_finding_suffix( '<check-id>[/<finding-key>]' )` →
  `apply_filters( 'themecheck_finding_suffix', '', $key )`, plus
  `do_action( 'themecheck_after_results', $themename )` under the results table and the
  existing `themecheck_checks_loaded`. With no callbacks registered, output is unchanged.
- Everything reviewer-specific moves to a separate private plugin,
  `envato-theme-check-reviewer` (`albertotaboadaenvato/envato-theme-check-reviewer`):
  `ai-review/`, `config/triggers.json` + `rules.snapshot.yml`, the trigger filter callback
  (with `check/finding → check` fallback), `wp theme review-tools sync-triggers`, and their
  tests and spec. It requires the public plugin ≥ 2.1.0.
- `feature/albertotaboada/ai-improvement-release` (PR #1) is rebuilt from `master` with
  only the public changes; the personal branch carries agent tooling only.

## Alternatives considered

- **Two long-lived branches of one codebase** (public without any hook, personal with
  `tc_trigger()` and `ai-review/` inside) — rejected: every sync with master rebases 19
  checks and `main.php`; the divergence is permanent.
- **Ship triggers/AI in the public plugin behind the AI toggle** — rejected: authors would
  see reviewer-only settings and `Trigger:` text, and the dependency on an internal
  `rules.yml` URL would leak into the public repository.

## Consequences

- Positive: upstream PR contains only author-relevant improvements; reviewer tooling can
  iterate without touching the public plugin; one codebase for checks.
- Negative: reviewers install two plugins; finding keys (`basic/wp_head`, …) become a small
  public contract the add-on depends on, catalogued in `docs/SPEC.md` §Extensibility.
