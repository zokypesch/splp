<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DukcapilController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| SPLP API Routes
|--------------------------------------------------------------------------
|
| Routes for SPLP (Sistem Perangkat Lunak Pemerintah) integration
| testing and demonstration
|
*/

Route::prefix('api/splp')->group(function () {
    
    // Health check endpoint
    Route::get('/health', [DukcapilController::class, 'healthCheck'])
        ->name('splp.health');
    
    // Configuration info (for debugging)
    Route::get('/config', [DukcapilController::class, 'getConfig'])
        ->name('splp.config');
    
    // Send population data for verification
    Route::post('/population-data', [DukcapilController::class, 'sendPopulationData'])
        ->name('splp.population-data');
    
    // Test endpoint to simulate sending data
    Route::post('/test-send', [DukcapilController::class, 'testSend'])
        ->name('splp.test-send');
});

/*
|--------------------------------------------------------------------------
| Demo Routes
|--------------------------------------------------------------------------
|
| Routes for demonstrating SPLP functionality
|
*/

Route::prefix('demo')->group(function () {
    
    // Demo page showing SPLP integration
    Route::get('/', function () {
        return view('demo.splp');
    })->name('demo.splp');
    
    // API documentation
    Route::get('/api-docs', function () {
        return view('demo.api-docs');
    })->name('demo.api-docs');
});
