<?php

use App\Http\Controllers\Guest\GuestOrderController;
use App\Http\Controllers\Guest\GuestPageController;
use App\Http\Controllers\Staff\OrderController;
use App\Http\Controllers\Staff\PaymentController;
use App\Http\Controllers\Staff\TableSessionController;
use App\Http\Controllers\Staff\GuestAccessController;
use App\Http\Controllers\Staff\StaffPageController;
use App\Http\Middleware\ResolveGuestAccessFromCookie;
use App\Http\Middleware\ResolveTenantFromQrToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Guest page
|--------------------------------------------------------------------------
| هادي كتفتح الواجهة HTML.
| /q/{token}/menu كيبقى JSON API.
*/
Route::get('/q/{token}', [GuestPageController::class, 'show'])
    ->middleware([
        ResolveTenantFromQrToken::class,
        ResolveGuestAccessFromCookie::class,
    ]);

/*
|--------------------------------------------------------------------------
| Guest JSON API
|--------------------------------------------------------------------------
*/
Route::prefix('q/{token}')
    ->middleware([
        ResolveTenantFromQrToken::class,
        ResolveGuestAccessFromCookie::class,
    ])
    ->group(function () {
        Route::get(
            '/menu',
            [GuestOrderController::class, 'menu']
        );

        Route::post(
            '/access-requests',
            [GuestOrderController::class, 'requestAccess']
        );

        Route::get(
            '/access-status',
            [GuestOrderController::class, 'checkStatus']
        );

        Route::post(
            '/orders',
            [GuestOrderController::class, 'storeOrder']
        );

        Route::get(
            '/orders/mine',
            [GuestOrderController::class, 'myOrders']
        );

        Route::post(
            '/service-requests',
            [GuestOrderController::class, 'serviceRequest']
        );
    });

/*
|--------------------------------------------------------------------------
| Staff API
|--------------------------------------------------------------------------
*/
Route::prefix('staff')
    ->middleware([
        'auth',
        'tenant',
    ])
    ->group(function () {
        Route::get('/', [StaffPageController::class, 'show']);

        Route::get(
            '/guest-accesses',
            [GuestAccessController::class, 'index']
        );

        Route::post(
            '/guest-accesses/{guestAccessId}/approve',
            [GuestAccessController::class, 'approve']
        );

        Route::post(
            '/guest-accesses/{guestAccessId}/revoke',
            [GuestAccessController::class, 'revoke']
        );

        Route::post(
            '/tables/{tableId}/open',
            [TableSessionController::class, 'open']
        );

        Route::post(
            '/sessions/{sessionId}/checkout',
            [TableSessionController::class, 'checkout']
        );

        Route::post(
            '/sessions/{sessionId}/close',
            [TableSessionController::class, 'close']
        );

        Route::get(
            '/orders/updates',
            [OrderController::class, 'index']
        );

        Route::post(
            '/orders',
            [OrderController::class, 'store']
        );

        Route::patch(
            '/orders/{orderId}/status',
            [OrderController::class, 'updateStatus']
        );

        Route::post(
            '/payments',
            [PaymentController::class, 'store']
        );
    });
