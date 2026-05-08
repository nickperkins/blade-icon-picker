# Docs Workspace

## Purpose
Planning, specification, and decision-making for the Blade Icon Picker package. This is where we think before we build. PRDs, solution designs, architecture decisions, and tradeoff analyses live here.

## Pipeline
PRD → Solution Design → (handoff to src/)

## Artifacts
- `prd.md` — Product Requirements Document (what & why, not how)
- `solution-design.md` — Technical design translating the PRD into concrete architecture, APIs, data flows, and implementation order

## What good looks like
- PRDs answer "what problem does this solve and for whom?" before answering "how"
- Technical decisions include the alternatives considered and why they were rejected
- Every spec section is testable — "how will we know this works?"
- The PRD stays focused on user-facing behavior; implementation details go in src/CONTEXT.md

## What to avoid
- Specifying implementation details (leave that to src/CONTEXT.md)
- Skipping the "why" — every feature should trace back to a user need
- Over-engineering the first version; prefer simple, composable primitives

## Skills
| Stage | Load skill |
|-------|------------|
| Discover / Define (problem framing) | `double-diamond` |
