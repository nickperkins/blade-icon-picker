<?php

namespace IconPicker\View\Components;

use IconPicker\Icons\IconManager;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IconPicker extends Component
{
    private const CHUNK_SIZE = 30;

    public function __construct(
        private IconManager $manager,
        public string $placeholder = 'Select an icon',
        public bool $disabled = false,
        public ?string $value = null,
    ) {}

    public function render(): View
    {
        $allIcons = $this->manager->getAllIcons();
        $iconArrays = array_map(fn ($icon) => $icon->toArray(), $allIcons);

        return view('icon-picker::components.icon-picker', [
            'iconList'    => $iconArrays,
            'placeholder' => $this->placeholder,
            'disabled'    => $this->disabled,
            'value'       => $this->value,
            'chunkSize'   => self::CHUNK_SIZE,
        ]);
    }
}
