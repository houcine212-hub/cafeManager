<?php

use App\Http\Middleware\ResolveGuestAccessFromCookie;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveTenantFromQrToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Respect HTTPS forwarded by ngrok/reverse proxies.
        $middleware->trustProxies(at: '*');

        // تسجيل الـ Middlewares ديال تحديد المقهى (Tenant) والوصول
        $middleware->alias([
            // للموظفين (staff/manager) المسجلين بـ auth
            'tenant' => ResolveTenant::class,

            // لمسارات الزبون عبر QR — كيحدد current_cafe_id
            // من رمز الـ QR ديال الـ route، قبل ما يوصل للـ Controller
            'tenant.qr' => ResolveTenantFromQrToken::class,

            // كيجيب guest_access من cookie موقعة HttpOnly
            // ماشي من input جاي فالـ body
            'guest.cookie' => ResolveGuestAccessFromCookie::class,
        ]);

        // مسارات QR عامة وما عندهاش Laravel session/CSRF token.
        // الاستثناء محدود غير بهاد المسارات، وماشي للتطبيق كامل.
        $middleware->validateCsrfTokens(except: [
            'q/*/access-requests',
            'q/*/orders',
            'q/*/service-requests',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ===================================================================
        // أخطاء الـ Ordering (Domain\Ordering\Exceptions)
        // ===================================================================

        $exceptions->render(function (\App\Domain\Ordering\Exceptions\InvalidOrderTransitionException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'invalid_order_transition',
                    'message' => $e->getMessage(),
                    'from' => $e->getFrom(),
                    'to' => $e->getTo(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Ordering\Exceptions\IdempotencyConflictException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'idempotency_conflict',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Ordering\Exceptions\ProductOutOfStockException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'product_out_of_stock',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Ordering\Exceptions\ProductUnavailableException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'product_unavailable',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Ordering\Exceptions\SessionNotAcceptingOrdersException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'session_not_accepting_orders',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Ordering\Exceptions\UnauthorizedGuestAccessException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'unauthorized_guest_access',
                    'message' => $e->getMessage(),
                ], 403);
            }
        });

        // ===================================================================
        // أخطاء الـ Visits
        // ===================================================================

        $exceptions->render(function (\App\Domain\Visits\Exceptions\InvalidQrCodeException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'invalid_qr_code',
                    'message' => $e->getMessage(),
                ], 404);
            }
        });

        $exceptions->render(function (\App\Domain\Visits\Exceptions\NoActiveSessionException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'no_active_session',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Visits\Exceptions\ActiveSessionAlreadyHasAccessException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'active_session_already_has_access',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        // ===================================================================
        // أخطاء Billing
        // ===================================================================

        $exceptions->render(function (\App\Domain\Billing\Exceptions\InvalidCheckoutStateException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'invalid_checkout_state',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PendingOrdersExistException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'pending_orders_exist',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\TenantMismatchException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'tenant_mismatch',
                    'message' => $e->getMessage(),
                ], 403);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PaymentAlreadyExistsException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'payment_already_exists',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\SessionNotReadyForPaymentException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'session_not_ready_for_payment',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PaymentAmountMismatchException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'payment_amount_mismatch',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\IdempotencyConflictException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'payment_idempotency_conflict',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PendingUnpaidSessionException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'pending_unpaid_session',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });
    })->create();
