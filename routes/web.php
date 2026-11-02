<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\ProcessingResultController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AudioController::class, 'index']);

// connection between laravel project and signal processing
Route::post('/process-audio', [AudioController::class, 'process']);

// Processing results page
Route::get('/results', [ProcessingResultController::class, 'index'])->name('results');

// Export all samples as an Excel file
Route::get('/results/export', [ProcessingResultController::class, 'export'])
    ->name('results.export');

// Delete one processing result and its files
Route::delete('/results/{result}', [AudioController::class, 'destroy'])
    ->name('results.destroy');