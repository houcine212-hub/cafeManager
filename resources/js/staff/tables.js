import { apiFetch, ConflictError } from '../core/http.js';

export function initStaffTables() {
    const root = document.querySelector('[data-staff-tables]');

    if (!root) return;

    root.querySelector('[data-tables-refresh]')?.addEventListener('click', () => {
        window.location.reload();
    });

    root.querySelectorAll('[data-open-table]').forEach(button => {
        button.addEventListener('click', async () => {
            button.disabled = true;

            try {
                await apiFetch(`/staff/tables/${button.dataset.openTable}/open`, {
                    method: 'POST',
                });
                window.location.reload();
            } catch (error) {
                button.disabled = false;
                button.textContent = error instanceof ConflictError
                    ? 'Table déjà occupée / الطاولة مشغولة'
                    : 'Réessayer / عاود المحاولة';
            }
        });
    });
}
