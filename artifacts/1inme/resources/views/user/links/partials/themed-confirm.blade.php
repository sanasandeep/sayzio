{{--
    Themed confirm dialog helper.

    Usage (JS):
        window.themedConfirm({
            title: 'Delete this block?',
            message: 'This permanently removes the block from your page.',
            confirmText: 'Delete',
            confirmIcon: 'fa-trash',
            cancelText: 'Cancel',
            iconClass: 'fa-trash',
            onConfirm: function () { ... },
            onCancel: function () { ... }
        });
--}}
<script>
if (typeof window.themedConfirm !== 'function') {
    window.themedConfirm = function (opts) {
        opts = opts || {};
        var title = opts.title || 'Are you sure?';
        var message = opts.message || '';
        var confirmText = opts.confirmText || 'Confirm';
        var cancelText = opts.cancelText || 'Cancel';
        var confirmIcon = opts.confirmIcon || '';
        var iconClass = opts.iconClass || 'fa-triangle-exclamation';
        var onConfirm = typeof opts.onConfirm === 'function' ? opts.onConfirm : function () {};
        var onCancel = typeof opts.onCancel === 'function' ? opts.onCancel : function () {};

        var overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[10000] flex items-center justify-center p-4';
        overlay.style.cssText = 'background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);';
        overlay.innerHTML = '' +
            '<div class="rounded-2xl p-5 max-w-sm w-full shadow-2xl" style="background: var(--bg-body, #0f0f1a); border: 1px solid var(--border-glass, rgba(255,255,255,0.1)); color: var(--text-primary, #fff);">' +
                '<div class="flex items-start gap-3 mb-3">' +
                    '<div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center" style="background: rgba(239,68,68,0.15); color: #f87171;">' +
                        '<i class="fas ' + iconClass + '"></i>' +
                    '</div>' +
                    '<div class="flex-1">' +
                        '<h3 class="text-sm font-semibold" data-themed-confirm-title></h3>' +
                        '<p class="text-xs mt-1" style="color: var(--text-faint, rgba(255,255,255,0.6));" data-themed-confirm-message></p>' +
                    '</div>' +
                '</div>' +
                '<div class="flex justify-end gap-2 mt-4">' +
                    '<button type="button" data-themed-confirm-cancel class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background: var(--bg-glass-input, rgba(255,255,255,0.05)); color: var(--text-primary, #fff); border: 1px solid var(--border-glass, rgba(255,255,255,0.1));"></button>' +
                    '<button type="button" data-themed-confirm-ok class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background: linear-gradient(135deg, #ef4444, #dc2626);"></button>' +
                '</div>' +
            '</div>';

        overlay.querySelector('[data-themed-confirm-title]').textContent = title;
        var msgEl = overlay.querySelector('[data-themed-confirm-message]');
        if (opts.messageHtml) { msgEl.innerHTML = opts.messageHtml; }
        else { msgEl.textContent = message; }
        overlay.querySelector('[data-themed-confirm-cancel]').textContent = cancelText;
        var okBtn = overlay.querySelector('[data-themed-confirm-ok]');
        okBtn.innerHTML = (confirmIcon ? '<i class="fas ' + confirmIcon + ' mr-1"></i>' : '') + confirmText;

        document.body.appendChild(overlay);
        var close = function () { overlay.remove(); document.removeEventListener('keydown', onKey); };
        var onKey = function (ev) { if (ev.key === 'Escape') { close(); onCancel(); } };
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', function (ev) { if (ev.target === overlay) { close(); onCancel(); } });
        overlay.querySelector('[data-themed-confirm-cancel]').addEventListener('click', function () { close(); onCancel(); });
        okBtn.addEventListener('click', function () { close(); onConfirm(); });
    };
}

/**
 * Themed replacement for `onsubmit="return confirm('...')"`.
 *
 * Usage in Blade:
 *     <form ... onsubmit="return window.themedConfirmSubmit(this, {
 *         title: 'Delete this post?',
 *         message: 'This cannot be undone.',
 *         confirmText: 'Delete',
 *         confirmIcon: 'fa-trash',
 *         iconClass: 'fa-trash'
 *     })">
 */
if (typeof window.themedConfirmSubmit !== 'function') {
    window.themedConfirmSubmit = function (form, opts) {
        if (!form || form.dataset.themedConfirmed === '1') {
            if (form) { form.dataset.themedConfirmed = ''; }
            return true;
        }
        var o = {};
        if (opts) { for (var k in opts) { if (Object.prototype.hasOwnProperty.call(opts, k)) o[k] = opts[k]; } }
        var origConfirm = o.onConfirm;
        o.onConfirm = function () {
            if (typeof origConfirm === 'function') origConfirm();
            form.dataset.themedConfirmed = '1';
            if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
            else { form.submit(); }
        };
        window.themedConfirm(o);
        return false;
    };
}

/**
 * Themed replacement for `onclick="return confirm('...')"` on a submit button
 * (or any button that triggers an action via subsequent code). Returns false
 * to cancel the immediate action and runs `onConfirm` if the user accepts.
 *
 * If `onConfirm` is omitted, the helper will look for the closest <form> and
 * submit it (mirroring the native browser behavior of a submit button).
 */
/**
 * Promise-returning replacement for native `confirm()`. Resolves true if the
 * user confirms, false if they cancel/escape/dismiss. Lets JS-level call sites
 * keep their `if (!await window.themedConfirmAsync(...)) return;` shape.
 */
if (typeof window.themedConfirmAsync !== 'function') {
    window.themedConfirmAsync = function (opts) {
        return new Promise(function (resolve) {
            var o = {};
            if (opts) { for (var k in opts) { if (Object.prototype.hasOwnProperty.call(opts, k)) o[k] = opts[k]; } }
            o.onConfirm = function () { resolve(true); };
            o.onCancel = function () { resolve(false); };
            window.themedConfirm(o);
        });
    };
}

if (typeof window.themedConfirmAction !== 'function') {
    window.themedConfirmAction = function (el, opts) {
        var o = {};
        if (opts) { for (var k in opts) { if (Object.prototype.hasOwnProperty.call(opts, k)) o[k] = opts[k]; } }
        if (typeof o.onConfirm !== 'function') {
            o.onConfirm = function () {
                var form = el && el.form ? el.form : (el && el.closest ? el.closest('form') : null);
                if (form) {
                    form.dataset.themedConfirmed = '1';
                    if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
                    else { form.submit(); }
                }
            };
        }
        window.themedConfirm(o);
        return false;
    };
}
</script>
