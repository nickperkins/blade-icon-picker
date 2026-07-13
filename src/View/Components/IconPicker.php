<?php

namespace IconPicker\View\Components;

use IconPicker\Icons\IconManager;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IconPicker extends Component
{
    public function __construct(
        private IconManager $manager,
        public string $placeholder = 'Select an icon',
        public bool $disabled = false,
        public ?string $value = null,
    ) {}

    public function render(): View
    {
        // Resolve the selected icon's SVG + label on initial render so the
        // trigger button displays it before any AJAX fetch happens.
        $selectedIcon = null;
        if ($this->value) {
            $icon = $this->manager->findIcon($this->value);
            if ($icon) {
                $selectedIcon = $icon->toArray();
            }
        }

        return view('icon-picker::components.icon-picker', [
            'placeholder'    => $this->placeholder,
            'disabled'       => $this->disabled,
            'value'          => $this->value,
            'endpoint'       => route('icon-picker.icons'),
            'setsEndpoint'   => route('icon-picker.sets'),
            'selectedIcon'   => $selectedIcon,
        ]);
    }
}
