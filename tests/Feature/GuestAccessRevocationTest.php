<?php

namespace Tests\Feature;

use App\Domain\Billing\CloseSessionService;
use App\Domain\Visits\RequestGuestAccessAction;
use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\GuestAccess;
use App\Models\QrCode;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessRevocationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private CafeTable $table;
    private QrCode $qrCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $cafe = Cafe::firstOrFail();
        app()->instance('current_cafe_id', $cafe->id);

        $this->manager = User::where('email', 'manager@demo.test')->firstOrFail();
        $this->table = CafeTable::where('label', 'Table 2')->firstOrFail();
        $this->qrCode = QrCode::where('table_id', $this->table->id)
            ->where('is_active', true)
            ->firstOrFail();

    }

    public function test_closing_a_session_revokes_approved_guest_access(): void
    {
        $session = $this->createOpenSession();
        $access = $this->createGuestAccess($session, 'approved-device');
        $access->update([
            'status' => GuestAccess::STATUS_APPROVED,
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);
        $session->update(['status' => TableSession::STATUS_CHECKOUT]);

        // The existing lifecycle test covers paid close; this focused regression
        // test uses the manager's documented unpaid exception path.
        (new CloseSessionService())->close($session, $this->manager, 'Test de révocation');

        $access->refresh();

        $this->assertSame(GuestAccess::STATUS_REVOKED, $access->status);
        $this->assertNotNull($access->revoked_at);
    }

    public function test_closing_a_session_revokes_pending_guest_access(): void
    {
        $session = $this->createOpenSession();
        $access = $this->createGuestAccess($session, 'pending-device');
        $session->update(['status' => TableSession::STATUS_CHECKOUT]);

        (new CloseSessionService())->close($session, $this->manager, 'Test de révocation');

        $access->refresh();

        $this->assertSame(GuestAccess::STATUS_REVOKED, $access->status);
        $this->assertNotNull($access->revoked_at);
    }

    private function createOpenSession(): TableSession
    {
        return TableSession::create([
            'cafe_id' => $this->manager->cafe_id,
            'table_id' => $this->table->id,
            'opened_by' => $this->manager->id,
            'opened_at' => now(),
            'status' => TableSession::STATUS_OPEN,
        ]);
    }

    private function createGuestAccess(TableSession $session, string $device): GuestAccess
    {
        $access = (new RequestGuestAccessAction())->execute(
            $this->qrCode->token,
            hash('sha256', $device),
        );

        $this->assertSame($session->id, $access->table_session_id);

        return $access;
    }
}
