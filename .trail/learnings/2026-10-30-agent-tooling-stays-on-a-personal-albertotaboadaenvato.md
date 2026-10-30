---
title: Agent tooling stays on a personal branch; a separate release branch is what goes to master and upstream
author: Albertotaboadaenvato
date: 2026-10-30
branch: feature/albertotaboada/ai-improvement
cost: { minutes: 1, tokens: 1730 }
id: l-15a493ba
scope: repo
status: active
src: agent
touches:
  - ".agents/skills/speckit-analyze/SKILL.md"
  - ".agents/skills/speckit-checklist/SKILL.md"
  - ".agents/skills/speckit-clarify/SKILL.md"
  - ".agents/skills/speckit-constitution/SKILL.md"
  - ".agents/skills/speckit-converge/SKILL.md"
  - ".agents/skills/speckit-implement/SKILL.md"
  - ".agents/skills/speckit-plan/SKILL.md"
  - ".agents/skills/speckit-specify/SKILL.md"
  - ".agents/skills/speckit-tasks/SKILL.md"
  - ".agents/skills/speckit-taskstoissues/SKILL.md"
---

## Decided
Agent tooling and planning documents (AGENTS.md, CLAUDE.md, GEMINI.md, ORCHESTRATION.md, .claude/, .gemini/, .agents/, .specify/, specs/, .trail/, docs/decisions/, docs/reports/, docs/IMPROVEMENT-PLAN.md) must never reach master or the upstream Envato repository. The release branch holds only product commits; the personal feature branch equals the release branch plus one tooling commit kept on top. Product changes go to the release branch and the feature branch is rebased onto it; tooling changes amend the top commit.

## Tried and ruled out
Filtering files at merge time: git cannot exclude paths from a merge, so a separate release branch is the only reliable mechanism.

## Watch out
Product files (checks/class-php-compatibility-check.php, docs/SPEC.md, phpcs.xml) must not link to the tooling paths, because they do not exist on the release branch; describe the decision in prose instead. Some tooling files were tracked by old commits, so the release branch carries an explicit untracking commit.

In the code:
release = feature/albertotaboada/ai-improvement-release; personal = feature/albertotaboada/ai-improvement (= release + 'chore: agent tooling' commit). Sync: git rebase feature/albertotaboada/ai-improvement-release feature/albertotaboada/ai-improvement
