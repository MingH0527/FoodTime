// ============================================
// THEME.JS - DARK/LIGHT MODE TOGGLE
// Persistent across ALL pages via localStorage
// ============================================

// ── 1. Read saved theme ──
(function () {
    var saved = localStorage.getItem('theme')
             || localStorage.getItem('foodTheme')
             || 'light';
    localStorage.setItem('theme', saved);
    localStorage.removeItem('foodTheme');
    document.documentElement.setAttribute('data-theme', saved);
})();

// ── 2. Wire guest button + sync on DOM ready ──
document.addEventListener('DOMContentLoaded', function () {
    syncAllIcons();
    syncLangLabel();

    // Guest header toggle only
    // Logged-in ddThemeBtn uses inline onclick in nav.php
    var mainBtn = document.getElementById('themeToggle');
    if (mainBtn) mainBtn.addEventListener('click', toggleTheme);
});

// ── Toggle (also called by inline onclick on ddThemeBtn in nav.php) ──
function toggleTheme() {
    var html    = document.documentElement;
    var current = html.getAttribute('data-theme') || 'light';
    var next    = current === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);

    syncAllIcons();
}

// ── Sync every moon/sun icon on the page ──
function syncAllIcons() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    // Guest header icon
    var mainIcon = document.querySelector('#themeToggle i');
    if (mainIcon) mainIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';

    // Dropdown icon
    var ddIcon = document.getElementById('ddThemeIcon');
    if (ddIcon) ddIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}

// ── Sync language label from localStorage on page load ──
function syncLangLabel() {
    var label = document.getElementById('ddLangLabel');
    if (!label) return;
    var lang = localStorage.getItem('lang') || 'en';
    var map  = { en: 'EN', ms: 'MY', zh: '中文' };
    label.textContent = map[lang] || 'EN';
}