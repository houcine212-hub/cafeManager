<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = ServiceRequest::query()
            ->whereIn('status', [
                ServiceRequest::STATUS_OPEN,
                ServiceRequest::STATUS_ACKNOWLEDGED,
            ])
            ->with(['tableSession.table', 'handledBy'])
            ->latest('id')
            ->get()
            ->map(fn (ServiceRequest $serviceRequest) => $this->present($serviceRequest));

        return response()->json([
            'service_requests' => $requests,
        ]);
    }

    public function update(
        Request $request,
        int|string $serviceRequestId
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:acknowledged,resolved,cancelled',
            ],
        ]);

        return DB::transaction(function () use ($request, $serviceRequestId, $validated) {
            $serviceRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequestId);

            $nextStatus = $validated['status'];
            $allowedTransitions = [
                ServiceRequest::STATUS_OPEN => [
                    ServiceRequest::STATUS_ACKNOWLEDGED,
                    ServiceRequest::STATUS_CANCELLED,
                ],
                ServiceRequest::STATUS_ACKNOWLEDGED => [
                    ServiceRequest::STATUS_RESOLVED,
                    ServiceRequest::STATUS_CANCELLED,
                ],
            ];

            if (! in_array($nextStatus, $allowedTransitions[$serviceRequest->status] ?? [], true)) {
                return response()->json([
                    'error' => 'invalid_service_request_transition',
                    'message' => 'Cette demande ne peut plus changer vers cet état.',
                ], 409);
            }

            $beforeState = $serviceRequest->only(['status', 'handled_by']);

            $serviceRequest->update([
                'status' => $nextStatus,
                'handled_by' => $request->user()->id,
            ]);

            AuditLog::create([
                'cafe_id' => $serviceRequest->cafe_id,
                'actor_type' => AuditLog::ACTOR_USER,
                'actor_id' => $request->user()->id,
                'action' => "service_request.{$nextStatus}",
                'entity_type' => 'service_requests',
                'entity_id' => $serviceRequest->id,
                'before_state' => $beforeState,
                'after_state' => $serviceRequest->only(['status', 'handled_by']),
                'reason' => 'Traitement par le personnel.',
                'occurred_at' => now(),
            ]);

            return response()->json([
                'message' => 'Demande mise à jour.',
                'service_request' => $this->present($serviceRequest->load([
                    'tableSession.table',
                    'handledBy',
                ])),
            ]);
        });
    }

    private function present(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => $serviceRequest->id,
            'type' => $serviceRequest->type,
            'status' => $serviceRequest->status,
            'table_session_id' => $serviceRequest->table_session_id,
            'created_at' => $serviceRequest->created_at,
            'updated_at' => $serviceRequest->updated_at,
            'handled_by' => $serviceRequest->handled_by,
            'handled_by_name' => $serviceRequest->handledBy?->name,
            'table' => [
                'id' => $serviceRequest->tableSession?->table?->id,
                'label' => $serviceRequest->tableSession?->table?->label,
            ],
        ];
    }
}
