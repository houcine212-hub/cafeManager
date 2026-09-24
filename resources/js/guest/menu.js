import { apiFetch } from '../core/http.js';
import { SubmissionAttempt } from '../core/idempotency.js';
import { initGuestAccess } from './access.js';
import { Cart, formatMadMinor } from './cart.js';
import { submitGuestOrder } from './orders.js';
import { submitServiceRequest } from './service-requests.js';

function locale() {
    return document.documentElement.lang?.startsWith('ar') ? 'ar' : 'fr';
}

const LABELS = {
    fr: {
        available: 'Disponible',
        unavailable: 'Indisponible',
        noProducts: 'Aucun produit disponible',
        all: 'Tout',
        loadingMenu: 'Chargement du menu',
        menuLoaded: 'Menu chargé',
        menuError: 'Le menu est indisponible',
        emptyCartTitle: 'Votre panier est vide',
        emptyCartSub: 'Découvrez notre menu et ajoutez vos produits préférés.',
        viewMenu: 'Voir le menu',
        notePlaceholder: 'Note',
        unavailableInCart: 'Certains produits ne sont plus disponibles',
        unavailableInCartSub: 'Retirez-les ou ajustez la quantité pour continuer.',
        sending: 'Envoi de la commande',
        orderConfirmed: 'Commande confirmée',
        stockChanged: 'Le stock ou la session a changé',
        orderFailed: 'La commande n’a pas été envoyée',
        approvalRequired: 'Approbation requise',
        loadingOrders: 'Chargement',
        ordersRefreshed: 'Commandes actualisées',
        ordersError: 'Impossible de charger les commandes',
        noOrders: 'Aucune commande',
        total: 'Total',
        session: 'Session',
        checkingAccess: 'Vérification de l’accès',
        pendingHeading: 'En attente de l’approbation du personnel',
        pendingSub: 'Le personnel va valider votre accès dans un instant.',
        approvedHeading: 'Accès approuvé',
        approvedSub: 'Vous pouvez maintenant passer commande.',
        blockedHeading: 'L’accès à cette table n’a pas été approuvé',
        blockedSub: 'Veuillez contacter le personnel si vous pensez qu’il s’agit d’une erreur.',
        endedHeading: 'Cette session est terminée',
        endedSub: 'La table a été fermée par le personnel ou le temps de session est écoulé.',
        notFoundHeading: 'Aucun accès actif pour cette table',
        notFoundSub: 'Demandez l’accès pour pouvoir commander.',
        conflictHeading: 'La demande n’a pas pu être envoyée',
        requestAccess: 'Demander l’accès',
        checkAgain: 'Vérifier à nouveau',
        backToMenu: 'Retour au menu',
        approvalNote: 'Votre commande sera activée après l’approbation du personnel.',
        orderStatus: {
            new: 'Nouvelle', accepted: 'Acceptée', preparing: 'En préparation',
            ready: 'Prête', served: 'Servie', cancelled: 'Annulée',
        },
    },
    ar: {
        available: 'متوفر',
        unavailable: 'غير متوفر',
        noProducts: 'لا توجد منتجات متاحة',
        all: 'الكل',
        loadingMenu: 'جار تحميل القائمة',
        menuLoaded: 'تم تحميل القائمة',
        menuError: 'القائمة غير متاحة',
        emptyCartTitle: 'السلة فارغة',
        emptyCartSub: 'اكتشف قائمتنا وأضف منتجاتك المفضلة.',
        viewMenu: 'تصفح القائمة',
        notePlaceholder: 'ملاحظة',
        unavailableInCart: 'بعض المنتجات لم تعد متوفرة',
        unavailableInCartSub: 'قم بإزالتها أو تعديل الكمية للمتابعة.',
        sending: 'جار إرسال الطلب',
        orderConfirmed: 'تم تأكيد الطلب',
        stockChanged: 'تغير المخزون أو الجلسة',
        orderFailed: 'تعذر إرسال الطلب',
        approvalRequired: 'الموافقة مطلوبة',
        loadingOrders: 'جار التحميل',
        ordersRefreshed: 'تم تحديث الطلبات',
        ordersError: 'تعذر تحميل الطلبات',
        noOrders: 'لا توجد طلبات',
        total: 'المجموع',
        session: 'الجلسة',
        checkingAccess: 'جار التحقق من الدخول',
        pendingHeading: 'في انتظار موافقة الموظفين',
        pendingSub: 'الموظفون سيوافقون على طلبك قريباً.',
        approvedHeading: 'تمت الموافقة على الدخول',
        approvedSub: 'يمكنك الآن تقديم الطلب.',
        blockedHeading: 'لم تتم الموافقة على الدخول إلى هذه الطاولة',
        blockedSub: 'يرجى الاتصال بالموظفين إذا كنت تعتقد أن هذا خطأ.',
        endedHeading: 'انتهت جلسة هذه الطاولة',
        endedSub: 'تم إغلاق الطاولة من قبل الموظفين أو انتهى وقت الجلسة.',
        notFoundHeading: 'لا يوجد دخول نشط لهذه الطاولة',
        notFoundSub: 'اطلب الدخول حتى تتمكن من الطلب.',
        conflictHeading: 'تعذر إرسال الطلب',
        requestAccess: 'طلب الدخول',
        checkAgain: 'التحقق من جديد',
        backToMenu: 'العودة إلى القائمة',
        approvalNote: 'سيتم تفعيل طلبك بعد موافقة الموظفين.',
        orderStatus: {
            new: 'جديدة', accepted: 'مقبولة', preparing: 'قيد التحضير',
            ready: 'جاهزة', served: 'تم التقديم', cancelled: 'ملغاة',
        },
    },
};

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

function showMessage(node, message, kind = 'info') {
    node.hidden = !message;
    node.dataset.kind = kind;
    const icon = kind === 'error' ? ICON_INFO : kind === 'success' ? ICON_CHECK : '';
    node.innerHTML = icon ? `${icon}<span>${message}</span>` : message;
}

const ICON_PLUS = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>';
const ICON_MINUS = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 10h12"/></svg>';
const ICON_CUP = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h9a2.5 2.5 0 0 1 0 5h-.6"/><path d="M4 6v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V6"/></svg>';
const ICON_CUP_X = '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h9a2.5 2.5 0 0 1 0 5h-.6"/><path d="M4 6v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V6"/><circle cx="14.5" cy="5" r="3.2"/><path d="M13.2 3.7l2.6 2.6M15.8 3.7l-2.6 2.6"/></svg>';
const ICON_CART_BLOB = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h1.6L6 12.5h9l1.5-6.5H5"/><circle cx="8" cy="16.5" r="1.2"/><circle cx="14" cy="16.5" r="1.2"/></svg>';
const ICON_CLOCK = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6v4.3l3 1.8"/></svg>';
const ICON_CHECK = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>';
const ICON_TABLE_BLOCKED = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3.2" y="7.5" width="9" height="2" rx="0.8"/><path d="M5 9.5V15M10.4 9.5V15"/><circle cx="15" cy="6" r="4.1"/><path d="M12.7 3.7l4.6 4.6"/></svg>';
const ICON_TABLE = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="14" height="2.2" rx="0.9"/><path d="M5.2 10.2V16M14.8 10.2V16"/></svg>';
const ICON_INFO = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 9v4.5M10 6.6v.1"/></svg>';

const APPROVAL_ART = {
    pending: { tone: 'default', icon: ICON_CLOCK, heading: 'pendingHeading', sub: 'pendingSub', progress: true },
    approved: { tone: 'default', icon: ICON_CHECK, heading: 'approvedHeading', sub: 'approvedSub', progress: true },
    revoked: { tone: 'warning', icon: ICON_TABLE_BLOCKED, heading: 'blockedHeading', sub: 'blockedSub', progress: false },
    expired: { tone: 'muted', icon: ICON_TABLE, heading: 'endedHeading', sub: 'endedSub', progress: false },
    not_found: { tone: 'default', icon: ICON_TABLE, heading: 'notFoundHeading', sub: 'notFoundSub', progress: false },
    conflict: { tone: 'warning', icon: ICON_TABLE_BLOCKED, heading: 'conflictHeading', sub: '', progress: false },
};

function orderStatusLabel(status) {
    return t().orderStatus[status] ?? status;
}

function orderTotal(order, items) {
    const value = order.total_final ?? order.total_amount ?? order.total;
    if (value !== undefined && value !== null) return Number(value);
    return items.reduce((total, item) => total + Number(item.line_total ?? 0), 0);
}

function productCard(product, cart, onChange) {
    const labels = t();
    const card = element('article', { className: `guest-product${product.is_available === false ? ' is-unavailable' : ''}` });
    const media = element('div', { className: 'guest-product__media' });

    if (product.image) {
        const image = element('img');
        image.src = product.image;
        image.alt = product.name;
        image.loading = 'lazy';
        media.append(image);
    } else {
        media.append(element('span', { className: 'guest-product__placeholder', html: ICON_CUP }));
    }

    if (product.is_available === false) {
        media.append(element('span', { className: 'guest-product__unavailable-badge', text: labels.unavailable }));
    }

    const body = element('div', { className: 'guest-product__body' });
    body.append(element('h3', { text: product.name }));
    body.append(element('p', { text: product.description || labels.available }));

    const footer = element('div', { className: 'guest-product__footer' });
    footer.append(element('strong', { text: `${product.price} DH` }));

    const add = element('button', { className: 'guest-product__add', type: 'button', html: ICON_PLUS });
    add.disabled = product.is_available === false;
    add.setAttribute('aria-label', product.is_available === false ? labels.unavailable : product.name);
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
    if (!root) return;

    const basePath = window.location.pathname.replace(/\/+$/, '');
    const cart = new Cart();
    const attempt = new SubmissionAttempt();
    let accessState = 'not_found';
    let accessActionMode = 'request';
    let products = [];

    const views = [...root.querySelectorAll('[data-guest-view]')];
    const navButtons = [...root.querySelectorAll('[data-nav-view]')];
    const status = root.querySelector('[data-menu-status]');
    const ordersStatus = root.querySelector('[data-orders-status]');
    const accessStatus = root.querySelector('[data-access-status]');
    const accessButton = root.querySelector('[data-request-access]');
    const accessButtonLabel = root.querySelector('[data-request-access-label]');
    const submitButton = root.querySelector('[data-submit-order]');
    const cartList = root.querySelector('[data-cart-lines]');
    const cartNoteCard = root.querySelector('[data-cart-note-card]');
    const cartNotice = root.querySelector('[data-cart-notice]');
    const cartCounts = [...root.querySelectorAll('[data-cart-count], [data-nav-cart-count]')];
    const cartTotals = [...root.querySelectorAll('[data-cart-total]')];
    const cartTotalsBlock = root.querySelector('[data-cart-totals]');
    const orderResult = root.querySelector('[data-order-result]');
    const ordersList = root.querySelector('[data-orders-list]');
    const approvalArt = root.querySelector('[data-approval-art]');
    const approvalIcon = root.querySelector('[data-approval-icon]');
    const approvalHeading = root.querySelector('[data-approval-heading]');
    const approvalSub = root.querySelector('[data-approval-sub]');
    const approvalProgress = root.querySelector('[data-approval-progress]');
    const approvalStep = root.querySelector('[data-approval-step]');
    const approvalNote = root.querySelector('[data-approval-note]');

    const setView = viewName => {
        views.forEach(view => {
            const active = view.dataset.guestView === viewName;
            view.hidden = !active;
            view.classList.toggle('is-active', active);
        });
        navButtons.forEach(button => button.classList.toggle('is-active', button.dataset.navView === viewName));
        if (viewName === 'orders') loadOrders();
        if (viewName === 'cart') syncCartAvailability();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const renderApprovalArt = state => {
        const labels = t();
        const config = APPROVAL_ART[state] ?? APPROVAL_ART.not_found;
        approvalArt.dataset.tone = config.tone === 'default' ? '' : config.tone;
        approvalIcon.innerHTML = config.icon;
        approvalHeading.textContent = labels[config.heading] ?? '';
        approvalSub.textContent = config.sub ? (labels[config.sub] ?? '') : '';
        approvalProgress.hidden = !config.progress;
        approvalNote.hidden = state !== 'pending';
        if (state === 'approved') {
            approvalStep.classList.remove('is-current');
            approvalStep.classList.add('is-done');
            approvalStep.querySelector('span').innerHTML = ICON_CHECK.replace('width="20" height="20"', 'width="10" height="10"');
        }

        if (state === 'approved') {
            accessButton.hidden = true;
        } else if (state === 'pending') {
            accessButton.hidden = false;
            accessButton.disabled = true;
        } else if (state === 'revoked' || state === 'expired' || state === 'conflict') {
            accessButton.hidden = false;
            accessButton.disabled = false;
            accessActionMode = 'refresh';
            accessButtonLabel.textContent = labels.checkAgain;
        } else {
            accessButton.hidden = false;
            accessButton.disabled = false;
            accessActionMode = 'request';
            accessButtonLabel.textContent = labels.requestAccess;
        }
    };

    const serviceButtons = [...root.querySelectorAll('[data-service-request]')];
    const serviceResult = root.querySelector('[data-service-request-result]');
    const activeServiceRequests = new Set();

    const updateServiceState = () => {
        serviceButtons.forEach(button => {
            button.disabled = accessState !== 'approved'
                || activeServiceRequests.has(button.dataset.serviceRequest);
        });
    };

    const updateSubmitState = () => {
        const hasUnavailable = cart.lines().some(line => line.unavailable);
        submitButton.disabled = cart.isEmpty() || accessState !== 'approved' || hasUnavailable;
        submitButton.title = accessState === 'approved' ? '' : t().approvalRequired;
    };

    const syncCartAvailability = () => {
        let anyUnavailable = false;
        cart.lines().forEach(line => {
            const current = products.find(product => product.id === line.product.id);
            line.unavailable = !current || current.is_available === false;
            if (line.unavailable) anyUnavailable = true;
        });
        if (anyUnavailable) {
            showMessage(cartNotice, `${t().unavailableInCart} — ${t().unavailableInCartSub}`, 'error');
        } else {
            showMessage(cartNotice, '');
        }
        renderCart();
    };

    const renderCart = () => {
        const labels = t();
        cartList.replaceChildren();
        const lines = cart.lines();

        if (!lines.length) {
            cartNoteCard.hidden = true;
            cartTotalsBlock.hidden = true;
            submitButton.hidden = true;
            const empty = element('div', { className: 'guest-empty-state' });
            empty.innerHTML = `
                <div class="guest-approval-art"><span>${ICON_CART_BLOB}</span></div>
                <strong>${labels.emptyCartTitle}</strong>
                <p>${labels.emptyCartSub}</p>
            `;
            const cta = element('button', { className: 'button button--primary', type: 'button', text: labels.viewMenu });
            cta.addEventListener('click', () => setView('menu'));
            empty.append(cta);
            cartList.append(empty);
        } else {
            cartNoteCard.hidden = false;
            cartTotalsBlock.hidden = false;
            submitButton.hidden = false;
        }

        let quantity = 0;
        lines.forEach(line => {
            quantity += line.quantity;
            const row = element('article', { className: `guest-cart-line${line.unavailable ? ' is-unavailable' : ''}` });
            const media = element('div', { className: 'guest-cart-line__media' });
            if (line.product.image) {
                const image = element('img');
                image.src = line.product.image;
                image.alt = line.product.name;
                media.append(image);
            } else {
                media.innerHTML = ICON_CUP;
            }

            const info = element('div', { className: 'guest-cart-line__info' });
            info.append(element('strong', { text: line.product.name }));
            if (line.unavailable) {
                info.append(element('span', { className: 'guest-cart-line__badge', text: labels.unavailable }));
            }
            const note = element('input');
            note.type = 'text';
            note.maxLength = 255;
            note.placeholder = labels.notePlaceholder;
            note.value = line.note;
            note.addEventListener('input', event => {
                cart.setNote(line.product.id, event.target.value);
                attempt.reset();
            });
            info.append(note);

            const controls = element('div', { className: 'guest-cart-line__controls' });
            const decrease = element('button', { type: 'button', html: ICON_MINUS });
            const count = element('span', { text: String(line.quantity) });
            const increase = element('button', { type: 'button', html: ICON_PLUS });
            decrease.addEventListener('click', () => {
                cart.setQuantity(line.product.id, line.quantity - 1);
                attempt.reset();
                syncCartAvailability();
            });
            increase.addEventListener('click', () => {
                cart.setQuantity(line.product.id, line.quantity + 1);
                attempt.reset();
                syncCartAvailability();
            });
            controls.append(decrease, count, increase);
            row.append(media, info, controls);
            cartList.append(row);
        });

        const total = formatMadMinor(cart.subtotalMinor());
        cartCounts.forEach(node => { node.textContent = String(quantity); });
        cartTotals.forEach(node => { node.textContent = total; });
        updateSubmitState();
    };

    const renderProducts = categoryId => {
        const grid = root.querySelector('[data-product-grid]');
        grid.replaceChildren();
        const visible = categoryId === 'all'
            ? products
            : products.filter(product => String(product.category_id) === String(categoryId));

        if (!visible.length) {
            grid.append(element('p', { className: 'alert', text: t().noProducts }));
            return;
        }
        visible.forEach(product => grid.append(productCard(product, cart, () => syncCartAvailability())));
    };

    const renderCategories = categories => {
        const nav = root.querySelector('[data-category-list]');
        nav.replaceChildren();
        const all = element('button', { className: 'button button--primary', text: t().all, type: 'button' });
        all.addEventListener('click', () => renderProducts('all'));
        nav.append(all);
        categories.forEach(category => {
            const button = element('button', { className: 'button', text: category.name, type: 'button' });
            button.addEventListener('click', () => renderProducts(category.id));
            nav.append(button);
        });
    };

    const loadMenu = async () => {
        showMessage(status, t().loadingMenu);
        try {
            const payload = await apiFetch(`${basePath}/menu`);
            products = (payload.categories ?? []).flatMap(category =>
                (category.products ?? []).map(product => ({ ...product, category_id: category.id }))
            );
            renderCategories(payload.categories ?? []);
            renderProducts('all');
            showMessage(status, t().menuLoaded, 'success');
            syncCartAvailability();
        } catch {
            showMessage(status, t().menuError, 'error');
        }
    };

    const loadOrders = async () => {
        const labels = t();
        showMessage(ordersStatus, labels.loadingOrders);
        try {
            const payload = await apiFetch(`${basePath}/orders/mine`);
            const orders = payload.orders ?? [];
            ordersList.replaceChildren();
            if (!orders.length) {
                const empty = element('div', { className: 'guest-empty-state' });
                empty.innerHTML = `
                    <div class="guest-approval-art"><span>${ICON_CUP}</span></div>
                    <strong>${labels.noOrders}</strong>
                `;
                ordersList.append(empty);
            }
            orders.forEach(order => {
                const items = order.orderItems ?? order.order_items ?? [];
                const card = element('article', { className: 'guest-order-card' });
                const top = element('div', { className: 'guest-order-card__top' });
                top.append(element('strong', { text: `N° #${order.id}` }));
                top.append(element('span', { className: 'guest-order-card__status', text: orderStatusLabel(order.status) }));
                card.append(top);
                items.forEach(item => {
                    const line = element('div', { className: 'guest-order-card__item' });
                    line.append(element('span', { text: item.product_name_snapshot ?? item.product?.name ?? '—' }));
                    line.append(element('span', { text: `×${item.quantity}` }));
                    card.append(line);
                });
                const total = element('div', { className: 'guest-order-card__total' });
                total.append(element('span', { text: labels.total }));
                total.append(element('strong', { text: `${orderTotal(order, items).toFixed(2)} DH` }));
                card.append(total);
                ordersList.append(card);
            });
            showMessage(ordersStatus, labels.ordersRefreshed, 'success');
        } catch {
            showMessage(ordersStatus, t().ordersError, 'error');
        }
    };

    const access = initGuestAccess(basePath, {
        onState(state) {
            accessState = state;
            renderApprovalArt(state);
            updateSubmitState();
            if (state === 'pending') setView('approval');
            updateServiceState();
        },
        onMessage(message) {
            accessStatus.textContent = message;
        },
    });

    root.querySelectorAll('[data-open-view], [data-nav-view]').forEach(button => {
        button.addEventListener('click', () => setView(button.dataset.openView ?? button.dataset.navView));
    });

    accessButton.addEventListener('click', async () => {
        accessButton.disabled = true;
        try {
            if (accessActionMode === 'refresh') {
                await access.refresh();
            } else {
                await access.requestAccess();
            }
        } finally {
            if (accessState !== 'pending') accessButton.disabled = false;
        }
    });

    serviceButtons.forEach(button => {
        button.addEventListener('click', async () => {
            const type = button.dataset.serviceRequest;
            button.disabled = true;

            showMessage(
                serviceResult,
                'Envoi de la demande / جار إرسال الطلب',
            );

            try {
                await submitServiceRequest(basePath, type);
                activeServiceRequests.add(type);
                showMessage(
                    serviceResult,
                    type === 'bill'
                        ? 'Demande d’addition envoyée / تم إرسال طلب الحساب'
                        : 'Le serveur arrive bientôt / النادل غادي يجي قريباً',
                    'success',
                );
            } catch (error) {
                showMessage(
                    serviceResult,
                    error.status === 403
                        ? 'Accès approuvé requis / خاص الموافقة على الدخول'
                        : 'La demande n’a pas pu être envoyée / تعذر إرسال الطلب',
                    'error',
                );
            } finally {
                updateServiceState();
            }
        });
    });

    submitButton.addEventListener('click', async () => {
        const labels = t();
        if (accessState !== 'approved') {
            setView('approval');
            accessStatus.textContent = labels.approvalRequired;
            return;
        }
        submitButton.disabled = true;
        showMessage(orderResult, labels.sending);
        try {
            await submitGuestOrder(basePath, cart, attempt);
            showMessage(orderResult, labels.orderConfirmed, 'success');
            cart.clear();
            attempt.reset();
            renderCart();
            setView('orders');
        } catch (error) {
            if (error.status === 409) {
                await loadMenu();
                syncCartAvailability();
                showMessage(orderResult, labels.stockChanged, 'error');
            } else {
                showMessage(orderResult, labels.orderFailed, 'error');
            }
        } finally {
            updateSubmitState();
        }
    });

    root.querySelector('[data-menu-retry]')?.addEventListener('click', loadMenu);
    root.querySelector('[data-orders-retry]')?.addEventListener('click', loadOrders);
    renderCart();
    updateServiceState();
    await loadMenu();
}
