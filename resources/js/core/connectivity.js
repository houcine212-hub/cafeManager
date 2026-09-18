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

export function lastUpdatedLabel(timestamp, now = Date.now()) {
    const seconds = Math.max(0, Math.floor((now - timestamp) / 1000));

    if (seconds < 8) return 'Mis à jour maintenant / محيّن الآن';
    if (seconds < 60) return `Mis à jour il y a ${seconds}s / منذ ${seconds} ثواني`;

    return `Mis à jour il y a ${Math.floor(seconds / 60)}min / منذ ${Math.floor(seconds / 60)} دقيقة`;
}

export function bindConnectionIndicator(element) {
    if (!element) return () => {};

    const update = () => {
        const online = navigator.onLine;

        element.dataset.online = String(online);
        element.textContent = online
            ? 'Connecté / متصل'
            : 'Hors connexion / غير متصل';
    };

    const cleanup = watchConnectivity({
        onOnline: update,
        onOffline: update,
    });

    update();

    return cleanup;
}
