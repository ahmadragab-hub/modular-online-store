import './bootstrap';

const themeKey = 'storefront_theme';

const validThemes = ['light', 'dark'];

const themeFromDom = document.documentElement.getAttribute('data-theme');

if (themeFromDom && validThemes.includes(themeFromDom)) {
    try {
        localStorage.setItem(themeKey, themeFromDom);
    } catch {
        // localStorage may be unavailable
    }
}

const mobileNavToggle = document.querySelector('[data-sf-nav-toggle]');
const mobileNavPanel = document.querySelector('[data-sf-nav-panel]');

if (mobileNavToggle && mobileNavPanel) {
    mobileNavToggle.addEventListener('click', () => {
        const expanded = mobileNavToggle.getAttribute('aria-expanded') === 'true';
        mobileNavToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (expanded) {
            mobileNavPanel.setAttribute('hidden', '');
        } else {
            mobileNavPanel.removeAttribute('hidden');
        }
    });
}