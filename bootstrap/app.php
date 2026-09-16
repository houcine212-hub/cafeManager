<?php

use App\Http\Middleware\ResolveTenant;
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
        // تسجيل الـ Middleware اللي كيعمر لينا current_cafe_id
        $middleware->alias([
            'tenant' => ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // التقاط الـ Exception ديال الانتقالات وإرجاع 409 Conflict مع الداتا
        $exceptions->render(function (\App\Domain\Ordering\Exceptions\InvalidOrderTransitionException $e, Request $request) {
            // باش يرجع JSON غير إلا كان الطلب من API أوي الـ JS
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'invalid_order_transition',
                    'message' => $e->getMessage(),
                    'from' => $e->getFrom(),
                    'to' => $e->getTo(),
                ], 409);
            }
        });

        // التقاط تضارب الـ Idempotency (مكرر بـ محتوى مختلف)
        $exceptions->render(function (\App\Domain\Ordering\Exceptions\IdempotencyConflictException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'idempotency_conflict',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        // التقاط حالة الستوك D15 نفد
        $exceptions->render(function (\App\Domain\Ordering\Exceptions\ProductOutOfStockException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'product_out_of_stock',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        // ===================================================================
        // ✨ جديد: أخطاء تجميد الحساب والـ Billing (Checkout Service)
        // ===================================================================

        // حالة الزيارة ما كتسمحش بالـ Checkout (مثلاً مسدودة من قبل)
        $exceptions->render(function (\App\Domain\Billing\Exceptions\InvalidCheckoutStateException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'invalid_checkout_state',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        // باقي كاينين طلبات معلقة (new/accepted/preparing/ready) ما تسلماتش بعد
        $exceptions->render(function (\App\Domain\Billing\Exceptions\PendingOrdersExistException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'pending_orders_exist',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        // محاولة الوصول لزيارة ديال café آخر (IDOR) — أمنية، 403 ماشي 409
        $exceptions->render(function (\App\Domain\Billing\Exceptions\TenantMismatchException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'tenant_mismatch',
                    'message' => $e->getMessage(),
                ], 403);
            }
        });

                // ===================================================================
        // ✨ جديد: أخطاء الأداء والإغلاق (PaymentService / CloseSessionService)
        // ===================================================================

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PaymentAlreadyExistsException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'payment_already_exists', 'message' => $e->getMessage()], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\SessionNotReadyForPaymentException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'session_not_ready_for_payment', 'message' => $e->getMessage()], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PaymentAmountMismatchException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'payment_amount_mismatch', 'message' => $e->getMessage()], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\IdempotencyConflictException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'payment_idempotency_conflict', 'message' => $e->getMessage()], 409);
            }
        });

        $exceptions->render(function (\App\Domain\Billing\Exceptions\PendingUnpaidSessionException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'pending_unpaid_session', 'message' => $e->getMessage()], 409);
            }
        });
    })->create();
