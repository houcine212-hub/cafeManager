<?php

namespace App\Domain\Visits;

use App\Domain\Visits\Exceptions\ActiveSessionAlreadyHasAccessException;
use App\Domain\Visits\Exceptions\InvalidQrCodeException;
use App\Domain\Visits\Exceptions\NoActiveSessionException;
use App\Models\GuestAccess;
use App\Models\QrCode;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestGuestAccessAction
{
    /**
     * تنفيذ طلب دخول الزبون عبر الـ QR وفق القرار D09.
     *
     * ⚠️ ملاحظة تصميم مهمة:
     * current_cafe_id كيتعمّر هنا صراحة، جوا الـ transaction، فور ما
     * نعرفو cafe_id من الـ QR token. ما كنعتمدوش على middleware خارجي
     * حيت الزبون داخل مجهول (بلا session موظف تعرف المقهى مسبقاً) —
     * الـ QR هو المصدر الوحيد اللي كيعرفنا بيه.
     *
     * app()->instance() كيبقى مربوط طول الـ request/worker الحالي.
     * فـ PHP-FPM (stateless per request) هادشي آمن. الخطر الوحيد:
     * ما نستدعيوش هاد الـ action من جوا queue worker طويل العمر
     * (queue:work) بلا ما نعاودو نعمرو current_cafe_id لكل job —
     * queue:work ماشي فـ allowlist ديال tenantScopeBypassed() عمداً.
     *
     * الـ queries هنا كتستعمل withoutGlobalScope('cafe') بشكل صريح ومحدود
     * قبل ما نعرفو cafe_id، مع فلترة يدوية عليه.
     *
     * @param string $qrToken رمز الـ QR (64 حرفاً)
     * @param string $cookieTokenHash الهاش SHA-256 ديال كوكيز الزبون
     * @return GuestAccess
     */
    public function execute(string $qrToken, string $cookieTokenHash): GuestAccess
    {
        $this->assertValidHashFormat($cookieTokenHash);

        return DB::transaction(function () use ($qrToken, $cookieTokenHash) {
            // 1. جلب رمز الـ QR والتأكد من أنه نشط، مع قفله.
            //    lockForUpdate هنا يمنع أن الموظف يسحب (revoke) الرمز
            //    فنفس اللحظة اللي كيتقرا فيها هنا.
            $qr = QrCode::withoutGlobalScope('cafe')
                ->where('token', $qrToken)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $qr) {
                // رمز غير موجود ورمز ملغي كيعطيو نفس الاستثناء عمداً —
                // ما نكشفوش للمهاجم واش الرمز كان موجود قبل ولا لا.
                throw new InvalidQrCodeException();
            }

            $cafeId = (int) $qr->cafe_id;

            // تعمير current_cafe_id فالـ container فور ما نعرفو المقهى.
            // خاص يتدار قبل أي كتابة (GuestAccess::save) حيت BelongsToCafe
            // كيرفض أي إنشاء بلا tenant context نشط (fail-closed).
            app()->instance('current_cafe_id', $cafeId);

            // 2. التحقق من وجود جلسة مفتوحة للطاولة.
            //    غير 'open' — الجلسة فـ 'checkout' الحساب ديالها مجمّد
            //    والطلبات موقوفة (القسم 07)، إذن وصول جديد ما عندو معنى.
            $session = TableSession::where('cafe_id', $cafeId)
                ->where('table_id', $qr->table_id)
                ->where('status', TableSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new NoActiveSessionException();
            }

            // 3. فحص D09: واش كاين وصول نشط حالياً لنفس الجلسة؟
            $existingActive = GuestAccess::where('cafe_id', $cafeId)
                ->where('table_session_id', $session->id)
                ->whereIn('status', [GuestAccess::STATUS_PENDING, GuestAccess::STATUS_APPROVED])
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                // ⚠️ hash_equals() إجباري هنا — ماشي ===.
                // token_hash هو المفتاح الوحيد اللي كيفرق بين "نفس الزبون
                // كيعاود يشارجي" و "شخص أجنبي بغا يدخل للطاولة". مقارنة
                // عادية عرضة لـ timing attack (القسم 13).
                if (hash_equals((string) $existingActive->token_hash, $cookieTokenHash)) {
                    return $existingActive;
                }

                // شخص آخر كيحاول يفتح وصول متزامن -> رفض فوري (409)
                throw new ActiveSessionAlreadyHasAccessException();
            }

            // 4. إنشاء طلب وصول جديد بحالة pending فانتظار موافقة السرباي.
            $access = new GuestAccess([
                'cafe_id' => $cafeId,
                'table_session_id' => $session->id,
                'token_hash' => $cookieTokenHash,
                'status' => GuestAccess::STATUS_PENDING,
                'requested_at' => now(),
            ]);

            $access->save();

            return $access;
        });
    }

    /**
     * فحص شكل الـ hash قبل أي DB query — SHA-256 hex = 64 حرف.
     * كيمنع قيم فاسدة توصل للعمود char(64) وتتقطع بصمت.
     */
    private function assertValidHashFormat(string $hash): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            throw new RuntimeException('Format de token client invalide (SHA-256 hex attendu).');
        }
    }
}
