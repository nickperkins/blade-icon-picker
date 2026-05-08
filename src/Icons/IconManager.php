<?php

namespace IconPicker\Icons;

use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use RuntimeException;

class IconManager
{
    private ?array $cachedIcons = null;

    public function __construct(
        private Factory $factory,
        private IconsManifest $manifest,
    ) {}

    /** @return Icon[] */
    public function getAllIcons(): array
    {
        if ($this->cachedIcons !== null) {
            return $this->cachedIcons;
        }

        $icons = [];
        $sets = $this->factory->all();
        $manifest = $this->manifest->getManifest($sets);

        foreach ($manifest as $setName => $pathIcons) {
            $prefix = $sets[$setName]['prefix'];

            foreach ($pathIcons as $path => $iconNames) {
                foreach ($iconNames as $iconName) {
                    $label = $this->buildLabel($iconName);
                    $id = $prefix . '-' . $iconName;

                    try {
                        $svg = $this->renderSvg($id);
                    } catch (\RuntimeException) {
                        continue; // skip corrupted icons, don't abort the whole set
                    }

                    $icons[] = new Icon($id, $label, $svg);
                }
            }
        }

        return $this->cachedIcons = $icons;
    }

    public function renderSvg(string $id): string
    {
        try {
            return $this->factory->svg($id)->toHtml();
        } catch (\Throwable $e) {
            throw new RuntimeException("Icon not found: {$id}", 0, $e);
        }
    }

    private function buildLabel(string $iconName): string
    {
        // Icon names with a single-char style prefix (e.g. "o-home", "s-home", "c-academic-cap")
        if (preg_match('/^([a-z])-(.+)$/', $iconName, $m)) {
            return strtoupper($m[1]) . ' ' . $this->titleCase($m[2]);
        }

        // Flat icon names with no prefix (e.g. "home", "address-book")
        return $this->titleCase($iconName);
    }

    private function titleCase(string $name): string
    {
        return str_replace(
            ['-', '_'],
            ' ',
            ucwords(str_replace(['-', '_'], ' ', $name))
        );
    }
}
