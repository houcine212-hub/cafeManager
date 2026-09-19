import { apiFetch } from '../core/http.js';

export function listPendingGuestAccesses() {
    return apiFetch('/staff/guest-accesses?status=pending');
}

export function approveGuestAccess(id) {
    return apiFetch(`/staff/guest-accesses/${id}/approve`, { method: 'POST' });
}

export function revokeGuestAccess(id) {
    return apiFetch(`/staff/guest-accesses/${id}/revoke`, { method: 'POST' });
}
