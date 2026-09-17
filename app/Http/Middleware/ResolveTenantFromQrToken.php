<?php

namespace App\Http\Middleware;

use App\Models\QrCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromQrToken
{
    /**
     * تحديد المقهى (current_cafe_id) من رمز الـ QR ديال الـ route (/q/{token}).
     *
     * هاد الـ Middleware خاصو يخدم قبل ResolveTenant فمسارات الزبون،
     * حيت الزبون ما عندوش auth()->user() ولا guest_access محدد مسبقاً —
     * الـ QR token هو المصدر الوحيد الموثوق لمعرفة المقهى (نفس المبدأ
     * المشروح فـ RequestGuestAccessAction).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        $qr = QrCode::withoutGlobalScope('cafe')
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $qr) {
            abort(404, 'QR code invalide ou révoqué.');
        }

        app()->instance('current_cafe_id', $qr->cafe_id);

        // كنحطو الـ QR فالـ attributes باش الـ Controller يستعملها بلا
        // إعادة query (وباش ResolveTenant العادي، إذا تنادى بعدها، يلقى
        // current_cafe_id ديجا معمر ويسكت).
        $request->attributes->set('qr_code', $qr);

        return $next($request);
    }
}