<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Billing\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function store(
        Request $request,
        PaymentService $paymentService
    ): JsonResponse {
        $validated = $request->validate([
            'session_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'method' => [
                'required',
                'in:cash,card,other',
            ],
            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:64',
            ],
        ]);

        $session = TableSession::findOrFail(
            $validated['session_id']
        );

        $idempotencyKey = $validated['idempotency_key']
            ?? (string) Str::uuid();

        $payment = $paymentService->recordPayment(
            session: $session,
            staff: $request->user(),
            amount: (string) $validated['amount'],
            method: $validated['method'],
            idempotencyKey: $idempotencyKey,
            reference: $validated['reference'] ?? null
        );

        return response()->json([
            'message' => 'Paiement enregistré avec succès.',
            'payment' => $payment,
        ], 201);
    }
}
