<?php

use App\Http\Controllers\Guest\GuestOrderController;
use App\Http\Controllers\Guest\GuestPageController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Staff\OrderController;
use App\Http\Controllers\Staff\PaymentController;
use App\Http\Controllers\Staff\ServiceRequestController;
use App\Http\Controllers\Staff\TableSessionController;
use App\Http\Controllers\Staff\GuestAccessController;
use App\Http\Controllers\Staff\StaffPageController;
use App\Http\Controllers\Staff\TeamController;
use App\Http\Middleware\ResolveGuestAccessFromCookie;
use App\Http\Middleware\ResolveTenantFromQrToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [OnboardingController::class, 'createRegistration'])->name('register');
    Route::post('/register', [OnboardingController::class, 'storeRegistration'])->name('register.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('onboarding')
    ->middleware(['auth', 'tenant'])
    ->group(function () {
        Route::get('/', [OnboardingController::class, 'setup'])
            ->name('onboarding.setup');

        Route::post('/tables', [OnboardingController::class, 'storeTable'])
            ->name('onboarding.tables.store');

        Route::post('/categories', [OnboardingController::class, 'storeCategory'])
            ->name('onboarding.categories.store');

        Route::post('/products', [OnboardingController::class, 'storeProduct'])
            ->name('onboarding.products.store');
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
        Route::get('/', [StaffPageController::class, 'show'])->name('staff.dashboard');
        Route::get('/orders', [StaffPageController::class, 'orders'])->name('staff.orders');
        Route::get('/service-requests', [StaffPageController::class, 'serviceRequests'])->name('staff.service-requests');
        Route::get('/tables', [StaffPageController::class, 'tables'])->name('staff.tables');
        Route::get('/payments', [StaffPageController::class, 'payments'])->name('staff.payments');
        Route::get('/team', [TeamController::class, 'index'])->name('staff.team');

        Route::post('/team', [TeamController::class, 'store'])->name('staff.team.store');

        Route::patch('/team/{memberId}/status', [TeamController::class, 'updateStatus'])
            ->name('staff.team.status');

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

        Route::get(
            '/service-requests/updates',
            [ServiceRequestController::class, 'index']
        );

        Route::patch(
            '/service-requests/{serviceRequestId}/status',
            [ServiceRequestController::class, 'update']
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
