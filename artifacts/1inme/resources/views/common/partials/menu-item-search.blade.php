<style>
.menu-search{margin:1rem 0;padding:1rem;border:1px solid #d4d4d4;border-radius:14px;background:#fff;color:#262626}
.menu-search input,.menu-search select,.menu-search button{font:inherit;color:#262626;background:#fff;border:1px solid #d4d4d4;border-radius:8px;padding:.65rem;width:100%;box-sizing:border-box}
.menu-search label{display:block;font-size:.85rem}.menu-search-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.65rem;margin-top:.65rem}
.menu-search summary{cursor:pointer;margin-top:.6rem}.menu-search-status{margin:.6rem 0 0;font-size:.85rem}
[data-menu-search-hidden]{display:none!important}
</style>
<div class="menu-search" id="menu-item-search">
    <label>Search items<input type="search" data-filter="query" placeholder="Name or description" autocomplete="off"></label>
    <details><summary>Filters</summary><div class="menu-search-grid">
        <label>Category<select data-filter="category"><option value="">All categories</option>
            @foreach($searchTree as $searchSection)
                <option value="{{ $searchSection['category']->id }}">{{ $searchSection['category']->name }}</option>
                @foreach($searchSection['subs'] as $searchSub)
                    <option value="{{ $searchSub['category']->id }}">{{ $searchSection['category']->name }} / {{ $searchSub['category']->name }}</option>
                @endforeach
            @endforeach
        </select></label>
        <label>Item mark<select data-filter="mark"><option value="">All marks</option></select></label>
        <label>Minimum price<input type="number" min="0" step="0.01" data-filter="min" placeholder="Any"></label>
        <label>Maximum price<input type="number" min="0" step="0.01" data-filter="max" placeholder="Any"></label>
        <label>Availability<select data-filter="availability"><option value="">All items</option><option value="available">Available only</option><option value="sold">Sold out only</option></select></label>
    </div></details>
    <p class="menu-search-status" role="status" aria-live="polite"></p>
    <button type="button" data-clear>Clear search and filters</button>
</div>
<script>
(function () {
    function init() {
        var panel = document.getElementById('menu-item-search');
        if (!panel) return;
        var root = panel.parentElement;
        var rows = Array.from(root.querySelectorAll('[data-menu-search-item]'));
        var fields = {};
        panel.querySelectorAll('[data-filter]').forEach(function (field) { fields[field.dataset.filter] = field; });
        var labels = new Set();
        rows.forEach(function (row) {
            row.searchMarks = JSON.parse(row.dataset.searchMarks || '[]');
            row.searchMarks.forEach(function (label) { labels.add(label); });
        });
        Array.from(labels).sort().forEach(function (label) {
            var option = document.createElement('option'); option.value = label; option.textContent = label;
            fields.mark.appendChild(option);
        });
        function apply() {
            var query = fields.query.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
            var count = 0;
            rows.forEach(function (row) {
                var data = row.dataset, section = row.closest('.cat');
                var category = fields.category.value;
                var text = (data.searchText || '').toLocaleLowerCase();
                var price = Number(data.searchPrice);
                var match = query.every(function (word) { return text.includes(word); }) &&
                    (!category || data.searchCategory === category || (section && section.id === 'sec-' + category)) &&
                    (!fields.mark.value || row.searchMarks.includes(fields.mark.value)) &&
                    (fields.min.value === '' || price >= Number(fields.min.value)) &&
                    (fields.max.value === '' || price <= Number(fields.max.value)) &&
                    (!fields.availability.value || (data.searchSold === '1') === (fields.availability.value === 'sold'));
                row.toggleAttribute('data-menu-search-hidden', !match);
                if (match) count++;
            });
            root.querySelectorAll('.cat,.subcat').forEach(function (section) {
                var items = Array.from(section.querySelectorAll('[data-menu-search-item]'));
                section.toggleAttribute('data-menu-search-hidden', items.length > 0 && items.every(function (row) { return row.hasAttribute('data-menu-search-hidden'); }));
            });
            panel.querySelector('[role="status"]').textContent = count ? count + ' of ' + rows.length + ' items shown' : 'No items match. Clear the filters or try another search.';
        }
        panel.addEventListener('input', apply); panel.addEventListener('change', apply);
        panel.querySelector('[data-clear]').addEventListener('click', function () {
            Object.values(fields).forEach(function (field) { field.value = ''; }); apply(); fields.query.focus();
        });
        apply();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
