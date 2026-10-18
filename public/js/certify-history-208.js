// Certify 2.0.8: Progressive enhancement for server-backed History filters.
// The GET form continues to work without JavaScript.
(() => {
    'use strict';
    const form = document.getElementById('historyFilters');
    const input = document.getElementById('historySearch');
    const select = document.getElementById('historyStatus');
    const results = document.getElementById('historyResults');
    if (!form || !input || !select || !results) return;

    let timer = null;
    let activeRequest = null;
    let generation = 0;
    const initialAction = form.getAttribute('action') || window.location.pathname;

    async function fetchMatches() {
        const url = new URL(initialAction, window.location.href);
        const query = input.value.trim();
        if (query) url.searchParams.set('q', query);
        if (select.value !== 'all') url.searchParams.set('status', select.value);
        generation += 1;
        const requestId = generation;
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        form.classList.add('is-loading');
        results.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url.toString(), {
                signal: activeRequest.signal,
                headers: {'Accept': 'text/html'}
            });
            if (!response.ok) throw new Error('History search failed');
            const html = await response.text();
            if (requestId !== generation) return;
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            const updated = documentFragment.getElementById('historyResults');
            if (!updated) throw new Error('Search results not found');
            results.innerHTML = updated.innerHTML;
            window.history.replaceState(null, '', url.pathname + url.search);
            const currentClear = document.getElementById('historyClear');
            if (currentClear) currentClear.remove();
            if (query || select.value !== 'all') {
                const clear = document.createElement('a');
                clear.id = 'historyClear';
                clear.className = 'history-premium-clear';
                clear.href = initialAction;
                clear.textContent = 'Clear';
                form.append(clear);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                // A full navigation remains a reliable fallback for offline installs
                // and environments that disable fetch or DOMParser.
                window.location.assign(url.toString());
            }
        } finally {
            if (requestId === generation) {
                form.classList.remove('is-loading');
                results.removeAttribute('aria-busy');
                activeRequest = null;
            }
        }
    }

    function queueFetch(delay) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(fetchMatches, delay);
    }
    input.addEventListener('input', () => queueFetch(280));
    select.addEventListener('change', () => queueFetch(0));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (timer) clearTimeout(timer);
        void fetchMatches();
    });
    window.addEventListener('popstate', () => {
        // Browser back/forward should restore both controls and the result set.
        const url = new URL(window.location.href);
        input.value = url.searchParams.get('q') || '';
        select.value = url.searchParams.get('status') || 'all';
        queueFetch(0);
    });
})();
