<?php

namespace Tests\Feature\Guest;

use App\Http\Middleware\ResolveGuestAccessFromCookie;
use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\GuestAccess;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $manager;
    protected CafeTable $table;
    protected QrCode $qr;
    protected TableSession $session;
    protected Product $coca;
    protected Product $cafeEspresso;

    protected function setUp(): void
    {
        parent::setUp();


        $this->seed(DatabaseSeeder::class);

        $this->cafe = Cafe::firstOrFail();

        app()->instance(
            'current_cafe_id',
            $this->cafe->id
        );

        $this->manager = User::where(
            'cafe_id',
            $this->cafe->id
        )->firstOrFail();

        $this->table = CafeTable::where(
            'cafe_id',
            $this->cafe->id
        )
            ->where('label', 'Table 2')
            ->firstOrFail();

        $this->qr = QrCode::withoutGlobalScope('cafe')
            ->where('cafe_id', $this->cafe->id)
            ->where('table_id', $this->table->id)
            ->where('is_active', true)
            ->firstOrFail();

        $this->session = TableSession::firstOrCreate(
            [
                'table_id' => $this->table->id,
                'status' => TableSession::STATUS_OPEN,
            ],
            [
                'cafe_id' => $this->cafe->id,
                'opened_by' => $this->manager->id,
            ]
        );

        $this->coca = Product::where(
            'cafe_id',
            $this->cafe->id
        )
            ->where('name', 'Coca-Cola (33cl)')
            ->firstOrFail();

        $this->cafeEspresso = Product::where(
            'cafe_id',
            $this->cafe->id
        )
            ->where('name', 'Café Noir (Espresso)')
            ->firstOrFail();

        GuestAccess::where(
            'table_session_id',
            $this->session->id
        )->delete();
    }

   private function postJsonWithDeviceCookie(
    string $url,
    string $cookieValue,
    array $data = []
) {
    return $this
        ->withUnencryptedCookie(
            ResolveGuestAccessFromCookie::COOKIE_NAME,
            $cookieValue
        )
        ->withCredentials()
        ->postJson($url, $data);
}

private function getJsonWithDeviceCookie(
    string $url,
    string $cookieValue
) {
    return $this
        ->withUnencryptedCookie(
            ResolveGuestAccessFromCookie::COOKIE_NAME,
            $cookieValue
        )
        ->withCredentials()
        ->getJson($url);
}

    private function cookieValueFromResponse($response): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (
                $cookie->getName() ===
                ResolveGuestAccessFromCookie::COOKIE_NAME
            ) {
                return $cookie->getValue();
            }
        }

        $this->fail(
            'The guest device cookie was not returned.'
        );
    }

    public function test_guest_can_view_menu_via_valid_qr_token(): void
    {
        $response = $this->getJson(
            "/q/{$this->qr->token}/menu"
        );

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'table',
                'categories' => [
                    '*' => [
                        'id',
                        'name',
                        'products',
                    ],
                ],
            ]);
    }

    public function test_invalid_qr_token_returns_404(): void
    {
        $response = $this->getJson(
            '/q/' . Str::random(64) . '/menu'
        );

        $response->assertStatus(404);
    }

    public function test_guest_requests_access_and_receives_secure_cookie(): void
    {
        $response = $this->postJson(
            "/q/{$this->qr->token}/access-requests"
        );

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => "Demande d'accès enregistrée.",
                'status' => 'pending',
            ])
            ->assertCookie(
                ResolveGuestAccessFromCookie::COOKIE_NAME
            );

        $this->assertDatabaseHas(
            'guest_accesses',
            [
                'table_session_id' => $this->session->id,
                'status' => 'pending',
            ]
        );
    }

    public function test_guest_cannot_order_before_staff_approval(): void
    {
        $authResponse = $this->postJson(
            "/q/{$this->qr->token}/access-requests"
        );

        $cookieValue = $this->cookieValueFromResponse(
            $authResponse
        );

        $orderResponse = $this->postJsonWithDeviceCookie(
            "/q/{$this->qr->token}/orders",
            $cookieValue,
            [
                'items' => [
                    [
                        'product_id' => $this->coca->id,
                        'quantity' => 1,
                    ],
                ],
                'idempotency_key' => Str::random(64),
            ]
        );

        $orderResponse
            ->assertStatus(403)
            ->assertJson([
                'error' => 'unauthorized_guest',
            ]);
    }

    public function test_approved_guest_can_successfully_place_order_via_http(): void
    {
        $authResponse = $this->postJson(
            "/q/{$this->qr->token}/access-requests"
        );

        $cookieValue = $this->cookieValueFromResponse(
            $authResponse
        );

        $accessId = $authResponse->json(
            'guest_access_id'
        );

        GuestAccess::where(
            'id',
            $accessId
        )->update([
            'status' => 'approved',
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);

        $statusResponse = $this->getJsonWithDeviceCookie(
            "/q/{$this->qr->token}/access-status",
            $cookieValue
        );

        $statusResponse
            ->assertStatus(200)
            ->assertJson([
                'status' => 'approved',
                'is_approved' => true,
            ]);

        $stockBefore = $this->coca
            ->fresh()
            ->stock_quantity;

        $orderResponse = $this->postJsonWithDeviceCookie(
            "/q/{$this->qr->token}/orders",
            $cookieValue,
            [
                'items' => [
                    [
                        'product_id' => $this->coca->id,
                        'quantity' => 2,
                        'note' => 'bien frais',
                    ],
                    [
                        'product_id' => $this->cafeEspresso->id,
                        'quantity' => 1,
                    ],
                ],
                'idempotency_key' => Str::random(64),
            ]
        );

        $orderResponse
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Commande transmise avec succès.',
                'order' => [
                    'source' => 'qr',
                    'status' => 'new',
                ],
            ]);

        $this->assertEquals(
            $stockBefore - 2,
            $this->coca->fresh()->stock_quantity
        );

        $myOrdersResponse = $this->getJsonWithDeviceCookie(
            "/q/{$this->qr->token}/orders/mine",
            $cookieValue
        );

        $myOrdersResponse
            ->assertStatus(200)
            ->assertJsonCount(1, 'orders');
    }

    public function test_guest_can_call_waiter_with_dedup(): void
    {
        $authResponse = $this->postJson(
            "/q/{$this->qr->token}/access-requests"
        );

        $cookieValue = $this->cookieValueFromResponse(
            $authResponse
        );

        $accessId = $authResponse->json(
            'guest_access_id'
        );

        GuestAccess::where(
            'id',
            $accessId
        )->update([
            'status' => 'approved',
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);

        $statusResponse = $this->getJsonWithDeviceCookie(
            "/q/{$this->qr->token}/access-status",
            $cookieValue
        );

        $statusResponse
            ->assertStatus(200)
            ->assertJson([
                'status' => 'approved',
                'is_approved' => true,
            ]);

        $req1 = $this->postJsonWithDeviceCookie(
            "/q/{$this->qr->token}/service-requests",
            $cookieValue,
            [
                'type' => 'waiter',
            ]
        );

        $req1
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Demande envoyée au serveur.',
            ]);

        $req2 = $this->postJsonWithDeviceCookie(
            "/q/{$this->qr->token}/service-requests",
            $cookieValue,
            [
                'type' => 'waiter',
            ]
        );

        $req2
            ->assertStatus(200)
            ->assertJson([
                'message' =>
                    'Demande déjà enregistrée et en cours de traitement.',
            ]);
    }
}
