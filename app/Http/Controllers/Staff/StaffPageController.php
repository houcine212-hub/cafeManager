<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StaffPageController extends Controller
{
    public function show(Request $request)
    {
        return view('staff.dashboard', [
            'role' => $request->user()->role?->name ?? 'staff',
        ]);
    }
}
