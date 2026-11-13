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
