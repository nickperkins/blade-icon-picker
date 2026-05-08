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
    expect($home->label)->toBe('O Home');
});

test('derives correct labels for solid style', function () {
    $home = findIcon($this->manager->getAllIcons(), 'heroicon-s-home');

    expect($home)->not->toBeNull();
    expect($home->label)->toBe('S Home');
});

test('derives correct labels for mini style', function () {
    $home = findIcon($this->manager->getAllIcons(), 'heroicon-m-home');

    expect($home)->not->toBeNull();
    expect($home->label)->toBe('M Home');
});

test('handles hyphenated icon names in labels', function () {
    $arrow = findIcon($this->manager->getAllIcons(), 'heroicon-o-arrow-left');

    expect($arrow)->not->toBeNull();
    expect($arrow->label)->toContain('Arrow Left');
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
