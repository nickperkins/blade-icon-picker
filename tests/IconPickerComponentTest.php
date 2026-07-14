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

// ── Endpoint wiring ──

test('passes endpoint URL in x-data instead of serialized icon data', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('endpoint:', false);
    $view->assertSee('setsEndpoint:', false);
    $view->assertSee('currentValue', false);
    $view->assertSee('initialSelectedIcon', false);
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

test('passes selected icon data for initial render', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => 'heroicon-o-home'],
    );

    // The selected icon's SVG and label should be embedded for initial display
    $view->assertSee('initialSelectedIcon', false);
    $view->assertSee('Home', false);
});

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

// ── Filter controls ──

test('renders set and variant filter dropdowns', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('ip-filters', false);
    $view->assertSee('ip-filter-select', false);
    $view->assertSee('All sets', false);
    $view->assertSee('All styles', false);
    $view->assertSee('Outline', false);
});

// ── Wire:model ──

test('passes through wire:model to root element', function () {
    $view = $this->blade(
        '<x-icon-picker::icon-picker wire:model="icon" :value="$value" />',
        ['value' => null],
    );

    $view->assertSee('wire:model="icon"', false);
});
