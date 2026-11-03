<?php

use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\PersonalAccessTokenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/tokens', [TokenController::class, 'store'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('auth/tokens/current', [TokenController::class, 'destroy']);

    Route::middleware('abilities:*')->group(function () {
        Route::get('user', [UserController::class, 'show']);
        Route::patch('user/preferences', [UserController::class, 'updatePreferences']);

        Route::get('tokens', [PersonalAccessTokenController::class, 'index']);
        Route::post('tokens', [PersonalAccessTokenController::class, 'store']);
        Route::delete('tokens/{token}', [PersonalAccessTokenController::class, 'destroy']);
    });
});
