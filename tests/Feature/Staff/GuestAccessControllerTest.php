<?php

namespace Tests\Feature\Staff;

use App\Models\AuditLog;
use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\GuestAccess;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestAccessControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $manager;
    protected User $serveur;
    protected CafeTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->cafe = Cafe::firstOrFail();
        app()->instance('current_cafe_id', $this->cafe->id);
        $this->manager = User::where('cafe_id', $this->cafe->id)
            ->where('email', 'manager@demo.test')
            ->firstOrFail();
        $this->serveur = User::where('cafe_id', $this->cafe->id)
            ->where('email', 'serveur@demo.test')
            ->firstOrFail();
        $this->table = CafeTable::where('cafe_id', $this->cafe->id)
            ->where('label', 'Table 2')
            ->firstOrFail();
    }

    private function createPendingAccess(): GuestAccess
    {
        $session = TableSession::create([
            'cafe_id' => $this->cafe->id,
            'table_id' => $this->table->id,
            'opened_by' => $this->serveur->id,
            'status' => TableSession::STATUS_OPEN,
        ]);

        return GuestAccess::create([
            'cafe_id' => $this->cafe->id,
            'table_session_id' => $session->id,
            'token_hash' => hash('sha256', Str::random(32)),
            'status' => GuestAccess::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_list_guest_accesses(): void
    {
        $this->getJson('/staff/guest-accesses')->assertStatus(401);
    }

    public function test_staff_can_list_pending_guest_accesses(): void
    {
        $access = $this->createPendingAccess();

        $response = $this->actingAs($this->serveur)
            ->getJson('/staff/guest-accesses?status=pending');

        $response->assertOk()
            ->assertJsonPath('guest_accesses.0.id', $access->id)
            ->assertJsonPath('guest_accesses.0.status', GuestAccess::STATUS_PENDING)
            ->assertJsonPath('guest_accesses.0.table.label', $this->table->label);
    }

    public function test_staff_can_approve_pending_guest_access(): void
    {
        $access = $this->createPendingAccess();

        $response = $this->actingAs($this->serveur)
            ->postJson("/staff/guest-accesses/{$access->id}/approve");

        $response->assertOk()
            ->assertJson([
                'message' => 'Accès invité approuvé.',
                'guest_access' => [
                    'id' => $access->id,
                    'status' => GuestAccess::STATUS_APPROVED,
                ],
            ]);

        $this->assertDatabaseHas('guest_accesses', [
            'id' => $access->id,
            'status' => GuestAccess::STATUS_APPROVED,
            'approved_by' => $this->serveur->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'cafe_id' => $this->cafe->id,
            'action' => 'guest_access.approved',
            'entity_type' => 'guest_accesses',
            'entity_id' => $access->id,
            'actor_id' => $this->serveur->id,
        ]);
    }

    public function test_staff_can_revoke_approved_guest_access(): void
    {
        $access = $this->createPendingAccess();
        $access->update([
            'status' => GuestAccess::STATUS_APPROVED,
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->serveur)
            ->postJson("/staff/guest-accesses/{$access->id}/revoke");

        $response->assertOk()
            ->assertJsonPath('guest_access.status', GuestAccess::STATUS_REVOKED);

        $this->assertDatabaseHas('guest_accesses', [
            'id' => $access->id,
            'status' => GuestAccess::STATUS_REVOKED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'cafe_id' => $this->cafe->id,
            'action' => 'guest_access.revoked',
            'entity_id' => $access->id,
            'actor_id' => $this->serveur->id,
        ]);
    }

    public function test_staff_cannot_approve_access_after_session_is_closed(): void
    {
        $access = $this->createPendingAccess();
        $access->tableSession->update(['status' => TableSession::STATUS_CLOSED]);

        $response = $this->actingAs($this->serveur)
            ->postJson("/staff/guest-accesses/{$access->id}/approve");

        $response->assertStatus(409)
            ->assertJson(['error' => 'session_not_open']);
    }
}
