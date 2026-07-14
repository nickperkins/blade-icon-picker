<?php

namespace IconPicker\Tests;

use IconPicker\IconPickerRoutes;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \IconPicker\IconPickerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Use blade-icons defaults; blade-heroicons auto-registers its set

        // Register the icon-picker AJAX routes so the Blade component
        // can resolve the endpoint URLs during test rendering.
        $app['router']->group(['prefix' => 'icon-picker/api'], function ($router) {
            IconPickerRoutes::register('');
        });
    }
}
