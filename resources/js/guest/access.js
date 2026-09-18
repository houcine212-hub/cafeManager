import { apiFetch, ConflictError } from '../core/http.js';
import { createPoller } from '../core/polling.js';

const messages = {
    pending: 'En attente de l’approbation du personnel / في انتظار موافقة الموظفين',
    approved: 'Accès approuvé / تمت الموافقة على الدخول',
    revoked: 'Accès révoqué / تم إلغاء الدخول',
    expired: 'Session expirée / انتهت الجلسة',
    not_found: 'Aucun accès actif pour cet appareil / لا يوجد دخول نشط لهذا الجهاز',
    conflict: 'Une demande est déjà en traitement / يوجد طلب آخر قيد المعالجة',
};

export function initGuestAccess(basePath, { onState, onMessage } = {}) {
    let poller = null;

    const stop = () => {
        poller?.stop();
        poller = null;
    };

    const applyState = status => {
        onState?.(status);
        onMessage?.(
            messages[status] ?? 'État inconnu / حالة غير معروفة',
        );

        if (['approved', 'revoked', 'expired', 'not_found'].includes(status)) {
            stop();
        }
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

            onMessage?.(
                'Impossible de vérifier l’accès / تعذر التحقق من الدخول',
            );

            throw error;
        }
    };

    const startPolling = () => {
        stop();

        poller = createPoller(readStatus, {
            intervalMs: 3000,
            maxIntervalMs: 15000,
        });
    };

    const requestAccess = async () => {
        try {
            const data = await apiFetch(`${basePath}/access-requests`, {
                method: 'POST',
            });

            applyState(data.status ?? 'pending');
            startPolling();

            return data;
        } catch (error) {
            if (error instanceof ConflictError) {
                applyState('conflict');
                return null;
            }

            onMessage?.(
                'La demande n’a pas pu être envoyée / تعذر إرسال الطلب',
            );

            throw error;
        }
    };

    readStatus()
        .then(data => {
            if (data?.status === 'pending') {
                startPolling();
            }
        })
        .catch(() => {});

    return {
        requestAccess,
        refresh: readStatus,
        stop,
    };
}
