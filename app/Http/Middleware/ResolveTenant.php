<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'User account is inactive.',
            ], 403);
        }

        $cafe = DB::table('cafes')
            ->where('id', (int) $user->cafe_id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();

        if (! $cafe) {
            return response()->json([
                'message' => 'Cafe access is inactive.',
            ], 403);
        }

        // Override any context left by a previous request.
        app()->instance(
            'current_cafe_id',
            (int) $cafe->id
        );

        return $next($request);
    }
}