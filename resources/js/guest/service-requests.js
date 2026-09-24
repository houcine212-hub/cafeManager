import { apiFetch } from '../core/http.js';

export async function submitServiceRequest(basePath, type) {
    return apiFetch(`${basePath}/service-requests`, {
        method: 'POST',
        body: { type },
    });
}
