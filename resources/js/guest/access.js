import { apiFetch, ConflictError } from '../core/http.js';
import { createPoller } from '../core/polling.js';

function locale() {
    return document.documentElement.lang?.startsWith('ar') ? 'ar' : 'fr';
}

const MESSAGES = {
    fr: {
        pending: 'En attente de l’approbation du personnel',
        approved: 'Accès approuvé',
        revoked: 'Accès révoqué',
        expired: 'Session expirée',
        not_found: 'Aucun accès actif pour cet appareil',
        conflict: 'La demande ne peut pas être traitée maintenant',
        unknown: 'État inconnu',
    },
    ar: {
        pending: 'في انتظار موافقة الموظفين',
        approved: 'تمت الموافقة على الدخول',
        revoked: 'تم إلغاء الدخول',
        expired: 'انتهت الجلسة',
        not_found: 'لا يوجد دخول نشط لهذا الجهاز',
        conflict: 'لا يمكن معالجة الطلب حالياً',
        unknown: 'حالة غير معروفة',
    },
};

const CONFLICT_MESSAGES = {
    fr: {
        InvalidQrCodeException: 'QR code invalide',
        NoActiveSessionException: 'Aucune session active pour cette table',
        ActiveSessionAlreadyHasAccessException: 'Cette table a déjà un accès actif',
    },
    ar: {
        InvalidQrCodeException: 'رمز QR غير صالح',
        NoActiveSessionException: 'لا توجد جلسة مفتوحة لهذه الطاولة',
        ActiveSessionAlreadyHasAccessException: 'هذه الطاولة لديها دخول نشط بالفعل',
    },
};

export function initGuestAccess(basePath, { onState, onMessage } = {}) {
    let poller = null;

    const stop = () => {
        poller?.stop();
        poller = null;
    };

    const applyState = status => {
        onState?.(status);
        onMessage?.(MESSAGES[locale()][status] ?? MESSAGES[locale()].unknown);
        if (['approved', 'revoked', 'expired', 'not_found'].includes(status)) stop();
    };

    const readStatus = async () => {
        try {
            const data = await apiFetch(`${basePath}/access-status`);
            applyState(data.status);
            return data;
        } catch (error) {
            if (error.status === 404) {
                applyState('not_found');
                return null;
            }
            onMessage?.(locale() === 'ar' ? 'تعذر التحقق من الدخول' : 'Impossible de vérifier l’accès');
            throw error;
        }
    };

    const startPolling = () => {
        stop();
        poller = createPoller(readStatus, { intervalMs: 3000, maxIntervalMs: 15000 });
    };

    const requestAccess = async () => {
        try {
            const data = await apiFetch(`${basePath}/access-requests`, { method: 'POST' });
            applyState(data.status ?? 'pending');
            startPolling();
            return data;
        } catch (error) {
            if (error instanceof ConflictError) {
                applyState('conflict');
                onMessage?.(CONFLICT_MESSAGES[locale()][error.data?.error] ?? error.message ?? MESSAGES[locale()].conflict);
                return null;
            }
            onMessage?.(locale() === 'ar' ? 'تعذر إرسال الطلب' : 'La demande n’a pas pu être envoyée');
            throw error;
        }
    };

    readStatus().then(data => {
        if (data?.status === 'pending') startPolling();
    }).catch(() => {});

    return { requestAccess, refresh: readStatus, stop };
}