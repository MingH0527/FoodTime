// ============================================
// LANGUAGE.JS - MULTI-LANGUAGE SWITCHER
// Persistent across ALL pages via localStorage
// Requires: translations.js loaded FIRST (in <head>)
// ============================================

// ── Migrate old keys → new key 'language' ──
(function () {
    var old = localStorage.getItem('foodLanguage') || localStorage.getItem('lang');
    if (old) {
        localStorage.setItem('language', old);
        localStorage.removeItem('foodLanguage');
        localStorage.removeItem('lang');
    }
})();

let currentLanguage = localStorage.getItem('language') || 'en';

// ── Apply language immediately on DOM ready ──
document.addEventListener('DOMContentLoaded', function () {

    if (typeof translations === 'undefined') {
        console.error('❌ Translations not loaded! Make sure js/translations.js is in <head> BEFORE language.js');
        return;
    }

    // Apply saved language right away (no flash)
    setLanguage(currentLanguage);
    updateActiveLanguage(currentLanguage);
    syncLangLabels(currentLanguage);

    // ── Guest header lang button (#langBtn) ──
    var langBtn = document.getElementById('langBtn');
    if (langBtn) {
        langBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleLanguageDropdown();
        });
    }

    // ── Guest header dropdown options (.language-dropdown a) ──
    document.querySelectorAll('.language-dropdown a').forEach(function (option) {
        option.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            changeLanguage(this.getAttribute('data-lang'));
        });
    });

    // ── Logged-in dropdown options (.dd-lang-menu a) ──
    // NOTE: header.php also wires these up — this is a safe fallback
    document.querySelectorAll('.dd-lang-menu a').forEach(function (option) {
        option.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var lang = this.getAttribute('data-lang');
            document.getElementById('ddLangWrap')?.classList.remove('open');
            changeLanguage(lang);
        });
    });

    // ── Close guest dropdown when clicking outside ──
    document.addEventListener('click', function (e) {
        var dropdown = document.getElementById('languageDropdown');
        var btn      = document.querySelector('.lang-btn');
        if (dropdown && btn &&
            !dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
});

// ── Toggle guest dropdown ──
function toggleLanguageDropdown() {
    var dropdown = document.getElementById('languageDropdown');
    if (dropdown) dropdown.classList.toggle('show');
}

// ── Change + save language (THE single source of truth) ──
// Called by: dd-lang-menu clicks, guest dropdown clicks, setDdLang (old pages)
function changeLanguage(lang) {
    if (!lang) return;
    currentLanguage = lang;
    localStorage.setItem('language', lang);   // ← always 'language', never 'lang'
    setLanguage(lang);
    updateActiveLanguage(lang);
    syncLangLabels(lang);

    var dropdown = document.getElementById('languageDropdown');
    if (dropdown) dropdown.classList.remove('show');
}

// ── Apply translations to all [data-i18n] elements ──
function setLanguage(lang) {
    if (typeof translations === 'undefined') return;
    var t = translations[lang] || translations['en'];
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
        var key = el.getAttribute('data-i18n');
        if (t[key] !== undefined) el.textContent = t[key];
    });
}

// ── Highlight active language in all dropdowns ──
function updateActiveLanguage(lang) {
    document.querySelectorAll('.language-dropdown a, .dd-lang-menu a').forEach(function (opt) {
        opt.classList.toggle('active', opt.getAttribute('data-lang') === lang);
    });
}

// ── Sync the "EN / MY / 中文" label in logged-in dropdown ──
function syncLangLabels(lang) {
    var labelEl = document.getElementById('ddLangLabel');
    if (labelEl) {
        var labels = { en: 'EN', ms: 'MY', zh: '中文' };
        labelEl.textContent = labels[lang] || 'EN';
    }
}