# Tests Workspace

## Purpose
Test suite for the Blade Icon Picker package. Uses Pest + Orchestra Testbench for isolated package testing.

## Pipeline
Implementation → Test → Fix → Merge

## What good looks like
- Integration/Component tests for IconManager: resolving icons, SVG rendering, handling missing icons
- Component tests for the Blade component: rendering, state binding, ARIA accessibility attributes
- Pure unit tests where no framework boot is needed (empty packs, value objects)
- No tests that require a full Laravel app; everything runs under testbench
- Tests are fast (no database, no HTTP calls)
- Edge cases covered: empty state, null/empty values, invalid icon name, no icon sets installed
- Sample-based assertions over exhaustive iteration (prove integration, don't audit third-party data)
- Tests aligned with the `testing` skill (pyramid placement, assertion value, no duplication)

## What to avoid
- Testing third-party behavior (blade-icons, Livewire internals)
- Slow tests or tests that require external services
- Over-mocking; prefer real blade-icons factory in tests where practical

## Skills
| Stage | Load skill |
|-------|------------|
| Writing or reviewing any test | `testing` |
