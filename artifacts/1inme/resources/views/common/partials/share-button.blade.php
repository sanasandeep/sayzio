{{--
    The share button, for every page type that has a display.

    Sana, 2026-09-23: "for all link in bio or any other pages or link
    types where display is there, i want share button with qr code...
    default active".

    This markup lived inline in common/biolink.blade.php and nowhere
    else. Lifting it out is most of the feature: a menu, a resume, an
    event page and a review wall all render their own template, and none
    of them could carry a button that only existed inside one of them.

    Parameters
      $sbLink   (Link|null)  the link, when there is one in scope
      $sbUrl    (string)     canonical URL of the page being shared
      $sbTitle  (string)     what the page is called, for the OS sheet
      $sb       (array|null) resolved settings; resolved here if omitted

    A resume reached at @handle/resume has no Link at all, which is why
    $sb can be passed directly rather than dug out of $sbLink.

    Nothing renders unless the owner has it on. Everything below is
    scoped to .sz-share so it cannot collide with a page's own CSS --
    the biolink's `.share-fab` was generic enough to.
--}}
@php
    use App\Modules\User\Support\ShareButton;

    $sb = $sb ?? ShareButton::resolve(
        isset($sbLink) && $sbLink ? (array) ($sbLink->settings['biolink'] ?? []) : []
    );

    $sbUrl   = $sbUrl   ?? url()->current();
    $sbTitle = $sbTitle ?? config('app.name');
@endphp

@if (! empty($sb['enabled']))
@php
    // The QR is rendered by Sayzio, not by a third party. Two reasons:
    // the URL of every page carrying this button was being handed to
    // api.qrserver.com on each view, and a scan routed through someone
    // else's image host can never be counted.
    $sbQrTarget = ShareButton::tagged($sbUrl, ShareButton::QR_TAG);
    $sbQrSrc    = route('qr.public.render', [
        'url'      => $sbQrTarget,
        'size'     => (int) $sb['qr_size'],
        'fg_color' => $sb['qr_fg_color'],
        'bg_color' => $sb['qr_bg_color'],
    ]);

    $sbNetworks = array_values(array_filter(
        ShareButton::NETWORKS,
        fn ($n) => in_array($n['key'], $sb['networks'], true)
    ));

    $sbIconSize = ['sm' => '16px', 'md' => '20px', 'lg' => '26px'][$sb['size']] ?? '20px';
    $sbFabSize  = ['sm' => '44px', 'md' => '52px', 'lg' => '62px'][$sb['size']] ?? '52px';
    $sbId       = 'szshare'.substr(md5($sbUrl), 0, 8);
@endphp

<style>
    /* Anchors. The popup opens upward from a bottom button and downward
       from a top one, so it never runs off the screen it is pinned to. */
    /* box-sizing is set explicitly: these pages do not all apply a
       global border-box reset, and without it the panel's declared width
       plus its padding overflow a narrow screen. */
    .sz-share, .sz-share * { box-sizing: border-box; }
    .sz-share { position: fixed; z-index: 9990; }
    .sz-share.at-bottom-right  { bottom: 20px; right: 20px; }
    .sz-share.at-bottom-left   { bottom: 20px; left: 20px; }
    .sz-share.at-bottom-center { bottom: 20px; left: 50%; transform: translateX(-50%); }
    .sz-share.at-top-right     { top: 20px; right: 20px; }
    .sz-share.at-top-left      { top: 20px; left: 20px; }

    .sz-share-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        border: none; cursor: pointer; font: inherit; line-height: 1;
        box-shadow: 0 8px 28px rgba(0,0,0,.22);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .sz-share-btn:hover  { transform: translateY(-2px); }
    .sz-share-btn:active { transform: translateY(0); }
    .sz-share-btn:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }

    .sz-share-btn.as-fab  { border-radius: 50%; }
    .sz-share-btn.as-bar  { border-radius: 12px; padding: 12px 20px; font-size: 13px; font-weight: 600; }
    .sz-share-btn.as-icon { background: transparent !important; box-shadow: none; padding: 8px; opacity: .75; }
    .sz-share-btn.as-icon:hover { opacity: 1; }

    /* The panel paints its own light surface rather than inheriting the
       page's. The old version assumed a dark page and set white text on
       a translucent white fill -- invisible on every light page. */
    .sz-share-panel {
        position: absolute; width: 260px; max-width: calc(100vw - 40px);
        background: #ffffff; color: #101828;
        border: 1px solid rgba(16,24,40,.09); border-radius: 16px;
        box-shadow: 0 18px 50px rgba(16,24,40,.22);
        padding: 14px; opacity: 0; visibility: hidden;
        transform: translateY(8px); transition: all .18s ease;
    }
    .sz-share-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }

    .sz-share.at-bottom-right  .sz-share-panel { bottom: calc(100% + 12px); right: 0; }
    .sz-share.at-bottom-left   .sz-share-panel { bottom: calc(100% + 12px); left: 0; }
    .sz-share.at-bottom-center .sz-share-panel { bottom: calc(100% + 12px); left: 50%; transform: translate(-50%, 8px); }
    .sz-share.at-bottom-center .sz-share-panel.open { transform: translate(-50%, 0); }
    .sz-share.at-top-right     .sz-share-panel { top: calc(100% + 12px); right: 0; }
    .sz-share.at-top-left      .sz-share-panel { top: calc(100% + 12px); left: 0; }

    .sz-share-qr { text-align: center; margin-bottom: 12px; }
    .sz-share-qr img { width: 172px; height: 172px; border-radius: 10px; display: block; margin: 0 auto; }
    .sz-share-qr span { display: block; font-size: 10px; margin-top: 6px; color: #667085; }

    .sz-share-copy { display: flex; gap: 6px; align-items: center; margin-bottom: 10px; }
    .sz-share-copy input {
        flex: 1; min-width: 0; font: inherit; font-size: 11px; color: #101828;
        background: #f4f5f7; border: 1px solid rgba(16,24,40,.08);
        border-radius: 9px; padding: 8px 10px; outline: none;
    }
    .sz-share-copy button {
        flex: 0 0 auto; border: none; cursor: pointer;
        background: #f4f5f7; color: #101828; border-radius: 9px; padding: 8px 11px; font-size: 12px;
    }
    .sz-share-copy button:hover { background: #e9eaee; }

    /* A grid rather than a wrapping flex row: with flex, the last row
       stretches its one or two items to full width and the block stops
       reading as one set of equal buttons. */
    .sz-share-nets { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
    .sz-share-nets a {
        text-align: center; text-decoration: none;
        padding: 9px 0; border-radius: 9px; font-size: 15px;
        background: #f4f5f7; color: #344054; transition: background .15s ease;
    }
    .sz-share-nets a:hover { background: #e9eaee; }

    @media (prefers-reduced-motion: reduce) {
        .sz-share-btn, .sz-share-panel { transition: none; }
        .sz-share-btn:hover { transform: none; }
    }
</style>

<div class="sz-share at-{{ $sb['position'] }}" id="{{ $sbId }}">
    <button type="button"
            class="sz-share-btn as-{{ $sb['style'] }}"
            aria-haspopup="dialog" aria-expanded="false"
            aria-label="{{ $sb['label'] }}"
            style="background: {{ $sb['color'] }}; color: {{ $sb['text_color'] }};
                   @if($sb['style'] === 'fab') width: {{ $sbFabSize }}; height: {{ $sbFabSize }}; font-size: {{ $sbIconSize }};
                   @elseif($sb['style'] === 'icon') color: {{ $sb['color'] }}; font-size: {{ $sbIconSize }};
                   @endif">
        <i class="fas fa-share-alt"></i>
        @if ($sb['style'] === 'bar')<span>{{ $sb['label'] }}</span>@endif
    </button>

    <div class="sz-share-panel" role="dialog" aria-label="{{ $sb['label'] }}">
        @if (! empty($sb['show_qr']))
        <div class="sz-share-qr">
            <img src="{{ $sbQrSrc }}" alt="QR code for this page" loading="lazy" width="172" height="172">
            <span>Scan to open this page</span>
        </div>
        @endif

        <div class="sz-share-copy">
            <input type="text" value="{{ $sbUrl }}" readonly aria-label="Page address"
                   onclick="this.select()">
            <button type="button" data-sz-copy="{{ $sbUrl }}" aria-label="Copy the address">
                <i class="fas fa-copy"></i>
            </button>
        </div>

        <div class="sz-share-nets">
            @foreach ($sbNetworks as $n)
                <a href="{{ sprintf($n['url'], urlencode(\App\Modules\User\Support\ShareButton::tagged($sbUrl, $n['tag']))) }}"
                   target="_blank" rel="noopener noreferrer"
                   title="{{ $n['label'] }}" aria-label="Share on {{ $n['label'] }}">
                    <i class="{{ $n['icon'] }}"></i>
                </a>
            @endforeach
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById(@json($sbId));
    if (!root) return;

    var btn   = root.querySelector('.sz-share-btn');
    var panel = root.querySelector('.sz-share-panel');

    function setOpen(open) {
        panel.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        // The OS share sheet is better than anything drawn here when it
        // exists -- but only on a tap, and only when the page is secure,
        // or the browser rejects the call. The panel is the fallback and
        // the desktop path, and it is what carries the QR either way.
        if (navigator.share && window.matchMedia('(max-width: 640px)').matches) {
            navigator.share({ title: @json($sbTitle), url: @json($sbUrl) }).catch(function () {
                setOpen(true);
            });
            return;
        }
        setOpen(!panel.classList.contains('open'));
    });

    root.querySelector('[data-sz-copy]').addEventListener('click', function () {
        var self = this;
        var done = function () {
            self.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function () { self.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
        };
        // The address is read from the attribute rather than interpolated
        // into this handler: a URL can contain a quote, and a quote in a
        // string literal is a broken button.
        var value = self.getAttribute('data-sz-copy');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function () {});
            return;
        }
        var input = root.querySelector('.sz-share-copy input');
        input.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
    });

    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>
@endif
