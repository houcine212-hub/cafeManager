import { createPoller } from '../core/polling.js';
import { approveGuestAccess, listPendingGuestAccesses, revokeGuestAccess } from './access-queue.js';

const LABELS = {
    fr: {
        emptyTitle: 'Aucune demande en attente',
        emptySub: 'Les nouvelles demandes d’accès des tables apparaîtront ici.',
        table: 'Table',
        approve: 'Approuver',
        reject: 'Refuser',
        approveSuccess: 'Accès approuvé',
        rejectSuccess: 'Accès refusé',
        actionError: 'Action impossible',
        loadError: 'Impossible de charger la liste',
        justNow: 'À l’instant',
        minutesAgo: n => `Il y a ${n} min`,
    },
    ar: {
        emptyTitle: 'لا توجد طلبات معلقة',
        emptySub: 'ستظهر هنا طلبات الدخول الجديدة من الطاولات.',
        table: 'الطاولة',
        approve: 'موافقة',
        reject: 'رفض',
        approveSuccess: 'تمت الموافقة على الدخول',
        rejectSuccess: 'تم رفض الدخول',
        actionError: 'تعذر تنفيذ العملية',
        loadError: 'تعذر تحميل القائمة',
        justNow: 'الآن',
        minutesAgo: n => `منذ ${n} د`,
    },
};

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

function formatAge(value) {
    const labels = t();
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    const minutes = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60000));
    return minutes < 1 ? labels.justNow : labels.minutesAgo(minutes);
}

const ICON_APPROVE = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>';
const ICON_REJECT = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>';
const ICON_TABLE = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6.5" r="2.75"/><path d="M2.5 16c.6-3 2.8-4.5 5.5-4.5s4.9 1.5 5.5 4.5"/></svg>';

function emptyStateNode() {
    const labels = t();
    const wrap = element('div', { className: 'staff-empty-state' });
    wrap.innerHTML = `
        <svg class="empty-illustration" viewBox="0 0 160 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <ellipse cx="80" cy="122" rx="52" ry="8" fill="var(--color-line)" opacity=".5"/>
            <rect x="46" y="18" width="68" height="86" rx="10" fill="var(--color-paper)" stroke="var(--color-line)" stroke-width="2"/>
            <rect x="62" y="10" width="36" height="16" rx="6" fill="var(--color-green-soft)" stroke="var(--color-green)" stroke-width="2"/>
            <path d="M60 44h40M60 58h40M60 72h26" stroke="var(--color-line)" stroke-width="3" stroke-linecap="round"/>
            <circle cx="112" cy="86" r="18" fill="var(--color-green)"/>
            <path d="M104 86l6 6 12-13" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <strong>${labels.emptyTitle}</strong>
        <p>${labels.emptySub}</p>
    `;
    return wrap;
}

export function initStaffDashboard() {
    const root = document.querySelector('[data-staff-dashboard]');
    if (!root) return;

    const list = root.querySelector('[data-access-list]');
    const feedback = root.querySelector('[data-access-feedback]');
    const count = root.querySelector('[data-access-count]');
    const refreshButton = root.querySelector('[data-access-refresh]');

    const render = accesses => {
        const labels = t();
        list.replaceChildren();
        count.textContent = String(accesses.length);

        if (!accesses.length) {
            list.append(emptyStateNode());
            return;
        }

        accesses.forEach(access => {
            const card = element('article', { className: 'staff-access-card' });
            card.dataset.accessId = access.id;

            const context = element('div', { className: 'staff-access-card__context' });
            const icon = element('span', { className: 'staff-access-card__icon', html: ICON_TABLE });
            const text = element('div', { className: 'staff-access-card__text' });
            text.append(element('strong', { text: `${access.table?.label ?? labels.table}` }));
            text.append(element('small', { text: `${formatAge(access.requested_at)} · #${access.table_session_id}` }));
            context.append(icon, text);

            const actions = element('div', { className: 'staff-access-card__actions' });
            const approve = element('button', { className: 'button button--primary', type: 'button' });
            approve.innerHTML = `${ICON_APPROVE}<span>${labels.approve}</span>`;
            const revoke = element('button', { className: 'button button--danger', type: 'button' });
            revoke.innerHTML = `${ICON_REJECT}<span>${labels.reject}</span>`;

            const run = async (action, successMessage) => {
                card.dataset.state = 'busy';
                try {
                    await action(access.id);
                    showFeedback(feedback, successMessage, 'success');
                    await refresh();
                } catch (error) {
                    showFeedback(feedback, error.message ?? labels.actionError, 'error');
                    card.dataset.state = '';
                }
            };

            approve.addEventListener('click', () => run(approveGuestAccess, labels.approveSuccess));
            revoke.addEventListener('click', () => run(revokeGuestAccess, labels.rejectSuccess));
            actions.append(approve, revoke);
            card.append(context, actions);
            list.append(card);
        });
    };

    const refresh = async () => {
        try {
            const payload = await listPendingGuestAccesses();
            render(payload.guest_accesses ?? []);
        } catch (error) {
            showFeedback(feedback, error.message ?? t().loadError, 'error');
        }
    };

    refreshButton.addEventListener('click', refresh);
    createPoller(refresh, { intervalMs: 5000, maxIntervalMs: 30000 });
}