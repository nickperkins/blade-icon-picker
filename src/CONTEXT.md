# Src Workspace

## Purpose
PHP package source code. The service provider, icon manager, Blade component class, and any supporting PHP classes.

## Pipeline
Spec (from docs/) → Implement → Review → (handoff to tests/)

## What good looks like
- Classes have single responsibilities; the IconManager resolves icons, the Blade component handles rendering
- Every public method is typed (parameters and return types)
- Dependencies (blade-icons, livewire) are injected, not resolved from the container at call sites
- The service provider is handwritten (no spatie package tools) — it's ~30 lines and easily maintained
- No Filament dependency anywhere; this package stands alone
- The Icon value object carries SVG markup as a string field, enabling truly lazy DOM rendering

## What to avoid
- Static facades or service location inside domain classes
- Hardcoding icon paths or set names
- Pulling in Filament as a dependency, even for convenience
- Using `spatie/laravel-package-tools` — the service provider is too simple to need it

## Key Dependencies
- `blade-ui-kit/blade-icons` — icon resolution and SVG rendering
- `blade-ui-kit/blade-heroicons` — supported out of the box (user installs it; suggested in composer.json)
- `livewire/livewire` — wire:model binding on the Blade component
