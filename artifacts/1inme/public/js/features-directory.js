(() => {
    const root = document.querySelector('[data-feature-directory]');
    if (!root) return;
    const input = root.querySelector('#fx-search');
    const cards = [...root.querySelectorAll('[data-feature-card]')];
    const groups = [...root.querySelectorAll('[data-feature-group]')];
    const links = [...root.querySelectorAll('[data-group-link]')];
    const status = root.querySelector('[data-result-status]');
    const clear = root.querySelector('[data-clear-search]');
    const originalOpen = new Map();
    root.querySelector('[data-search-control]').hidden = false;
    function filter() {
        const query = input.value.trim().toLocaleLowerCase();
        const terms = query.split(/\s+/).filter(Boolean);
        let visible = 0;
        cards.forEach(card => {
            const matches = terms.every(term => card.dataset.search.includes(term));
            card.hidden = !matches;
            if (matches) visible++;
            card.querySelectorAll('details').forEach(detail => {
                if (query) {
                    if (!originalOpen.has(detail)) originalOpen.set(detail, detail.open);
                    detail.open = matches && terms.every(term => detail.textContent.toLocaleLowerCase().includes(term));
                } else if (originalOpen.has(detail)) {
                    detail.open = originalOpen.get(detail);
                    originalOpen.delete(detail);
                }
            });
        });
        groups.forEach(group => {
            group.hidden = ![...group.querySelectorAll('[data-feature-card]')].some(card => !card.hidden);
            links.find(link => link.dataset.groupLink === group.dataset.featureGroup).hidden = group.hidden;
        });
        clear.hidden = !query;
        status.hidden = !query;
        status.textContent = query ? `${visible} matching feature ${visible === 1 ? 'collection' : 'collections'} for “${input.value.trim()}”` : '';
        root.querySelector('[data-empty]').hidden = visible > 0;
    }
    function reset() { input.value = ''; filter(); input.focus(); }
    input.addEventListener('input', filter);
    input.addEventListener('keydown', event => { if (event.key === 'Escape') reset(); });
    clear.addEventListener('click', reset);
    root.querySelector('[data-reset-search]').addEventListener('click', reset);
    function revealHash() {
        const target = document.getElementById(location.hash.slice(1));
        if (!target || !root.contains(target)) return;
        if (input.value) { input.value = ''; filter(); }
        if (target.matches('[data-feature-card]')) target.querySelectorAll('details').forEach(detail => { detail.open = true; });
        target.scrollIntoView({ block: 'start' });
    }
    window.addEventListener('hashchange', revealHash);
    if (location.hash) revealHash();
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                links.forEach(link => {
                    if (link.dataset.groupLink === entry.target.dataset.featureGroup) link.setAttribute('aria-current', 'location');
                    else link.removeAttribute('aria-current');
                });
            });
        }, { rootMargin: '-15% 0px -65% 0px', threshold: 0 });
        groups.forEach(group => observer.observe(group));
    }
})();
