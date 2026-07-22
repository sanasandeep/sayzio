{{--
    Shared copy-URI row for admin settings pages.

    Usage:
        @include('admin.partials.copy-uri', ['label' => 'Redirect / callback URL', 'value' => route('…')])

    Props:
        label – display label above the URI
        value – the URI string to show and copy
--}}
<div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-xs">
    <div class="text-[10px] uppercase tracking-wider text-white/40 mb-1.5 ak-note">{{ $label ?? 'URL' }}</div>
    <div class="flex items-center gap-2">
        <code class="font-mono text-[12px] text-white/80 break-all flex-1 ak-strong">{{ $value }}</code>
        <button type="button"
                data-copy-val="{{ $value }}"
                onclick="navigator.clipboard.writeText(this.dataset.copyVal); var t=this; t.textContent='Copied!'; setTimeout(function(){t.textContent='Copy'},1500)"
                class="shrink-0 text-[11px] px-2 py-1 rounded-md bg-white/5 hover:bg-white/10 border border-white/10 text-white/70 whitespace-nowrap transition ak-strong">
            Copy
        </button>
    </div>
</div>
