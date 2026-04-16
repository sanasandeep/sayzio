@once
@push('styles')
<style>
    /* Toolbar above an enhanced table */
    .et-toolbar {
        display: flex; align-items: center; gap: 10px;
        flex-wrap: wrap; margin-bottom: 12px;
    }
    .et-search {
        position: relative; flex: 1; min-width: 200px;
    }
    .et-search input {
        width: 100%; padding: 9px 12px 9px 34px;
        border-radius: 10px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-primary);
        font-size: 12px; outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .et-search input:focus {
        border-color: rgba(27,132,255,0.5);
        box-shadow: 0 0 0 3px rgba(27,132,255,0.12);
    }
    .et-search i {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        color: var(--text-faint); font-size: 12px; pointer-events: none;
    }
    .et-pagesize {
        padding: 8px 10px; border-radius: 10px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-primary);
        font-size: 12px; outline: none;
        cursor: pointer;
    }
    .et-info {
        font-size: 11px; color: var(--text-faint);
        font-weight: 500; white-space: nowrap;
    }
    /* Sortable headers */
    .enhanced-table thead th { cursor: pointer; user-select: none; position: relative; }
    .enhanced-table thead th[data-no-sort] { cursor: default; }
    .enhanced-table thead th .et-sort-ind {
        display: inline-block; margin-left: 6px; opacity: .35;
        font-size: 10px; transition: opacity .15s ease;
    }
    .enhanced-table thead th[data-no-sort] .et-sort-ind { display: none; }
    .enhanced-table thead th:hover .et-sort-ind { opacity: .8; }
    .enhanced-table thead th.et-sorted-asc .et-sort-ind,
    .enhanced-table thead th.et-sorted-desc .et-sort-ind { opacity: 1; color: #7fbbff; }
    .et-empty {
        text-align: center; padding: 28px 16px;
        color: var(--text-faint); font-size: 12px; font-style: italic;
    }
    /* Pagination footer */
    .et-pagination {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; margin-top: 12px; flex-wrap: wrap;
    }
    .et-pages { display: flex; align-items: center; gap: 4px; }
    .et-page-btn {
        min-width: 32px; height: 32px; padding: 0 10px;
        border-radius: 8px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-dimmed); font-size: 12px; font-weight: 600;
        cursor: pointer; transition: all .15s ease;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .et-page-btn:hover:not(:disabled) {
        background: rgba(27,132,255,0.12);
        border-color: rgba(27,132,255,0.35);
        color: var(--text-primary);
    }
    .et-page-btn:disabled { opacity: .35; cursor: not-allowed; }
    .et-page-btn.active {
        background: linear-gradient(135deg, #3e97ff, #1b84ff);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 12px rgba(27,132,255,0.35);
    }
    .et-page-btn.dots { background: transparent; border: 0; cursor: default; }
</style>
@endpush

@push('scripts')
<script>
(function(){
    function parseValue(cell) {
        if (!cell) return '';
        var raw = cell.getAttribute('data-sort-value');
        if (raw !== null) {
            var n = parseFloat(raw);
            return isNaN(n) ? raw.toLowerCase() : n;
        }
        var txt = (cell.innerText || cell.textContent || '').trim();
        // Try numeric (strip %, $, commas, s suffix etc.)
        var clean = txt.replace(/[,%$\s]/g, '').replace(/s$/, '');
        if (clean !== '' && !isNaN(parseFloat(clean)) && isFinite(clean)) {
            return parseFloat(clean);
        }
        return txt.toLowerCase();
    }

    function buildToolbar(table) {
        var pageSize = parseInt(table.getAttribute('data-page-size') || '10', 10);
        var noSearch = table.hasAttribute('data-no-search');

        var toolbar = document.createElement('div');
        toolbar.className = 'et-toolbar';

        if (!noSearch) {
            var search = document.createElement('div');
            search.className = 'et-search';
            search.innerHTML = '<i class="fas fa-search"></i><input type="text" placeholder="Search...">';
            toolbar.appendChild(search);
        }

        var info = document.createElement('span');
        info.className = 'et-info';
        toolbar.appendChild(info);

        var sizeSel = document.createElement('select');
        sizeSel.className = 'et-pagesize';
        [10, 25, 50, 100].forEach(function(n){
            var o = document.createElement('option');
            o.value = n; o.textContent = n + ' / page';
            if (n === pageSize) o.selected = true;
            sizeSel.appendChild(o);
        });
        toolbar.appendChild(sizeSel);

        return { toolbar: toolbar, info: info, sizeSel: sizeSel,
                 input: noSearch ? null : toolbar.querySelector('input') };
    }

    function buildPagination() {
        var wrap = document.createElement('div');
        wrap.className = 'et-pagination';
        var info = document.createElement('span');
        info.className = 'et-info';
        var pages = document.createElement('div');
        pages.className = 'et-pages';
        wrap.appendChild(info);
        wrap.appendChild(pages);
        return { wrap: wrap, info: info, pages: pages };
    }

    function pageButtons(current, total) {
        // Returns array of {label, value, active, disabled}
        var out = [];
        out.push({label: '<i class="fas fa-chevron-left text-[10px]"></i>', value: current - 1, disabled: current <= 1});
        var add = function(p){ out.push({label: String(p), value: p, active: p === current}); };
        var dots = function(){ out.push({label: '…', dots: true}); };
        if (total <= 7) {
            for (var i = 1; i <= total; i++) add(i);
        } else {
            add(1);
            if (current > 4) dots();
            var start = Math.max(2, current - 1);
            var end = Math.min(total - 1, current + 1);
            for (var j = start; j <= end; j++) add(j);
            if (current < total - 3) dots();
            add(total);
        }
        out.push({label: '<i class="fas fa-chevron-right text-[10px]"></i>', value: current + 1, disabled: current >= total});
        return out;
    }

    function enhance(table) {
        if (table._etInited) return;
        table._etInited = true;

        var tbody = table.tBodies[0];
        if (!tbody) return;
        var allRows = Array.from(tbody.querySelectorAll(':scope > tr'));
        if (allRows.length === 0) return;

        // Wrap headers with sort indicator
        var headers = Array.from(table.tHead ? table.tHead.querySelectorAll('th') : []);
        headers.forEach(function(th, idx) {
            if (!th.hasAttribute('data-no-sort')) {
                var ind = document.createElement('span');
                ind.className = 'et-sort-ind';
                ind.innerHTML = '<i class="fas fa-sort"></i>';
                th.appendChild(ind);
            }
        });

        var t = buildToolbar(table);
        var p = buildPagination();
        table.parentNode.insertBefore(t.toolbar, table);
        table.parentNode.insertBefore(p.wrap, table.nextSibling);

        var state = {
            query: '',
            sortIdx: -1,
            sortDir: 1, // 1 asc, -1 desc
            page: 1,
            pageSize: parseInt(table.getAttribute('data-page-size') || '10', 10),
            filtered: allRows.slice(),
        };

        function applyFilter() {
            var q = state.query.trim().toLowerCase();
            if (!q) {
                state.filtered = allRows.slice();
            } else {
                state.filtered = allRows.filter(function(r){
                    return (r.innerText || r.textContent || '').toLowerCase().indexOf(q) !== -1;
                });
            }
        }

        function applySort() {
            if (state.sortIdx < 0) return;
            var idx = state.sortIdx, dir = state.sortDir;
            state.filtered.sort(function(a, b){
                var av = parseValue(a.cells[idx]);
                var bv = parseValue(b.cells[idx]);
                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;
                return 0;
            });
        }

        function render() {
            var size = state.pageSize;
            var total = state.filtered.length;
            var totalPages = Math.max(1, Math.ceil(total / size));
            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;
            var start = (state.page - 1) * size;
            var end = Math.min(start + size, total);

            // Reorder DOM: detach all, then append filtered+sorted page slice
            allRows.forEach(function(r){ if (r.parentNode) r.parentNode.removeChild(r); });
            var sliced = state.filtered.slice(start, end);
            sliced.forEach(function(r){ tbody.appendChild(r); });

            // Empty state
            var existingEmpty = tbody.querySelector('.et-empty-row');
            if (existingEmpty) existingEmpty.remove();
            if (sliced.length === 0) {
                var colCount = headers.length || (allRows[0] ? allRows[0].cells.length : 1);
                var emptyTr = document.createElement('tr');
                emptyTr.className = 'et-empty-row';
                var td = document.createElement('td');
                td.colSpan = colCount;
                td.className = 'et-empty';
                td.textContent = state.query ? 'No results match your search.' : 'No records to display.';
                emptyTr.appendChild(td);
                tbody.appendChild(emptyTr);
            }

            // Toolbar info + pagination info
            t.info.textContent = total === 0 ? '0 records' :
                ('Showing ' + (start + 1) + '–' + end + ' of ' + total);
            p.info.textContent = 'Page ' + state.page + ' of ' + totalPages;

            // Page buttons
            p.pages.innerHTML = '';
            pageButtons(state.page, totalPages).forEach(function(b){
                if (b.dots) {
                    var d = document.createElement('span');
                    d.className = 'et-page-btn dots';
                    d.innerHTML = b.label;
                    p.pages.appendChild(d);
                    return;
                }
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'et-page-btn' + (b.active ? ' active' : '');
                btn.disabled = !!b.disabled;
                btn.innerHTML = b.label;
                btn.addEventListener('click', function(){
                    state.page = b.value;
                    render();
                });
                p.pages.appendChild(btn);
            });

            // Hide pagination if everything fits on one page AND we're using default size
            p.wrap.style.display = totalPages > 1 ? '' : 'none';
        }

        // Wire up search
        if (t.input) {
            var debounce;
            t.input.addEventListener('input', function(e){
                clearTimeout(debounce);
                debounce = setTimeout(function(){
                    state.query = e.target.value;
                    state.page = 1;
                    applyFilter();
                    applySort();
                    render();
                }, 120);
            });
        }

        // Page size
        t.sizeSel.addEventListener('change', function(e){
            state.pageSize = parseInt(e.target.value, 10);
            state.page = 1;
            render();
        });

        // Header click sort
        headers.forEach(function(th, idx) {
            if (th.hasAttribute('data-no-sort')) return;
            th.addEventListener('click', function(){
                if (state.sortIdx === idx) {
                    state.sortDir = state.sortDir === 1 ? -1 : 1;
                } else {
                    state.sortIdx = idx;
                    state.sortDir = 1;
                }
                headers.forEach(function(h){ h.classList.remove('et-sorted-asc', 'et-sorted-desc'); });
                th.classList.add(state.sortDir === 1 ? 'et-sorted-asc' : 'et-sorted-desc');
                var ind = th.querySelector('.et-sort-ind');
                if (ind) ind.innerHTML = state.sortDir === 1
                    ? '<i class="fas fa-sort-up"></i>'
                    : '<i class="fas fa-sort-down"></i>';
                applySort();
                render();
            });
        });

        applyFilter();
        render();
    }

    function initAll(root) {
        (root || document).querySelectorAll('table.enhanced-table').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ initAll(); });
    } else {
        initAll();
    }
    // Re-init when tables are dynamically inserted (e.g. AJAX partials)
    window.enhanceTables = initAll;
})();
</script>
@endpush
@endonce
