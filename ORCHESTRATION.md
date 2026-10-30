# Orchestrated Plugin Development (Orca)

Development of this plugin runs as a supervised Orca coordination loop. One
long-lived **orchestrator** plans and routes; every other agent is a **worker**
that receives exactly one task, finishes it, sends `worker_done`, and is
released. Load the runtime guide before dispatching anything:

```text
orca skills get orchestration
```

All gates in `CLAUDE.md` / `AGENTS.md` still apply. This file only defines
**who** does each step and **how** work is cut.

## 1. Roles

| Model | Role | Allowed | Forbidden |
|---|---|---|---|
| `claude-fable-5-1` | **Orchestrator** (this session, always alive) | `orca orchestration *` (run-create, task-create, worker-start, check, reply, worker-release, worker-list), `orca skills get`, `speckit` commands, writing/reviewing docs (`CLAUDE.md`, `AGENTS.md`, `docs/SPEC.md`, ADRs, this file), approving spec diffs with the user, final synthesis | Any shell command against code (`grep`, `cat`, `php`, `composer`, `git`, `wp`, `phpcs`, Playwright). Editing PHP/CSS/JS. If evidence or a change is needed, dispatch a worker |
| `claude-haiku-5-5` `--effort medium` | **Scout** | Deterministic read-only evidence: inventories of `checks/`, grep for patterns, list Envato triggers in `config.yml` not covered by a check, run `php -l`/`phpcs`/`phpunit` and report output, false-positive sweeps (`vendor/bin/phpunit --testsuite sweep`) | Editing any file, design judgement |
| `claude-sonnet-5-5` `--effort medium` | **Builder** | Bounded implementation with a clear spec and a fixture: tooling files (`composer.json`, `phpcs.xml`, `phpstan.neon`, tests bootstrap), hooks/skills under `.claude/`, fixture themes, settings toggles, UI wiring, docs generation | Designing new checks from scratch, modernising `checkbase.php`/`main.php` |
| `claude-opus-5-5` `--effort high` | **Check author / core modernizer** | New token-based checks (sniff wrappers), PHP 8.4 modernisation of core files, migration of a regex check to tokens, fixing a failing test with a non-obvious cause | Broad sweeps (ask the Scout), unrelated refactors |
| `gpt-6-luna` `--effort low` | **Codex runner** | Same scope as the Scout. Dispatched when the Scout is busy or for parallel evidence/test runs | Edits, adjudication |
| `gpt-6.1-sol` `--effort medium` | **Bulk builder** | Same as Builder when the scope is many small similar files (e.g. 20 fixture themes, catalogue of 60 checks into `SPEC.md`). Split scope with Sonnet; never duplicate files | Core files, new check design |
| `gpt-6-astra` `--effort medium` | **Security reviewer** | `ai-review/` and any admin/AJAX surface: nonces, capabilities, escaping of LLM output, key storage, outbound requests when AI is disabled. Also independent review of any PR before the user merges | Non-security edits; it reports, does not fix |

If a model id is unavailable, `worker-start` fails with the receipt; do not
substitute silently — report and let the user choose. Always compare
`launch.requested` with `launch.effective`.

Dispatch mapping:

```text
orca orchestration worker-start --spec "<task>" --worktree current --agent claude --model claude-haiku-5-5  --effort medium --json
orca orchestration worker-start --spec "<task>" --worktree current --agent claude --model claude-sonnet-5-5 --effort medium --json
orca orchestration worker-start --spec "<task>" --worktree current --agent claude --model claude-opus-5-5   --effort high   --json
orca orchestration worker-start --spec "<task>" --worktree current --agent codex  --model gpt-6-luna   --effort low    --json
orca orchestration worker-start --spec "<task>" --worktree current --agent codex  --model gpt-6.1-sol  --effort medium --json
orca orchestration worker-start --spec "<task>" --worktree current --agent codex  --model gpt-6-astra  --effort medium --json
```

Codex effort goes through `--effort`, never `-c model_reasoning_effort=...`.

## 2. One task, one worker, then release

- Every Dispatch carries exactly one task from the roadmap in
  `docs/IMPROVEMENT-PLAN.md §5` (or a sub-task of it). No "while you're there".
- A worker that discovers extra work **asks**; it does not expand scope.
- After an accepted `worker_done`, the orchestrator runs `worker-release`
  before acking. `worker-retain` only when the user asks to inspect a terminal.
  Reuse a terminal only for an *immediate* follow-up of the same task by the
  same model (e.g. a failed test fix).
- Never end a turn while `worker-list --terminal-state reclaimable` returns rows.

## 3. Spec-driven gates (orchestrator enforces)

Every behaviour-changing task goes through, in order:

1. **Spec** — orchestrator drafts the `docs/SPEC.md` diff (or confirms none is
   needed) and gets the user's explicit approval. No worker is dispatched for
   tests or code before this.
2. **Red test** — a Builder/Opus worker adds the fixture + PHPUnit test and
   reports it **failing** with the command output. Acceptance for this task is
   a red test, not a green one.
3. **Implementation** — a second Dispatch (may reuse the same terminal) makes
   it pass. Acceptance: that test green, full suite green, phpcs clean,
   false-positive sweep clean.
4. **Review** — Scout or Codex runner re-runs the suite independently; for
   `ai-review/` or any admin/AJAX surface, `gpt-6-astra` reviews the diff.
5. **Commit** — only when the user asks. A Builder commits with a message
   ending in the attribution line from the session; the orchestrator never
   runs git.

Tooling, docs, fixtures-only and refactor tasks skip step 1 but still need an
observable acceptance (command + expected output).

## 4. Standard wave plan

```text
Wave 0  Scout         inventory: checks list, triggers without a check, PHP 8.4 risk grep, phpcs -i
Wave 1  Builder       roadmap #1 tooling (composer/phpcs.xml/phpstan.neon/tests bootstrap)
        Bulk builder  roadmap #2 catalogue 60 checks into docs/SPEC.md (from Scout inventory)
        Orchestrator  roadmap #3 ADR 0001 with the user
Wave 2  Opus          roadmap #4 fixtures + tests for the 5 riskiest regex checks
        Opus          roadmap #5 core PHP 8.4 modernisation (separate worker, separate files)
        Security      review ai-review/ for "zero requests when disabled"
Wave 3  Opus ×N       roadmap #6/#7 one new check per worker, each with its fixture pair
        Builder       roadmap #8 hooks + skills, #9 AI optional toggle
```

Workers in the same wave never share files. If two tasks need the same file,
serialise them with `task-create --deps`.

Wait with:

```text
orca orchestration check --wait --types "worker_done,escalation,question" --timeout-ms 900000 --json
```

## 5. Task-spec template

```text
Target: <files or component>
Change: <one concrete result>
Constraints: PHP 8.1+ strict types; token-based checks only; do not touch files outside Ownership; no commits; read AGENTS.md and the relevant .trail/learnings entry first.
Ownership: <exact file list this worker may create/edit>
Observable acceptance: <command> prints <expected>; php -l and phpcs clean on touched files; `vendor/bin/phpunit --testsuite sweep` output reported.
Report: worker_done with --outcome, --files-modified, test output, and anything unverified marked as such.
```

## 6. What the orchestrator writes itself

Only prose: `CLAUDE.md`, `AGENTS.md`, this file, `docs/SPEC.md` diffs,
`docs/decisions/*.md`, `docs/IMPROVEMENT-PLAN.md`, and the final report to the
user. Everything that is code, config, tests or fixtures is a worker task.
