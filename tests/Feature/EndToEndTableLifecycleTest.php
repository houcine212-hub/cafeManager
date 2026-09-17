<?php

namespace Tests\Feature;

use App\Domain\Billing\CheckoutService;
use App\Domain\Billing\CloseSessionService;
use App\Domain\Billing\Exceptions\PaymentAlreadyExistsException;
use App\Domain\Billing\Exceptions\PaymentAmountMismatchException;
use App\Domain\Billing\Exceptions\PendingOrdersExistException;
use App\Domain\Billing\PaymentService;
use App\Domain\Ordering\CreateOrderAction;
use App\Domain\Ordering\OrderTransitionService;
use App\Domain\Visits\RequestGuestAccessAction;
use App\Models\AuditLog;
use App\Models\CafeTable;
use App\Models\GuestAccess;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبار End-to-End كامل لدورة حياة الطاولة:
 * فتح زيارة -> دخول زبون QR -> طلبات (staff + guest) -> ستوك ->
 * transitions -> checkout -> payment -> إغلاق.
 *
 * هذا هو نفس السيناريو اللي تجرب يدوياً بـ Tinker، محوّل لتيست آلي
 * محفوظ فالمشروع، باش يتعاود تلقائياً مع كل تعديل مستقبلي (CI).
 */
class EndToEndTableLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $serveur;
    private CafeTable $table;
    private QrCode $qrCode;
    private Product $productNoStock;
    private Product $productTracked;

    protected function setUp(): void
{
    parent::setUp();

    $this->seed(DatabaseSeeder::class);

    // نجيب cafe_id مباشرة قبل أي Model عندو tenant scope
    $cafeId = \Illuminate\Support\Facades\DB::table('cafes')
        ->value('id');

    $this->assertNotNull($cafeId);

    app()->instance(
        'current_cafe_id',
        (int) $cafeId
    );

    $this->manager = User::where(
        'cafe_id',
        $cafeId
    )
        ->where('email', 'manager@demo.test')
        ->firstOrFail();

    $this->serveur = User::where(
        'cafe_id',
        $cafeId
    )
        ->where('email', 'serveur@demo.test')
        ->firstOrFail();

    $this->table = CafeTable::where(
        'cafe_id',
        $cafeId
    )
        ->where('label', 'Table 2')
        ->firstOrFail();

    $this->qrCode = QrCode::where(
        'cafe_id',
        $cafeId
    )
        ->where('table_id', $this->table->id)
        ->where('is_active', true)
        ->firstOrFail();

    $this->productNoStock = Product::where(
        'cafe_id',
        $cafeId
    )
        ->where('name', 'like', '%Café Noir%')
        ->firstOrFail();

    $this->productTracked = Product::where(
        'cafe_id',
        $cafeId
    )
        ->where('name', 'like', '%Coca-Cola%')
        ->firstOrFail();
}

    public function test_full_table_lifecycle_from_open_to_close(): void
    {
        // ==============================================================
        // 1. فتح زيارة جديدة
        // ==============================================================
        $session = TableSession::create([
            'cafe_id' => $this->manager->cafe_id,
            'table_id' => $this->table->id,
            'opened_by' => $this->manager->id,
            'opened_at' => now(),
            'status' => TableSession::STATUS_OPEN,
        ]);

        $this->assertSame('open', $session->status);

        // ==============================================================
        // 2. دخول الزبون عبر QR (D09: pending -> approved)
        // ==============================================================
        $requestGuestAccessAction = new RequestGuestAccessAction();
        $cookieHash = hash('sha256', 'device-fingerprint-test-1');

        $guestAccess = $requestGuestAccessAction->execute($this->qrCode->token, $cookieHash);

        $this->assertSame(GuestAccess::STATUS_PENDING, $guestAccess->status);

        $guestAccess->update([
            'status' => GuestAccess::STATUS_APPROVED,
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);
        $guestAccess->refresh();

        $this->assertTrue($guestAccess->isApproved());

        // ==============================================================
        // 3. طلب أول من الزبون (QR) — منتوجين، واحد بستوك متتبع
        // ==============================================================
        $createOrderAction = new CreateOrderAction();
        $stockBefore = $this->productTracked->stock_quantity;

        $order1 = $createOrderAction->execute(
            cafeId: $this->manager->cafe_id,
            sessionId: $session->id,
            items: [
                ['product_id' => $this->productNoStock->id, 'quantity' => 2],
                ['product_id' => $this->productTracked->id, 'quantity' => 1],
            ],
            idempotencyKey: 'test-order-guest-1',
            source: 'qr',
            guestAccessId: $guestAccess->id,
        );

        $this->assertSame('new', $order1->status);
        $this->assertCount(2, $order1->orderItems);

        // فحص دقة الحساب (bcmul) للسطر الأول
        $this->assertSame('20.00', $order1->orderItems[0]->line_total);
        $this->assertSame('12.00', $order1->orderItems[1]->line_total);

        // فحص الستوك D15: تناقص بالضبط بالكمية المطلوبة
        $this->productTracked->refresh();
        $this->assertSame($stockBefore - 1, $this->productTracked->stock_quantity);

        // ==============================================================
        // 4. طلب ثاني من السرباي (staff)
        // ==============================================================
        $order2 = $createOrderAction->execute(
            cafeId: $this->manager->cafe_id,
            sessionId: $session->id,
            items: [
                ['product_id' => $this->productNoStock->id, 'quantity' => 1],
            ],
            idempotencyKey: 'test-order-staff-1',
            source: 'staff',
            userId: $this->serveur->id,
        );

        $this->assertSame('new', $order2->status);
        $this->assertNull($order2->guest_access_id);
        $this->assertSame($this->serveur->id, $order2->created_by_user_id);

        // ==============================================================
        // 5. محاولة Checkout قبل ما تكمل الطلبات — خاصها تفشل
        // ==============================================================
        $checkoutService = new CheckoutService();

        $this->expectExceptionCallback(function () use ($checkoutService, $session) {
            $checkoutService->checkout($session, $this->manager);
        }, PendingOrdersExistException::class);

        // ==============================================================
        // 6. تحضير وتسليم الطلبات (state machine كاملة)
        // ==============================================================
        $transitionService = new OrderTransitionService();
        $flow = [
            'new' => 'accepted',
            'accepted' => 'preparing',
            'preparing' => 'ready',
            'ready' => 'served',
        ];

        $current1 = $order1;
        while (isset($flow[$current1->status])) {
            $current1 = $transitionService->transition($current1, $flow[$current1->status], $this->manager);
        }
        $this->assertSame('served', $current1->status);

        $current2 = $order2;
        while (isset($flow[$current2->status])) {
            $current2 = $transitionService->transition($current2, $flow[$current2->status], $this->manager);
        }
        $this->assertSame('served', $current2->status);

        // ==============================================================
        // 7. Idempotency: نفس المفتاح خاصو يرجع نفس الـ Order، بلا تكرار
        // ==============================================================
        $retryOrder = $createOrderAction->execute(
            cafeId: $this->manager->cafe_id,
            sessionId: $session->id,
            items: [
                ['product_id' => $this->productNoStock->id, 'quantity' => 2],
                ['product_id' => $this->productTracked->id, 'quantity' => 1],
            ],
            idempotencyKey: 'test-order-guest-1',
            source: 'qr',
            guestAccessId: $guestAccess->id,
        );

        $this->assertSame($order1->id, $retryOrder->id);
        $this->assertDatabaseCount('orders', 2); // ماشي 3 — الـ retry ما خلقش سطر جديد

        // ==============================================================
        // 8. Checkout الحقيقي (كلشي served دابا)
        // ==============================================================
        $checkoutSession = $checkoutService->checkout($session, $this->manager);

        $this->assertSame('checkout', $checkoutSession->status);

        $expectedTotal = bcadd(
            bcmul($this->productNoStock->price, '3', 2), // 2+1 من Café Noir
            bcmul($this->productTracked->price, '1', 2), // 1 Coca
            2
        );
        $this->assertSame($expectedTotal, $checkoutSession->total_final);

        // ==============================================================
        // 9. أداء بمبلغ غلط — خاصو يترفض
        // ==============================================================
        $paymentService = new PaymentService();

        $this->expectExceptionCallback(function () use ($paymentService, $session) {
            $paymentService->recordPayment($session, $this->manager, '10.00', 'cash', 'pay-key-wrong');
        }, PaymentAmountMismatchException::class);

        // ==============================================================
        // 10. أداء صحيح
        // ==============================================================
        $payment = $paymentService->recordPayment(
            $session,
            $this->manager,
            $checkoutSession->total_final,
            'cash',
            'pay-key-correct'
        );

        $this->assertSame($checkoutSession->total_final, $payment->amount);
        $this->assertDatabaseCount('payments', 1);

        // ==============================================================
        // 11. أداء ثاني لنفس الزيارة — خاصو يترفض (D04)
        // ==============================================================
        $this->expectExceptionCallback(function () use ($paymentService, $session, $checkoutSession) {
            $paymentService->recordPayment($session, $this->manager, $checkoutSession->total_final, 'card', 'pay-key-duplicate');
        }, PaymentAlreadyExistsException::class);

        $this->assertDatabaseCount('payments', 1); // بقات 1 غير

        // ==============================================================
        // 12. إغلاق الزيارة وتحرير الطاولة
        // ==============================================================
        $closeService = new CloseSessionService();
        $closedSession = $closeService->close($session, $this->manager);

        $this->assertSame('closed', $closedSession->status);
        $this->assertSame('paid', $closedSession->closure_reason);
        $this->assertNull($closedSession->active_table_marker); // تحرر الطاولة (generated column)

        // ==============================================================
        // 13. التحقق النهائي: Audit + عدم وجود زيارة نشطة على الطاولة
        // ==============================================================
        $auditActions = AuditLog::where('entity_type', 'table_sessions')
            ->where('entity_id', $session->id)
            ->pluck('action');

        $this->assertEqualsCanonicalizing(
            ['session.checkout', 'session.closed'],
            $auditActions->toArray()
        );

        $activeSessions = TableSession::where('table_id', $this->table->id)
            ->whereIn('status', ['open', 'checkout'])
            ->count();

        $this->assertSame(0, $activeSessions);
    }

    /**
     * Helper: PHPUnit's expectException() كيوقف تنفيذ باقي التيست بعد
     * أول استدعاء، فما نقدروش نستعملوه مرتين فنفس التيست methode.
     * هاد الـ callback كيسمح لينا نستمرو فالسيناريو بعد كل فحص استثناء.
     */
    private function expectExceptionCallback(callable $callback, string $expectedExceptionClass): void
    {
        try {
            $callback();
            $this->fail("Expected exception [{$expectedExceptionClass}] was not thrown.");
        } catch (\Throwable $e) {
            $this->assertInstanceOf($expectedExceptionClass, $e);
        }
    }
}
