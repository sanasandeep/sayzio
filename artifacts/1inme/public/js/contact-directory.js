(function () {
    const root = document.getElementById('directory');
    if (!root) return;
    const search = document.getElementById('directory-search');
    if (search) search.addEventListener('input', function () {
        const query = search.value.trim().toLocaleLowerCase();
        let count = 0;
        root.querySelectorAll('[data-contact]').forEach(function (contact) {
            contact.hidden = !contact.dataset.search.includes(query);
            if (!contact.hidden) count++;
        });
        root.querySelectorAll('[data-category]').forEach(function (category) {
            category.hidden = !Array.from(category.querySelectorAll('[data-contact]')).some(c => !c.hidden);
        });
        document.getElementById('directory-no-results').hidden = count > 0;
    });
    const reference = document.getElementById('directory-reference');
    if (reference) reference.addEventListener('input', function () {
        const label = reference.previousElementSibling.textContent.replace(' (optional)', '');
        root.querySelectorAll('[data-message-base]').forEach(function (action) {
            const extra = reference.value.trim() ? '\n' + label + ': ' + reference.value.trim() : '';
            action.href = action.dataset.messageBase + encodeURIComponent(action.dataset.message + extra);
        });
    });
})();
