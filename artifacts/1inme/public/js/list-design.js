/* Shared progressive enhancement for explicitly marked GET filter forms. */
(function () {
    function readHistory(key) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(value) ? value.filter(v => typeof v === 'string' && v.length <= 200).slice(0, 8) : [];
        } catch (_) { return []; }
    }
    function saveHistory(key, values) {
        try { localStorage.setItem(key, JSON.stringify(values)); } catch (_) {}
    }
    function node(tag, className, text) {
        const el = document.createElement(tag);
        el.className = className;
        if (text) el.textContent = text;
        return el;
    }
    function button(className, text) {
        const el = node('button', className, text);
        el.type = 'button';
        return el;
    }
    function enhance(form, index) {
        if (form.dataset.listEnhanced || form.method.toLowerCase() !== 'get') return;
        form.dataset.listEnhanced = '1';
        const original = Array.from(form.children);
        const search = form.querySelector('input[name="search"]:not([type="hidden"]),input[name="q"]:not([type="hidden"])');
        const fields = Array.from(form.querySelectorAll('select[name],input[type="date"][name],input[type="number"][name],input[type="checkbox"][name]'));
        if (!search && !fields.length) return;
        form.classList.add('app-filter-surface');
        const bar = node('div', 'app-search-bar');
        const toggle = button('app-filter-toggle', '+');
        toggle.setAttribute('aria-label', 'Show filters');
        toggle.setAttribute('aria-expanded', 'false');
        bar.append(toggle);
        if (search) {
            const oldParent = search.parentElement;
            search.classList.add('app-search-input');
            search.autocomplete = 'off';
            search.setAttribute('aria-label', search.getAttribute('aria-label') || search.placeholder || 'Search');
            bar.append(search);
            // Leave mixed wrappers intact (some also hold action controls).
            if (oldParent !== form && !oldParent.querySelector('input,select,button,a')) oldParent.hidden = true;
        } else {
            bar.append(node('span', 'app-filter-title', 'Filter results'));
        }
        const submit = node('button', 'app-filter-submit btn-primary-gradient', search ? 'Search' : 'Apply filters');
        submit.type = 'submit';
        bar.append(submit);
        const panel = node('div', 'app-filter-panel');
        panel.id = 'shared-filter-panel-' + index;
        panel.hidden = true;
        toggle.setAttribute('aria-controls', panel.id);
        original.forEach(child => {
            if (child !== search && !child.hidden && !(child.tagName === 'INPUT' && child.type === 'hidden')) panel.append(child);
        });
        form.prepend(bar);
        form.append(panel);
        if (!panel.children.length) toggle.hidden = true;
        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', String(!panel.hidden));
            toggle.setAttribute('aria-label', panel.hidden ? 'Show filters' : 'Hide filters');
        });
        form.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
        // Tags describe the currently applied query, not unsubmitted edits.
        const url = new URL(window.location.href);
        const tags = node('div', 'app-filter-tags');
        fields.forEach(field => {
            const values = url.searchParams.getAll(field.name);
            values.forEach(value => {
                if (!value) return;
                const option = field.tagName === 'SELECT' ? Array.from(field.options).find(o => o.value === value) : null;
                const labelElement = field.closest('label') || (field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]'));
                const label = field.getAttribute('aria-label') || (labelElement && labelElement.childNodes[0].textContent.trim()) || field.name.replace(/_/g, ' ').replace(/\[\]$/, '');
                const title = label + ': ' + (option ? option.textContent.trim() : value);
                const target = new URL(url);
                target.searchParams.delete(field.name);
                values.filter(v => v !== value).forEach(v => target.searchParams.append(field.name, v));
                target.searchParams.delete('page');
                const tag = node('a', 'app-filter-tag', title + ' ×');
                tag.href = target.href;
                tag.setAttribute('aria-label', 'Remove ' + title);
                tags.append(tag);
            });
        });
        if (tags.children.length) {
            const clear = node('a', 'app-filter-clear', 'Clear filters');
            const target = new URL(url);
            fields.forEach(field => target.searchParams.delete(field.name));
            target.searchParams.delete('page');
            clear.href = target.href;
            tags.append(clear);
            form.append(tags);
        }
        if (!search) return;
        const scope = document.querySelector('[data-list-history-scope]');
        const key = 'sayzio.list-search.' + (scope ? scope.dataset.listHistoryScope : '') + '.' + window.location.pathname;
        let history = readHistory(key);
        function remember() {
            const term = search.value.trim().slice(0, 200);
            if (!term) return;
            history = [term, ...history.filter(v => v.toLowerCase() !== term.toLowerCase())].slice(0, 8);
            saveHistory(key, history);
        }
        const recent = node('div', 'app-search-recent');
        recent.hidden = true;
        bar.after(recent);
        function render() {
            recent.replaceChildren();
            const matching = history.filter(v => v.toLowerCase().includes(search.value.trim().toLowerCase()));
            recent.hidden = !matching.length;
            if (!matching.length) return;
            const heading = node('div', 'app-recent-heading', 'Recent searches · on this device');
            const clear = button('app-filter-clear', 'Clear all');
            clear.addEventListener('click', () => { history = []; saveHistory(key, history); render(); });
            heading.append(clear);
            recent.append(heading);
            matching.forEach(term => {
                const row = node('div', 'app-recent-row');
                const replay = button('app-recent-term', term);
                replay.addEventListener('click', () => { search.value = term; search.dispatchEvent(new Event('input', { bubbles: true })); form.requestSubmit(submit); });
                const remove = button('app-recent-remove', '×');
                remove.setAttribute('aria-label', 'Remove search: ' + term);
                remove.addEventListener('click', () => { history = history.filter(v => v !== term); saveHistory(key, history); render(); });
                row.append(replay, remove);
                recent.append(row);
            });
        }
        remember();
        form.addEventListener('submit', remember);
        search.addEventListener('focus', render);
        search.addEventListener('input', render);
        form.addEventListener('keydown', event => { if (event.key === 'Escape') recent.hidden = true; });
        document.addEventListener('click', event => { if (!form.contains(event.target)) recent.hidden = true; });
    }
    function boot() { document.querySelectorAll('form[data-list-filters]').forEach(enhance); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
