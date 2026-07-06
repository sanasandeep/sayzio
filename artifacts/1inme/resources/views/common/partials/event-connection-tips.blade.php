{{--
    "10x your connections" advice tips — distinct from link-type-pairings.
    Pairings are factual cross-promo cards; these read as encouraging tips
    coaching the host on turning one-time attendees into lasting followers
    or contacts (link-in-bio, vCard, calendar, reviews).

    Props:
      $compact (bool) — true renders a tighter, fewer-card version for the
        public event page so it doesn't crowd event details (default false).
      $theme (string) — 'dark' (default), 'light', or 'biolink'.
      $fontColor (string|null) — hex color, required when $theme is
        'biolink'; ignored otherwise.
      $surface (string) — where the tips are shown: 'directory' (events
        directory) or 'event' (public event page). Drives click tracking
        so the product team can see which tip converts on which surface
        (default 'directory').

    Each card deep-links into the create flow for its suggested link type,
    same audience-aware logic as link-type-pairings (logged-in creators go
    straight to the type-specific create screen, visitors go to signup with
    a `redirect` back to it).
--}}
@php
    $__ectTips = \App\Modules\Common\Support\SitePagesContent::eventConnectionTips();
    $__ectCompact = $compact ?? false;
    $__ectSurface = ($surface ?? 'directory') === 'event' ? 'event' : 'directory';
    $__ectTrackSource = 'event_tips_' . $__ectSurface;
    if ($__ectCompact) {
        $__ectTips = array_slice($__ectTips, 0, 2);
    }
@endphp
@if(!empty($__ectTips))
    @php
        $__ectTheme = $theme ?? 'dark';
        $__ectIsBiolink = $__ectTheme === 'biolink';
        $__ectIsLight = $__ectTheme === 'light';

        if ($__ectIsBiolink) {
            $__ectFc = $fontColor ?? '#ffffff';
            $__ectText = $__ectFc;
            $__ectMuted = $__ectFc . 'aa';
            $__ectCardBg = $__ectFc . '0d';
            $__ectCardBorder = $__ectFc . '1f';
            $__ectIconBg = $__ectFc . '15';
            $__ectIconColor = $__ectFc;
            $__ectAccent = $__ectFc;
        } elseif ($__ectIsLight) {
            $__ectText = '#111827';
            $__ectMuted = 'rgba(17,24,39,.62)';
            $__ectCardBg = 'linear-gradient(180deg, rgba(61,107,255,.05), rgba(255,255,255,0))';
            $__ectCardBorder = 'rgba(61,107,255,.16)';
            $__ectIconBg = 'rgba(61,107,255,.1)';
            $__ectIconColor = '#3d6bff';
            $__ectAccent = '#3d6bff';
        } else {
            $__ectText = '#f4f4f8';
            $__ectMuted = '#9aa0ad';
            $__ectCardBg = 'linear-gradient(180deg, rgba(61,107,255,.12), rgba(255,255,255,.02))';
            $__ectCardBorder = 'rgba(110,97,255,.28)';
            $__ectIconBg = 'rgba(110,97,255,.18)';
            $__ectIconColor = '#a3b3ff';
            $__ectAccent = '#8fa8ff';
        }

        $__ectLoggedIn = auth('web')->check();
    @endphp
    <section class="ev-connection-tips" data-ect-source="{{ $__ectTrackSource }}" aria-label="Tips to grow your connections" style="max-width:{{ $__ectCompact ? '880px' : '960px' }}; margin:{{ $__ectCompact ? '28px' : '40px' }} auto 0; padding:0 16px 8px; color:{{ $__ectText }};">
        <div style="text-align:center; margin-bottom:16px;">
            <span style="display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:{{ $__ectAccent }};">
                <i class="fas fa-bolt"></i> 10x your connections
            </span>
            <h2 style="font-size:{{ $__ectCompact ? '15px' : '18px' }}; font-weight:800; margin:6px 0 0;">
                {{ $__ectCompact ? 'Turn attendees into lasting connections' : 'Make every event work harder for you' }}
            </h2>
            @unless($__ectCompact)
                <p style="font-size:12.5px; margin:6px auto 0; max-width:560px; color:{{ $__ectMuted }};">
                    A few small additions turn one-time attendees into followers, contacts and reviewers you can reach again.
                </p>
            @endunless
        </div>
        <div class="ev-connection-tips-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax({{ $__ectCompact ? '230px' : '220px' }}, 1fr)); gap:12px;">
            @foreach($__ectTips as $__ectItem)
                @php
                    [$__ectRouteName, $__ectRouteParams] = \App\Modules\Common\Support\SitePagesContent::linkTypePairingCreateRoute($__ectItem['type']);
                    $__ectCreateUrl = route($__ectRouteName, $__ectRouteParams);
                    $__ectItemHref = $__ectLoggedIn
                        ? $__ectCreateUrl
                        : (route('user.register') . '?redirect=' . urlencode($__ectCreateUrl));
                @endphp
                <a href="{{ $__ectItemHref }}" class="ev-connection-tip-card" data-ect-tip="{{ $__ectItem['type'] }}" style="display:flex; flex-direction:column; gap:8px; padding:16px; border-radius:16px; background:{{ $__ectCardBg }}; border:1px solid {{ $__ectCardBorder }}; text-decoration:none; color:inherit;">
                    <span style="flex:0 0 auto; width:36px; height:36px; border-radius:10px; background:{{ $__ectIconBg }}; display:flex; align-items:center; justify-content:center;">
                        <i class="fas {{ $__ectItem['icon'] }}" style="font-size:14px; color:{{ $__ectIconColor }};"></i>
                    </span>
                    <span style="font-weight:700; font-size:13.5px; line-height:1.3;">{{ $__ectItem['title'] }}</span>
                    <span style="font-size:12px; line-height:1.5; color:{{ $__ectMuted }}; flex:1;">{{ $__ectItem['tip'] }}</span>
                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:700; color:{{ $__ectAccent }}; margin-top:2px;">
                        {{ $__ectItem['cta'] }} <i class="fas fa-arrow-right" style="font-size:10px;"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <style>
        .ev-connection-tip-card { transition: transform .18s ease, border-color .18s ease; }
        .ev-connection-tip-card:hover { transform: translateY(-3px); border-color: {{ $__ectAccent }}; }
        @media (prefers-reduced-motion: reduce) {
            .ev-connection-tip-card { transition: none; }
            .ev-connection-tip-card:hover { transform: none; }
        }
    </style>

    {{-- Fire-and-forget click tracking so the product team can see which
         tip (and on which surface) actually drives creators into the create
         flow. Reuses the anonymous, allow-listed marketing-events pipeline;
         source = event_tips_{surface}, target = tip link type. --}}
    <script>
    (function () {
        if (window.__E2E__) return;
        var url = @json(route('marketing-events.track'));
        document.querySelectorAll('.ev-connection-tips[data-ect-source] .ev-connection-tip-card[data-ect-tip]').forEach(function (card) {
            card.addEventListener('click', function () {
                try {
                    var section = card.closest('.ev-connection-tips');
                    var source = section && section.getAttribute('data-ect-source');
                    var target = card.getAttribute('data-ect-tip');
                    if (!source || !target) return;
                    var data = new FormData();
                    data.append('source', source);
                    data.append('target', target);
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(url, data);
                    } else {
                        fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
                    }
                } catch (e) { /* fire-and-forget */ }
            });
        });
    })();
    </script>
@endif
