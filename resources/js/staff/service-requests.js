import { apiFetch } from '../core/http.js';
import { createPoller } from '../core/polling.js';

const TYPE_LABELS = {
    waiter: 'Appel serveur / نداء النادل',
    bill: 'Demande d’addition / طلب الحساب',
};

const STATUS_LABELS = {
    open: 'Nouveau / جديد',
    acknowledged: 'Pris en charge / تم التكفل به',
};

const ACTIONS = {
    open: {
        status: 'acknowledged',
        label: 'Prendre en charge / التكفل',
    },
    acknowledged: {
        status: 'resolved',
        label: 'Terminer / إنهاء',
    },
};

function element(tag, { className, text, type } = {}) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    if (type) node.type = type;
    return node;
}

function showFeedback(node, message, kind = 'info') {
    node.hidden = !message;
    node.dataset.kind = kind;
    node.textContent = message;
}

function requestCard(serviceRequest, { onAction }) {
    const card = element('article', { className: 'staff-service-request-card' });
    card.dataset.requestId = serviceRequest.id;

    const header = element('div', { className: 'staff-service-request-card__header' });
    header.append(element('strong', {
        text: serviceRequest.table?.label ?? `Session #${serviceRequest.table_session_id}`,
    }));
    header.append(element('span', {
        className: 'status',
        text: STATUS_LABELS[serviceRequest.status] ?? serviceRequest.status,
    }));

    const meta = element('p', {
        className: 'staff-service-request-card__meta',
        text: `#${serviceRequest.id} · ${TYPE_LABELS[serviceRequest.type] ?? serviceRequest.type}`,
    });

    const action = ACTIONS[serviceRequest.status];
    const button = element('button', {
        className: 'button button--primary',
        text: action?.label ?? 'Mettre à jour / تحديث',
        type: 'button',
    });

    if (action) {
        button.addEventListener('click', () => onAction(serviceRequest, action, card, button));
    } else {
        button.disabled = true;
    }

    card.append(header, meta, button);
    return card;
}

export function initStaffServiceRequests() {
    const root = document.querySelector('[data-staff-service-requests]');
    if (!root) return;

    const list = root.querySelector('[data-service-request-list]');
    const feedback = root.querySelector('[data-service-request-feedback]');
    const refreshButton = root.querySelector('[data-service-request-refresh]');
    let requests = [];

    const render = () => {
        list.replaceChildren();

        if (!requests.length) {
            list.append(element('p', {
                className: 'staff-empty',
                text: 'Aucune demande active / لا توجد طلبات حالية',
            }));
            return;
        }

        requests.forEach(serviceRequest => {
            list.append(requestCard(serviceRequest, {
                onAction: async (requestItem, action, card, button) => {
                    button.disabled = true;
                    card.dataset.state = 'busy';

                    try {
                        await apiFetch(`/staff/service-requests/${requestItem.id}/status`, {
                            method: 'PATCH',
                            body: { status: action.status },
                        });
                        showFeedback(feedback, 'Demande mise à jour / تم تحديث الطلب', 'success');
                        await refresh();
                    } catch (error) {
                        card.dataset.state = '';
                        button.disabled = false;
                        showFeedback(feedback, error.message ?? 'Action impossible / تعذر تنفيذ العملية', 'error');
                    }
                },
            }));
        });
    };

    const refresh = async () => {
        try {
            const payload = await apiFetch('/staff/service-requests/updates');
            requests = payload.service_requests ?? [];
            render();
        } catch (error) {
            showFeedback(feedback, error.message ?? 'Impossible de charger les demandes / تعذر تحميل الطلبات', 'error');
        }
    };

    refreshButton.addEventListener('click', refresh);
    createPoller(refresh, { intervalMs: 5000, maxIntervalMs: 30000 });
}
