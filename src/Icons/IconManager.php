<?php

namespace IconPicker\Icons;

use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use RuntimeException;

class IconManager
{
    private ?array $cachedIcons = null;
    private ?array $cachedSets = null;

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
                    $label = $this->buildLabel($iconName, $setName);
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

    /**
     * Returns metadata about all registered icon sets for filter dropdowns.
     *
     * @return array<int, array{set: string, prefix: string, label: string, count: int}>
     */
    public function getSets(): array
    {
        if ($this->cachedSets !== null) {
            return $this->cachedSets;
        }

        // Count icons per prefix from the cached icon list
        $counts = [];
        foreach ($this->getAllIcons() as $icon) {
            $prefix = explode('-', $icon->id, 2)[0];
            $counts[$prefix] = ($counts[$prefix] ?? 0) + 1;
        }

        $result = [];
        foreach ($this->factory->all() as $setName => $config) {
            $prefix = $config['prefix'];
            $result[] = [
                'set'    => $setName,
                'prefix' => $prefix,
                'label'  => $this->setLabel($setName),
                'count'  => $counts[$prefix] ?? 0,
            ];
        }

        return $this->cachedSets = $result;
    }

    /**
     * Paginated, filtered icon retrieval for AJAX endpoint.
     *
     * @return array{icons: Icon[], total: int, hasMore: bool}
     */
    public function getIcons(
        ?string $setPrefix = null,
        ?string $variant = null,
        ?string $query = null,
        int $page = 1,
        int $perPage = 60,
    ): array {
        $all = $this->getAllIcons();

        $filtered = array_values(array_filter($all, function (Icon $icon) use ($setPrefix, $variant, $query) {
            // Filter by set prefix (e.g. 'heroicon', 'gmdi')
            if ($setPrefix !== null && !str_starts_with($icon->id, $setPrefix . '-')) {
                return false;
            }

            // Filter by variant character
            if ($variant !== null) {
                $iconVariant = $this->detectVariant($icon->id);
                if ($iconVariant !== $variant) {
                    return false;
                }
            }

            // Filter by search query (case-insensitive substring on id + label)
            if ($query !== null && $query !== '') {
                $haystack = strtolower($icon->id . ' ' . $icon->label);
                if (!str_contains($haystack, strtolower($query))) {
                    return false;
                }
            }

            return true;
        }));

        $total = count($filtered);
        $offset = ($page - 1) * $perPage;
        $pageIcons = array_slice($filtered, $offset, $perPage);

        return [
            'icons'   => $pageIcons,
            'total'   => $total,
            'hasMore' => $offset + $perPage < $total,
        ];
    }

    /**
     * Returns a single icon by ID, or null if not found.
     * Used to render the selected icon on initial page load without
     * fetching the full icon list.
     */
    public function findIcon(string $id): ?Icon
    {
        foreach ($this->getAllIcons() as $icon) {
            if ($icon->id === $id) {
                return $icon;
            }
        }
        return null;
    }

    public function renderSvg(string $id): string
    {
        try {
            return $this->factory->svg($id)->toHtml();
        } catch (\Throwable $e) {
            throw new RuntimeException("Icon not found: {$id}", 0, $e);
        }
    }

    /**
     * Sets that use suffix-style variants (e.g. gmdi-home-o → outline).
     * All other sets are assumed to use prefix-style (e.g. heroicon-o-home)
     * or no variants at all.
     */
    private const SUFFIX_VARIANT_SETS = [
        'google-material-design-icons',
    ];

    /**
     * Builds a human-readable label from an icon name.
     * Strips variant prefixes (Heroicons: "o-home" → "Home") or suffixes
     * (Google Material: "home-o" → "Home") depending on the set's variant style.
     */
    private function buildLabel(string $iconName, string $setName): string
    {
        // Check if this set uses prefix-style variants (Heroicons: "o-home")
        if (preg_match('/^([a-z])-(.+)$/', $iconName, $m)) {
            return $this->titleCase($m[2]);
        }

        // Check if this set uses suffix-style variants (Google Material: "home-o")
        if (in_array($setName, self::SUFFIX_VARIANT_SETS, true)
            && preg_match('/^(.+)-([a-z])$/', $iconName, $m)
        ) {
            return $this->titleCase($m[1]);
        }

        // Flat icon names with no variant (e.g. "home", "address-book")
        return $this->titleCase($iconName);
    }

    /**
     * Detects the variant character from an icon ID.
     * Heroicons style: prefix-VARIANT-name (e.g. heroicon-o-home → 'o')
     * Google Material style: prefix-name-VARIANT (e.g. gmdi-home-o → 'o')
     */
    private function detectVariant(string $id): ?string
    {
        // Strip the set prefix to get the icon name portion
        $parts = explode('-', $id, 2);
        if (count($parts) < 2) {
            return null;
        }
        $iconName = $parts[1];
        $segments = explode('-', $iconName);

        // Heroicons-style: first segment is a single char (e.g. "o-home" → 'o')
        if (count($segments) > 1 && preg_match('/^[a-z]$/', $segments[0])) {
            return $segments[0];
        }

        // Google Material-style: last segment is a single char (e.g. "home-o" → 'o')
        $last = $segments[count($segments) - 1];
        if (count($segments) > 1 && preg_match('/^[a-z]$/', $last)) {
            return $last;
        }

        return null;
    }

    /**
     * Derives a human-readable label from a set name.
     * e.g. 'heroicons' → 'Heroicons', 'google-material-design-icons' → 'Google Material Design Icons'
     */
    private function setLabel(string $setName): string
    {
        $known = [
            'heroicons'                    => 'Heroicons',
            'google-material-design-icons' => 'Google Material Design Icons',
        ];

        if (isset($known[$setName])) {
            return $known[$setName];
        }

        // Fallback: title-case the set name, stripping "-icons" suffix
        $clean = preg_replace('/-icons$/', '', $setName);
        return ucwords(str_replace(['-', '_'], ' ', $clean));
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
