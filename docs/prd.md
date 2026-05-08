# Blade Icon Picker — Product Requirements Document

**Date:** 2026-05-08

---

## 1. Problem Statement

Laravel developers building Livewire forms often need to let users pick an icon — for menu items, categories, feature flags, or CMS content types. Today they have three bad options:

1. **A plain text input.** User types `heroicon-o-home` from memory. Error-prone and hostile.
2. **A `<select>` with a hand-curated list.** Fragile, incomplete, requires manual maintenance.
3. **A Filament-specific icon picker** (e.g., `guava/filament-icon-picker`). Tightly coupled to Filament internals — `generate_icon_html()`, `FilamentAsset`, `$wire.callSchemaComponentMethod()`. Can't use it in a plain Livewire form outside Filament.

There is no standalone, Livewire-compatible icon picker component for Laravel.

## 2. Target Users

**Primary:** Laravel developers building admin panels, CMS backends, or internal tools with Livewire — who are NOT using Filament (or are using Filament but want a picker outside the admin panel, like in a user-facing settings form).

**Secondary:** Package developers who want to embed an icon picker in their own packages without pulling in Filament as a dependency.

## 3. User Experience (v1)

### 3.1 Basic Usage

```blade
<livewire:my-form>
    <x-icon-picker::icon-picker wire:model="icon" />
</livewire:my-form>
```

The developer drops the component into any Livewire form. `wire:model` binds the selected icon name to a Livewire property. That's it.

### 3.2 Visual Behavior

| State | What the user sees |
|-------|--------------------|
| Empty (no icon selected) | A clickable field with placeholder text: "Select an icon" |
| Selected | The field shows the rendered SVG icon + its human-readable name |
| Clicked / focused | A dropdown panel opens below the field containing a search box and icon grid |
| Typing in search | Icons filter in real-time via substring token matching (no server round-trip) |
| Scrolling in grid | More icons load incrementally (lazy rendering) |
| Clicking an icon | That icon is selected, the dropdown closes, the Livewire property updates |
| Clicking the clear (×) button | Selection is cleared, field returns to empty state |

### 3.3 Dropdown Panel Layout

```
┌─────────────────────────────────────────┐
│  [Search icons...]                       │
│                                          │
│  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐   │
│  │home│ │user│ │gear│ │bell│ │star│   │
│  └────┘ └────┘ └────┘ └────┘ └────┘   │
│  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐   │
│  │mail│ │chat│ │lock│ │flag│ │book│   │
│  └────┘ └────┘ └────┘ └────┘ └────┘   │
│  ┌────┐ ┌────┐                          │
│  │cake│ │moon│  ← scrolls for more      │
│  └────┘ └────┘                          │
└─────────────────────────────────────────┘
```

- Search input with substring token matching (no server round-trip)
- Responsive grid: 4 cols mobile → 6 cols sm → 9 cols lg → 12 cols xl
- Icons render as inline SVGs with label text below
- Selected icon is visually highlighted (primary color background)

## 4. Feature Set (v1)

### P0 — Must Have
- Blade component usable in any Livewire form via `wire:model`
- Renders selected icon preview (SVG + name) in closed state
- Click-to-open dropdown panel
- Client-side substring token matching search across all available icons
- Lazy rendering: icons load incrementally as user scrolls (`x-intersect`, chunk size 30)
- Clear button (visible only when icon is selected)
- Keyboard accessibility: tab, arrow keys, enter, escape navigation
- Works with `blade-ui-kit/blade-heroicons` out of the box

### P1 — Should Have
- Custom placeholder text (default: "Select an icon")
- Disabled state (blocks user interaction; programmatic `wire:model` updates still work)
- Inline mode (panel always visible, not a dropdown)

### P2 — Nice to Have
- Tooltips on icon hover showing full icon name
- Custom icon upload
- Set filtering (e.g., filter by Outline vs Solid vs Mini)
- Configurable chunk size

### Out of Scope (v1)
- Icon color customization in the picker (user's CSS handles this)
- Custom icon packs that aren't blade-icons compatible
- Admin panel CRUD for managing icons
- Filament integration (it's intentionally framework-agnostic)
- Fuse.js or other fuzzy search library (substring token matching is sufficient)
- Config file (`config/icon-picker.php`) — added when there's a real config surface
- Set filtering (all icons from all packs shown together; variant label distinguishes them)
- Validation error display inside the component (use standard `@error` directive)

## 5. Technical Approach

### 5.1 Icon Resolution Strategy

The package uses `blade-ui-kit/blade-icons` as its icon backend. This means:

- **Any blade-icons pack works automatically.** Install `blade-ui-kit/blade-heroicons` and the picker sees all Heroicons. Install `blade-ui-kit/blade-fontawesome` and those appear too. No changes to the picker.
- **SVG rendering uses blade-icons' built-in renderer**, not Filament's `generate_icon_html()`. Icons render inline via `@svg` directive.
- **Labels are computed server-side** and embedded in the page payload. Format: `O Home`, `S Home`, `M Home` (style prefix + title-cased name).

### 5.2 Architecture Diagram

```
┌──────────────────────────────────────────────┐
│  Blade Component                              │
│  <x-icon-picker::icon-picker wire:model="x">  │
│                                               │
│  ┌─────────────────┐   ┌───────────────────┐ │
│  │ IconPicker.php   │   │ icon-picker.js    │ │
│  │ (View Component) │◄──│ (Alpine.js)       │ │
│  │                  │   │                   │ │
│  │ - wire:model     │   │ - substring       │ │
│  │ - exposes icons  │   │   search          │ │
│  │   as JSON        │   │ - lazy rendering  │ │
│  │ - renders SVG    │   │ - dropdown state  │ │
│  │                  │   │ - keyboard nav    │ │
│  └────────┬─────────┘   └───────────────────┘ │
│           │                                    │
│  ┌────────▼─────────┐                         │
│  │ IconManager.php   │                         │
│  │                    │                         │
│  │ - getAllIcons()    │                         │
│  │ - renderSvg($id)   │                         │
│  └────────┬──────────┘                         │
│           │                                    │
│  ┌────────▼──────────┐                        │
│  │ blade-icons        │                        │
│  │ Factory            │                        │
│  │ (all registered    │                        │
│  │  icons)            │                        │
│  └───────────────────┘                         │
└──────────────────────────────────────────────┘
```

### 5.3 Data Flow on Page Load

1. Blade renders the component with all available icons serialized as JSON in the Alpine x-data (`[{id, label}, ...]`)
2. Alpine initializes: reads icon list, sets initial selection if provided
3. User opens dropdown: sees first chunk of 30 icons rendered
4. User scrolls: sentinel element triggers `x-intersect` → next chunk of 30 appended
5. User searches: substring token matching filters in-memory against label + icon ID, results replace visible grid, chunk counter resets

No AJAX calls during browsing or searching. The icon list is a one-time payload. On icon selection, Alpine calls `$wire.set()` to sync back to Livewire (a single network round-trip).

### 5.4 Emptiness & Edge Cases

**No icon packs installed:** The component renders a helpful message: "No icon sets found. Install blade-ui-kit/blade-heroicons: `composer require blade-ui-kit/blade-heroicons`".

**Icon list size:** Heroicons v2 has ~292 icons × 3 styles = ~876 icons. The JSON payload for IDs + labels is ~50KB uncompressed, ~8KB gzipped. Acceptable for a one-time page load. For larger packs (FontAwesome 2,000+), a future optimization could paginate the initial load.

**Search matching behavior:** The query is split into tokens. An icon matches if EVERY token appears as a substring of the icon's label OR raw ID (case-insensitive). E.g., typing "ar le" matches `heroicon-o-arrow-left` ("O Arrow Left").

### 5.5 Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| Substring token matching (no Fuse.js) | Simple, zero-dependency, handles 90%+ of real-world searches; add Fuse.js later if needed |
| Full icon list in page payload | One-time cost is small (8KB gzipped); eliminates AJAX complexity |
| Lazy DOM rendering via x-intersect (chunk size 30) | Rendering 800+ SVGs upfront kills performance; chunk-append is simple and sufficient for v1 |
| blade-icons as backend | Already a standard Laravel dependency; supports any icon pack; users already have it |
| No Filament dependency | The whole point of this package |
| esbuild for JS bundling | Minification and future-proofing; no heavy webpack/vite pipeline |
| Standalone CSS (no Tailwind dependency) | Package must work without Tailwind; ~150 lines of self-contained CSS |
| No set filtering in v1 | Simpler UI; variant labels ("O Home" vs "S Home") differentiate icons |
| Optimistic close on selection | Dropdown closes immediately; `$wire.set()` fires in background for instant UX |
| Constructor injection for IconManager | Standard Laravel pattern; explicit dependencies; testable |

## 6. Integration Examples

### 6.1 Basic Livewire Form

```php
// App\Livewire\CreateMenu.php
class CreateMenu extends Component
{
    public string $icon = '';

    public function rules()
    {
        return ['icon' => ['required', 'string']];
    }

    public function render()
    {
        return view('livewire.create-menu');
    }
}
```

```blade
{{-- livewire/create-menu.blade.php --}}
<form wire:submit="save">
    <x-icon-picker::icon-picker
        wire:model="icon"
        placeholder="Choose a menu icon"
    />

    @error('icon')
        <p class="text-red-500">{{ $message }}</p>
    @enderror

    <button type="submit">Save</button>
</form>
```

### 6.2 Custom Placeholder

```blade
<x-icon-picker::icon-picker
    wire:model="icon"
    placeholder="Choose a menu icon"
/>
```

### 6.3 Disabled State

```blade
<x-icon-picker::icon-picker
    wire:model="icon"
    disabled
/>
```

## 7. Package Structure

```
blade-icon-picker/
├── AGENTS.md
├── README.md
├── composer.json                      ← requires blade-icons ^1.0, livewire ^3.0
├── package.json                       ← esbuild only (dev dependency)
├── bin/
│   └── build.js                       ← esbuild config (minifies JS + CSS)
├── resources/
│   ├── css/
│   │   └── icon-picker.css            ← standalone styles (~150 lines, no framework)
│   ├── js/
│   │   └── components/
│   │       └── icon-picker.js         ← Alpine.js component (substring search, lazy load, keyboard nav)
│   ├── views/
│   │   └── components/
│   │       └── icon-picker.blade.php  ← main Blade component + inline icon grid
│   └── dist/                          ← bundled JS + CSS output (gitignored)
├── src/
│   ├── IconPickerServiceProvider.php  ← handwritten, no spatie dependency
│   ├── Icons/
│   │   ├── Icon.php                   ← value object: id, label, toArray()
│   │   └── IconManager.php            ← wraps blade-icons Factory; constructor-injected
│   └── View/
│       └── Components/
│           └── IconPicker.php         ← Blade component class
└── tests/
    ├── IconManagerTest.php            ← Pest + Testbench
    └── IconPickerComponentTest.php
```

## 8. Success Criteria

1. **A developer can install the package, add the component to a Livewire form, and pick an icon — with no additional configuration beyond having blade-heroicons installed.**
2. **Search filters results in <100ms with 1,000+ icons indexed.**
3. **Initial page load overhead (icon list JSON) is ≤15KB gzipped.**
4. **Zero server requests during icon browsing or searching.**
5. **Package has zero Filament imports.**

---

## 9. Open Questions

All open questions resolved:

1. **blade-heroicons dependency:** `suggest` in composer.json. Component handles empty state gracefully with a helpful message.
2. **SVG rendering:** Inline via blade-icons `@svg` directive.
3. **Label computation:** Server-side, computed once during boot. Format: `O Home`, `S Home`, `M Home`.
