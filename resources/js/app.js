import Alpine from 'alpinejs';

// Theme: honour a stored choice, else the OS preference. Applied in the <head>
// inline script too, to avoid a flash before this module loads.
window.theme = {
    get current() {
        return localStorage.getItem('theme')
            ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    },
    apply(value) {
        document.documentElement.classList.toggle('dark', value === 'dark');
        localStorage.setItem('theme', value);
    },
    toggle() {
        this.apply(this.current === 'dark' ? 'light' : 'dark');
    },
};

// Shared background polling (notification bell, message thread). The server has no
// WebSockets or queue worker (cPanel), so the browser polls — which on shared hosting
// multiplies by every open tab. This keeps that cheap:
//   - nothing is fetched while the tab is hidden; coming back refreshes at once,
//   - while nothing changes the interval backs off (×1.5, up to `max`); a change or an
//     explicit reset() (e.g. the user just sent a message) returns it to `base`,
//   - ±10% jitter, so a classroom of students opened at the same moment does not hit the
//     server in lockstep.
// `task` returns true when it found something new. Must be defined before Alpine starts.
window.pollWhenVisible = function (task, { base, max = base * 4, backoff = 1.5 } = {}) {
    let delay = base;
    let timer = null;
    let stopped = false;

    const schedule = () => {
        if (stopped) return;
        clearTimeout(timer);
        timer = setTimeout(run, delay * (0.9 + Math.random() * 0.2));
    };

    const run = async () => {
        if (stopped || document.hidden) return;   // paused; onVisible resumes

        let changed = false;
        try { changed = (await task()) === true; } catch (e) { /* a failed poll just backs off */ }

        delay = changed ? base : Math.min(delay * backoff, max);
        schedule();
    };

    const onVisible = () => {
        if (stopped || document.hidden) return;
        delay = base;
        clearTimeout(timer);
        run();
    };

    document.addEventListener('visibilitychange', onVisible);
    schedule();

    return {
        reset() { delay = base; schedule(); },
        stop() { stopped = true; clearTimeout(timer); document.removeEventListener('visibilitychange', onVisible); },
    };
};

// Full emoji set for the chat picker: ~110 KB, so it is a separate chunk fetched on first open only.
window.loadEmojiData = () => import('./emoji-data.js').then((m) => m.default);
// Result share card renderer (results page only) — fetched on first use.
window.loadShareCard = () => import('./share-card.js').then((m) => m.default);

// Shared key behaviour for plain <textarea> comment boxes (the chat has its own, in thread.blade.php):
//   Enter -> onEnter() (submit)    Shift+Enter -> new line    Ctrl/Cmd+B -> wrap/unwrap the selection in *bold*
// Enter is ignored while an input method (e.g. Avro) is composing a word.
window.richKeys = function (e, onEnter) {
    const t = e.target;
    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'b' || e.key === 'B')) {
        e.preventDefault();
        const a = t.selectionStart, b = t.selectionEnd, v = t.value, sel = v.slice(a, b);
        let from, to;
        if (sel.length >= 2 && sel.startsWith('*') && sel.endsWith('*')) {
            t.value = v.slice(0, a) + sel.slice(1, -1) + v.slice(b); from = a; to = b - 2;
        } else {
            t.value = v.slice(0, a) + '*' + sel + '*' + v.slice(b); from = a + 1; to = b + 1;
        }
        t.setSelectionRange(from, to);
        t.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey && !e.metaKey && !e.isComposing && e.keyCode !== 229) {
        e.preventDefault();
        onEnter();
    }
};

// Keep the reader's place when a control re-loads the same page with different parameters
// (the amol date / month navigation). There is no client-side routing, so a plain link would
// land at the top; instead the scroll offset is stashed just before leaving and restored on
// arrival. Links opt in with data-keep-scroll; other code can call window.keepScroll().
window.keepScroll = () => {
    try { sessionStorage.setItem('keepScroll', JSON.stringify({ path: location.pathname, y: window.scrollY })); } catch (e) {}
};
document.addEventListener('click', (e) => {
    if (e.target.closest('a[data-keep-scroll]')) window.keepScroll();
});
try {
    const saved = JSON.parse(sessionStorage.getItem('keepScroll') || 'null');
    sessionStorage.removeItem('keepScroll');
    if (saved && saved.path === location.pathname) {
        history.scrollRestoration = 'manual';
        window.scrollTo({ top: saved.y, left: 0, behavior: 'instant' });
    }
} catch (e) {}

// Every navigation is a full page load, so on a slow connection a tap can look ignored. A thin line
// across the top shows the next page is on its way (only if it takes longer than a moment), and a
// submit button goes busy so a second tap cannot send the form twice.
(() => {
    const bar = document.createElement('div');
    bar.id = 'nav-progress';
    bar.setAttribute('aria-hidden', 'true');
    let timer = null;
    let safety = null;

    const start = () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (! bar.isConnected) document.body.appendChild(bar);
            bar.style.transition = 'none';
            bar.style.width = '0';
            bar.offsetWidth;   // restart from zero
            bar.style.transition = '';
            bar.classList.add('is-on');
            bar.style.width = '85%';
        }, 150);
        // A response that never replaces the page (a file download, a 204) must not leave it spinning.
        clearTimeout(safety);
        safety = setTimeout(stop, 10000);
    };
    const stop = () => {
        clearTimeout(timer);
        clearTimeout(safety);
        bar.classList.remove('is-on');
        bar.style.width = '0';
        document.querySelectorAll('[data-busy]').forEach((el) => {
            el.disabled = false;
            el.removeAttribute('data-busy');
            el.removeAttribute('aria-busy');
        });
    };

    document.addEventListener('click', (e) => {
        const a = e.target.closest?.('a[href]');
        if (! a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-progress')) return;
        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin || ! /^https?:$/.test(url.protocol)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;   // in-page jump
        if (/\/export(\/|$)/.test(url.pathname)) return;   // CSV downloads stay on the page
        start();
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        // data-confirm forms are intercepted (prevented) by the confirm dialog; fetch()-driven forms
        // prevent too — neither navigates, so neither gets the bar.
        if (e.defaultPrevented || form.target === '_blank' || form.hasAttribute('data-no-progress')) return;
        start();
        const btn = e.submitter;
        if (btn && btn.tagName === 'BUTTON') {
            // after this tick, so the clicked button's name/value is still part of the submission
            setTimeout(() => { btn.disabled = true; btn.setAttribute('data-busy', ''); btn.setAttribute('aria-busy', 'true'); }, 0);
        }
    });

    // Back/forward restores a frozen page from the cache — clear anything left running.
    window.addEventListener('pageshow', stop);
})();

window.Alpine = Alpine;
Alpine.start();

// Homepage scroll-reveal: each [data-reveal] section fades + rises into view the
// first time it enters the viewport, then stays revealed (no re-triggering on
// scroll-back). If IntersectionObserver is unavailable, everything is revealed
// immediately — content is never left permanently hidden waiting on JS.
const revealTargets = document.querySelectorAll('[data-reveal]');
if (revealTargets.length) {
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -10% 0px' });

        revealTargets.forEach((el) => revealObserver.observe(el));
    } else {
        revealTargets.forEach((el) => el.classList.add('is-revealed'));
    }
}

// Homepage stat counters: each [data-count-up] animates from 0 up to its real value
// (already server-rendered, correct, and locale-digit-formatted) the first time it
// scrolls into view — on page load if it's already visible, same as the reveal above.
// Digits are localized in JS the same way the bn() helper does it server-side, since
// every intermediate frame needs formatting, not just the final one.
const countTargets = document.querySelectorAll('[data-count-up]');
if (countTargets.length) {
    const BENGALI_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    const isBengaliLocale = document.documentElement.lang.startsWith('bn');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const formatCount = (n) => isBengaliLocale
        ? String(n).replace(/[0-9]/g, (digit) => BENGALI_DIGITS[Number(digit)])
        : String(n);

    const animateCount = (el) => {
        const target = parseInt(el.dataset.countUp, 10);
        if (! Number.isFinite(target) || target <= 0 || prefersReducedMotion) {
            return;   // already showing the correct server-rendered value
        }

        const duration = 1200;
        const start = performance.now();

        const step = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - (1 - progress) ** 3;   // ease-out cubic
            el.textContent = formatCount(Math.round(target * eased));
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    };

    if ('IntersectionObserver' in window) {
        const countObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    countObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        countTargets.forEach((el) => countObserver.observe(el));
    }
    // No IntersectionObserver fallback needed: the server-rendered value is already
    // correct and visible, so skipping the animation there is a silent, safe no-op.
}
