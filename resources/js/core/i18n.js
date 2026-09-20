const SUPPORTED_LOCALES = ['fr', 'ar'];
const COOKIE_NAME = 'app_locale';

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

function readLocaleCookie() {
    const match = document.cookie.match(new RegExp(`(?:^|; )${COOKIE_NAME}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

function writeLocaleCookie(locale) {
    document.cookie = `${COOKIE_NAME}=${locale}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
}

export function preferredLocale() {
    return normalizeLocale(readLocaleCookie() || document.documentElement.lang);
}

/**
 * Persists the chosen locale (cookie, read server-side by the SetLocale
 * middleware) and reloads so Blade re-renders with the matching text.
 */
export function setPreferredLocale(locale, { reload = true } = {}) {
    const normalized = applyLocale(locale);
    writeLocaleCookie(normalized);
    if (reload) window.location.reload();
    return normalized;
}

/**
 * Binds both switcher styles:
 * - `[data-locale]` buttons (existing guest/auth pages)
 * - `[data-locale-select]` dropdown (new staff header)
 */
export function bindLocaleSwitcher() {
    const buttons = document.querySelectorAll('[data-locale]');
    const selects = document.querySelectorAll('[data-locale-select]');
    const current = preferredLocale();

    applyLocale(current);

    const onButtonClick = event => setPreferredLocale(event.currentTarget.dataset.locale);
    const onSelectChange = event => setPreferredLocale(event.currentTarget.value);

    buttons.forEach(button => {
        button.classList.toggle('is-active', button.dataset.locale === current);
        button.addEventListener('click', onButtonClick);
    });

    selects.forEach(select => {
        select.value = current;
        select.addEventListener('change', onSelectChange);
    });

    return () => {
        buttons.forEach(button => button.removeEventListener('click', onButtonClick));
        selects.forEach(select => select.removeEventListener('change', onSelectChange));
    };
}