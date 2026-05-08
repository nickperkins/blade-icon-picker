# Blade Icon Picker — Solution Design

**Date:** 2026-05-08
**Source:** [PRD](./prd.md)

---

## 1. Architecture Overview

This package has three layers, all running inside the host Laravel application:

| Layer | Runs Where | Responsibility |
|-------|-----------|----------------|
| PHP backend (`src/`) | Server | Icon resolution via blade-icons, rendering the Blade component, serializing the icon catalog (including SVG content) into the page payload |
| Blade view (`resources/views/`) | Server-side render | HTML scaffold: trigger field, dropdown panel, icon grid template, and the Alpine.js `x-data` with the icon list |
| Alpine.js frontend (`resources/js/`) | Browser | Search, lazy DOM rendering via `x-intersect`, keyboard navigation, dropdown open/close, `$wire.set()` for Livewire sync |

**Key constraint:** Zero server round-trips during browsing or searching. The full icon catalog including SVGs is embedded as JSON in the page. The only network request is `$wire.set()` on selection — a single round-trip to update the Livewire-bound property.

```
┌────────────────────────────────────────────────────────────┐
│                    Page Load (Server)                       │
│                                                            │
│  IconPicker.php  ──► IconManager.php ──► blade-icons       │
│  (view component)     (getAllIcons())     Factory           │
│        │                 renderSvg()                       │
│        ▼                                                  │
│  icon-picker.blade.php                                    │
│  ┌──────────────────────────────────────────────────┐     │
│  │ <div x-data="iconPicker({ icons: [...], ... })">  │     │
│  │   <!-- each icon: {id, label, svg} -->            │     │
│  │   <!-- trigger field -->                          │     │
│  │   <!-- dropdown panel with icon grid -->          │     │
│  │ </div>                                            │     │
│  └──────────────────────────────────────────────────┘     │
│                                                            │
│  The JSON payload ships to the browser.                    │
│  SVGs are raw strings; DOM elements are created lazily     │
│  only when the chunk becomes visible.                      │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│                    Browser (Alpine.js)                      │
│                                                            │
│  iconPicker() manages state:                               │
│    - isOpen (boolean)                                      │
│    - selectedId                                            │
│    - query                                                 │
│    - chunkCount                                            │
│    - activeIconIndex (for keyboard nav)                    │
│                                                            │
│  User clicks icon  ──►  $wire.set(propertyName, id)       │
│  User types query  ──►  substring token match in-memory    │
│  User scrolls      ──►  x-intersect fires → next chunk     │
└────────────────────────────────────────────────────────────┘
```

---

## 2. PHP Classes

### 2.1 `IconPicker\Icons\Icon` — Value Object

A single icon entry. Simple, immutable, serializable.

```php
namespace IconPicker\Icons;

final readonly class Icon
{
    public string $id;      // e.g. "heroicon-o-home"
    public string $label;   // e.g. "O Home"
    public string $svg;     // inline SVG markup string

    public function __construct(string $id, string $label, string $svg) {}

    /** @return array{id: string, label: string, svg: string} */
    public function toArray(): array;
}
```

### 2.2 `IconPicker\Icons\IconManager` — Icon Resolution

Wraps the blade-icons `Factory`. Responsible for discovering, labeling, and rendering all registered icons.

```php
namespace IconPicker\Icons;

use BladeUI\Icons\Factory;

class IconManager
{
    private const CHUNK_SIZE = 30;

    public function __construct(private Factory $factory) {}

    /** @return Icon[] */
    public function getAllIcons(): array;

    /** Render an icon as inline SVG, or throw if not found. */
    public function renderSvg(string $id): string;
}
```

**`getAllIcons()` algorithm:**

```
for each icon set registered in Factory:
    for each icon name in set:
        prefix     = set prefix (e.g. "heroicon-o")
        rawName    = icon name without prefix (e.g. "home", "arrow-left")
        styleLabel = derive from prefix: last segment after final "-", uppercased
                     (e.g. "heroicon-o" → "O", "heroicon-s" → "S", "heroicon-m" → "M")
        label      = styleLabel + " " + titleCase(rawName)
        id         = prefix . '-' . rawName     (e.g. "heroicon-o-home")
        svg        = $this->renderSvg($id)       (inline SVG markup string)
        yield Icon(id, label, svg)
```

**ID convention matters.** Blade-icons stores icons using `-` as the separator (e.g. `heroicon-o-home`), not `:`. The `@svg` Blade directive, `Factory::svg()`, and all blade-icons consumers expect this format. The `id` field stored in the database via `$wire.set()` must match this convention so that the value works with any blade-icons consumer in the application.

**`renderSvg()`:**

Delegates to the blade-icons `Factory::svg()` method. Wrapped in try/catch — if the icon doesn't exist, throws a custom `IconNotFoundException` with the ID in the message.

### 2.3 `IconPicker\View\Components\IconPicker` — Blade Component Class

Extends `Illuminate\View\Component`. This is the server-side entry point for each `<x-icon-picker::icon-picker>` tag.

```php
namespace IconPicker\View\Components;

use IconPicker\Icons\IconManager;
use Illuminate\Contracts\View\View;

class IconPicker extends Component
{
    private const CHUNK_SIZE = 30;

    public function __construct(
        private IconManager $manager,
        public string $placeholder = 'Select an icon',
        public bool $disabled = false,
        public ?string $value = null,
    ) {}

    /** @return array{id: string, label: string, svg: string}[] */
    public function icons(): array;  // calls $this->manager->getAllIcons()

    public function render(): View
    {
        return view('icon-picker::components.icon-picker', [
            'icons'       => $this->icons(),
            'placeholder' => $this->placeholder,
            'disabled'    => $this->disabled,
            'value'       => $this->value,
            'chunkSize'   => self::CHUNK_SIZE,
        ]);
    }
}
```

**`$value` prop for Livewire binding.** The component is a Blade component, not a Livewire component. It cannot read the parent Livewire component's property value from `wire:model` alone. Instead, the developer passes the current value explicitly:

```blade
<x-icon-picker::icon-picker wire:model="icon" :value="$icon" />
```

The `$value` prop is passed to the Alpine component as `currentValue`, seeding the initial selection state. When Livewire re-renders the component after a `$wire.set()` callback, the new `$value` prop is provided and Alpine re-initializes with the correct selected icon.

**Constructor injection** of `IconManager` is standard Laravel: the container resolves it automatically since `IconManager` is bound in the service provider and its own dependency (`Factory`) is available via blade-icons.

**`chunkSize` is hardcoded** as a private constant (30). Not a public constructor prop — configurable chunk size is P2 and the PRD defers it. The constant is passed to the view for Alpine's use.

**`render()` explicitly passes data** to the view including `$icons`, `$placeholder`, `$disabled`, `$value`, and `$chunkSize`. Public methods on the component are not automatically available as `$variable` in the view.

### 2.4 `IconPicker\IconPickerServiceProvider` — Service Provider

```php
namespace IconPicker;

use IconPicker\Icons\IconManager;
use IconPicker\View\Components\IconPicker;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use BladeUI\Icons\Factory;

class IconPickerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IconManager::class, fn ($app) =>
            new IconManager($app->make(Factory::class))
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'icon-picker');

        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/icon-picker'),
        ], 'icon-picker-assets');

        Blade::component('icon-picker::icon-picker', IconPicker::class);
    }
}
```

**Thin provider.** It binds `IconManager` as a singleton (icons computed once per request), loads views, publishes assets, and registers the Blade component under the `icon-picker::icon-picker` tag — matching the `<x-icon-picker::icon-picker>` usage throughout the PRD.

**No `spatie/laravel-package-tools`.** PRD section 7 says "handwritten, no spatie dependency." The provider is ~25 lines — simple enough to write by hand.

---

## 3. Blade View

`resources/views/components/icon-picker.blade.php`

### 3.1 Template Structure

```blade
@props(['disabled' => false, 'placeholder' => 'Select an icon', 'value' => null])

{{-- Empty state: no icon packs installed --}}
@if(empty($icons))
    <div class="ip-empty">
        No icon sets found. Install blade-ui-kit/blade-heroicons:
        <code>composer require blade-ui-kit/blade-heroicons</code>
    </div>
@else
    <div
        class="ip-root"
        x-data="iconPicker({
            icons: {{ \Illuminate\Support\Js::from($icons) }},
            currentValue: {{ \Illuminate\Support\Js::from($value) }},
            placeholder: {{ \Illuminate\Support\Js::from($placeholder) }},
            disabled: {{ $disabled ? 'true' : 'false' }},
            chunkSize: {{ $chunkSize }}
        })"
        x-on:keydown.escape.window="close()"
        {{ $attributes->except(['placeholder', 'disabled', 'value']) }}
    >
        {{-- TRIGGER: wrapping div; trigger button + clear button are siblings --}}
        <div class="ip-trigger-wrapper">
            <button
                type="button"
                class="ip-trigger"
                :disabled="disabled"
                x-bind:aria-expanded="isOpen"
                aria-haspopup="listbox"
                x-ref="trigger"
                x-on:click="toggle()"
            >
                <span x-show="!selectedId" x-text="placeholder"></span>
                <template x-if="selectedIcon">
                    <span class="ip-trigger-selected">
                        <span x-html="selectedIcon.svg"></span>
                        <span x-text="selectedIcon.label"></span>
                    </span>
                </template>
                <span class="ip-chevron" aria-hidden="true">▼</span>
            </button>

            <button
                x-show="selectedId"
                type="button"
                class="ip-clear"
                x-on:click.stop="clear()"
                aria-label="Clear selection"
            >&times;</button>
        </div>

        {{-- DROPDOWN PANEL --}}
        <div
            x-show="isOpen"
            x-trap="isOpen"
            class="ip-dropdown"
            x-on:click.outside="close()"
        >
            {{-- SEARCH INPUT --}}
            <input
                type="text"
                class="ip-search"
                x-model="query"
                x-ref="searchInput"
                placeholder="Search icons..."
                x-on:input="onSearch()"
            />

            {{-- ICON GRID --}}
            <div class="ip-grid" x-ref="grid" role="listbox">
                <template x-for="(icon, index) in visibleIcons" :key="icon.id">
                    <button
                        type="button"
                        class="ip-icon-btn"
                        x-bind:class="{
                            'ip-icon-btn--selected': icon.id === selectedId,
                            'ip-icon-btn--active': index === activeIconIndex
                        }"
                        role="option"
                        x-bind:aria-selected="(icon.id === selectedId).toString()"
                        x-on:click="select(icon)"
                    >
                        <span x-html="icon.svg"></span>
                        <span class="ip-icon-label" x-text="icon.label"></span>
                    </button>
                </template>

                {{-- SENTINEL for infinite scroll --}}
                <div
                    x-show="hasMore"
                    x-intersect="loadNextChunk()"
                ></div>
            </div>

            {{-- EMPTY SEARCH STATE --}}
            <div x-show="visibleIcons.length === 0" class="ip-empty">
                No icons match your search.
            </div>
        </div>
    </div>
@endif
```

### 3.2 Wire:model Integration

The component accepts standard `wire:model` via `$attributes`. Laravel's Blade component system merges attributes — so `wire:model="icon"` flows through to the root `<div>`.

**Usage pattern:**

```blade
<x-icon-picker::icon-picker wire:model="icon" :value="$icon" />
```

The developer passes BOTH `wire:model` (for Alpine to know which Livewire property to update) and `:value="$icon"` (to seed the initial selection). This is the standard pattern for non-Livewire Blade components that need to reflect Livewire state.

On selection, Alpine extracts the property name from the `wire:model` attribute and calls `$wire.set(propertyName, iconId)`. Livewire picks up the change, re-renders the parent component, and the new `$value` reaches the Blade component — Alpine re-initializes with `selectedId` matching the new value, showing the selected icon in the trigger field.

### 3.3 Handled `wire:model` Modifiers

Livewire 3 modifiers (`.live`, `.blur`, `.debounce.300ms`) produce DOM attribute names like `wire:model.live`, not `wire:model`. The Alpine component searches for any attribute starting with `wire:model` to extract the property name (see §4.5). This ensures modifiers work correctly.

### 3.4 "No Icon Sets Installed" Empty State

If `IconManager::getAllIcons()` returns an empty array, the Blade view renders a static message instead of the interactive picker:

```blade
@if(empty($icons))
    <div class="ip-empty">
        No icon sets found. Install blade-ui-kit/blade-heroicons:
        <code>composer require blade-ui-kit/blade-heroicons</code>
    </div>
@else
    {{-- normal picker markup --}}
@endif
```

Blade short-circuits: Alpine and the JS bundle are never loaded.

---

## 4. Alpine.js Component

`resources/js/components/icon-picker.js`

### 4.1 State

```js
export function iconPicker(config) {
    return {
        // --- static config ---
        allIcons: config.icons,  // [{id, label, svg}, ...] — SVGs are strings, not parsed DOM
        placeholder: config.placeholder,
        disabled: config.disabled,
        chunkSize: config.chunkSize,

        // --- reactive state ---
        isOpen: false,
        selectedId: config.currentValue || '',
        query: '',
        chunkCount: 1,
        activeIconIndex: -1,

        // --- computed (via getters) ---
        get selectedIcon() { /* §4.5 */ },
        get filteredIcons() { /* §4.3 */ },
        get visibleIcons() { /* §4.4 */ },
        get hasMore() { /* §4.4 */ },

        // --- methods ---
        toggle() { /* §4.5 */ },
        close() { /* §4.5 */ },
        select(icon) { /* §4.5 */ },
        clear() { /* §4.5 */ },
        onSearch() { /* §4.5 */ },
        loadNextChunk() { /* §4.4 */ },
        onKeydown(event) { /* §4.6 */ },
    };
}
```

**No `open` / `open()` collision.** The boolean state is named `isOpen`. There is no `open()` method — `toggle()` handles opening, and there's no standalone open action needed beyond `toggle()` (clicking the field) and the `x-trap` directive setting focus automatically. The PRD does not require a programmatic `open()` — the user opens the dropdown by clicking the trigger.

### 4.2 SVG Rendering Strategy

Each icon in the JSON payload carries THREE fields: `{id, label, svg}`. The `svg` field contains the full inline SVG markup string rendered by blade-icons at page-load time.

- **Trigger preview:** `selectedIcon.svg` is bound via `x-html` — shows the selected icon's SVG instantly.
- **Grid icons:** `icon.svg` from the chunk is bound via `x-html` — each visible button gets its SVG.

SVGs are raw strings in JSON, not parsed DOM elements. They only become DOM elements when Alpine renders them via `x-html` — which only happens for icons in the currently visible chunk. This means lazy rendering via `x-intersect` truly defers DOM creation.

**Payload size trade-off (acknowledged deviation from PRD criterion #3):**

| | Uncompressed | Gzipped |
|---|---|---|
| IDs + labels (~876 icons) | ~50 KB | ~8 KB |
| SVG strings (~876 icons) | ~200 KB | ~18 KB |
| **Total** | **~250 KB** | **~26 KB** |

The PRD's success criterion #3 targets ≤15KB gzipped for the "icon list JSON." Adding SVG content pushes the total to ~26KB gzipped. This is a deliberate deviation. The alternatives considered and rejected:

- **Hidden `<template>` bank in the DOM:** SVG markup is parsed at page load (defeating lazy render). DOM parse time increases. Still gzipped ~20KB on top of the JSON payload.
- **AJAX SVG fetch on scroll:** Violates the zero-round-trip constraint. Adds complexity.
- **Embed in JSON payload (chosen):** Gzipped SVG strings are compact due to repetition. Truly lazy DOM creation. Zero additional requests. The 26KB total is acceptable for a one-time page load on modern connections.

### 4.3 Search: Substring Token Matching

```js
get filteredIcons() {
    if (!this.query.trim()) return this.allIcons;

    const tokens = this.query.trim().toLowerCase().split(/\s+/);

    return this.allIcons.filter(icon => {
        const haystack = (icon.id + ' ' + icon.label).toLowerCase();
        return tokens.every(token => haystack.includes(token));
    });
}
```

**Behavior matches PRD §5.4:** Split query into tokens by whitespace. An icon matches if EVERY token is a substring of the combined `id + label` (case-insensitive). Example: "ar le" matches `heroicon-o-arrow-left`.

**No debounce needed.** The filter is synchronous in-memory over ~1,000 items. It completes in well under 1ms.

### 4.4 Lazy DOM Rendering

```js
get visibleIcons() {
    return this.filteredIcons.slice(0, this.chunkCount * this.chunkSize);
}

get hasMore() {
    return this.visibleIcons.length < this.filteredIcons.length;
}

loadNextChunk() {
    this.chunkCount++;
}
```

`x-intersect` fires when the sentinel `<div>` enters the viewport. `loadNextChunk()` increments `chunkCount`, which expands `visibleIcons`. Alpine's `x-for` reacts, appending the next 30 icon buttons to the DOM. Each button's SVG string is now parsed for the first time — no SVG DOM elements existed before this chunk.

**Resetting on search:** `onSearch()` resets `chunkCount` to 1, so filtered results restart from chunk 1.

### 4.5 Core Methods

```js
toggle() {
    if (this.disabled) return;
    this.isOpen = !this.isOpen;
    if (this.isOpen) {
        this.chunkCount = 1;
        this.activeIconIndex = -1;
        this.$nextTick(() => {
            this.$refs.searchInput?.focus();
        });
    }
}

close() {
    this.isOpen = false;
    this.query = '';
    this.chunkCount = 1;
    this.activeIconIndex = -1;
}

select(icon) {
    this.selectedId = icon.id;
    // Optimistic close per PRD §5.5:
    this.close();
    // Livewire sync in background:
    const modelName = this.resolveWireModel();
    if (this.$wire && modelName) {
        this.$wire.set(modelName, icon.id);
    }
}

clear() {
    this.selectedId = '';
    const modelName = this.resolveWireModel();
    if (this.$wire && modelName) {
        this.$wire.set(modelName, '');
    }
}

resolveWireModel() {
    // Handles wire:model and wire:model.live / wire:model.blur / wire:model.debounce.300ms
    const attr = Array.from(this.$el.attributes).find(a =>
        a.name.startsWith('wire:model')
    );
    return attr?.value ?? null;
}

get selectedIcon() {
    return this.allIcons.find(i => i.id === this.selectedId) ?? null;
}

onSearch() {
    this.chunkCount = 1;
    this.activeIconIndex = -1;
}
```

**Null guard on `$wire.set()`:** If the component is placed without `wire:model`, or `$wire` is unavailable (not inside a Livewire component), the `.set()` call is skipped gracefully with no JS exception.

**wire:model modifier support:** `resolveWireModel()` searches for any attribute starting with `wire:model` — handles `.live`, `.blur`, `.debounce.*` modifiers correctly.

### 4.6 Keyboard Navigation

Simple, linear keyboard navigation per PRD P0 requirement. No fragile DOM measurement.

| Key | Action |
|-----|--------|
| **Tab** | Moves focus naturally (browser behavior; constrained by `x-trap`) |
| **Arrow Down / Arrow Right** | Move highlight to next icon in `visibleIcons` (wrap at end) |
| **Arrow Up / Arrow Left** | Move highlight to previous icon in `visibleIcons` (wrap at start) |
| **Enter** | Select highlighted icon |
| **Escape** | Close dropdown, return focus to trigger |

Implementation:

```js
onKeydown(event) {
    if (!this.isOpen) return;

    const total = this.visibleIcons.length;
    if (total === 0) return;

    switch (event.key) {
        case 'ArrowDown':
        case 'ArrowRight':
            event.preventDefault();
            this.activeIconIndex = (this.activeIconIndex + 1) % total;
            this.scrollActiveIntoView();
            break;
        case 'ArrowUp':
        case 'ArrowLeft':
            event.preventDefault();
            this.activeIconIndex = (this.activeIconIndex - 1 + total) % total;
            this.scrollActiveIntoView();
            break;
        case 'Enter':
            event.preventDefault();
            if (this.activeIconIndex >= 0) {
                this.select(this.visibleIcons[this.activeIconIndex]);
            }
            break;
        case 'Escape':
            this.close();
            this.$refs.trigger?.focus();
            break;
    }
}
```

**No `getItemsPerRow()`.** The PRD does not specify grid-aware arrow navigation (up/down as row jumps). Linear prev/next meets the P0 accessibility requirement. Grid-aware behavior can be added as a polish item in a later version if users request it.

**Scroll-into-view:** `scrollActiveIntoView()` calls `scrollIntoView({block: 'nearest'})` on the DOM element at `activeIconIndex`, ensuring keyboard users can always see the highlighted icon.

### 4.7 Focus Trap

`x-trap="isOpen"` on the dropdown panel keeps Tab/Shift+Tab cycling within the dropdown while open. This directive is provided by `@alpinejs/focus`, which is bundled into the package's distributed JS via esbuild. The consumer does not need to install it separately — it ships with the package.

---

## 5. CSS

`resources/css/icon-picker.css`

~150 lines of standalone CSS per PRD §5.5. No Tailwind dependency, no framework. Namespaced under `.ip-*` prefix to avoid collisions.

### 5.1 CSS Architecture

```
.ip-root                Container — position: relative (anchors dropdown)
.ip-trigger-wrapper      Flex wrapper containing trigger + clear button (position: relative)
.ip-trigger             Clickable field — border, padding, cursor, flex layout
  .ip-trigger-selected  Flex row: SVG icon + label text
  .ip-chevron           Dropdown arrow indicator
.ip-clear               × button — absolute positioned right within the trigger wrapper
.ip-dropdown            Absolute positioned panel below trigger — border, shadow, bg
.ip-search              Text input — full width, padding, border-bottom
.ip-grid                CSS Grid container — responsive columns
.ip-icon-btn            Grid item — center content, border-radius, hover/selected states
  .ip-icon-btn--selected  Primary color background, white text
  .ip-icon-btn--active    Keyboard focus ring (ring color outline)
.ip-icon-label          Text below SVG — small, truncated
.ip-empty               Centered message for no results / no icons
```

### 5.2 Responsive Grid

```css
.ip-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);  /* mobile */
    gap: 0.5rem;
    max-height: 300px;
    overflow-y: auto;
}

@media (min-width: 640px) {
    .ip-grid { grid-template-columns: repeat(6, 1fr); }
}

@media (min-width: 1024px) {
    .ip-grid { grid-template-columns: repeat(9, 1fr); }
}

@media (min-width: 1280px) {
    .ip-grid { grid-template-columns: repeat(12, 1fr); }
}
```

Matches PRD §3.3: 4 cols mobile → 6 sm → 9 lg → 12 xl.

### 5.3 Using CSS Custom Properties for Theming

The CSS uses custom properties for colors so consumers can override them:

```css
.ip-root {
    --ip-primary: #4f46e5;       /* indigo-600 */
    --ip-primary-light: #eef2ff; /* indigo-50 */
    --ip-border: #d1d5db;        /* gray-300 */
    --ip-text: #111827;          /* gray-900 */
    --ip-text-muted: #6b7280;    /* gray-500 */
    --ip-bg: #ffffff;
    --ip-radius: 0.5rem;
}
```

A developer using Tailwind can override these in their own CSS to match their theme.

### 5.4 SVG Sizing

Icons rendered inline as SVGs need sizing constraints:

```css
.ip-trigger svg { width: 1.25rem; height: 1.25rem; }
.ip-icon-btn svg { width: 1.5rem; height: 1.5rem; }
```

---

## 6. JS Build Pipeline

### 6.1 Source

```
resources/js/components/icon-picker.js
```

### 6.2 Runtime Dependencies

The Alpine component imports `@alpinejs/focus` (for `x-trap`). This is a runtime dependency bundled into the distributed JS.

```js
import { focus } from '@alpinejs/focus';
```

### 6.3 Build Script

`bin/build.js` — esbuild configuration:

```js
const esbuild = require('esbuild');

esbuild.build({
    entryPoints: ['resources/js/components/icon-picker.js'],
    bundle: true,
    minify: true,
    outfile: 'resources/dist/icon-picker.js',
    format: 'iife',
    globalName: 'IconPicker',
    external: ['alpinejs'],  // alpinejs is provided by the consumer app
}).catch(() => process.exit(1));
```

**IIFE format** with `globalName: 'IconPicker'` — the consumer includes `<script src="/vendor/icon-picker/icon-picker.js">` after Alpine, and calls `Alpine.plugin(IconPicker.focus); Alpine.data('iconPicker', IconPicker.iconPicker)`.

**Alpine is external** — we don't bundle it. The consumer already has it.

**`@alpinejs/focus` is bundled** — it's a tiny plugin (~1KB min+gzip) and the consumer shouldn't need to install it separately.

### 6.4 Distribution

`resources/dist/` is gitignored. The consumer publishes assets via:

```bash
php artisan vendor:publish --tag=icon-picker-assets
```

This copies `resources/dist/` to `public/vendor/icon-picker/`.

---

## 7. composer.json

```json
{
    "name": "nickperkins/blade-icon-picker",
    "description": "Standalone icon picker Blade component for Livewire forms",
    "type": "library",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "IconPicker\\": "src/"
        }
    },
    "require": {
        "php": "^8.2",
        "blade-ui-kit/blade-icons": "^1.0",
        "livewire/livewire": "^3.0",
        "illuminate/support": "^10.0 || ^11.0 || ^12.0"
    },
    "suggest": {
        "blade-ui-kit/blade-heroicons": "Provides Heroicons for the icon picker"
    },
    "extra": {
        "laravel": {
            "providers": [
                "IconPicker\\IconPickerServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable",
    "require-dev": {
        "orchestra/testbench": "^8.0 || ^9.0 || ^10.0",
        "pestphp/pest": "^2.0 || ^3.0",
        "blade-ui-kit/blade-heroicons": "^2.0"
    }
}
```

**blade-heroicons** is `suggest`, not `require` — per PRD §9.1. It's in `require-dev` so tests can exercise the real integration.

---

## 8. Testing Strategy

### 8.1 Unit Tests: `IconManagerTest`

Tests run under Orchestra Testbench with blade-icons and blade-heroicons installed as dev dependencies.

| Test | What it verifies |
|------|-----------------|
| `getAllIcons returns all heroicons` | With blade-heroicons installed, `getAllIcons()` returns ~876 items |
| `getAllIcons includes svg markup in each icon` | Each Icon has a non-empty `svg` string containing `<svg` |
| `getAllIcons derives correct labels for outline style` | Icon `heroicon-o-home` has label `O Home` |
| `getAllIcons derives correct labels for solid style` | Icon `heroicon-s-home` has label `S Home` |
| `getAllIcons derives correct labels for mini style` | Icon `heroicon-m-home` has label `M Home` |
| `getAllIcons handles hypenated names` | Icon `heroicon-o-arrow-left` has label `O Arrow Left` |
| `getAllIcons uses dash separator in IDs` | Icon ID is `heroicon-o-home`, not `heroicon-o:home` |
| `getAllIcons returns empty array when no packs installed` | No blade-icons packs registered → empty result |
| `renderSvg returns valid SVG markup` | Calling `renderSvg('heroicon-o-home')` returns an `<svg>` string |
| `renderSvg throws for unknown icon` | Calling `renderSvg('nonexistent')` throws an exception |

### 8.2 Feature Tests: `IconPickerComponentTest`

| Test | What it verifies |
|------|-----------------|
| `component renders with placeholder text` | HTML output contains `Select an icon` |
| `component renders custom placeholder` | Passing `placeholder="Choose"` → HTML contains `Choose` |
| `component includes icon list as JSON with SVGs` | Alpine `x-data` contains the serialized icon array with `svg` keys |
| `component renders disabled trigger when disabled` | Output has `disabled` attribute on trigger button |
| `component shows empty state when no icons` | HTML contains help message with composer require command |
| `component accepts wire:model attribute` | The root div has `wire:model` attribute |
| `component accepts value prop` | The `x-data` call includes `currentValue` matching the passed `$value` |

### 8.3 JavaScript Tests

Not in scope for v1. The Alpine component is small enough (~120 lines) to verify manually. If complexity grows, we can add Vitest or similar in a future iteration.

### 8.4 Key Testing Guidelines

- **No Full Laravel app needed.** Everything runs under Testbench.
- **No HTTP calls, no database.** Tests are pure unit/feature.
- **Don't mock blade-icons Factory** in integration tests. The real Factory with heroicons provides more confidence.
- **Test edge cases explicitly:** empty packs, missing icons, special characters in icon names.

---

## 9. Data Flow Diagrams

### 9.1 Page Load (Initial Render)

```
HTTP Request
     │
     ▼
IconPicker::render()
     │
     ├── $this->manager->getAllIcons()
     │       │
     │       ├── Factory::all()  →  [prefix => [...names]]
     │       ├── derive labels
     │       ├── renderSvg(id) per icon  →  inline SVG markup string
     │       └── return Icon[]  (each has {id, label, svg})
     │
     └── return view('icon-picker::components.icon-picker', [
             'icons'       => $this->icons(),     // array of {id, label, svg}
             'placeholder'  => $this->placeholder,
             'disabled'    => $this->disabled,
             'value'       => $this->value,       // null or string
             'chunkSize'   => self::CHUNK_SIZE,   // 30
         ]);
              │
              ▼
     Blade renders HTML:
       - If $icons is empty: static "no icon sets installed" message
       - Otherwise: full interactive markup
         - Root <div> with x-data="iconPicker({...})"
         - Trigger wrapper with trigger button + clear button (siblings)
         - Dropdown panel with search input, icon grid, sentinel
              │
              ▼
     HTML sent to browser
     Alpine initializes: allIcons has SVGs as strings
     No SVG DOM elements exist yet (only the trigger's empty state)
```

### 9.2 Icon Selection Flow

```
User clicks icon in grid
     │
     ▼
select(icon)
     │
     ├── selectedId = icon.id               // reactive state update
     ├── close()                            // clears query, sets isOpen=false
     │       ├── isOpen = false
     │       ├── query = ''
     │       ├── chunkCount = 1
     │       └── activeIconIndex = -1
     │
     └── $wire.set(modelName, icon.id)      // single network round-trip
              │
              ▼
     Livewire re-renders parent component
     $icon property updated to "heroicon-o-home"
     IconPicker Blade component receives :value="$icon"
              │
              ▼
     Alpine re-initializes with selectedId = "heroicon-o-home"
     selectedIcon getter finds matching entry → shows SVG + label
     Dropdown is closed (fresh state)
```

### 9.3 Search and Lazy Load Flow

```
User types "ar le"
     │
     ▼
onSearch()
     ├── chunkCount = 1
     └── activeIconIndex = -1

     (query is already "ar le" via x-model)

filteredIcons recomputes:
     tokens = ["ar", "le"]
     filter: EVERY token is substring of (id + label)
     matches: arrow-left, arrow-right, ...
     count: ~20-30 icons

visibleIcons recomputes:
     filteredIcons.slice(0, 1 * 30)  → first 30 matches

DOM updates:
     x-for renders 20-30 icon buttons
     Each icon's SVG string is parsed into DOM elements (first time)
     sentinel not visible → hasMore is false → sentinel hidden

User scrolls down within grid
     │
     ▼
Sentinel enters viewport → x-intersect fires
     │
     ▼
loadNextChunk()
     chunkCount = 2

visibleIcons recomputes:
     filteredIcons.slice(0, 2 * 30)  → 40-60 matches

DOM updates:
     x-for appends 20 more icon buttons with SVGs
```

---

## 10. Implementation Order

The work breaks down into 5 sequential phases. Each phase has a clear completion criterion.

### Phase 1: PHP Skeleton (tests first)
1. Set up testbench with Pest: `tests/Pest.php`, `tests/TestCase.php`
2. Write `IconTest` (value object unit test)
3. Implement `Icon` value object (with `svg` field)
4. Write `IconManagerTest` (with real blade-icons + heroicons)
5. Implement `IconManager` (label derivation + SVG rendering per icon)
6. Write `IconPickerComponentTest`
7. Implement `IconPicker` component class (with `$value` prop) + `IconPickerServiceProvider`
8. Update `composer.json`

**Completion criterion:** `vendor/bin/pest` passes all PHP tests. No views or JS exist yet.

### Phase 2: Blade View
1. Create `resources/views/components/icon-picker.blade.php`
2. Wire up `IconPicker::render()` to point at it
3. Render basic HTML scaffold: trigger wrapper (trigger + clear siblings), dropdown, grid, sentinel
4. Verify via `IconPickerComponentTest` assertions on HTML output
5. Handle empty state (no icon packs installed)
6. Seed Alpine `x-data` with icons array including `svg` field

**Completion criterion:** Tests pass. Viewing a test page shows the static HTML structure (no interactivity yet).

### Phase 3: Alpine.js — Core Interactivity
1. Create `resources/js/components/icon-picker.js` with `iconPicker()` function
2. Implement state: `isOpen`, `selectedId`, `query`, `chunkCount`, `activeIconIndex`
3. Implement `toggle()`, `close()`, `select()`, `clear()`
4. Implement `visibleIcons` with chunking — SVGs are strings, parsed on first render
5. Wire up `x-intersect` sentinel for lazy load
6. Implement search via `filteredIcons` (substring token matching, reset chunk counter)
7. Wire up `resolveWireModel()` for Livewire sync (handles modifiers)

**Completion criterion:** Full interactive flow works in a browser: click open → search → scroll → select → Livewire property updates.

### Phase 4: CSS + Polish
1. Create `resources/css/icon-picker.css`
2. Style trigger wrapper, trigger button, clear button (absolute positioned sibling), dropdown, grid, search, buttons, empty state
3. Responsive grid breakpoints (4 → 6 → 9 → 12 cols)
4. Selected/active/hover states for grid buttons
5. Transitions (dropdown open/close)

**Completion criterion:** Component looks polished and matches the PRD layout spec. Responsive grid works at all breakpoints.

### Phase 5: Build Pipeline + Keyboard Nav
1. Create `bin/build.js` esbuild config (bundles `@alpinejs/focus`, externals `alpinejs`)
2. Set up `package.json` with build script
3. Verify minified output in `resources/dist/`
4. Add `vendor:publish` tag for assets
5. Implement keyboard navigation (arrow keys, enter, escape, scroll-into-view)
6. Verify `x-trap` works via bundled `@alpinejs/focus`
7. Final integration test: full end-to-end flow in a test Livewire form

**Completion criterion:** `npm run build` produces a minified bundle. Keyboard nav works. Published assets load correctly in a consumer app.

---

## 11. Open Design Decisions

| Decision | Status | Notes |
|----------|--------|-------|
| SVG rendering: embedded in JSON vs. template bank vs. AJAX | **Resolved: embedded in JSON** | Strings in JSON, parsed lazily by visible chunks only. Truly lazy DOM creation. Gzipped SVGs are compact. Exceeds 15KB criterion (see below). |
| Fuse.js dependency | **Resolved: not needed** | Substring token matching handles real-world searches. Add later if users complain. |
| spatie/laravel-package-tools | **Resolved: not used** | Service provider is ~30 lines. No benefit from the dependency. |
| CSS framework dependency | **Resolved: standalone CSS** | Custom properties for theming. No Tailwind requirement. |
| Set filtering in v1 | **Resolved: out of scope** | Variant labels distinguish icons. Filtering adds UI complexity. |
| wire:model on root div vs. hidden input | **Resolved: on root div** | Standard Livewire pattern. Alpine reads `wire:model` attribute to know which property to set. |
| Configurable chunk size prop | **Resolved: deferred to P2** | Hardcoded as private constant `CHUNK_SIZE = 30` in the component class. |
| Success criterion #3 (≤15KB gzipped) | **Resolved: deliberate deviation** | Embedding SVGs in JSON pushes to ~26KB. Trade-off: truly lazy rendering, zero extra requests, simpler code. Criterion should be renegotiated or split into "IDs+labels ≤ 8KB" and "SVGs ≤ 20KB." |

---

## 12. Success Criteria Mapping

| PRD Criterion | Design Coverage |
|---------------|----------------|
| 1. Works with zero config beyond blade-heroicons installed | `IconManager` auto-discovers via blade-icons Factory. Composer `suggest` for heroicons. |
| 2. Search <100ms with 1,000+ icons | Substring token matching over an in-memory array takes <1ms. |
| 3. Initial payload ≤15KB gzipped | **Deliberate deviation.** IDs + labels: ~8KB gzipped. With SVGs embedded: ~26KB gzipped total. See §4.2 and §11 for trade-off rationale. |
| 4. Zero server requests during browsing/search | All search is in-memory. No AJAX. Only `$wire.set()` on selection. |
| 5. Zero Filament imports | No Filament packages in composer.json. No `use Filament\...` anywhere. |
