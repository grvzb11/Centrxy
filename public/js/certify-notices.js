/* Certify v2.0.73: Flash action notices auto-dismiss in five seconds. */
(() => {
    'use strict';
    const DISPLAY_MS = 5000;
    const FADE_MS = 180;

    document.querySelectorAll('[data-certify-flash]').forEach((notice) => {
        let remaining = DISPLAY_MS;
        let startedAt = 0;
        let timer = null;
        let dismissed = false;

        const pause = () => {
            if (timer === null || dismissed) return;
            window.clearTimeout(timer);
            timer = null;
            remaining = Math.max(0, remaining - (Date.now() - startedAt));
        };
        const dismiss = () => {
            if (dismissed) return;
            dismissed = true;
            if (timer !== null) window.clearTimeout(timer);
            notice.classList.add('is-dismissing');
            window.setTimeout(() => notice.remove(), FADE_MS);
        };
        const resume = () => {
            if (dismissed || timer !== null || document.hidden) return;
            if (notice.matches(':hover') || notice.contains(document.activeElement)) return;
            if (remaining <= 0) { dismiss(); return; }
            startedAt = Date.now();
            timer = window.setTimeout(dismiss, remaining);
        };

        notice.querySelector('.certify-flash-close')?.addEventListener('click', dismiss);
        notice.addEventListener('mouseenter', pause);
        notice.addEventListener('mouseleave', resume);
        notice.addEventListener('focusin', pause);
        notice.addEventListener('focusout', (event) => {
            if (!notice.contains(event.relatedTarget)) resume();
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) pause();
            else resume();
        });
        resume();
    });
})();
