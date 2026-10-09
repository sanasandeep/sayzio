(function () {
    const root = document.getElementById('listing-catalog');
    if (!root) return;
    const controls = ['catalog-search', 'catalog-kind', 'catalog-category', 'catalog-location', 'catalog-price'].map(id => document.getElementById(id));
    if (controls.every(Boolean)) {
        const [search, kind, category, location, price] = controls;
        function filter() {
            const query = search.value.trim().toLocaleLowerCase();
            const ceiling = price.value === '' ? null : Number(price.value);
            let count = 0;
            root.querySelectorAll('[data-listing]').forEach(function (listing) {
                const d = listing.dataset;
                const match = d.search.includes(query) && (!kind.value || d.kind === kind.value)
                    && (!category.value || d.category === category.value)
                    && (!location.value || d.location === location.value)
                    && (ceiling === null || (d.price !== '' && Number(d.price) <= ceiling));
                listing.hidden = !match;
                if (match) count++;
            });
            root.querySelectorAll('[data-catalog-section]').forEach(function (section) {
                section.hidden = !Array.from(section.querySelectorAll('[data-listing]')).some(listing => !listing.hidden);
            });
            document.getElementById('catalog-no-results').hidden = count > 0;
            document.getElementById('catalog-result-count').textContent = count + ' matching listings';
        }
        controls.forEach(control => control.addEventListener('input', filter));
    }
    root.querySelectorAll('[data-open-inquiry]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = document.getElementById(button.dataset.openInquiry);
            if (!form) return;
            form.open = true;
            form.querySelector('input[name="name"]').focus();
        });
    });
})();
