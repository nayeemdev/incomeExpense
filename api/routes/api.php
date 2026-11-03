<?php

use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\LedgerInvitationController;
use App\Http\Controllers\LedgerMemberController;
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

        Route::get('currencies', [CurrencyController::class, 'index']);

        Route::get('invitations/{token}', [InvitationController::class, 'show']);
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

        Route::get('ledgers', [LedgerController::class, 'index']);
        Route::post('ledgers', [LedgerController::class, 'store']);

        Route::prefix('ledgers/{ledger}')->middleware('can:view,ledger')->scopeBindings()->group(function () {
            Route::get('/', [LedgerController::class, 'show']);
            Route::get('members', [LedgerMemberController::class, 'index']);
            Route::delete('members/{member}', [LedgerMemberController::class, 'destroy']);

            Route::middleware('can:manage,ledger')->group(function () {
                Route::patch('/', [LedgerController::class, 'update']);
                Route::delete('/', [LedgerController::class, 'destroy']);
                Route::patch('members/{member}', [LedgerMemberController::class, 'update']);
                Route::get('invitations', [LedgerInvitationController::class, 'index']);
                Route::post('invitations', [LedgerInvitationController::class, 'store']);
                Route::delete('invitations/{invitation}', [LedgerInvitationController::class, 'destroy']);
            });
        });
    });
});
