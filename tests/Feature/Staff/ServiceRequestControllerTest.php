<?php

namespace Tests\Feature\Staff;

use App\Models\AuditLog;
use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceRequestControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected Cafe $cafe;
    protected User $serveur;
    protected CafeTable $table;
    protected TableSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $cafeId = DB::table('cafes')->value('id');
        app()->instance('current_cafe_id', (int) $cafeId);
        $this->cafe = Cafe::findOrFail($cafeId);
        $this->serveur = User::where('email', 'serveur@demo.test')->firstOrFail();
        $this->table = CafeTable::where('label', 'Table 2')->firstOrFail();

        TableSession::where('table_id', $this->table->id)
            ->whereIn('status', [TableSession::STATUS_OPEN, TableSession::STATUS_CHECKOUT])
            ->update([
                'status' => TableSession::STATUS_CLOSED,
                'closure_reason' => TableSession::CLOSURE_EXCEPTION_UNPAID,
                'closed_at' => now(),
                'closed_by' => $this->serveur->id,
            ]);

        $this->session = TableSession::create([
            'cafe_id' => $this->cafe->id,
            'table_id' => $this->table->id,
            'opened_by' => $this->serveur->id,
            'status' => TableSession::STATUS_OPEN,
        ]);
    }

    private function createRequest(string $status = ServiceRequest::STATUS_OPEN): ServiceRequest
    {
        return ServiceRequest::create([
            'cafe_id' => $this->cafe->id,
            'table_session_id' => $this->session->id,
            'type' => ServiceRequest::TYPE_WAITER,
            'status' => $status,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_service_requests(): void
    {
        $this->getJson('/staff/service-requests/updates')
            ->assertUnauthorized();
    }

    public function test_staff_can_poll_active_service_requests_with_table_context(): void
    {
        $serviceRequest = $this->createRequest();

        $response = $this
            ->actingAs($this->serveur)
            ->getJson('/staff/service-requests/updates');

        $response
            ->assertOk()
            ->assertJsonPath('service_requests.0.id', $serviceRequest->id)
            ->assertJsonPath('service_requests.0.type', ServiceRequest::TYPE_WAITER)
            ->assertJsonPath('service_requests.0.table.label', $this->table->label);
    }

    public function test_staff_can_acknowledge_and_resolve_a_service_request(): void
    {
        $serviceRequest = $this->createRequest();

        $acknowledge = $this
            ->actingAs($this->serveur)
            ->patchJson("/staff/service-requests/{$serviceRequest->id}/status", [
                'status' => ServiceRequest::STATUS_ACKNOWLEDGED,
            ]);

        $acknowledge
            ->assertOk()
            ->assertJsonPath('service_request.status', ServiceRequest::STATUS_ACKNOWLEDGED)
            ->assertJsonPath('service_request.handled_by', $this->serveur->id);

        $resolve = $this
            ->actingAs($this->serveur)
            ->patchJson("/staff/service-requests/{$serviceRequest->id}/status", [
                'status' => ServiceRequest::STATUS_RESOLVED,
            ]);

        $resolve
            ->assertOk()
            ->assertJsonPath('service_request.status', ServiceRequest::STATUS_RESOLVED);

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => ServiceRequest::STATUS_RESOLVED,
            'handled_by' => $this->serveur->id,
        ]);

        $this->assertSame(2, AuditLog::where('entity_type', 'service_requests')
            ->where('entity_id', $serviceRequest->id)
            ->count());
    }

    public function test_staff_cannot_skip_acknowledgement_for_an_open_request(): void
    {
        $serviceRequest = $this->createRequest();

        $response = $this
            ->actingAs($this->serveur)
            ->patchJson("/staff/service-requests/{$serviceRequest->id}/status", [
                'status' => ServiceRequest::STATUS_RESOLVED,
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('error', 'invalid_service_request_transition');

        $this->assertSame(
            ServiceRequest::STATUS_OPEN,
            $serviceRequest->fresh()->status
        );
    }
}
