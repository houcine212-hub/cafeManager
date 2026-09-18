function toMinorUnits(value) {
    const [whole, fraction = ''] = String(value).split('.');
    return (Number(whole) * 100) + Number((fraction + '00').slice(0, 2));
}

export function formatMadMinor(minor) {
    return `${(minor / 100).toFixed(2)} DH`;
}

export class Cart {
    #lines = new Map();

    add(product) {
        const current = this.#lines.get(product.id) ?? {
            product,
            quantity: 0,
            note: '',
        };

        current.quantity = Math.min(50, current.quantity + 1);
        this.#lines.set(product.id, current);
    }

    remove(productId) {
        this.#lines.delete(Number(productId));
    }

    setQuantity(productId, quantity) {
        const id = Number(productId);

        if (quantity <= 0) {
            return this.remove(id);
        }

        const line = this.#lines.get(id);

        if (line) {
            line.quantity = Math.min(50, Math.floor(quantity));
        }
    }

    setNote(productId, note) {
        const line = this.#lines.get(Number(productId));

        if (line) {
            line.note = note.slice(0, 255);
        }
    }

    lines() {
        return [...this.#lines.values()];
    }

    subtotalMinor() {
        return this.lines().reduce(
            (total, line) => total + (
                toMinorUnits(line.product.price) * line.quantity
            ),
            0,
        );
    }

    toPayload() {
        return this.lines().map(line => ({
            product_id: line.product.id,
            quantity: line.quantity,
            ...(line.note ? { note: line.note } : {}),
        }));
    }

    isEmpty() {
        return this.#lines.size === 0;
    }

    clear() {
        this.#lines.clear();
    }
}
