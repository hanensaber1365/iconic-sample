<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\ProcessingResultController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AudioController::class, 'index']);

// connection between laravel project and signal processing
Route::post('/process-audio', [AudioController::class, 'process']);

// Processing results page
Route::get('/results', [ProcessingResultController::class, 'index'])->name('results');

// Which plots of a result are ready (they are drawn in the background)
Route::get('/results/{result}/plots', [ProcessingResultController::class, 'plots'])
    ->name('results.plots');

// Export all samples as an Excel file
Route::get('/results/export', [ProcessingResultController::class, 'export'])
    ->name('results.export');

// Delete one processing result and its files
// A result that is already gone (double click, Back button, second tab)
// returns to the results page instead of a bare 404.
Route::delete('/results/{result}', [AudioController::class, 'destroy'])
    ->name('results.destroy')
    ->missing(fn () => redirect()
        ->route('results')
        ->with('success', 'This sample was already deleted.'));