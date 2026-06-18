@php
    use App\Modules\Common\Support\PublicAnnouncements;
    // $surface: 'site' (public/marketing) or 'dashboard' (logged-in app).
    // $fixed:   when true the bar is position:fixed above a fixed page header
    //           (e.g. the landing page) and offsets the header + body via the
    //           --inme-anno-h CSS variable. When false (default) it renders in
    //           normal flow above a sticky/static header.
    $surface = $surface ?? 'site';
    $fixed   = $fixed ?? false;
    $__announcements = PublicAnnouncements::forSurface($surface, auth()->check());
@endphp
@if(!empty($__announcements))
<div class="inme-announcements{{ $fixed ? ' inme-announcements--fixed' : '' }}" data-surface="{{ $surface }}" @if($fixed) data-fixed="1" @endif>
    @foreach($__announcements as $__a)
        <div class="inme-anno"
             role="status"
             data-anno-key="{{ $__a['audience'] }}"
             data-anno-version="{{ $__a['version'] }}"
             hidden>
            <span class="inme-anno-dot" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
            <span class="inme-anno-text">{{ $__a['message'] }}</span>
            @if($__a['link_url'] !== '')
                <a class="inme-anno-cta" href="{{ $__a['link_url'] }}" rel="noopener">
                    {{ $__a['link_label'] !== '' ? $__a['link_label'] : 'Learn more' }}
                    <i class="fas fa-arrow-right text-[9px]"></i>
                </a>
            @endif
            <button type="button" class="inme-anno-dismiss" aria-label="Dismiss announcement" title="Dismiss">
                <i class="fas fa-times text-[11px]"></i>
            </button>
        </div>
    @endforeach
</div>
<style>
    .inme-announcements { display: flex; flex-direction: column; }
    .inme-announcements--fixed { position: fixed; top: 0; left: 0; right: 0; z-index: 60; }
    .inme-anno {
        display: flex; align-items: center; gap: 12px;
        padding: 9px 16px; font-size: 13px; line-height: 1.4; font-weight: 500;
        color: #ede9fe;
        background: linear-gradient(90deg, rgba(124,58,237,0.97), rgba(168,85,247,0.97));
        border-bottom: 1px solid rgba(255,255,255,0.12);
        font-family: 'Space Grotesk', system-ui, sans-serif;
    }
    .inme-anno-dot { display: inline-flex; flex-shrink: 0; opacity: 0.9; font-size: 12px; }
    .inme-anno-text { flex: 1; min-width: 0; }
    .inme-anno-cta {
        flex-shrink: 0; display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 11px; border-radius: 999px; font-size: 12px; font-weight: 600;
        color: #5b21b6; background: #fff; text-decoration: none; white-space: nowrap;
        transition: transform .12s ease, box-shadow .12s ease;
    }
    .inme-anno-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,0.18); }
    .inme-anno-dismiss {
        flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 999px; border: 0; cursor: pointer;
        color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.12);
        transition: background .12s ease, color .12s ease;
    }
    .inme-anno-dismiss:hover { background: rgba(255,255,255,0.22); color: #fff; }
    /* The dashboard renders banners inside the rounded content area, so soften
       the bottom border there. */
    .inme-announcements[data-surface="dashboard"] .inme-anno {
        border-radius: 12px; border-bottom: 0; margin-bottom: 12px;
    }
</style>
<script>
(function(){
    var STORE = '1inme_anno_dismissed';
    function read() {
        try { return JSON.parse(localStorage.getItem(STORE) || '{}') || {}; }
        catch (e) { return {}; }
    }
    function write(obj) {
        try { localStorage.setItem(STORE, JSON.stringify(obj)); } catch (e) {}
    }
    var root = document.querySelector('.inme-announcements');
    if (!root) return;
    var isFixed = root.getAttribute('data-fixed') === '1';

    // For a fixed bar above a fixed page header, publish the live banner height
    // as --inme-anno-h (the header reads it to drop below) and pad the body so
    // in-flow content clears the bar.
    function syncOffset() {
        if (!isFixed) return;
        var h = root.querySelector('.inme-anno') ? root.offsetHeight : 0;
        if (h > 0) {
            document.documentElement.style.setProperty('--inme-anno-h', h + 'px');
            document.body.style.paddingTop = h + 'px';
        } else {
            document.documentElement.style.removeProperty('--inme-anno-h');
            document.body.style.paddingTop = '';
        }
    }

    var dismissed = read();
    root.querySelectorAll('.inme-anno').forEach(function(el){
        var key = el.getAttribute('data-anno-key');
        var ver = parseInt(el.getAttribute('data-anno-version'), 10) || 1;
        // Show only if not dismissed at or above the current content version.
        if ((parseInt(dismissed[key], 10) || 0) >= ver) {
            el.parentNode && el.parentNode.removeChild(el);
            return;
        }
        el.hidden = false;
        var btn = el.querySelector('.inme-anno-dismiss');
        if (btn) {
            btn.addEventListener('click', function(){
                var d = read();
                d[key] = ver;
                write(d);
                el.parentNode && el.parentNode.removeChild(el);
                syncOffset();
            });
        }
    });

    syncOffset();
    window.addEventListener('resize', syncOffset);
})();
</script>
@endif
