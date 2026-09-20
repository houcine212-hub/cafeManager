import { apiFetch } from '../core/http.js';
import { createPoller } from '../core/polling.js';

const COLUMNS = ['new', 'preparing', 'ready', 'served'];

// Orders in `accepted` are shown in the "preparing" column: from the floor's
// point of view an accepted order is already being worked on.
const COLUMN_OF_STATUS = {
    new: 'new',
    accepted: 'preparing',
    preparing: 'preparing',
    ready: 'ready',
    served: 'served',
};

const NEXT_STATUS = {
    new: 'accepted',
    accepted: 'preparing',
    preparing: 'ready',
    ready: 'served',
};

const ACTION_LABEL_KEY = {
    new: 'accept',
    accepted: 'startPrep',
    preparing: 'markReady',
    ready: 'serve',
};

const LABELS = {
    fr: {
        columnNew: 'Nouvelles',
        columnPreparing: 'En préparation',
        columnReady: 'Prêtes',
        columnServed: 'Servies',
        accept: 'Accepter',
        startPrep: 'Démarrer',
        markReady: 'Marquer prête',
        serve: 'Servir',
        noOrders: 'Aucune commande',
        qr: 'QR',
        staff: 'Staff',
        session: 'Session',
        updateSuccess: 'Statut mis à jour',
        updateError: 'Action impossible',
        loadError: 'Impossible de charger les commandes',
        note: 'Note',
    },
    ar: {
        columnNew: 'جديدة',
        columnPreparing: 'قيد التحضير',
        columnReady: 'جاهزة',
        columnServed: 'تم التقديم',
        accept: 'قبول',
        startPrep: 'بدء التحضير',
        markReady: 'جاهز',
        serve: 'تقديم',
        noOrders: 'لا توجد طلبات',
        qr: 'زبون',
        staff: 'موظف',
        session: 'الجلسة',
        updateSuccess: 'تم تحديث الحالة',
        updateError: 'تعذر تنفيذ العملية',
        loadError: 'تعذر تحميل الطلبات',
        note: 'ملاحظة',
    },
};

const COLUMN_TITLE_KEY = {
    new: 'columnNew',
    preparing: 'columnPreparing',
    ready: 'columnReady',
    served: 'columnServed',
};

const ICON_ARROW = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h11M11 5l5 5-5 5"/></svg>';

function locale() {
    return document.documentElement.lang?.startsWith('ar') ? 'ar' : 'fr';
}

function t() {
    return LABELS[locale()];
}

function element(tag, { className, text, type, html } = {}) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    if (html !== undefined) node.innerHTML = html;
    if (type) node.type = type;
    return node;
}

function showFeedback(node, message, kind = 'info') {
    node.hidden = !message;
    node.dataset.kind = kind;
    node.textContent = message;
}

export function initStaffOrderQueue() {
    const root = document.querySelector('[data-staff-dashboard]');
    if (!root) return;

    const board = root.querySelector('[data-order-board]');
    const feedback = root.querySelector('[data-order-feedback]');
    const refreshButton = root.querySelector('[data-order-refresh]');
    const orders = new Map();
    let cursor = null;

    const orderCard = order => {
        const labels = t();
        const card = element('article', { className: 'staff-order-card' });
        card.dataset.orderId = order.id;

        const header = element('div', { className: 'staff-order-card__header' });
        const context = order.table_session?.table?.label ?? `${labels.session} #${order.table_session_id}`;
        header.append(element('strong', { text: `#${order.id} · ${context}` }));
        card.append(header);

        card.append(element('p', {
            className: 'staff-order-card__meta',
            text: order.source === 'qr' ? labels.qr : labels.staff,
        }));

        const items = element('div', { className: 'staff-order-card__items' });
        (order.order_items ?? []).forEach(item => {
            const row = element('div', { className: 'staff-order-card__item' });
            const details = element('div', { className: 'staff-order-card__item-details' });
            details.append(element('span', { text: item.product_name_snapshot ?? item.product?.name ?? '—' }));
            if (item.note) {
                details.append(element('small', {
                    className: 'staff-order-card__note',
                    text: `${labels.note}: ${item.note}`,
                }));
            }
            row.append(details);
            row.append(element('strong', { text: `×${item.quantity}` }));
            if (item.note) row.title = item.note;
            items.append(row);
        });
        card.append(items);

        const nextStatus = NEXT_STATUS[order.status];
        if (nextStatus) {
            const button = element('button', { className: 'button button--primary', type: 'button' });
            button.innerHTML = `<span>${labels[ACTION_LABEL_KEY[order.status]]}</span>${ICON_ARROW}`;
            button.addEventListener('click', async () => {
                card.dataset.state = 'busy';
                try {
                    await apiFetch(`/staff/orders/${order.id}/status`, {
                        method: 'PATCH',
                        body: { status: nextStatus, reason: 'Traitement staff.' },
                    });
                    showFeedback(feedback, t().updateSuccess, 'success');
                    await refresh(true);
                } catch (error) {
                    showFeedback(feedback, error.message ?? t().updateError, 'error');
                    cursor = null;
                    orders.clear();
                    await safeRefresh(true);
                } finally {
                    card.dataset.state = '';
                }
            });
            card.append(button);
        }

        return card;
    };

    const render = () => {
        const labels = t();
        board.replaceChildren();

        const buckets = { new: [], preparing: [], ready: [], served: [] };
        [...orders.values()].forEach(order => {
            const column = COLUMN_OF_STATUS[order.status];
            if (column) buckets[column].push(order);
        });

        COLUMNS.forEach(columnKey => {
            const columnOrders = buckets[columnKey].sort((a, b) => Number(b.id) - Number(a.id));

            const column = element('div', { className: 'staff-order-column' });
            column.dataset.column = columnKey;

            const head = element('div', { className: 'staff-order-column__head' });
            head.append(element('span', { text: labels[COLUMN_TITLE_KEY[columnKey]] }));
            head.append(element('span', { className: 'staff-order-column__count', text: String(columnOrders.length) }));
            column.append(head);

            const listNode = element('div', { className: 'staff-order-column__list' });
            if (!columnOrders.length) {
                listNode.append(element('p', { className: 'staff-order-column__empty', text: labels.noOrders }));
            } else {
                columnOrders.forEach(order => listNode.append(orderCard(order)));
            }
            column.append(listNode);

            board.append(column);
        });
    };

    const refresh = async (full = false) => {
        if (full) {
            cursor = null;
            orders.clear();
        }
        const url = cursor === null
            ? '/staff/orders/updates'
            : `/staff/orders/updates?since=${encodeURIComponent(cursor)}`;
        const payload = await apiFetch(url);
        cursor = payload.server_time;
        (payload.orders ?? []).forEach(order => orders.set(order.id, order));
        render();
    };

    const safeRefresh = async (full = false) => {
        try {
            await refresh(full);
        } catch (error) {
            showFeedback(feedback, error.message ?? t().loadError, 'error');
        }
    };

    refreshButton.addEventListener('click', () => safeRefresh(true));
    createPoller(safeRefresh, { intervalMs: 5000, maxIntervalMs: 30000 });
}