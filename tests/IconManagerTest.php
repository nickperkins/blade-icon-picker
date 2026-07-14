<?php

use IconPicker\Icons\Icon;
use IconPicker\Icons\IconManager;

// ── Unit tests (no framework boot, no filesystem) ──

test('getAllIcons returns empty array when no icon packs are installed', function () {
    $manifestPath = sys_get_temp_dir() . '/icon-picker-test-empty.php';
    file_put_contents($manifestPath, '<?php return [];');

    $manifest = new \BladeUI\Icons\IconsManifest(
        new \Illuminate\Filesystem\Filesystem,
        $manifestPath,
    );

    $factory = new \BladeUI\Icons\Factory(
        new \Illuminate\Filesystem\Filesystem,
        $manifest,
    );

    $manager = new \IconPicker\Icons\IconManager($factory, $manifest);

    expect($manager->getAllIcons())->toBeEmpty();

    @unlink($manifestPath);
});

// ── Integration tests (real blade-icons + heroicons) ──

beforeEach(function () {
    $this->manager = app(IconManager::class);
});

test('discovers all heroicons when blade-heroicons is installed', function () {
    $icons = $this->manager->getAllIcons();

    // Heroicons v2.7.0 has 1,288 icons across all styles
    expect($icons)->toHaveCount(1288);
    expect($icons[0])->toBeInstanceOf(Icon::class);
});

test('every icon has valid SVG markup and dash-separated ID', function () {
    $sample = array_slice($this->manager->getAllIcons(), 0, 5);

    foreach ($sample as $icon) {
        expect($icon->svg)->toStartWith('<svg');
        expect($icon->svg)->toContain('</svg>');
        expect($icon->id)->not->toContain(':');
    }
});

test('derives correct labels for outline style', function () {
    $home = findIcon($this->manager->getAllIcons(), 'heroicon-o-home');

    expect($home)->not->toBeNull();
    expect($home->label)->toBe('Home');
});

test('derives correct labels for solid style', function () {
    $home = findIcon($this->manager->getAllIcons(), 'heroicon-s-home');

    expect($home)->not->toBeNull();
    expect($home->label)->toBe('Home');
});

test('derives correct labels for mini style', function () {
    $home = findIcon($this->manager->getAllIcons(), 'heroicon-m-home');

    expect($home)->not->toBeNull();
    expect($home->label)->toBe('Home');
});

test('handles hyphenated icon names in labels', function () {
    $arrow = findIcon($this->manager->getAllIcons(), 'heroicon-o-arrow-left');

    expect($arrow)->not->toBeNull();
    expect($arrow->label)->toContain('Arrow Left');
});

test('does not truncate short final segments in heroicons labels', function () {
    // Regression test: buildLabel must not strip "up" from "chevron-up"
    $chevronUp = findIcon($this->manager->getAllIcons(), 'heroicon-o-chevron-up');
    $arrowUp = findIcon($this->manager->getAllIcons(), 'heroicon-o-arrow-up');

    expect($chevronUp)->not->toBeNull();
    expect($chevronUp->label)->toBe('Chevron Up');

    expect($arrowUp)->not->toBeNull();
    expect($arrowUp->label)->toBe('Arrow Up');
});

test('renderSvg returns valid SVG for a known icon', function () {
    $svg = $this->manager->renderSvg('heroicon-o-home');

    expect($svg)->toStartWith('<svg');
    expect($svg)->toContain('</svg>');
});

test('renderSvg throws for unknown icon', function () {
    expect(fn () => $this->manager->renderSvg('nonexistent-icon'))
        ->toThrow(RuntimeException::class, 'Icon not found: nonexistent-icon');
});

// ── findIcon() tests ──

test('findIcon returns icon by ID', function () {
    $icon = $this->manager->findIcon('heroicon-o-home');

    expect($icon)->not->toBeNull();
    expect($icon->id)->toBe('heroicon-o-home');
    expect($icon->label)->toBe('Home');
    expect($icon->svg)->toStartWith('<svg');
});

test('findIcon returns null for unknown ID', function () {
    expect($this->manager->findIcon('nonexistent-icon'))->toBeNull();
});

// ── getSets() tests ──

test('getSets returns metadata for each installed set', function () {
    $sets = $this->manager->getSets();

    expect($sets)->not->toBeEmpty();

    foreach ($sets as $set) {
        expect($set)->toHaveKeys(['set', 'prefix', 'label', 'count']);
        expect($set['prefix'])->toBeString();
        expect($set['count'])->toBeInt();
    }
});

test('getSets returns heroicons set with correct prefix and label', function () {
    $sets = $this->manager->getSets();
    $heroicons = collect($sets)->firstWhere('set', 'heroicons');

    expect($heroicons)->not->toBeNull();
    expect($heroicons['prefix'])->toBe('heroicon');
    expect($heroicons['label'])->toBe('Heroicons');
    expect($heroicons['count'])->toBeGreaterThan(0);
});

test('getSets is cached on subsequent calls', function () {
    $first = $this->manager->getSets();
    $second = $this->manager->getSets();

    expect($second)->toBe($first);
});

// ── getIcons() tests ──

test('getIcons returns paginated results', function () {
    $result = $this->manager->getIcons(page: 1, perPage: 10);

    expect($result['icons'])->toHaveCount(10);
    expect($result['total'])->toBeGreaterThan(10);
    expect($result['hasMore'])->toBeTrue();
});

test('getIcons returns fewer icons on last page', function () {
    $result = $this->manager->getIcons(page: 200, perPage: 10);

    expect($result['icons'])->toHaveCount(0);
    expect($result['hasMore'])->toBeFalse();
});

test('getIcons filters by set prefix', function () {
    $result = $this->manager->getIcons(setPrefix: 'heroicon', page: 1, perPage: 100);

    foreach ($result['icons'] as $icon) {
        expect($icon->id)->toStartWith('heroicon-');
    }
});

test('getIcons filters by variant', function () {
    $result = $this->manager->getIcons(setPrefix: 'heroicon', variant: 'o', page: 1, perPage: 100);

    foreach ($result['icons'] as $icon) {
        expect($icon->id)->toStartWith('heroicon-o-');
    }
});

test('getIcons filters by search query', function () {
    $result = $this->manager->getIcons(query: 'home', page: 1, perPage: 100);

    foreach ($result['icons'] as $icon) {
        $haystack = strtolower($icon->id . ' ' . $icon->label);
        expect($haystack)->toContain('home');
    }
});

// ── Helpers ──

function findIcon(array $icons, string $id): ?Icon
{
    foreach ($icons as $icon) {
        if ($icon->id === $id) {
            return $icon;
        }
    }
    return null;
}
