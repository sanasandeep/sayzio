{{--
    Live-release fallback hint under each Zio Browser override field.
    Expects: $overrideValue (string), $fallbackUrl (string), and
    $browser_release_version (string) from the parent view.
--}}
@php
    $__hasOverride = trim((string) ($overrideValue ?? '')) !== '';
    $__fallback = trim((string) ($fallbackUrl ?? ''));
    $__version = trim((string) ($browser_release_version ?? ''));
@endphp
<p class="ak-note mt-1 text-[11px] text-white/40 break-all">
    @if ($__hasOverride)
        <span class="text-emerald-400 font-semibold">Override active</span>, replaces the live-release fallback{{ $__version !== '' ? ' (v' . $__version . ')' : '' }}:
    @else
        Current fallback, live release{{ $__version !== '' ? ' v' . $__version : '' }}:
    @endif
    @if ($__fallback !== '')
        <a href="{{ $__fallback }}" target="_blank" rel="noopener" class="underline decoration-white/20 hover:text-white/70">{{ $__fallback }}</a>
    @else
        <span class="text-amber-400">none, the live release has no installer for this platform</span>
    @endif
</p>
