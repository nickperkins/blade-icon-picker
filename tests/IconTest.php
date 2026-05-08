<?php

use IconPicker\Icons\Icon;

test('toArray serialises all fields', function () {
    $icon = new Icon('heroicon-o-home', 'O Home', '<svg></svg>');

    expect($icon->toArray())->toBe([
        'id' => 'heroicon-o-home',
        'label' => 'O Home',
        'svg' => '<svg></svg>',
    ]);
});
