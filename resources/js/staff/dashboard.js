import { createPoller } from '../core/polling.js';
import { approveGuestAccess, listPendingGuestAccesses, revokeGuestAccess } from './access-queue.js';

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

function formatAge(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    const minutes = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60000));
    return minutes < 1 ? 'À l’instant / الآن' : `Il y a ${minutes} min / منذ ${minutes} د`;
}

export function initStaffDashboard() {
    const root = document.querySelector('[data-staff-dashboard]');
    if (!root) return;

    const list = root.querySelector('[data-access-list]');
    const feedback = root.querySelector('[data-access-feedback]');
    const count = root.querySelector('[data-access-count]');
    const refreshButton = root.querySelector('[data-access-refresh]');

    const render = accesses => {
        list.replaceChildren();
        count.textContent = String(accesses.length);

        if (!accesses.length) {
            list.append(element('p', {
                className: 'staff-empty',
                text: 'Aucune demande en attente / لا توجد طلبات معلقة',
            }));
            return;
        }

        accesses.forEach(access => {
            const card = element('article', { className: 'staff-access-card' });
            card.dataset.accessId = access.id;

            const context = element('div', { className: 'staff-access-card__context' });
            context.append(element('strong', {
                text: `${access.table?.label ?? 'Table'} / الطاولة`,
            }));
            context.append(element('small', {
                text: `Demande ${formatAge(access.requested_at)} · Session #${access.table_session_id}`,
            }));

            const actions = element('div', { className: 'staff-access-card__actions' });
            const approve = element('button', {
                className: 'button button--primary',
                text: 'Approve / موافقة',
                type: 'button',
            });
            const revoke = element('button', {
                className: 'button button--danger',
                text: 'Reject / رفض',
                type: 'button',
            });

            const run = async (action, successMessage) => {
                card.dataset.state = 'busy';
                try {
                    await action(access.id);
                    showFeedback(feedback, successMessage, 'success');
                    await refresh();
                } catch (error) {
                    showFeedback(feedback, error.message ?? 'Action impossible / تعذر تنفيذ العملية', 'error');
                    card.dataset.state = '';
                }
            };

            approve.addEventListener('click', () => run(
                approveGuestAccess,
                'Accès approuvé / تمت الموافقة على الدخول',
            ));
            revoke.addEventListener('click', () => run(
                revokeGuestAccess,
                'Accès refusé / تم رفض الدخول',
            ));
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
            showFeedback(feedback, error.message ?? 'Impossible de charger la liste / تعذر تحميل القائمة', 'error');
        }
    };

    refreshButton.addEventListener('click', refresh);
    createPoller(refresh, { intervalMs: 5000, maxIntervalMs: 30000 });
}
