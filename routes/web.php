<?php

declare(strict_types=1);

use App\Http\Controllers\Dev\ComponentGalleryController;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'testing'])) {
    Route::redirect('/', '/dev/ui');
    Route::get('/dev/ui', ComponentGalleryController::class)->name('dev.ui');
}
