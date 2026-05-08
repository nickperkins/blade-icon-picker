# Blade Icon Picker — Agent Map

Standalone Laravel package providing an icon picker form component for use in Livewire forms.
Scans Heroicons (and any blade-icons pack) and presents them in a searchable, scrollable grid using Alpine.js.

## Workspaces

| Workspace | Purpose |
|-----------|---------|
| `docs/`   | Planning, PRD, technical specs, architectural decisions |
| `src/`    | PHP package source — service provider, icon manager, Blade component class |
| `resources/` | Frontend assets — Blade views, JS (Alpine.js), CSS |
| `tests/`  | Test suite (Pest + testbench) |
| `bin/`    | Build scripts (esbuild for JS bundling) |

## Routing

| Task | Go to | Read | Skill |
|------|-------|------|-------|
| Plan, spec, or make decisions | `docs/` | `docs/CONTEXT.md` | `double-diamond` |
| Write PHP source (service provider, managers, components) | `src/` | `src/CONTEXT.md` | — |
| Write Blade views, JS, or CSS | `resources/` | `resources/CONTEXT.md` | `frontend-design` |
| Write or review tests | `tests/` | `tests/CONTEXT.md` | `testing` |
| Review code changes | `docs/` | `src/CONTEXT.md` | `isolated-code-review` |
| Build JS bundle | `bin/` | `resources/CONTEXT.md` | — |
| Cross-workspace change (e.g. new feature) | Start in `docs/` | `docs/CONTEXT.md` | — |

## Naming Conventions

- PHP classes: PSR-4 under `IconPicker\` namespace, mapped to `src/`
- Blade views: `icon-picker::` namespace, kebab-case filenames
- JS components: camelCase in `resources/js/components/`
- CSS: `resources/css/icon-picker.css`
- Build output: `resources/dist/` (gitignored — built by CI and release workflow)
