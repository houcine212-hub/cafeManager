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

    })->create();
