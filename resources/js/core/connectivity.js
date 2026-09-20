function currentLocale() {
    return document.documentElement.lang?.startsWith('ar') ? 'ar' : 'fr';
}

export function watchConnectivity({ onOnline, onOffline } = {}) {
    const online = () => onOnline?.();
    const offline = () => onOffline?.();

    window.addEventListener('online', online);
    window.addEventListener('offline', offline);

    return () => {
        window.removeEventListener('online', online);
        window.removeEventListener('offline', offline);
    };
}

export function bindConnectionIndicator(element) {
    if (!element) return () => {};

    const label = element.querySelector('[data-connection-label]') ?? element;

    const update = () => {
        const online = navigator.onLine;
        const locale = currentLocale();

        element.dataset.online = String(online);
        label.textContent = online
            ? (locale === 'ar' ? 'متصل' : 'Connecté')
            : (locale === 'ar' ? 'غير متصل' : 'Hors connexion');
    };

    const cleanup = watchConnectivity({
        onOnline: update,
        onOffline: update,
    });

    update();

    return cleanup;
}

/**
 * Renders a live HH:MM clock into `element`, formatted for the current
 * document locale. Returns a cleanup function.
 */
export function bindClock(element) {
    if (!element) return () => {};

    const update = () => {
        const locale = currentLocale();
        const formatter = new Intl.DateTimeFormat(locale === 'ar' ? 'ar' : 'fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
        });
        element.textContent = formatter.format(new Date());
    };

    update();
    const timer = window.setInterval(update, 30000);

    return () => window.clearInterval(timer);
}
