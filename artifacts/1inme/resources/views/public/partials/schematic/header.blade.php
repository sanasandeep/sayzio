{{--
    Schematic skin header. Deliberately not the glass pill nav: this skin is a
    technical document, so the header is a hairline rule with the page routes
    written as paths. Theme switching reuses window.inmeToggleTheme(), the same
    function the standard header calls, so the cookie and the rest of the site
    stay in sync.
--}}
@php
    $__schNav = [
        ['label' => '/',          'route' => 'site.schematic.home'],
        ['label' => '/features',  'route' => 'site.schematic.features'],
        ['label' => '/pricing',   'route' => 'site.schematic.pricing'],
        ['label' => '/about',     'route' => 'site.schematic.about'],
    ];
@endphp

<header class="sch-nav">
    <div class="sch-wrap sch-nav-in">
        <a class="sch-brand" href="{{ route('site.schematic.home') }}">
            <b>SAYZIO</b><span>Hyderabad</span>
        </a>

        <nav class="sch-nav-links" aria-label="Primary">
            @foreach($__schNav as $item)
                <a href="{{ route($item['route']) }}"
                   @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="sch-nav-act">
            <button type="button" class="sch-toggle" id="schThemeBtn"
                    aria-label="Switch colour theme">
                <i aria-hidden="true"></i><span id="schThemeLabel">Theme</span>
            </button>
            @auth
                <a class="sch-btn" href="{{ route('user.dashboard') }}">Dashboard</a>
            @else
                <a class="sch-btn" href="{{ route('user.register') }}">Start free</a>
            @endauth
        </div>
    </div>
</header>

<div class="sch-mobnav" role="navigation" aria-label="Primary, compact">
    @foreach($__schNav as $item)
        <a href="{{ route($item['route']) }}"
           @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] === '/' ? 'Home' : ltrim($item['label'], '/') }}</a>
    @endforeach
</div>

@push('scripts')
@verbatim
<script>
(function () {
  var btn = document.getElementById('schThemeBtn');
  var label = document.getElementById('schThemeLabel');
  if (!btn || !label) return;
  function isLight() { return document.documentElement.classList.contains('light-mode'); }
  function sync() { label.textContent = isLight() ? 'Light' : 'Dark'; }
  btn.addEventListener('click', function () {
    if (window.inmeToggleTheme) { window.inmeToggleTheme(); }
    else { document.documentElement.classList.toggle('light-mode'); }
    sync();
    window.dispatchEvent(new CustomEvent('sch-theme-repaint'));
  });
  window.addEventListener('inme-theme-changed', sync);
  sync();
})();
</script>
@endverbatim
@endpush
