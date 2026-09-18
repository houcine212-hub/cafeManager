function randomHex(bytes = 32) {
    const values = new Uint8Array(bytes);
    crypto.getRandomValues(values);
    return [...values].map(value => value.toString(16).padStart(2, '0')).join('');
}

export function newIdempotencyKey() {
    // Backend contracts require a 64-character key.
    return randomHex(32);
}

export class SubmissionAttempt {
    constructor() {
        this.key = newIdempotencyKey();
    }

    reset() {
        this.key = newIdempotencyKey();
        return this.key;
    }
}
