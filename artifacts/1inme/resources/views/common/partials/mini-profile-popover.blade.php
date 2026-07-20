{{--
    Mini-profile popover system.  Include once per layout that needs it.

    Usage on trigger elements:
        data-mini-profile="{{ $user->handle }}"

    The JS watches the page via event delegation and opens a small
    popover (hover-intent 200ms on desktop, tap on touch) fetching
    GET /@{handle}/mini (public JSON).  Dismissed by outside-click or
    Esc.  No-op when the creator's profile is unpublished.
--}}
<div id="mini-profile-root"></div>
<script>
(function () {
    if (window.__miniProfileReady) return;
    window.__miniProfileReady = true;

    const CACHE = {};
    let current = null;
    let hoverTimer = null;
    let hideTimer = null;

    function buildPopoverEl() {
        const el = document.createElement('div');
        el.id = 'mini-profile-popover';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'false');
        Object.assign(el.style, {
            position: 'fixed',
            zIndex: '9990',
            background: 'rgba(15,23,42,0.97)',
            border: '1px solid rgba(255,255,255,0.10)',
            borderRadius: '14px',
            boxShadow: '0 20px 50px rgba(0,0,0,0.5)',
            padding: '16px',
            width: '260px',
            pointerEvents: 'auto',
            backdropFilter: 'blur(12px)',
            WebkitBackdropFilter: 'blur(12px)',
            display: 'none',
        });
        el.addEventListener('mouseenter', () => { clearTimeout(hideTimer); });
        el.addEventListener('mouseleave', () => { scheduleHide(); });
        document.body.appendChild(el);
        return el;
    }

    function getPopover() {
        return document.getElementById('mini-profile-popover') || buildPopoverEl();
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function renderPopover(popover, data, triggerEl) {
        const accent = data.theme_color || '#3d6bff';
        const initials = (data.name || '?').trim().split(/\s+/).map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
        const verified = data.is_verified
            ? `<span title="Verified" style="color:${esc(accent)};margin-left:4px;font-size:11px;"><i class="fas fa-circle-check"></i></span>` : '';
        const avatar = data.avatar
            ? `<img src="${esc(data.avatar)}" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid ${esc(accent)}30;" alt="">`
            : `<div style="width:48px;height:48px;border-radius:50%;background:${esc(accent)};display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px;flex-shrink:0;">${esc(initials)}</div>`;
        const tagline = data.tagline ? `<div style="font-size:11.5px;color:rgba(255,255,255,0.6);margin-top:3px;line-height:1.4;">${esc(data.tagline)}</div>` : '';
        const followers = data.followers_count !== undefined
            ? `<div style="font-size:11px;margin-top:6px;color:rgba(255,255,255,0.45);">${Number(data.followers_count).toLocaleString()} followers</div>` : '';
        const viewBtn = data.profile_published && data.profile_url
            ? `<a href="${esc(data.profile_url)}" style="display:block;width:100%;margin-top:12px;padding:7px 0;border-radius:8px;background:${esc(accent)};color:#fff;font-size:12px;font-weight:700;text-align:center;text-decoration:none;">View profile <i class="fas fa-arrow-right" style="font-size:10px;margin-left:2px;"></i></a>`
            : '';

        popover.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;">
                ${avatar}
                <div style="min-width:0;flex:1;">
                    <div style="font-size:14px;font-weight:700;color:#fff;display:flex;align-items:center;flex-wrap:wrap;">${esc(data.name)}${verified}</div>
                    ${data.handle ? `<div style="font-size:11.5px;color:rgba(255,255,255,0.45);">@${esc(data.handle)}</div>` : ''}
                    ${tagline}
                </div>
            </div>
            ${followers}
            ${viewBtn}`;

        popover.style.borderTopColor = accent;
    }

    function positionPopover(popover, triggerEl) {
        const rect = triggerEl.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const pw = 260;

        let top = rect.bottom + 8;
        let left = rect.left;

        if (left + pw > vw - 12) left = Math.max(8, vw - pw - 12);
        if (top + 250 > vh - 8 && rect.top > 250) top = rect.top - 250 - 8;

        popover.style.top = top + 'px';
        popover.style.left = left + 'px';
    }

    async function fetchMini(handle) {
        if (CACHE[handle] !== undefined) return CACHE[handle];
        CACHE[handle] = null;
        try {
            const r = await fetch('/@' + encodeURIComponent(handle) + '/mini', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!r.ok) { CACHE[handle] = null; return null; }
            const body = await r.json();
            CACHE[handle] = body.data || null;
        } catch (_) {
            CACHE[handle] = null;
        }
        return CACHE[handle];
    }

    function openPopover(handle, triggerEl) {
        const popover = getPopover();
        popover.style.display = 'block';
        positionPopover(popover, triggerEl);
        current = { handle, triggerEl };

        if (CACHE[handle]) {
            renderPopover(popover, CACHE[handle], triggerEl);
            return;
        }
        popover.innerHTML = '<div style="text-align:center;padding:10px 0;"><span style="color:rgba(255,255,255,0.4);font-size:12px;">Loading…</span></div>';
        fetchMini(handle).then(data => {
            if (!data) { closePopover(); return; }
            if (current && current.handle === handle) renderPopover(popover, data, triggerEl);
        });
    }

    function closePopover() {
        current = null;
        const popover = document.getElementById('mini-profile-popover');
        if (popover) popover.style.display = 'none';
    }

    function scheduleHide() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(closePopover, 300);
    }

    // ── Event delegation ──────────────────────────────────────────────
    const isTouch = () => window.matchMedia('(hover: none)').matches;

    document.addEventListener('mouseover', function (e) {
        if (isTouch()) return;
        const el = e.target.closest('[data-mini-profile]');
        if (!el) return;
        const handle = el.dataset.miniProfile;
        if (!handle) return;
        clearTimeout(hoverTimer);
        clearTimeout(hideTimer);
        hoverTimer = setTimeout(() => openPopover(handle, el), 200);
    });

    document.addEventListener('mouseout', function (e) {
        if (isTouch()) return;
        const el = e.target.closest('[data-mini-profile]');
        if (!el) return;
        clearTimeout(hoverTimer);
        scheduleHide();
    });

    document.addEventListener('click', function (e) {
        if (!isTouch()) return;
        const el = e.target.closest('[data-mini-profile]');
        if (el) {
            const handle = el.dataset.miniProfile;
            if (!handle) return;
            const popover = document.getElementById('mini-profile-popover');
            if (popover && popover.style.display !== 'none' && current && current.handle === handle) {
                closePopover();
            } else {
                openPopover(handle, el);
            }
            return;
        }
        const popover = document.getElementById('mini-profile-popover');
        if (popover && !popover.contains(e.target)) closePopover();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePopover();
    });
})();
</script>
