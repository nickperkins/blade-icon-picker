<?php

use IconPicker\Icons\Icon;

test('toArray serialises all fields', function () {
    $icon = new Icon('heroicon-o-home', 'Home', '<svg></svg>');

    expect($icon->toArray())->toBe([
        'id' => 'heroicon-o-home',
        'label' => 'Home',
        'svg' => '<svg></svg>',
    ]);
});
