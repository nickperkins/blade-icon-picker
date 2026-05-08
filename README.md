# Blade Icon Picker

A standalone icon picker Blade component for Livewire forms. Works with any [blade-icons](https://github.com/blade-ui-kit/blade-icons) pack — Heroicons, Font Awesome, or custom sets.

```blade
<x-icon-picker::icon-picker wire:model="icon" :value="$icon" />
```

## Installation

```bash
composer require nickperkins/blade-icon-picker
composer require blade-ui-kit/blade-heroicons  # recommended default icon pack
```

Publish the assets:

```bash
php artisan vendor:publish --tag=icon-picker-assets
```

Include the JS and CSS in your layout:

```blade
<head>
    <link rel="stylesheet" href="{{ asset('vendor/icon-picker/icon-picker.css') }}">
    {{-- ... --}}
</head>
<body>
    {{-- ... --}}
    <script src="{{ asset('vendor/icon-picker/icon-picker.js') }}"></script>
    {{-- Must come AFTER Alpine.js --}}
</body>
```

In Livewire 3, the JS auto-registers with Alpine — no manual `Alpine.data()` call needed.

## Usage

### Basic

```blade
<x-icon-picker::icon-picker wire:model="icon" :value="$icon" />
```

**Important:** Pass both `wire:model` (for write-back) and `:value="$icon"` (for initial/redisplay). This is required because the component is a Blade component, not a Livewire component.

### Custom placeholder

```blade
<x-icon-picker::icon-picker
    wire:model="icon"
    :value="$icon"
    placeholder="Choose a menu icon"
/>
```

### Disabled

```blade
<x-icon-picker::icon-picker
    wire:model="icon"
    :value="$icon"
    disabled
/>
```

### Livewire form example

```php
use Livewire\Component;

class CreateMenu extends Component
{
    public string $icon = '';

    public function rules(): array
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
<form wire:submit="save">
    <x-icon-picker::icon-picker wire:model="icon" :value="$icon" />

    @error('icon')
        <p class="text-red-500">{{ $message }}</p>
    @enderror

    <button type="submit">Save</button>
</form>
```

## Features

- **Search:** Substring token matching — type `ar le` to find `Arrow Left`
- **Lazy rendering:** Icons render 30 at a time as you scroll
- **Keyboard navigation:** Arrow keys, Enter, Escape
- **Responsive grid:** 4 columns on mobile → 12 on desktop
- **Any blade-icons pack:** Heroicons, Font Awesome, custom — all auto-discovered
- **Standalone CSS:** No Tailwind dependency; override colors with CSS custom properties

## Theming

Override the CSS custom properties anywhere in your stylesheet:

```css
.icon-picker-root {
    --ip-primary: #dc2626;
    --ip-bg: #fef2f2;
}
```

Dark mode is supported automatically via `prefers-color-scheme`.

## No icon packs installed?

The component renders a helpful message telling the developer to install `blade-ui-kit/blade-heroicons`.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Livewire 3
- blade-icons 1.0+

## Acknowledgments

Inspired by [lukas-frey/filament-icon-picker](https://github.com/lukas-frey/filament-icon-picker), which demonstrated that an icon picker is a valuable UX pattern for Livewire forms.

## License

MIT
