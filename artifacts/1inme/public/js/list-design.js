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
    function queryValues(url, name) {
        const base = name.replace(/\[\]$/, '');
        return Array.from(url.searchParams).filter(([key]) => key === base || key === base + '[]' || (key.startsWith(base + '[') && /^\d+\]$/.test(key.slice(base.length + 1)))).map(([, value]) => value);
    }
    function deleteQuery(url, name) {
        const base = name.replace(/\[\]$/, '');
        Array.from(url.searchParams.keys()).forEach(key => { if (key === base || key === base + '[]' || (key.startsWith(base + '[') && /^\d+\]$/.test(key.slice(base.length + 1)))) url.searchParams.delete(key); });
    }
    function fieldLabel(field) {
        const label = field.closest('label') || (field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]'));
        const text = label && Array.from(label.childNodes).filter(n => n.nodeType === Node.TEXT_NODE).map(n => n.textContent.trim()).join(' ').trim();
        const names = { calendar:'Calendars', source:'Source', project_id:'Projects', link_id:'Links', form_id:'Forms', visitor_type:'Visitor type', owner:'Added by', q:'Search' };
        return field.getAttribute('aria-label') || field.title || text || names[field.name.replace(/_multi\[\]$/, '')] || field.name.replace(/_multi\[\]$/, '').replace(/_/g, ' ');
    }
    let closeCurrentPicker = null;
    function enhanceSelect(select, index) {
        const label = fieldLabel(select);
        const originalName = select.name;
        const multiple = select.hasAttribute('data-filter-multiple');
        const url = new URL(location.href);
        let applied = multiple ? (queryValues(url, originalName + '_multi').length ? queryValues(url, originalName + '_multi') : queryValues(url, originalName)) : queryValues(url, originalName);
        const options = Array.from(select.options);
        if (multiple && !applied.length) applied = options.filter(o => o.selected).map(o => o.value);
        if (multiple) {
            select.name = originalName + '_multi[]';
            select.multiple = true;
            options.forEach(option => option.selected = applied.includes(option.value) && option.value !== '' && option.value !== 'all');
        }
        select.removeAttribute('onchange');
        const wrap = node('div', 'app-filter-picker');
        const caption = node('span', 'app-picker-caption', label);
        const trigger = button('app-picker-trigger', '');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        const menu = node('div', 'app-picker-menu');
        menu.id = 'filter-picker-' + index;
        menu.setAttribute('role', 'dialog');
        menu.setAttribute('aria-label', label + (multiple ? ' · select multiple' : ''));
        menu.hidden = true;
        trigger.setAttribute('aria-controls', menu.id);
        const heading = node('div', 'app-picker-heading', label);
        const close = button('app-picker-close', '×');
        close.setAttribute('aria-label', 'Close ' + label);
        heading.append(close); menu.append(heading);
        let search = null;
        if (options.length > 7) {
            search = node('input', 'app-picker-search');
            search.type = 'search'; search.placeholder = 'Find options…';
            search.setAttribute('aria-label', 'Find ' + label);
            menu.append(search);
        }
        const list = node('div', 'app-picker-options'); menu.append(list);
        const rows = [];
        function update() {
            const chosen = options.filter(o => o.selected && o.value !== '' && (!multiple || o.value !== 'all'));
            const text = chosen.length ? chosen.map(o => o.textContent.trim()).join(', ') : (options.find(o => !o.value || o.value === 'all')?.textContent.trim() || 'Any');
            trigger.replaceChildren(node('span', 'app-picker-selection', text), node('span', 'app-picker-caret', '⌄'));
            trigger.setAttribute('aria-label', label + ': ' + text);
            rows.forEach(({input, option}) => input.checked = option.value === '' || (multiple && option.value === 'all') ? chosen.length === 0 : option.selected);
        }
        function hide(returnFocus = false) {
            menu.hidden = true; trigger.setAttribute('aria-expanded', 'false');
            if (closeCurrentPicker === hide) closeCurrentPicker = null;
            if (returnFocus) trigger.focus();
        }
        function position() {
            const rect = trigger.getBoundingClientRect();
            const width = Math.min(Math.max(rect.width, 260), innerWidth - 24);
            menu.style.width = width + 'px';
            menu.style.left = Math.max(12, Math.min(rect.left, innerWidth - width - 12)) + 'px';
            menu.style.top = Math.min(rect.bottom + 6, Math.max(12, innerHeight - Math.min(360, menu.scrollHeight) - 12)) + 'px';
            menu.style.maxHeight = Math.max(160, innerHeight - 24) + 'px';
        }
        options.forEach((option, optionIndex) => {
            if (option.disabled) return;
            const row = node('label', 'app-picker-option');
            const input = node('input', ''); input.type = multiple ? 'checkbox' : 'radio';
            if (!multiple) input.name = 'picker-radio-' + index;
            input.setAttribute('aria-label', option.textContent.trim());
            row.append(input, node('span', '', option.textContent.trim())); list.append(row); rows.push({row,input,option});
            input.addEventListener('change', () => {
                if (!multiple || option.value === '' || option.value === 'all') options.forEach(o => o.selected = o === option && (!multiple || (o.value !== '' && o.value !== 'all')));
                else { option.selected = input.checked; options.filter(o => o.value === '' || o.value === 'all').forEach(o => o.selected = false); }
                update(); select.dispatchEvent(new Event('change', { bubbles:true }));
                if (!multiple) hide(true);
            });
        });
        if (search) search.addEventListener('input', () => rows.forEach(({row,option}) => row.hidden = !option.textContent.toLowerCase().includes(search.value.toLowerCase())));
        menu.append(node('p', 'app-picker-help', multiple ? 'Choose any matching values, then apply filters.' : 'Choose one value, then apply filters.'));
        close.addEventListener('click', () => hide(true));
        trigger.addEventListener('click', () => {
            if (!menu.hidden) { hide(); return; }
            if (closeCurrentPicker) closeCurrentPicker();
            menu.hidden = false; trigger.setAttribute('aria-expanded', 'true'); closeCurrentPicker = hide; position();
            (search || rows[0]?.input || close).focus();
        });
        trigger.addEventListener('keydown', e => { if(e.key === 'ArrowDown') { e.preventDefault(); if(menu.hidden) trigger.click(); (search || rows[0]?.input || close).focus(); } });
        menu.addEventListener('keydown', e => { if(e.key === 'Escape') { e.preventDefault(); hide(true); } });
        document.addEventListener('pointerdown', e => { if(!wrap.contains(e.target) && !menu.contains(e.target)) hide(); });
        document.addEventListener('focusin', e => { if(!wrap.contains(e.target) && !menu.contains(e.target)) hide(); });
        window.addEventListener('resize', () => hide());
        window.addEventListener('scroll', () => { if(!menu.hidden) position(); }, true);
        select.hidden = true; select.before(wrap); wrap.append(caption,trigger,select); document.body.append(menu); update();
        return { originalName, select };
    }
    function enhance(form, index) {
        if (form.dataset.listEnhanced || form.method.toLowerCase() !== 'get') return;
        form.dataset.listEnhanced = '1';
        const original = Array.from(form.children);
        const search = form.querySelector('input[name="search"]:not([type="hidden"]),input[name="q"]:not([type="hidden"])');
        const fields = Array.from(form.querySelectorAll('select[name],input[type="date"][name],input[type="number"][name],input[type="checkbox"][name]'));
        if (!search && !fields.length) return;
        const dateFilters = form.hasAttribute('data-date-filters');
        fields.forEach(field => field.removeAttribute('onchange'));
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
            const rangeField = form.querySelector('select[name=range]');
            const dates = fields.filter(field => field.type === 'date').map(field => field.value).filter(Boolean);
            const rangeLabel = rangeField ? rangeField.options[rangeField.selectedIndex].textContent.trim() : form.dataset.rangeLabel;
            bar.append(node('span', 'app-filter-title', dateFilters ? (rangeLabel || (dates.length ? dates.join(' – ') : 'Choose date range')) : 'Filter results'));
        }
        const submit = node('button', 'app-filter-submit btn-primary-gradient', search ? 'Search' : (dateFilters ? 'Apply dates' : 'Apply filters'));
        submit.type = 'submit';
        bar.append(submit);
        const panel = node('div', 'app-filter-panel');
        panel.id = 'shared-filter-panel-' + index;
        panel.hidden = true;
        toggle.setAttribute('aria-controls', panel.id);
        original.forEach(child => {
            if (dateFilters && child.tagName === 'A') { bar.append(child); return; }
            if (dateFilters && child.tagName === 'BUTTON' && child.type === 'submit') { child.hidden = true; return; }
            if (child !== search && !child.hidden && !(child.tagName === 'INPUT' && child.type === 'hidden')) panel.append(child);
        });
        form.prepend(bar);
        form.append(panel);
        if (!panel.children.length) toggle.hidden = true;
        const pickers = fields.filter(field => field.tagName === 'SELECT').map((field, i) => enhanceSelect(field, index + '-' + i));

        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', String(!panel.hidden));
            toggle.setAttribute('aria-label', panel.hidden ? 'Show filters' : 'Hide filters');
        });
        document.addEventListener('pointerdown', event => { if (!form.contains(event.target) && !event.target.closest('.app-picker-menu')) { panel.hidden = true; toggle.setAttribute('aria-expanded','false'); } });
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
            const picker = pickers.find(p => p.select === field);
            const oldName = picker ? picker.originalName : field.name;
            let values = queryValues(url, field.name);
            if (!values.length && picker) values = queryValues(url, oldName);
            values.forEach(value => {
                if (!value || value === 'all') return;
                const option = field.tagName === 'SELECT' ? Array.from(field.options).find(o => o.value === value) : null;
                const title = fieldLabel(field) + ': ' + (option ? option.textContent.trim() : value);
                const target = new URL(url);
                deleteQuery(target, oldName); deleteQuery(target, field.name);
                const remaining = values.filter(v => v !== value);
                remaining.forEach(v => target.searchParams.append(field.name, v));
                if (!remaining.length && field.dataset.filterEmptyValue) target.searchParams.set(oldName, field.dataset.filterEmptyValue);
                target.searchParams.delete('page');
                const tag = node('a', 'app-filter-tag', title + ' ×'); tag.href = target.href;
                tag.setAttribute('aria-label', 'Remove ' + title); tags.append(tag);
            });
        });
        if (tags.children.length) {
            const clear = node('a', 'app-filter-clear', 'Clear filters');
            const target = new URL(url);
            fields.forEach(field => deleteQuery(target, field.name));
            pickers.forEach(p => deleteQuery(target, p.originalName));
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
