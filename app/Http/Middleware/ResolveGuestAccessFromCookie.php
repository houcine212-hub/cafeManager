<?php

namespace App\Http\Middleware;

use App\Models\GuestAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveGuestAccessFromCookie
{
    public const COOKIE_NAME = 'guest_device_token';

    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->cookie(self::COOKIE_NAME);

        // 🔬 DEBUG مؤقت — نمسحوه من بعد ما نلقاو المشكل
        if (app()->environment('testing')) {
            Log::info('MIDDLEWARE_COOKIE_DEBUG', [
                'all_cookies_raw' => $request->cookies->all(),
                'target_cookie_value' => $rawToken,
            ]);
        }

        if ($rawToken) {
            $hash = hash('sha256', $rawToken);

            $guestAccess = GuestAccess::where('token_hash', $hash)->first();

            $request->attributes->set('guest_access', $guestAccess);
            $request->attributes->set('guest_cookie_hash', $hash);
        }

        return $next($request);
    }
}
