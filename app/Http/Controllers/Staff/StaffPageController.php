<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use App\Models\GuestAccess;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Http\Request;

class StaffPageController extends Controller
{
    public function show(Request $request)
    {
        return view('staff.dashboard', $this->pageContext($request) + [
            'stats' => [
                'pending_accesses' => GuestAccess::query()
                    ->where('status', GuestAccess::STATUS_PENDING)
                    ->whereHas('tableSession', fn ($query) => $query->where('status', TableSession::STATUS_OPEN))
                    ->count(),
                'active_orders' => Order::query()
                    ->whereIn('status', ['new', 'accepted', 'preparing', 'ready'])
                    ->count(),
                'open_tables' => TableSession::query()
                    ->where('status', TableSession::STATUS_OPEN)
                    ->count(),
                'checkout_tables' => TableSession::query()
                    ->where('status', TableSession::STATUS_CHECKOUT)
                    ->count(),
            ],
        ]);
    }

    public function orders(Request $request)
    {
        return view('staff.orders', $this->pageContext($request));
    }

    public function serviceRequests(Request $request)
    {
        return view('staff.service-requests', $this->pageContext($request));
    }

    public function tables(Request $request)
    {
        $sessions = TableSession::query()
            ->whereIn('status', [TableSession::STATUS_OPEN, TableSession::STATUS_CHECKOUT])
            ->with('table')
            ->withCount([
                'orders as active_orders_count' => fn ($query) => $query->whereIn(
                    'status',
                    ['new', 'accepted', 'preparing', 'ready']
                ),
            ])
            ->get()
            ->keyBy('table_id');

        return view('staff.tables', $this->pageContext($request) + [
            'tables' => CafeTable::query()
                ->where('is_active', true)
                ->orderBy('label')
                ->get(),
            'sessions' => $sessions,
        ]);
    }

    public function payments(Request $request)
    {
        return view('staff.payments', $this->pageContext($request) + [
            'sessions' => TableSession::query()
                ->where('status', TableSession::STATUS_CHECKOUT)
                ->with(['table', 'payment'])
                ->latest('updated_at')
                ->get(),
        ]);
    }

    private function pageContext(Request $request): array
    {
        return [
            'role' => $request->user()->role?->name ?? 'staff',
        ];
    }
}
