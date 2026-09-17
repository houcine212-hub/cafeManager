<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Guest\GuestOrderController;
use App\Http\Middleware\ResolveGuestAccessFromCookie;
use App\Http\Middleware\ResolveTenantFromQrToken;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('q/{token}')
    ->middleware([ResolveTenantFromQrToken::class, ResolveGuestAccessFromCookie::class])
    ->group(function () {
        Route::get('/menu', [GuestOrderController::class, 'menu']);

        Route::post('/access-requests', [GuestOrderController::class, 'requestAccess'])
            ->middleware('throttle:10,1');

        Route::get('/access-status', [GuestOrderController::class, 'checkStatus'])
            ->middleware('throttle:60,1');

        Route::post('/orders', [GuestOrderController::class, 'storeOrder'])
            ->middleware('throttle:30,1');

        Route::get('/orders/mine', [GuestOrderController::class, 'myOrders'])
            ->middleware('throttle:60,1');

        Route::post('/service-requests', [GuestOrderController::class, 'serviceRequest'])
            ->middleware('throttle:10,1');
    });
