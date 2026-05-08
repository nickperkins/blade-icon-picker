# Resources Workspace

## Purpose
Frontend assets: Blade views, Alpine.js component(s), and CSS.

## Pipeline
Spec (from docs/) → Build → Review → Bundle → Test

## What good looks like
- The Blade component accepts standard Livewire binding (`wire:model`) and standard HTML attributes
- Alpine.js handles search, lazy loading, and UI state; no backend calls during interaction
- Client-side substring token matching for search (no fuzzy-search library needed)
- Icons lazy-load into the DOM via `x-intersect` to avoid rendering thousands of SVGs upfront
- SVGs are embedded as strings in the JSON payload and only parsed when the chunk becomes visible
- Standalone CSS (~200 lines, CSS custom properties for theming) — no Tailwind dependency
- The JS is bundled via esbuild, not a heavy webpack/vite pipeline

## What to avoid
- Inline styles; use CSS custom properties for theming
- Server round-trips for search or filtering (icons are loaded once, searched client-side)
- Filament's `generate_icon_html()` helper — use blade-icons SVG rendering directly
- Fuse.js or other fuzzy search library — substring token matching is sufficient for v1
- Tailwind dependency — the package must work in apps without Tailwind

## Key Dependencies
- `@alpinejs/focus` — focus trapping (bundled into distributed JS)
- `alpinejs` — provided by consumer's app
- `esbuild` — JS bundling (dev dependency)
