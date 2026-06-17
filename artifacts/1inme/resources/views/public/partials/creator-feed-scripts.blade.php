{{-- Shared client behaviour for the per-creator monetized feed:
     branded reactions, threaded comments, and the tip-modal trigger.
     Reused by the /@handle creator profile and the Paid Page link type
     so both surfaces drive the exact same handle-based endpoints. --}}
<script>
(() => {
    // ── Branded reactions ─────────────────────────────────
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    document.querySelectorAll('[data-cp-reactions]').forEach(group => {
        const endpoint = group.dataset.cpEndpoint;
        group.querySelectorAll('[data-cp-reaction]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                        body: JSON.stringify({reaction: btn.dataset.cpReaction}),
                    });
                    if (res.status === 401) { window.dispatchEvent(new CustomEvent('open-viewer-login')); return; }
                    const json = await res.json();
                    if (!json.success) return;
                    const totals = json.totals || {};
                    group.querySelectorAll('[data-cp-reaction]').forEach(b => {
                        const key = b.dataset.cpReaction;
                        const c = parseInt(totals[key] || 0, 10);
                        b.querySelector('[data-cp-count]').textContent = c > 0 ? c : '';
                        const isMine = json.reaction === key;
                        b.classList.toggle('bg-[color:var(--accent)]', isMine);
                        b.classList.toggle('text-white', isMine);
                        b.classList.toggle('border-[color:var(--accent)]', isMine);
                        b.classList.toggle('bg-white', !isMine);
                        b.classList.toggle('text-slate-700', !isMine);
                        b.classList.toggle('border-slate-200', !isMine);
                    });
                } catch (e) { /* swallow */ }
            });
        });
    });

    // ── Comments ──────────────────────────────────────────
    document.querySelectorAll('[data-cp-comment-form]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const endpoint = form.dataset.cpEndpoint;
            const fd = new FormData(form);
            const body = (fd.get('body') || '').toString().trim();
            const parentId = fd.get('parent_id') || null;
            if (!body) return;
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                body: JSON.stringify({body, parent_id: parentId}),
            });
            if (res.status === 401) { window.dispatchEvent(new CustomEvent('open-viewer-login')); return; }
            const json = await res.json();
            if (!json.success) {
                alert(json.message || 'Could not post comment');
                return;
            }
            // Append the new comment to the right thread.
            const list = form.closest('[data-cp-comments]')?.querySelector(parentId ? `[data-cp-replies="${parentId}"]` : '[data-cp-toplevel]');
            if (list) {
                const c = json.comment;
                const node = document.createElement('div');
                node.className = 'flex items-start gap-2 py-2';
                node.innerHTML = `
                    <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-semibold text-slate-600">${(c.author.name||'?').charAt(0)}</div>
                    <div class="flex-1 text-xs text-slate-700">
                        <span class="font-semibold text-slate-900">${c.author.name||'Someone'}</span>
                        <span class="text-slate-400"> · just now</span>
                        <p class="mt-0.5">${c.body.replace(/[<>&]/g, x => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[x]))}</p>
                    </div>`;
                list.appendChild(node);
            }
            form.reset();
        });
    });

    // ── Toggle reply form / comments expanded ────────────
    document.querySelectorAll('[data-cp-toggle-comments]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.cpToggleComments);
            if (target) target.classList.toggle('hidden');
        });
    });

    // ── Tip modal (Task #1209) ───────────────────────────
    const tipModal = document.getElementById('cp-tip-modal');
    const tipForm  = document.getElementById('cp-tip-form');
    const tipPostInput = document.getElementById('cp-tip-post-id');
    const tipCloseBtn  = document.getElementById('cp-tip-close');
    document.querySelectorAll('[data-cp-open-tip]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!tipModal) return;
            const handle = btn.dataset.cpTipHandle;
            const postId = btn.dataset.cpTipPost || '';
            tipForm.action = postId
                ? `/@${handle}/p/${postId}/tip`
                : `/@${handle}/tip`;
            tipPostInput.value = postId;
            tipModal.classList.remove('hidden');
        });
    });
    if (tipCloseBtn) tipCloseBtn.addEventListener('click', () => tipModal.classList.add('hidden'));
    if (tipModal) tipModal.addEventListener('click', (e) => {
        if (e.target === tipModal) tipModal.classList.add('hidden');
    });
    document.querySelectorAll('[data-cp-tip-amount]').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelector('input[name=amount]').value = b.dataset.cpTipAmount;
        });
    });

    // Auto-trigger viewer-OTP modal when controller flashed
    // viewer_login_required (e.g. user tried to subscribe while signed
    // out). Reuses the existing global modal already on the page.
    @if(session('viewer_login_required'))
        window.dispatchEvent(new CustomEvent('open-viewer-login', { detail: { creatorId: {{ (int) $creator->id }} } }));
    @endif
})();
</script>
