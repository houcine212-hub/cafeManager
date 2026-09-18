const SUPPORTED_LOCALES = ['fr', 'ar'];

export function normalizeLocale(locale) {
    return SUPPORTED_LOCALES.includes(locale) ? locale : 'fr';
}

export function applyLocale(locale, root = document.documentElement) {
    const normalized = normalizeLocale(locale);
    root.lang = normalized;
    root.dir = normalized === 'ar' ? 'rtl' : 'ltr';
    root.dataset.locale = normalized;
    return normalized;
}

export function preferredLocale() {
    return normalizeLocale(localStorage.getItem('cafe_locale') || document.documentElement.lang);
}

export function setPreferredLocale(locale) {
    const normalized = applyLocale(locale);
    localStorage.setItem('cafe_locale', normalized);
    return normalized;
}

export function bindLocaleSwitcher(selector = '[data-locale]') {
    const controls = document.querySelectorAll(selector);
    const change = event => setPreferredLocale(event.currentTarget.dataset.locale);

    controls.forEach(control => control.addEventListener('click', change));
    applyLocale(preferredLocale());

    return () => controls.forEach(control => control.removeEventListener('click', change));
}
