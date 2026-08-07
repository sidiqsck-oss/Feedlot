<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Rilis tanpa SSH.
 *
 * Hosting tujuan cuma punya File Manager dan cPanel, tidak ada terminal, jadi
 * `php artisan migrate` dijalankan lewat sini oleh GitHub Actions setelah file
 * selesai diunggah. Route ini mati (404) selama DEPLOY_TOKEN kosong.
 */
Route::prefix('__deploy')->group(function () {
    Route::post('/migrate', [DeployController::class, 'migrate']);
    Route::post('/optimize', [DeployController::class, 'optimize']);
    Route::get('/status', [DeployController::class, 'status']);
});
