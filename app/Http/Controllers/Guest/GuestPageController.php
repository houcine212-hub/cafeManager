<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GuestPageController extends Controller
{
    public function show(Request $request, string $token)
    {
        $qr = $request->attributes->get('qr_code');

        return view('guest.menu', [
            'table' => $qr->table->label,
        ]);
    }
}
