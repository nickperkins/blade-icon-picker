<?php

namespace IconPicker;

use IconPicker\Icons\IconManager;
use IconPicker\View\Components\IconPicker;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;

class IconPickerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IconManager::class, function ($app) {
            return new IconManager(
                $app->make(Factory::class),
                $app->make(IconsManifest::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'icon-picker');

        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/icon-picker'),
        ], 'icon-picker-assets');

        Blade::component('icon-picker::icon-picker', IconPicker::class);
    }
}
