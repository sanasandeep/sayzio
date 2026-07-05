{{--
    Reusable "Perfect pairings" cross-promo module for public link-type pages.

    Props:
      $pairingType (string|null) — catalog key from
        SitePagesContent::linkTypePairingsCatalog() (e.g. 'ics',
        'restaurant_menu', 'store_menu', 'resume', 'reviews', 'biolink').
      $theme (string) — 'dark' (default), 'light', or 'biolink'. 'biolink'
        adapts to the page's own accent via $fontColor since biolink pages
        have arbitrary custom backgrounds.
      $fontColor (string|null) — hex color, required when $theme is
        'biolink'; ignored otherwise.

    Each pairing card is its own audience-aware CTA that deep-links into the
    create flow for THAT pairing's type (SitePagesContent::
    linkTypePairingCreateRoute), not a single generic module-level CTA:
    logged-in creators go straight to the type-specific create screen,
    visitors go to signup with a `redirect` back to it.

    Renders nothing when no pairings are defined for $pairingType.
--}}
@php
    $__ltpPairings = \App\Modules\Common\Support\SitePagesContent::linkTypePairingsFor($pairingType ?? null);
@endphp
@if(!empty($__ltpPairings))
    @php
        $__ltpTheme = $theme ?? 'dark';
        $__ltpIsBiolink = $__ltpTheme === 'biolink';
        $__ltpIsLight = $__ltpTheme === 'light';

        if ($__ltpIsBiolink) {
            $__ltpFc = $fontColor ?? '#ffffff';
            $__ltpText = $__ltpFc;
            $__ltpMuted = $__ltpFc . 'aa';
            $__ltpCardBg = $__ltpFc . '0d';
            $__ltpCardBorder = $__ltpFc . '1f';
            $__ltpIconBg = $__ltpFc . '15';
            $__ltpIconColor = $__ltpFc;
        } elseif ($__ltpIsLight) {
            $__ltpText = '#111827';
            $__ltpMuted = 'rgba(17,24,39,.62)';
            $__ltpCardBg = '#ffffff';
            $__ltpCardBorder = 'rgba(0,0,0,.08)';
            $__ltpIconBg = 'rgba(61,107,255,.1)';
            $__ltpIconColor = '#3d6bff';
        } else {
            $__ltpText = '#f4f4f8';
            $__ltpMuted = '#9aa0ad';
            $__ltpCardBg = 'rgba(255,255,255,.05)';
            $__ltpCardBorder = 'rgba(255,255,255,.1)';
            $__ltpIconBg = 'rgba(255,255,255,.08)';
            $__ltpIconColor = '#c7c9d9';
        }

        $__ltpLoggedIn = auth('web')->check();
        $__ltpLinkColor = $__ltpIsLight ? '#3d6bff' : '#8ea2ff';
    @endphp
    <section class="ltp-pairings" aria-label="Perfect pairings" style="max-width:880px; margin:36px auto 0; padding:0 16px 8px; color:{{ $__ltpText }};">
        <h2 style="font-size:13.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin:0 0 14px; text-align:center; opacity:.85;">
            Perfect pairings
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px;">
            @foreach($__ltpPairings as $__ltpItem)
                @php
                    [$__ltpRouteName, $__ltpRouteParams] = \App\Modules\Common\Support\SitePagesContent::linkTypePairingCreateRoute($__ltpItem['type'] ?? '');
                    $__ltpCreateUrl = route($__ltpRouteName, $__ltpRouteParams);
                    $__ltpItemHref = $__ltpLoggedIn
                        ? $__ltpCreateUrl
                        : (route('user.register') . '?redirect=' . urlencode($__ltpCreateUrl));
                @endphp
                <a href="{{ $__ltpItemHref }}" style="display:flex; gap:12px; align-items:flex-start; padding:14px 16px; border-radius:14px; background:{{ $__ltpCardBg }}; border:1px solid {{ $__ltpCardBorder }}; text-decoration:none; color:inherit;">
                    <span style="flex:0 0 auto; width:34px; height:34px; border-radius:10px; background:{{ $__ltpIconBg }}; display:flex; align-items:center; justify-content:center;">
                        <i class="fas {{ $__ltpItem['icon'] }}" style="font-size:13px; color:{{ $__ltpIconColor }};"></i>
                    </span>
                    <span style="min-width:0;">
                        <span style="display:block; font-weight:700; font-size:13px;">{{ $__ltpItem['name'] }}</span>
                        <span style="display:block; font-size:11.5px; color:{{ $__ltpMuted }}; margin-top:2px; line-height:1.4;">{{ $__ltpItem['benefit'] }}</span>
                        <span style="display:block; font-size:11px; font-weight:700; color:{{ $__ltpLinkColor }}; margin-top:6px;">
                            {{ $__ltpLoggedIn ? 'Create it' : 'Sign up free' }} &rarr;
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
        <p style="text-align:center; font-size:11.5px; margin:16px 0 0; color:{{ $__ltpMuted }};">
            {{ $__ltpLoggedIn ? 'Tap a card to start building it.' : 'Free to start, no credit card.' }}
        </p>
    </section>
@endif
