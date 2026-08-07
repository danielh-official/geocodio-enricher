<?php

use App\Http\Controllers\EnricherController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('enricher', [EnricherController::class, 'index'])->name('enricher');
    Route::post('upload', [EnricherController::class, 'upload'])->name('upload');
    Route::post('enrich', [EnricherController::class, 'enrich'])->name('enrich');
    Route::get('download', [EnricherController::class, 'download'])->name('download');
});

require __DIR__.'/settings.php';
