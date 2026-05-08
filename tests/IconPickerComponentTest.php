<?php

// ── Placeholder ──

test('renders default placeholder', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('Select an icon', false);
});

test('renders custom placeholder', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker placeholder="Choose a menu icon" :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('Choose a menu icon', false);
});

// ── Value binding ──

test('embeds icon data as JSON payload in x-data', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    // Js::from wraps non-empty arrays in JSON.parse('...') for XSS safety
    $view->assertSee('JSON.parse(', false);
    $view->assertSee('currentValue', false);
});

test('renders selected value when provided', function (?string $value, string $expected) {
    $view = $this->blade(
        '<x-icon-picker::icon-picker wire:model="icon" :value="$value" />',
        ['value' => $value],
    );

    $view->assertSee("currentValue: {$expected}", false);
})->with([
    'non-empty' => ['heroicon-o-home', "'heroicon-o-home'"],
    'empty string' => ['', "''"],
    'null' => [null, 'null'],
]);

// ── Disabled state ──

test('renders disabled state', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker disabled :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('disabled: true', false);
});

// ── Accessibility ──

test('trigger has listbox ARIA attributes', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('aria-haspopup="listbox"', false);
    $view->assertSee('x-bind:aria-expanded="isOpen"', false);
});

test('grid has listbox role and options have option role', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('role="listbox"', false);
    $view->assertSee('role="option"', false);
    $view->assertSee('x-bind:aria-selected', false);
});

test('clear button has accessible label', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('aria-label="Clear selection"', false);
});

// ── Empty state ──

test('shows help message when no icon packs are installed', function () {
    $manager = Mockery::mock(\IconPicker\Icons\IconManager::class);
    $manager->shouldReceive('getAllIcons')->andReturn([]);
    $this->app->instance(\IconPicker\Icons\IconManager::class, $manager);

    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('No icon sets found', false);
    $view->assertSee('composer require blade-ui-kit/blade-heroicons', false);
});

// ── Wire:model ──

test('passes through wire:model to root element', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker wire:model="icon" :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('wire:model="icon"', false);
});
