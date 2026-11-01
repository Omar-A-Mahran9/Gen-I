<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use Illuminate\Support\Facades\Route;

/*
| Versioned API. Registered in bootstrap/app.php with the "api" middleware
| and the /api/v1 prefix. Conventions: docs/api-contracts.md.
*/

Route::name('api.v1.')->group(function (): void {
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('user', CurrentUserController::class)->name('user');
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');
    });
});
