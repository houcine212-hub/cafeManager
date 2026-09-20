const VIEWS = ['access', 'orders', 'tables'];

function viewFromHash() {
    const hash = window.location.hash.replace('#', '');
    return VIEWS.includes(hash) ? hash : 'access';
}

export function initStaffViews() {
    const navLinks = [...document.querySelectorAll('[data-staff-nav]')];
    const sections = [...document.querySelectorAll('[data-staff-view]')];
    if (!sections.length) return;

    const setView = viewName => {
        const target = VIEWS.includes(viewName) ? viewName : 'access';

        sections.forEach(section => {
            section.hidden = section.dataset.staffView !== target;
        });

        navLinks.forEach(link => {
            if (link.dataset.staffNav === target) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    navLinks.forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            const target = link.dataset.staffNav;
            window.history.replaceState(null, '', `#${target}`);
            setView(target);
        });
    });

    window.addEventListener('hashchange', () => setView(viewFromHash()));

    setView(viewFromHash());
}
