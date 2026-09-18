import { apiFetch } from '../core/http.js';
import { SubmissionAttempt } from '../core/idempotency.js';
import { initGuestAccess } from './access.js';
import { Cart, formatMadMinor } from './cart.js';
import { submitGuestOrder } from './orders.js';

function element(tag, { className, text, type } = {}) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    if (type) {
        node.type = type;
    }

    return node;
}

function showMessage(node, message, kind = 'info') {
    node.hidden = !message;
    node.dataset.kind = kind;
    node.textContent = message;
}

function productCard(product, cart, onChange) {
    const card = element('article', {
        className: 'guest-product surface',
    });

    const media = element('div', {
        className: 'guest-product__media',
    });

    if (product.image) {
        const image = element('img');

        image.src = product.image;
        image.alt = product.name;
        image.loading = 'lazy';

        media.append(image);
    } else {
        media.append(element('span', {
            text: 'Café',
            className: 'guest-product__placeholder',
        }));
    }

    const body = element('div', {
        className: 'guest-product__body',
    });

    body.append(element('h3', {
        text: product.name,
    }));

    if (product.description) {
        body.append(element('p', {
            text: product.description,
            className: 'muted',
        }));
    }

    const footer = element('div', {
        className: 'split guest-product__footer',
    });

    footer.append(element('strong', {
        text: `${product.price} DH`,
    }));

    const available = product.is_available !== false;

    const add = element('button', {
        className: 'button button--primary guest-product__add',
        text: available
            ? '+ Ajouter / إضافة'
            : 'Indisponible / غير متوفر',
        type: 'button',
    });

    add.disabled = !available;

    add.addEventListener('click', () => {
        cart.add(product);
        onChange();
    });

    footer.append(add);
    body.append(footer);
    card.append(media, body);

    return card;
}

export async function initGuestMenu() {
    const root = document.querySelector('[data-guest-menu]');

    if (!root) {
        return;
    }

    const basePath = window.location.pathname.replace(/\/+$/, '');
    const cart = new Cart();
    const attempt = new SubmissionAttempt();

    let accessState = 'not_found';
    let products = [];

    const status = root.querySelector('[data-menu-status]');
    const accessStatus = root.querySelector('[data-access-status]');
    const accessButton = root.querySelector('[data-request-access]');
    const submitButton = root.querySelector('[data-submit-order]');
    const cartList = root.querySelector('[data-cart-lines]');
    const cartCount = root.querySelector('[data-cart-count]');
    const cartTotal = root.querySelector('[data-cart-total]');
    const orderResult = root.querySelector('[data-order-result]');

    const updateSubmitState = () => {
        submitButton.disabled = cart.isEmpty()
            || accessState !== 'approved';

        submitButton.title = accessState === 'approved'
            ? ''
            : 'Approval required / الموافقة مطلوبة';
    };

    const renderCart = () => {
        cartList.replaceChildren();

        if (cart.isEmpty()) {
            cartList.append(element('p', {
                text: 'Votre panier est vide / السلة فارغة',
                className: 'muted',
            }));
        }

        let quantity = 0;

        for (const line of cart.lines()) {
            quantity += line.quantity;

            const row = element('div', {
                className: 'guest-cart__line',
            });

            const details = element('div', {
                className: 'guest-cart__details',
            });

            details.append(element('strong', {
                text: line.product.name,
            }));

            const note = element('input');

            note.type = 'text';
            note.maxLength = 255;
            note.placeholder = 'Note / ملاحظة';
            note.value = line.note;

            note.addEventListener('input', event => {
                cart.setNote(line.product.id, event.target.value);
                attempt.reset();
            });

            details.append(note);

            const controls = element('div', {
                className: 'guest-cart__controls',
            });

            const decrease = element('button', {
                className: 'button',
                text: '−',
                type: 'button',
            });

            const count = element('span', {
                text: String(line.quantity),
            });

            const increase = element('button', {
                className: 'button',
                text: '+',
                type: 'button',
            });

            decrease.addEventListener('click', () => {
                cart.setQuantity(line.product.id, line.quantity - 1);
                attempt.reset();
                renderCart();
            });

            increase.addEventListener('click', () => {
                cart.setQuantity(line.product.id, line.quantity + 1);
                attempt.reset();
                renderCart();
            });

            controls.append(decrease, count, increase);
            row.append(details, controls);
            cartList.append(row);
        }

        cartCount.textContent = String(quantity);
        cartTotal.textContent = formatMadMinor(cart.subtotalMinor());

        updateSubmitState();
    };

    const renderProducts = categoryId => {
        const grid = root.querySelector('[data-product-grid]');

        grid.replaceChildren();

        const visible = categoryId === 'all'
            ? products
            : products.filter(product =>
                String(product.category_id) === String(categoryId)
            );

        if (!visible.length) {
            grid.append(element('p', {
                className: 'alert',
                text: 'Aucun produit disponible / لا توجد منتجات متاحة',
            }));

            return;
        }

        visible.forEach(product => {
            grid.append(productCard(product, cart, renderCart));
        });
    };

    const renderCategories = categories => {
        const nav = root.querySelector('[data-category-list]');

        nav.replaceChildren();

        const all = element('button', {
            className: 'button button--primary',
            text: 'Tout / الكل',
            type: 'button',
        });

        all.addEventListener('click', () => renderProducts('all'));
        nav.append(all);

        categories.forEach(category => {
            const button = element('button', {
                className: 'button',
                text: category.name,
                type: 'button',
            });

            button.addEventListener('click', () => {
                renderProducts(category.id);
            });

            nav.append(button);
        });
    };

    const loadMenu = async () => {
        showMessage(
            status,
            'Chargement du menu / جار تحميل القائمة',
        );

        try {
            const payload = await apiFetch(`${basePath}/menu`);

            products = (payload.categories ?? []).flatMap(category =>
                (category.products ?? []).map(product => ({
                    ...product,
                    category_id: category.id,
                }))
            );

            renderCategories(payload.categories ?? []);
            renderProducts('all');

            showMessage(
                status,
                'Menu chargé / تم تحميل القائمة',
                'success',
            );
        } catch {
            showMessage(
                status,
                'Le menu est indisponible. Réessayez / القائمة غير متاحة. عاود المحاولة',
                'error',
            );
        }
    };

    const access = initGuestAccess(basePath, {
        onState(state) {
            accessState = state;
            accessButton.hidden = state === 'approved';
            accessButton.disabled = state === 'pending';

            updateSubmitState();
        },

        onMessage(message) {
            accessStatus.textContent = message;
        },
    });

    accessButton.addEventListener('click', async () => {
        accessButton.disabled = true;

        try {
            await access.requestAccess();
        } finally {
            if (accessState !== 'pending') {
                accessButton.disabled = false;
            }
        }
    });

    submitButton.addEventListener('click', async () => {
        submitButton.disabled = true;

        showMessage(
            orderResult,
            'Envoi de la commande / جار إرسال الطلب',
        );

        try {
            const response = await submitGuestOrder(
                basePath,
                cart,
                attempt,
                {
                    onState(state) {
                        if (state === 'uncertain') {
                            showMessage(
                                orderResult,
                                'Vérifiez vos commandes avant de réessayer / تحقق من طلباتك قبل إعادة المحاولة',
                                'error',
                            );
                        }
                    },
                },
            );

            const order = response?.order;

            showMessage(
                orderResult,
                `Commande #${order?.id ?? ''} confirmée / تم تأكيد الطلب`,
                'success',
            );

            cart.clear();
            attempt.reset();
            renderCart();
        } catch (error) {
            if (error.status === 409) {
                showMessage(
                    orderResult,
                    'Le stock ou la session a changé. Vérifiez votre panier / تغير المخزون أو الجلسة. راجع السلة',
                    'error',
                );
            } else if (error.status !== undefined) {
                showMessage(
                    orderResult,
                    'La commande n’a pas été envoyée / تعذر إرسال الطلب',
                    'error',
                );
            }
        } finally {
            updateSubmitState();
        }
    });

    root.querySelector('[data-menu-retry]')
        ?.addEventListener('click', loadMenu);

    renderCart();
    await loadMenu();
}
