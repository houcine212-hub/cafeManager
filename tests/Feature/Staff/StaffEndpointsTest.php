<?php

namespace Tests\Feature\Staff;

use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $manager;
    protected User $serveur;
    protected CafeTable $table;
    protected Product $coca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $cafeId = DB::table('cafes')->value('id');

        $this->assertNotNull($cafeId);

        app()->instance(
            'current_cafe_id',
            (int) $cafeId
        );

        $this->cafe = Cafe::findOrFail($cafeId);

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

        $this->coca = Product::where(
            'cafe_id',
            $cafeId
        )
            ->where('name', 'Coca-Cola (33cl)')
            ->firstOrFail();

        TableSession::where(
            'table_id',
            $this->table->id
        )
            ->whereIn('status', [
                TableSession::STATUS_OPEN,
                TableSession::STATUS_CHECKOUT,
            ])
            ->update([
                'status' => TableSession::STATUS_CLOSED,
                'closure_reason' => TableSession::CLOSURE_EXCEPTION_UNPAID,
                'closed_at' => now(),
                'closed_by' => $this->manager->id,
            ]);
    }

    private function createOpenSession(): TableSession
    {
        return TableSession::create([
            'cafe_id' => $this->cafe->id,
            'table_id' => $this->table->id,
            'opened_by' => $this->serveur->id,
            'status' => TableSession::STATUS_OPEN,
        ]);
    }

    private function createStaffOrder(
        TableSession $session,
        string $status = 'new'
    ): Order {
        return Order::create([
            'cafe_id' => $this->cafe->id,
            'table_session_id' => $session->id,
            'source' => 'staff',
            'created_by_user_id' => $this->serveur->id,
            'status' => $status,
            'idempotency_key' => Str::random(64),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_staff_routes(): void
    {
        $response = $this->getJson(
            '/staff/orders/updates'
        );

        $response->assertStatus(401);
    }

    public function test_staff_can_open_table_session(): void
    {
        $response = $this
            ->actingAs($this->serveur)
            ->postJson(
                "/staff/tables/{$this->table->id}/open"
            );

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Table ouverte avec succès.',
                'session' => [
                    'table_id' => $this->table->id,
                    'status' => 'open',
                ],
            ]);

        $this->assertDatabaseHas('table_sessions', [
            'cafe_id' => $this->cafe->id,
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);
    }

    public function test_staff_can_place_manual_order(): void
    {
        $session = $this->createOpenSession();

        $stockBefore = $this->coca
            ->fresh()
            ->stock_quantity;

        $response = $this
            ->actingAs($this->serveur)
            ->postJson('/staff/orders', [
                'session_id' => $session->id,
                'items' => [
                    [
                        'product_id' => $this->coca->id,
                        'quantity' => 1,
                        'note' => 'bien frais',
                    ],
                ],
                'idempotency_key' => Str::random(64),
            ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Commande enregistrée.',
                'order' => [
                    'source' => 'staff',
                    'status' => 'new',
                ],
            ]);

        $this->assertEquals(
            $stockBefore - 1,
            $this->coca->fresh()->stock_quantity
        );
    }

    public function test_staff_can_poll_active_orders_queue(): void
    {
        $session = $this->createOpenSession();
        $order = $this->createStaffOrder($session);

        $response = $this
            ->actingAs($this->serveur)
            ->getJson('/staff/orders/updates');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'server_time',
                'orders' => [
                    '*' => [
                        'id',
                        'status',
                        'order_items',
                        'table_session',
                    ],
                ],
            ])
            ->assertJsonPath(
                'orders.0.id',
                $order->id
            )
            ->assertJsonPath(
                'orders.0.table_session.table.id',
                $this->table->id
            );
    }

    public function test_staff_can_transition_order_to_served(): void
    {
        $session = $this->createOpenSession();
        $order = $this->createStaffOrder($session);

        foreach ([
            'accepted',
            'preparing',
            'ready',
            'served',
        ] as $status) {
            $response = $this
                ->actingAs($this->serveur)
                ->patchJson(
                    "/staff/orders/{$order->id}/status",
                    [
                        'status' => $status,
                        'reason' => 'Traitement par le serveur',
                    ]
                );

            $response
                ->assertStatus(200)
                ->assertJson([
                    'message' => 'Statut mis à jour.',
                    'order' => [
                        'status' => $status,
                    ],
                ]);
        }

        $this->assertSame(
            'served',
            $order->fresh()->status
        );
    }

    public function test_staff_full_checkout_payment_and_close_flow(): void
    {
        $session = $this->createOpenSession();
        $order = $this->createStaffOrder(
            $session,
            'served'
        );

        $order->orderItems()->create([
            'cafe_id' => $this->cafe->id,
            'product_id' => $this->coca->id,
            'product_name_snapshot' => $this->coca->name,
            'unit_price_snapshot' => $this->coca->price,
            'quantity' => 2,
            'line_total' => bcmul(
                (string) $this->coca->price,
                '2',
                2
            ),
        ]);

        $expectedTotal = bcmul(
            (string) $this->coca->price,
            '2',
            2
        );

        $checkoutResponse = $this
            ->actingAs($this->serveur)
            ->postJson(
                "/staff/sessions/{$session->id}/checkout"
            );

        $checkoutResponse
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Addition figée avec succès.',
                'total_final' => $expectedTotal,
            ]);

        $paymentResponse = $this
            ->actingAs($this->serveur)
            ->postJson('/staff/payments', [
                'session_id' => $session->id,
                'amount' => $expectedTotal,
                'method' => 'cash',
                'idempotency_key' => Str::random(64),
            ]);

        $paymentResponse
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Paiement enregistré avec succès.',
            ]);

        $closeResponse = $this
            ->actingAs($this->serveur)
            ->postJson(
                "/staff/sessions/{$session->id}/close"
            );

        $closeResponse
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Table libérée avec succès.',
                'session' => [
                    'status' => 'closed',
                    'closure_reason' => 'paid',
                ],
            ]);

        $this->assertNull(
            $session->fresh()->active_table_marker
        );
    }

    public function test_staff_cannot_update_order_from_another_cafe(): void
    {
        $session = $this->createOpenSession();
        $order = $this->createStaffOrder($session);

        $roleId = DB::table('roles')
            ->where('name', 'serveur')
            ->value('id');

        $otherCafeId = DB::table('cafes')->insertGetId([
            'name' => 'Other Cafe ' . Str::random(8),
            'currency' => 'MAD',
            'timezone' => 'Africa/Casablanca',
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherUserId = DB::table('users')->insertGetId([
            'cafe_id' => $otherCafeId,
            'role_id' => $roleId,
            'name' => 'Other Serveur',
            'email' => 'other-' . Str::random(8) . '@demo.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherUser = User::withoutGlobalScopes()
            ->findOrFail($otherUserId);

        app()->forgetInstance('current_cafe_id');

        $response = $this
            ->actingAs($otherUser)
            ->patchJson(
                "/staff/orders/{$order->id}/status",
                [
                    'status' => 'accepted',
                ]
            );

        $response->assertNotFound();

        $this->assertSame(
            'new',
            $order->fresh()->status
        );
    }
}
