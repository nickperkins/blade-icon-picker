<?php

namespace IconPicker;

use IconPicker\Http\Controllers\IconPickerController;
use Illuminate\Support\Facades\Route;

class IconPickerRoutes
{
    /**
     * Register the icon-picker AJAX routes.
     *
     * Call this from within your auth-protected route group so the
     * endpoints inherit the group's middleware and prefix:
     *
     *   Route::prefix('admin')->middleware('auth')->group(function () {
     *       IconPickerRoutes::register('icon-picker/api');
     *   });
     */
    public static function register(string $prefix = 'icon-picker/api'): void
    {
        Route::prefix($prefix)->group(function () {
            Route::get('/icons', [IconPickerController::class, 'index'])
                ->name('icon-picker.icons');
            Route::get('/sets', [IconPickerController::class, 'sets'])
                ->name('icon-picker.sets');
        });
    }
}
