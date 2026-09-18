import { apiFetch, ConflictError } from '../core/http.js';

export async function submitGuestOrder(
    basePath,
    cart,
    attempt,
    { onState } = {},
) {
    if (cart.isEmpty()) {
        return null;
    }

    onState?.('sending');

    try {
        const data = await apiFetch(`${basePath}/orders`, {
            method: 'POST',
            body: {
                items: cart.toPayload(),
                idempotency_key: attempt.key,
            },
        });

        onState?.('confirmed', data);

        return data;
    } catch (error) {
        if (error instanceof ConflictError) {
            onState?.('rejected', error);
        } else if (
            error.name === 'AbortError'
            || error instanceof TypeError
        ) {
            onState?.('uncertain', error);
        } else {
            onState?.('rejected', error);
        }

        throw error;
    }
}
