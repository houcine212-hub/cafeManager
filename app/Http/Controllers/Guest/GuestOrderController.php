<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Ordering\CreateOrderAction;
use App\Domain\Visits\Exceptions\ActiveSessionAlreadyHasAccessException;
use App\Domain\Visits\Exceptions\InvalidQrCodeException;
use App\Domain\Visits\Exceptions\NoActiveSessionException;
use App\Domain\Visits\RequestGuestAccessAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveGuestAccessFromCookie;
use App\Http\Requests\Guest\RequestAccessRequest;
use App\Http\Requests\Guest\ServiceRequestRequest;
use App\Http\Requests\Guest\SubmitOrderRequest;
use App\Models\Category;
use App\Models\GuestAccess;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestOrderController extends Controller
{
    public function menu(Request $request, string $token): JsonResponse
    {
        /** @var QrCode $qr */
        $qr = $request->attributes->get('qr_code');

        $categories = Category::where('cafe_id', $qr->cafe_id)
            ->where('is_active', true)
            ->with(['products' => function ($query) use ($qr) {
                $query->where('cafe_id', $qr->cafe_id)
                    ->availableForOrder()
                    ->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'table' => $qr->table->label,
            'categories' => $categories,
        ]);
    }

    public function requestAccess(
        RequestAccessRequest $request,
        string $token,
        RequestGuestAccessAction $action
    ): JsonResponse {
        /** @var QrCode $qr */
        $qr = $request->attributes->get('qr_code');

        $rawToken = $request->cookie(ResolveGuestAccessFromCookie::COOKIE_NAME)
            ?? bin2hex(random_bytes(32));

        $cookieHash = hash('sha256', $rawToken);

        try {
            $guestAccess = $action->execute($qr->token, $cookieHash);
        } catch (InvalidQrCodeException|NoActiveSessionException|ActiveSessionAlreadyHasAccessException $e) {
            return response()->json([
                'error' => class_basename($e),
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()
            ->json([
                'message' => "Demande d'accès enregistrée.",
                'guest_access_id' => $guestAccess->id,
                'status' => $guestAccess->status,
            ], 201)
            ->cookie(
                ResolveGuestAccessFromCookie::COOKIE_NAME,
                $rawToken,
                60 * 12,
                '/',
                null,
                true,
                true,
                false,
                'lax'
            );
    }

    public function checkStatus(Request $request, string $token): JsonResponse
    {
        /** @var QrCode $qr */
        $qr = $request->attributes->get('qr_code');
        /** @var GuestAccess|null $access */
        $access = $request->attributes->get('guest_access');

        if (! $access || ! $this->accessBelongsToQrSession($access, $qr, true)) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => $access->status,
            'is_approved' => $access->isApproved(),
        ]);
    }

    public function storeOrder(
        SubmitOrderRequest $request,
        string $token,
        CreateOrderAction $action
    ): JsonResponse {
        /** @var QrCode $qr */
        $qr = $request->attributes->get('qr_code');
        /** @var GuestAccess|null $guestAccess */
        $guestAccess = $request->attributes->get('guest_access');

        if (! $guestAccess || ! $guestAccess->isApproved()) {
            return $this->unauthorizedGuestResponse(
                'Vous devez attendre la validation du serveur avant de commander.'
            );
        }

        $session = $this->openSessionForQr($qr);

        if (! $session) {
            return response()->json([
                'error' => 'session_not_open',
                'message' => "La session de cette table n'est plus active.",
            ], 409);
        }

        if (! $this->accessBelongsToSession($guestAccess, $session)) {
            return $this->unauthorizedGuestResponse(
                'Cet accès invité ne correspond pas à cette session.'
            );
        }

        $order = $action->execute(
            cafeId: $qr->cafe_id,
            sessionId: $session->id,
            items: $request->validated('items'),
            idempotencyKey: $request->validated('idempotency_key'),
            source: 'qr',
            guestAccessId: $guestAccess->id,
            userId: null
        );

        return response()->json([
            'message' => 'Commande transmise avec succès.',
            'order' => $order,
        ], 201);
    }

    public function myOrders(Request $request, string $token): JsonResponse
    {
        /** @var QrCode $qr */
        $qr = $request->attributes->get('qr_code');
        /** @var GuestAccess|null $guestAccess */
        $guestAccess = $request->attributes->get('guest_access');

        if (! $guestAccess || ! $this->accessBelongsToQrSession($guestAccess, $qr, true)) {
            return response()->json(['orders' => []]);
        }

        $session = $this->activeSessionForQr($qr);

        if (! $session || ! $this->accessBelongsToSession($guestAccess, $session)) {
            return response()->json(['orders' => []]);
        }

        $orders = Order::where('cafe_id', $qr->cafe_id)
            ->where('table_session_id', $session->id)
            ->where('guest_access_id', $guestAccess->id)
            ->with('orderItems')
            ->latest('id')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function serviceRequest(ServiceRequestRequest $request, string $token): JsonResponse
    {
        /** @var QrCode $qr */
        $qr = $request->attributes->get('qr_code');
        /** @var GuestAccess|null $guestAccess */
        $guestAccess = $request->attributes->get('guest_access');

        if (! $guestAccess || ! $guestAccess->isApproved()) {
            return $this->unauthorizedGuestResponse('Accès non autorisé pour cette action.');
        }

        $session = $this->openSessionForQr($qr);

        if (! $session) {
            return response()->json([
                'error' => 'session_not_open',
                'message' => "La session de cette table n'est plus active.",
            ], 409);
        }

        if (! $this->accessBelongsToSession($guestAccess, $session)) {
            return $this->unauthorizedGuestResponse(
                'Cet accès invité ne correspond pas à cette session.'
            );
        }

        $existing = ServiceRequest::where('cafe_id', $qr->cafe_id)
            ->where('table_session_id', $session->id)
            ->where('type', $request->validated('type'))
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Demande déjà enregistrée et en cours de traitement.',
                'request' => $existing,
            ]);
        }

        $serviceRequest = ServiceRequest::create([
            'cafe_id' => $qr->cafe_id,
            'table_session_id' => $session->id,
            'guest_access_id' => $guestAccess->id,
            'type' => $request->validated('type'),
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Demande envoyée au serveur.',
            'request' => $serviceRequest,
        ], 201);
    }

    private function openSessionForQr(QrCode $qr): ?TableSession
    {
        return TableSession::where('cafe_id', $qr->cafe_id)
            ->where('table_id', $qr->table_id)
            ->where('status', TableSession::STATUS_OPEN)
            ->first();
    }

    private function activeSessionForQr(QrCode $qr): ?TableSession
    {
        return TableSession::where('cafe_id', $qr->cafe_id)
            ->where('table_id', $qr->table_id)
            ->whereIn('status', [TableSession::STATUS_OPEN, TableSession::STATUS_CHECKOUT])
            ->first();
    }

    private function accessBelongsToQrSession(
        GuestAccess $access,
        QrCode $qr,
        bool $activeOnly
    ): bool {
        if ((int) $access->cafe_id !== (int) $qr->cafe_id) {
            return false;
        }

        $query = TableSession::where('id', $access->table_session_id)
            ->where('cafe_id', $qr->cafe_id)
            ->where('table_id', $qr->table_id);

        if ($activeOnly) {
            $query->whereIn('status', [TableSession::STATUS_OPEN, TableSession::STATUS_CHECKOUT]);
        }

        return $query->exists();
    }

    private function accessBelongsToSession(GuestAccess $access, TableSession $session): bool
    {
        return (int) $access->cafe_id === (int) $session->cafe_id
            && (int) $access->table_session_id === (int) $session->id;
    }

    private function unauthorizedGuestResponse(string $message): JsonResponse
    {
        return response()->json([
            'error' => 'unauthorized_guest',
            'message' => $message,
        ], 403);
    }
}
