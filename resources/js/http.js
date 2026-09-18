export class ApiError extends Error {
    constructor(message, { status, data = null, response = null } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
        this.response = response;
    }
}

export class AuthError extends ApiError {}
export class ConflictError extends ApiError {}
export class ValidationError extends ApiError {}
export class ServerError extends ApiError {}

async function readJson(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) return null;

    try {
        return await response.json();
    } catch {
        return null;
    }
}

function errorMessage(data, fallback) {
    if (typeof data?.message === 'string') return data.message;
    if (typeof data?.error === 'string') return data.error;
    return fallback;
}

export async function apiFetch(url, {
    method = 'GET',
    body,
    signal,
    headers: extraHeaders = {},
} = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...extraHeaders,
    };

    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;

    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            signal,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch (error) {
        throw error;
    }

    if (response.status === 204) return null;

    const data = await readJson(response);
    if (response.ok) return data;

    const fallback = `Request failed with status ${response.status}.`;
    const options = { status: response.status, data, response };

    if (response.status === 401 || response.status === 403) {
        throw new AuthError(errorMessage(data, 'You are not authorized for this action.'), options);
    }
    if (response.status === 409) {
        throw new ConflictError(errorMessage(data, 'The requested state conflicts with the current state.'), options);
    }
    if (response.status === 422) {
        throw new ValidationError(errorMessage(data, 'Please check the submitted information.'), options);
    }
    if (response.status >= 500) {
        throw new ServerError(errorMessage(data, 'The server could not complete the request.'), options);
    }

    throw new ApiError(errorMessage(data, fallback), options);
}
